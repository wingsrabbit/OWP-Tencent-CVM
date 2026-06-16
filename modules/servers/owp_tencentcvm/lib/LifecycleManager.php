<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;
use Throwable;

final class LifecycleManager
{
    private ConfigStore $configStore;

    public function __construct()
    {
        $this->configStore = new ConfigStore();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function suspendAccount(array $params): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, 'SuspendAccount');
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->stopInstances($this->region($params, $instance), [$instanceId], false);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, 'SuspendAccount', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, 'SuspendAccount', $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'stopping']);
        Operations::record($serviceId, $templateId, 'SuspendAccount', 'success', 'StopInstances accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function unsuspendAccount(array $params): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, 'UnsuspendAccount');
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->startInstances($this->region($params, $instance), [$instanceId]);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, 'UnsuspendAccount', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, 'UnsuspendAccount', $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'starting']);
        Operations::record($serviceId, $templateId, 'UnsuspendAccount', 'success', 'StartInstances accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function terminateAccount(array $params): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, true);
        $templateId = $this->templateId($instance);
        $instanceId = (string) ($instance['instance_id'] ?? '');

        if ($instanceId === '') {
            Operations::record($serviceId, $templateId, 'TerminateAccount', 'skipped', 'No Tencent CVM instance was recorded for this service.');
            return 'success';
        }

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, 'TerminateAccount');
        }

        if (!$this->configStore->bool('allow_terminate', false)) {
            Operations::record($serviceId, $templateId, 'TerminateAccount', 'blocked', 'TerminateAccount is blocked by addon safety settings.');
            return 'TerminateAccount is blocked. Enable the addon safety setting only when destructive Tencent CVM termination is intended.';
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->terminateInstances($this->region($params, $instance), [$instanceId]);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, 'TerminateAccount', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, 'TerminateAccount', $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'terminating']);
        Operations::record($serviceId, $templateId, 'TerminateAccount', 'success', 'TerminateInstances accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0:int, 1:array<string, mixed>}
     */
    private function recordedInstance(array $params, bool $allowMissing): array
    {
        $serviceId = Instances::serviceId($params);
        if ($serviceId <= 0) {
            throw new RuntimeException('WHMCS service ID is required.');
        }

        $instance = Instances::findByServiceId($serviceId);
        if ($instance === null) {
            if ($allowMissing) {
                return [$serviceId, []];
            }

            throw new RuntimeException('No Tencent CVM instance is recorded for this WHMCS service.');
        }

        return [$serviceId, $instance];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function dryRunEnabled(array $params): bool
    {
        return $this->configStore->bool('dry_run', true) || !empty($params['configoption3']);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $instance
     */
    private function region(array $params, array $instance): string
    {
        $region = (string) ($instance['region'] ?? '');
        if ($region !== '') {
            return $region;
        }

        return isset($params['configoption2']) ? (string) $params['configoption2'] : $this->configStore->get('default_region', 'ap-guangzhou');
    }

    /**
     * @param array<string, mixed> $instance
     */
    private function templateId(array $instance): ?int
    {
        return isset($instance['template_id']) && (int) $instance['template_id'] > 0 ? (int) $instance['template_id'] : null;
    }

    /**
     * @param array<string, mixed> $instance
     */
    private function requireInstanceId(array $instance): string
    {
        $instanceId = (string) ($instance['instance_id'] ?? '');
        if ($instanceId === '') {
            throw new RuntimeException('No Tencent CVM instance ID is recorded for this WHMCS service.');
        }

        return $instanceId;
    }

    private function recordBlockedByDryRun(int $serviceId, ?int $templateId, string $operation): string
    {
        Operations::record($serviceId, $templateId, $operation, 'dry_run', 'Dry-run is enabled; no Tencent Cloud API call was made.');

        return 'Dry-run is enabled; no Tencent Cloud API call was made.';
    }

    private function recordFailure(int $serviceId, ?int $templateId, string $operation, string $message, string $requestId = ''): string
    {
        $safeMessage = Redactor::redactString($message);
        Operations::record($serviceId, $templateId, $operation, 'failed', $safeMessage, $requestId);

        return $safeMessage;
    }
}
