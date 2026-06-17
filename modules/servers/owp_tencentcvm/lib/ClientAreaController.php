<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use Throwable;

final class ClientAreaController
{
    /**
     * @param array<string, mixed> $params
     * @return array{templatefile:string, vars:array<string, mixed>}
     */
    public function render(array $params): array
    {
        $serviceId = Instances::serviceId($params);
        $message = $this->handlePost($params);
        $instance = $serviceId > 0 ? Instances::findByServiceId($serviceId) : null;
        $operations = $serviceId > 0 ? Operations::recentForService($serviceId, 8) : [];

        return [
            'templatefile' => 'clientarea',
            'vars' => array_merge(
                Config::clientAreaVariables($params),
                [
                    'clientMessage' => $message,
                    'csrfToken' => CsrfGuard::token(),
                    'instance' => $this->displayInstance($params, $instance),
                    'operations' => $operations,
                ]
            ),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{type:string, text:string, consoleUrl:string}
     */
    private function handlePost(array $params): array
    {
        $empty = ['type' => '', 'text' => '', 'consoleUrl' => ''];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $empty;
        }

        $action = (string) ($_POST['owp_client_action'] ?? '');
        if ($action === '') {
            return $empty;
        }

        if (!CsrfGuard::validatePost()) {
            return ['type' => 'warning', 'text' => 'Security token expired or invalid; no Tencent Cloud API call was made. Refresh the page and retry.', 'consoleUrl' => ''];
        }

        $manager = new LifecycleManager('client', $this->clientActor($params));

        try {
            return match ($action) {
                'sync' => $this->message(Operations::syncInstanceStatus($params), 'Instance status synchronized.'),
                'start' => $this->message($manager->startInstance($params), 'Start request submitted.'),
                'stop' => $this->confirmedMessage($manager, 'stopInstance', $params, 'STOP', 'Stop request submitted.'),
                'reboot' => $this->confirmedMessage($manager, 'rebootInstance', $params, 'REBOOT', 'Reboot request submitted.'),
                'reset_password' => $this->resetPassword($manager, $params),
                'console' => $this->console($manager, $params),
                'reinstall' => $this->reinstallBlocked($params),
                default => ['type' => 'warning', 'text' => 'Unknown client action.', 'consoleUrl' => ''],
            };
        } catch (Throwable $exception) {
            return ['type' => 'danger', 'text' => Redactor::redactString($exception->getMessage()), 'consoleUrl' => ''];
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function clientActor(array $params): string
    {
        $clientId = isset($params['clientsdetails']['id']) ? (string) $params['clientsdetails']['id'] : '';
        if ($clientId !== '') {
            return 'client#' . $clientId;
        }

        $serviceId = Instances::serviceId($params);

        return $serviceId > 0 ? 'service#' . $serviceId : 'client';
    }

    /**
     * @param null|array<string, mixed> $instance
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function displayInstance(array $params, ?array $instance): array
    {
        $instance = $instance ?? Instances::placeholderFromParams($params);
        $snapshot = $this->decodeSnapshot((string) ($instance['template_snapshot'] ?? ''));

        $display = array_merge([
            'service_id' => Instances::serviceId($params),
            'template_name' => Templates::selectedNameFromParams($params),
            'instance_id' => '',
            'state' => 'not_created',
            'region' => (string) ($params['configoption2'] ?? ''),
            'zone' => '',
            'public_ip' => '',
            'private_ip' => '',
            'package_name' => (string) ($params['productname'] ?? $params['producttype'] ?? 'Tencent CVM'),
            'due_date' => (string) ($params['nextduedate'] ?? ''),
            'instance_type' => (string) ($snapshot['instance_type'] ?? ''),
            'image_id' => (string) ($snapshot['image_id'] ?? ''),
            'bandwidth_mbps' => (string) ($snapshot['bandwidth_mbps'] ?? ''),
        ], $instance);

        $state = strtolower((string) ($display['state'] ?? 'not_created'));
        $hasInstance = (string) ($display['instance_id'] ?? '') !== '';
        $busy = in_array($state, ['creating', 'starting', 'stopping', 'rebooting', 'password_resetting', 'terminating'], true);

        $display['state'] = $state;
        $display['has_instance'] = $hasInstance ? '1' : '0';
        $display['is_busy'] = $busy ? '1' : '0';
        $display['can_start'] = $hasInstance && !$busy && $state !== 'running' ? '1' : '0';
        $display['can_stop'] = $hasInstance && !$busy && $state !== 'stopped' ? '1' : '0';
        $display['can_reboot'] = $hasInstance && !$busy && $state === 'running' ? '1' : '0';
        $display['can_console'] = $hasInstance && !$busy ? '1' : '0';
        $display['can_reset_password'] = $hasInstance && !$busy ? '1' : '0';
        $display['can_reinstall_request'] = $hasInstance && !$busy ? '1' : '0';

        return $display;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSnapshot(string $snapshot): array
    {
        if ($snapshot === '') {
            return [];
        }

        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{type:string, text:string, consoleUrl:string}
     */
    private function message(string $result, string $successText): array
    {
        if ($result === 'success') {
            return ['type' => 'success', 'text' => $successText, 'consoleUrl' => ''];
        }

        $type = str_contains(strtolower($result), 'dry-run') || str_contains(strtolower($result), 'blocked') ? 'warning' : 'danger';

        return ['type' => $type, 'text' => $result, 'consoleUrl' => ''];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{type:string, text:string, consoleUrl:string}
     */
    private function confirmedMessage(LifecycleManager $manager, string $method, array $params, string $required, string $successText): array
    {
        if ((string) ($_POST['confirm_value'] ?? '') !== $required) {
            return ['type' => 'warning', 'text' => 'Confirmation value did not match; no Tencent Cloud API call was made.', 'consoleUrl' => ''];
        }

        return $this->message($manager->{$method}($params), $successText);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{type:string, text:string, consoleUrl:string}
     */
    private function resetPassword(LifecycleManager $manager, array $params): array
    {
        $password = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($password === '' || $password !== $confirm) {
            return ['type' => 'warning', 'text' => 'Password confirmation did not match; no Tencent Cloud API call was made.', 'consoleUrl' => ''];
        }

        return $this->message($manager->resetInstancePassword($params, $password), 'Password reset request submitted.');
    }

    /**
     * @param array<string, mixed> $params
     * @return array{type:string, text:string, consoleUrl:string}
     */
    private function console(LifecycleManager $manager, array $params): array
    {
        $result = $manager->openConsole($params);
        if (str_starts_with($result, 'http://') || str_starts_with($result, 'https://')) {
            return ['type' => 'success', 'text' => 'Console URL is ready. It is short-lived.', 'consoleUrl' => $result];
        }

        return $this->message($result, 'Console URL is ready.');
    }

    /**
     * @param array<string, mixed> $params
     * @return array{type:string, text:string, consoleUrl:string}
     */
    private function reinstallBlocked(array $params): array
    {
        if ((string) ($_POST['confirm_value'] ?? '') !== 'REINSTALL') {
            return ['type' => 'warning', 'text' => 'Confirmation value did not match; no Tencent Cloud API call was made.', 'consoleUrl' => ''];
        }

        $serviceId = Instances::serviceId($params);
        $instance = $serviceId > 0 ? Instances::findByServiceId($serviceId) : null;
        $templateId = isset($instance['template_id']) && (int) $instance['template_id'] > 0 ? (int) $instance['template_id'] : null;

        Operations::record($serviceId, $templateId, 'ReinstallInstance', 'blocked', 'Reinstall is not supported in v0.8.3.', '', 'client', $this->clientActor($params));

        return ['type' => 'warning', 'text' => 'Reinstall is not supported in v0.8.3; no Tencent Cloud API call was made.', 'consoleUrl' => ''];
    }
}
