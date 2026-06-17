<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;
use Throwable;

final class LifecycleManager
{
    private ConfigStore $configStore;
    private string $actorType;
    private string $actor;

    public function __construct(string $actorType = 'system', string $actor = '')
    {
        $this->configStore = new ConfigStore();
        $this->actorType = $actorType;
        $this->actor = $actor;
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
        $this->record($serviceId, $templateId, 'SuspendAccount', 'success', 'StopInstances accepted.', $response->requestId());

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
        $this->record($serviceId, $templateId, 'UnsuspendAccount', 'success', 'StartInstances accepted.', $response->requestId());

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
            $this->record($serviceId, $templateId, 'TerminateAccount', 'skipped', 'No Tencent CVM instance was recorded for this service.');
            return 'success';
        }

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, 'TerminateAccount');
        }

        if (!$this->configStore->bool('allow_terminate', false)) {
            $this->record($serviceId, $templateId, 'TerminateAccount', 'blocked', 'TerminateAccount is blocked by addon safety settings.');
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
        $this->record($serviceId, $templateId, 'TerminateAccount', 'success', 'TerminateInstances accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function changePassword(array $params): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);
        $password = (string) ($params['password'] ?? '');

        $this->assertPasswordLooksValid($password);

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, 'ChangePassword');
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->resetInstancesPassword($this->region($params, $instance), [$instanceId], $password, false);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, 'ChangePassword', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, 'ChangePassword', $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'password_resetting']);
        $this->record($serviceId, $templateId, 'ChangePassword', 'success', 'ResetInstancesPassword accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function changePackage(array $params): string
    {
        $serviceId = Instances::serviceId($params);
        $templateId = null;

        if ($serviceId > 0) {
            $instance = Instances::findByServiceId($serviceId);
            if ($instance !== null) {
                $templateId = $this->templateId($instance);
            }
        }

        $this->record($serviceId, $templateId, 'ChangePackage', 'blocked', 'ChangePackage is not supported in v0.6.');

        return 'ChangePackage is not supported in v0.6. Open a planned resize workflow before changing Tencent CVM instance types.';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function startInstance(array $params): string
    {
        return $this->startRecordedInstance($params, 'StartInstance');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function stopInstance(array $params): string
    {
        return $this->stopRecordedInstance($params, 'StopInstance');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function rebootInstance(array $params): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, 'RebootInstance');
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->rebootInstances($this->region($params, $instance), [$instanceId], false);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, 'RebootInstance', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, 'RebootInstance', $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'rebooting']);
        $this->record($serviceId, $templateId, 'RebootInstance', 'success', 'RebootInstances accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function resetInstancePassword(array $params, string $password): string
    {
        $params['password'] = $password;

        return $this->resetPassword($params, 'ResetInstancePassword');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function openConsole(array $params): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->describeInstanceVncUrl($this->region($params, $instance), $instanceId);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, 'OpenConsole', $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, 'OpenConsole', $exception->getMessage());
        }

        $url = (string) ($response->data()['InstanceVncUrl'] ?? '');
        if ($url === '') {
            return $this->recordFailure($serviceId, $templateId, 'OpenConsole', 'Tencent Cloud did not return an InstanceVncUrl.', $response->requestId());
        }

        $this->record($serviceId, $templateId, 'OpenConsole', 'success', 'DescribeInstanceVncUrl returned a console URL.', $response->requestId());

        return $url;
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
     */
    private function startRecordedInstance(array $params, string $operation): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, $operation);
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->startInstances($this->region($params, $instance), [$instanceId]);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, $operation, $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, $operation, $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'starting']);
        $this->record($serviceId, $templateId, $operation, 'success', 'StartInstances accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function stopRecordedInstance(array $params, string $operation): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, $operation);
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->stopInstances($this->region($params, $instance), [$instanceId], false);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, $operation, $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, $operation, $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'stopping']);
        $this->record($serviceId, $templateId, $operation, 'success', 'StopInstances accepted.', $response->requestId());

        return 'success';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resetPassword(array $params, string $operation): string
    {
        [$serviceId, $instance] = $this->recordedInstance($params, false);
        $templateId = $this->templateId($instance);
        $instanceId = $this->requireInstanceId($instance);
        $password = (string) ($params['password'] ?? '');

        $this->assertPasswordLooksValid($password);

        if ($this->dryRunEnabled($params)) {
            return $this->recordBlockedByDryRun($serviceId, $templateId, $operation);
        }

        $client = new TencentClient($this->configStore->apiSettings());
        try {
            $response = $client->resetInstancesPassword($this->region($params, $instance), [$instanceId], $password, false);
        } catch (TencentApiException $exception) {
            return $this->recordFailure($serviceId, $templateId, $operation, $exception->getMessage(), $exception->requestId());
        } catch (Throwable $exception) {
            return $this->recordFailure($serviceId, $templateId, $operation, $exception->getMessage());
        }

        Instances::upsertForService($serviceId, ['state' => 'password_resetting']);
        $this->record($serviceId, $templateId, $operation, 'success', 'ResetInstancesPassword accepted.', $response->requestId());

        return 'success';
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

    private function assertPasswordLooksValid(string $password): void
    {
        $length = strlen($password);
        if ($length < 8 || $length > 30) {
            throw new RuntimeException('Tencent CVM password must be 8 to 30 characters.');
        }

        if (str_starts_with($password, '/') || preg_match('/\s/', $password) === 1) {
            throw new RuntimeException('Tencent CVM password must not start with "/" or contain whitespace.');
        }

        $categories = 0;
        $categories += preg_match('/[a-z]/', $password) === 1 ? 1 : 0;
        $categories += preg_match('/[A-Z]/', $password) === 1 ? 1 : 0;
        $categories += preg_match('/[0-9]/', $password) === 1 ? 1 : 0;
        $categories += preg_match('/[^A-Za-z0-9]/', $password) === 1 ? 1 : 0;

        if ($categories < 3) {
            throw new RuntimeException('Tencent CVM password must include at least three character categories.');
        }
    }

    private function recordBlockedByDryRun(int $serviceId, ?int $templateId, string $operation): string
    {
        $this->record($serviceId, $templateId, $operation, 'dry_run', 'Dry-run is enabled; no Tencent Cloud API call was made.');

        return 'Dry-run is enabled; no Tencent Cloud API call was made.';
    }

    private function recordFailure(int $serviceId, ?int $templateId, string $operation, string $message, string $requestId = ''): string
    {
        $safeMessage = Redactor::redactString($message);
        $this->record($serviceId, $templateId, $operation, 'failed', $safeMessage, $requestId);

        return $safeMessage;
    }

    private function record(int $serviceId, ?int $templateId, string $operation, string $status, string $message, string $requestId = ''): void
    {
        Operations::record($serviceId, $templateId, $operation, $status, $message, $requestId, $this->actorType, $this->actor);
    }
}
