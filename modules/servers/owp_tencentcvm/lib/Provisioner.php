<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;
use Throwable;

final class Provisioner
{
    private const CLIENT_TOKEN_GHOST_REBUILD_ATTEMPTS = 2;
    private const INSTANCE_VERIFY_ATTEMPTS = 3;
    private const INSTANCE_VERIFY_SECONDS = 2;

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
            if ($this->needsElasticIp($template, $existing, $dryRun)) {
                $this->assertTemplateCanProvision($template);
                return $this->ensureElasticIpForExistingInstance($serviceId, $template, $existing);
            }

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

        try {
            $network = (new NetworkResourceManager($this->configStore))->resolve($serviceId, (int) $template['id'], $template, $dryRun);
            $client = new TencentClient($this->configStore->apiSettings());
            $payload = $this->buildRunInstancesPayload($serviceId, $template, $network);
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

        try {
            $verifiedRun = $this->verifiedRunInstancesInstance(
                $client,
                (string) $template['region'],
                $serviceId,
                (int) $template['id'],
                $payload,
                $response,
                $instanceId
            );
            $instanceId = $verifiedRun['instance_id'];
            $response = $verifiedRun['response'];
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, (int) $template['id'], 'CreateAccount', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, (int) $template['id'], 'CreateAccount', $exception->getMessage());
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

        if (Templates::publicIpMode((string) ($template['public_ip_mode'] ?? Templates::PUBLIC_IP_DIRECT)) !== Templates::PUBLIC_IP_DIRECT) {
            try {
                $eip = (new ElasticIpManager($this->configStore))->ensureAssociated($serviceId, (int) $template['id'], $template, $instanceId);
                Instances::upsertForService($serviceId, [
                    'template_id' => (int) $template['id'],
                    'eip_address_id' => $eip['address_id'],
                    'public_ip' => $eip['public_ip'],
                ]);
            } catch (TencentApiException $exception) {
                return $this->recordFailure($serviceId, (int) $template['id'], 'AssociateAddress', $exception->getMessage(), $exception->requestId());
            } catch (Throwable $exception) {
                return $this->recordFailure($serviceId, (int) $template['id'], 'AssociateAddress', $exception->getMessage());
            }
        }

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
     * @param array<string, mixed> $template
     * @param array<string, mixed> $existing
     */
    private function needsElasticIp(array $template, array $existing, bool $dryRun): bool
    {
        if ($dryRun) {
            return false;
        }

        $mode = Templates::publicIpMode((string) ($template['public_ip_mode'] ?? Templates::PUBLIC_IP_DIRECT));

        return $mode !== Templates::PUBLIC_IP_DIRECT && trim((string) ($existing['eip_address_id'] ?? '')) === '';
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $existing
     */
    private function ensureElasticIpForExistingInstance(int $serviceId, array $template, array $existing): string
    {
        $templateId = (int) $template['id'];
        $instanceId = (string) ($existing['instance_id'] ?? '');

        try {
            $eip = (new ElasticIpManager($this->configStore))->ensureAssociated($serviceId, $templateId, $template, $instanceId);
            Instances::upsertForService($serviceId, [
                'template_id' => $templateId,
                'eip_address_id' => $eip['address_id'],
                'public_ip' => $eip['public_ip'],
            ]);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, 'AssociateAddress', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, 'AssociateAddress', $exception->getMessage());
        }

        Operations::record($serviceId, $templateId, 'CreateAccount', 'success', 'Existing Tencent CVM instance received its configured EIP.');

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
     * @param array{vpc_id:string, subnet_id:string, security_group_id:string, auto_network:bool} $network
     * @return array<string, mixed>
     */
    private function buildRunInstancesPayload(int $serviceId, array $template, array $network): array
    {
        $publicIpMode = Templates::publicIpMode((string) ($template['public_ip_mode'] ?? Templates::PUBLIC_IP_DIRECT));
        $payload = [
            'ClientToken' => $this->clientToken($serviceId, (int) $template['id']),
            'InstanceCount' => 1,
            'InstanceChargeType' => (string) $template['charge_type'],
            'InstanceType' => (string) $template['instance_type'],
            'ImageId' => (string) $template['image_id'],
            'InstanceName' => 'owp-whmcs-' . $serviceId,
            'Placement' => [
                'Zone' => (string) $template['zone'],
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

        $payload['InternetAccessible'] = $publicIpMode === Templates::PUBLIC_IP_DIRECT
            ? [
                'InternetChargeType' => 'TRAFFIC_POSTPAID_BY_HOUR',
                'InternetMaxBandwidthOut' => (int) $template['bandwidth_mbps'],
                'PublicIpAssigned' => true,
            ]
            : [
                'PublicIpAssigned' => false,
            ];

        if ($network['vpc_id'] !== '' && $network['subnet_id'] !== '') {
            $payload['VirtualPrivateCloud'] = [
                'VpcId' => $network['vpc_id'],
                'SubnetId' => $network['subnet_id'],
            ];
        }

        if ($network['security_group_id'] !== '') {
            $payload['SecurityGroupIds'] = [$network['security_group_id']];
        }

        return $payload;
    }

    private function clientToken(int $serviceId, int $templateId): string
    {
        return 'owp-whmcs-' . $serviceId . '-' . substr(hash('sha256', (string) $templateId), 0, 12);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{instance_id:string, response:TencentResponse}
     */
    private function verifiedRunInstancesInstance(
        TencentClient $client,
        string $region,
        int $serviceId,
        int $templateId,
        array $payload,
        TencentResponse $response,
        string $instanceId
    ): array {
        $baseClientToken = trim((string) ($payload['ClientToken'] ?? ''));
        if ($baseClientToken === '') {
            $baseClientToken = $this->clientToken($serviceId, $templateId);
        }

        for ($attempt = 0; $attempt <= self::CLIENT_TOKEN_GHOST_REBUILD_ATTEMPTS; $attempt++) {
            if ($this->runInstancesInstanceExists($client, $region, $serviceId, $templateId, $instanceId)) {
                return ['instance_id' => $instanceId, 'response' => $response];
            }

            if ($attempt >= self::CLIENT_TOKEN_GHOST_REBUILD_ATTEMPTS) {
                throw new RuntimeException('RunInstances kept returning instance IDs that DescribeInstances could not find. Last ghost InstanceId: ' . $instanceId . '.');
            }

            $ghostInstanceId = $instanceId;
            $payload['ClientToken'] = $this->clientTokenForGhostInstance($baseClientToken, $ghostInstanceId, $attempt + 1);
            $response = $client->runInstances($region, $payload, false);
            Operations::record(
                $serviceId,
                $templateId,
                'CreateAccount',
                'warning',
                'Re-ran RunInstances with a differentiated ClientToken after Tencent Cloud returned ghost instance ' . $ghostInstanceId . '.',
                $response->requestId()
            );

            $instanceId = $this->firstInstanceId($response);
            if ($instanceId === '') {
                throw new RuntimeException('RunInstances retry after ghost instance ' . $ghostInstanceId . ' returned no InstanceIdSet.');
            }
        }

        return ['instance_id' => $instanceId, 'response' => $response];
    }

    private function runInstancesInstanceExists(TencentClient $client, string $region, int $serviceId, int $templateId, string $instanceId): bool
    {
        $lastRequestId = '';

        for ($attempt = 1; $attempt <= self::INSTANCE_VERIFY_ATTEMPTS; $attempt++) {
            $response = $client->describeInstancesByIds($region, [$instanceId]);
            $lastRequestId = $response->requestId();
            $instances = $response->data()['InstanceSet'] ?? [];

            if (is_array($instances) && count($instances) > 0 && is_array($instances[0])) {
                Operations::record($serviceId, $templateId, 'DescribeInstances', 'success', 'Verified RunInstances instance ' . $instanceId . ' exists.', $response->requestId());
                return true;
            }

            if ($attempt < self::INSTANCE_VERIFY_ATTEMPTS) {
                sleep(self::INSTANCE_VERIFY_SECONDS);
            }
        }

        Operations::record(
            $serviceId,
            $templateId,
            'DescribeInstances',
            'warning',
            'RunInstances returned instance ' . $instanceId . ', but DescribeInstances did not find it; treating it as a ClientToken idempotency ghost.',
            $lastRequestId
        );

        return false;
    }

    private function clientTokenForGhostInstance(string $baseClientToken, string $ghostInstanceId, int $attempt): string
    {
        return $baseClientToken . '-' . substr(hash('sha256', $ghostInstanceId . ':' . $attempt), 0, 8);
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
        Operations::record($serviceId, $templateId, 'CreateAccount', 'dry_run', 'Dry-run validation succeeded; no Tencent CVM, network resource, or EIP was created.', $requestId);

        return 'Dry-run validation succeeded; no Tencent CVM, network resource, or EIP was created. Disable dry-run only when this WHMCS product is ready to provision billable CVMs.';
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
        $recorded = Instances::findByServiceId($serviceId);
        $eipAddressId = trim((string) ($recorded['eip_address_id'] ?? ''));
        $templateId = isset($recorded['template_id']) && (int) $recorded['template_id'] > 0 ? (int) $recorded['template_id'] : null;
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
        if ($eipAddressId !== '') {
            $publicIp = (new ElasticIpManager($this->configStore))->publicIpForAddress($region, $eipAddressId, $serviceId, $templateId);
            Instances::upsertForService($serviceId, [
                'eip_address_id' => $eipAddressId,
                'public_ip' => $publicIp,
            ]);
        }
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
