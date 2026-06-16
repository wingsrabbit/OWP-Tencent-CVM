<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;
use Throwable;

final class Provisioner
{
    private ConfigStore $configStore;

    public function __construct()
    {
        $this->configStore = new ConfigStore();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function createAccount(array $params): string
    {
        $serviceId = Instances::serviceId($params);
        if ($serviceId <= 0) {
            throw new RuntimeException('WHMCS service ID is required.');
        }

        $template = $this->resolveTemplate($params);
        $dryRun = $this->dryRunEnabled($params);
        $existing = Instances::findByServiceId($serviceId);

        if ($existing !== null && (string) ($existing['instance_id'] ?? '') !== '') {
            Operations::record($serviceId, (int) $template['id'], 'CreateAccount', 'skipped', 'Existing Tencent CVM instance already recorded.');
            return 'success';
        }

        $this->assertTemplateCanProvision($template);

        $snapshot = $this->encodeSnapshot($template);
        Instances::upsertForService($serviceId, [
            'template_id' => (int) $template['id'],
            'region' => (string) $template['region'],
            'zone' => (string) $template['zone'],
            'state' => $dryRun ? 'dry_run_pending' : 'creating',
            'template_snapshot' => $snapshot,
        ]);

        $client = new TencentClient($this->configStore->apiSettings());
        $payload = $this->buildRunInstancesPayload($serviceId, $template);

        try {
            $response = $client->runInstances((string) $template['region'], $payload, $dryRun);
        } catch (TencentApiException $exception) {
            if ($dryRun && $this->isDryRunSuccess($exception)) {
                return $this->recordDryRunSuccess($serviceId, (int) $template['id'], $exception->requestId());
            }

            return $this->recordFailure($serviceId, (int) $template['id'], 'CreateAccount', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, (int) $template['id'], 'CreateAccount', $exception->getMessage());
        }

        if ($dryRun) {
            return $this->recordDryRunSuccess($serviceId, (int) $template['id'], $response->requestId());
        }

        $instanceId = $this->firstInstanceId($response);
        if ($instanceId === '') {
            return $this->recordFailure($serviceId, (int) $template['id'], 'CreateAccount', 'RunInstances returned no InstanceIdSet.', $response->requestId());
        }

        Instances::upsertForService($serviceId, [
            'template_id' => (int) $template['id'],
            'instance_id' => $instanceId,
            'region' => (string) $template['region'],
            'zone' => (string) $template['zone'],
            'state' => 'creating',
            'template_snapshot' => $snapshot,
        ]);
        Operations::record($serviceId, (int) $template['id'], 'CreateAccount', 'success', 'RunInstances accepted instance ' . $instanceId . '.', $response->requestId());

        try {
            $this->syncByInstanceId($serviceId, $instanceId, (string) $template['region']);
        } catch (Throwable $exception) {
            Operations::record($serviceId, (int) $template['id'], 'SyncInstanceStatus', 'warning', Redactor::redactString($exception->getMessage()));
        }

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function syncInstanceStatus(array $params): string
    {
        $serviceId = Instances::serviceId($params);
        $instance = Instances::findByServiceId($serviceId);
        if ($instance === null || (string) ($instance['instance_id'] ?? '') === '') {
            return 'No Tencent CVM instance is recorded for this WHMCS service.';
        }

        $region = (string) ($instance['region'] ?? '');
        if ($region === '') {
            $region = isset($params['configoption2']) ? (string) $params['configoption2'] : $this->configStore->get('default_region', 'ap-guangzhou');
        }

        $this->syncByInstanceId($serviceId, (string) $instance['instance_id'], $region);

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function resolveTemplate(array $params): array
    {
        $name = Templates::selectedNameFromParams($params);
        $template = Templates::findByName($name);

        if ($template === null) {
            throw new RuntimeException('Admin template "' . $name . '" was not found.');
        }

        return $template;
    }

    /**
     * @param array<string, mixed> $template
     */
    private function assertTemplateCanProvision(array $template): void
    {
        if (empty($template['enabled'])) {
            throw new RuntimeException('Admin template "' . (string) $template['name'] . '" is disabled.');
        }

        if ((string) ($template['validation_status'] ?? '') !== 'valid') {
            throw new RuntimeException('Admin template "' . (string) $template['name'] . '" must validate successfully before provisioning.');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function dryRunEnabled(array $params): bool
    {
        return $this->configStore->bool('dry_run', true) || !empty($params['configoption3']);
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    private function buildRunInstancesPayload(int $serviceId, array $template): array
    {
        return [
            'ClientToken' => $this->clientToken($serviceId, (int) $template['id']),
            'InstanceCount' => 1,
            'InstanceChargeType' => (string) $template['charge_type'],
            'InstanceType' => (string) $template['instance_type'],
            'ImageId' => (string) $template['image_id'],
            'InstanceName' => 'owp-whmcs-' . $serviceId,
            'Placement' => [
                'Zone' => (string) $template['zone'],
            ],
            'VirtualPrivateCloud' => [
                'VpcId' => (string) $template['vpc_id'],
                'SubnetId' => (string) $template['subnet_id'],
            ],
            'SecurityGroupIds' => [
                (string) $template['security_group_id'],
            ],
            'InternetAccessible' => [
                'InternetChargeType' => 'TRAFFIC_POSTPAID_BY_HOUR',
                'InternetMaxBandwidthOut' => (int) $template['bandwidth_mbps'],
                'PublicIpAssigned' => true,
            ],
            'SystemDisk' => [
                'DiskType' => (string) $template['system_disk_type'],
                'DiskSize' => (int) $template['system_disk_size_gb'],
            ],
            'EnhancedService' => [
                'SecurityService' => ['Enabled' => true],
                'MonitorService' => ['Enabled' => true],
            ],
        ];
    }

    private function clientToken(int $serviceId, int $templateId): string
    {
        return 'owp-whmcs-' . $serviceId . '-' . substr(hash('sha256', (string) $templateId), 0, 12);
    }

    private function isDryRunSuccess(TencentApiException $exception): bool
    {
        return stripos($exception->errorCode(), 'DryRun') !== false;
    }

    private function recordDryRunSuccess(int $serviceId, int $templateId, string $requestId): string
    {
        Instances::upsertForService($serviceId, [
            'template_id' => $templateId,
            'state' => 'dry_run_validated',
        ]);
        Operations::record($serviceId, $templateId, 'CreateAccount', 'dry_run', 'Dry-run validation succeeded; no Tencent CVM was created.', $requestId);

        return 'Dry-run validation succeeded; no Tencent CVM was created. Disable dry-run only when this WHMCS product is ready to provision billable CVMs.';
    }

    private function recordFailure(int $serviceId, int $templateId, string $operation, string $message, string $requestId = ''): string
    {
        $safeMessage = Redactor::redactString($message);
        Instances::upsertForService($serviceId, [
            'template_id' => $templateId,
            'state' => 'failed',
        ]);
        Operations::record($serviceId, $templateId, $operation, 'failed', $safeMessage, $requestId);

        return $safeMessage;
    }

    private function firstInstanceId(TencentResponse $response): string
    {
        $ids = $response->data()['InstanceIdSet'] ?? [];
        if (!is_array($ids) || count($ids) === 0) {
            return '';
        }

        return (string) $ids[0];
    }

    private function syncByInstanceId(int $serviceId, string $instanceId, string $region): void
    {
        $client = new TencentClient($this->configStore->apiSettings());
        $response = $client->describeInstancesByIds($region, [$instanceId]);
        $instances = $response->data()['InstanceSet'] ?? [];

        if (!is_array($instances) || count($instances) === 0 || !is_array($instances[0])) {
            Instances::upsertForService($serviceId, [
                'instance_id' => $instanceId,
                'region' => $region,
                'state' => 'not_found',
            ]);
            Operations::record($serviceId, null, 'SyncInstanceStatus', 'warning', 'Tencent CVM instance was not found.', $response->requestId());
            return;
        }

        Instances::updateFromTencentInstance($serviceId, $instances[0], $region);
        Operations::record($serviceId, null, 'SyncInstanceStatus', 'success', 'Tencent CVM instance status synchronized.', $response->requestId());
    }

    /**
     * @param array<string, mixed> $template
     */
    private function encodeSnapshot(array $template): string
    {
        $encoded = json_encode($template, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }
}
