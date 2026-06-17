<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use Throwable;
use WHMCS\Database\Capsule;

final class Operations
{
    public static function notImplemented(string $operation, string $detail = ''): string
    {
        $message = sprintf(
            '%s is not implemented in OWP Tencent CVM v%s; no Tencent Cloud API call was made.',
            $operation,
            Config::version()
        );

        if ($detail !== '') {
            $message .= ' ' . $detail;
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function clientArea(array $params): array
    {
        return (new ClientAreaController())->render($params);
    }

    public static function createAccount(array $params): string
    {
        try {
            return (new Provisioner())->createAccount($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function suspendAccount(array $params): string
    {
        try {
            return (new LifecycleManager())->suspendAccount($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function unsuspendAccount(array $params): string
    {
        try {
            return (new LifecycleManager())->unsuspendAccount($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function terminateAccount(array $params): string
    {
        try {
            return (new LifecycleManager())->terminateAccount($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function changePassword(array $params): string
    {
        try {
            return (new LifecycleManager())->changePassword($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function changePackage(array $params): string
    {
        try {
            return (new LifecycleManager())->changePackage($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function startInstance(array $params): string
    {
        try {
            return (new LifecycleManager('client'))->startInstance($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function stopInstance(array $params): string
    {
        try {
            return (new LifecycleManager('client'))->stopInstance($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function rebootInstance(array $params): string
    {
        try {
            return (new LifecycleManager('client'))->rebootInstance($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function resetInstancePassword(array $params): string
    {
        return 'ResetInstancePassword is available from the embedded client-area form so password confirmation can be collected safely.';
    }

    public static function openConsole(array $params): string
    {
        try {
            return (new LifecycleManager('client'))->openConsole($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function syncInstanceStatus(array $params): string
    {
        try {
            return (new Provisioner())->syncInstanceStatus($params);
        } catch (Throwable $exception) {
            return Redactor::redactString($exception->getMessage());
        }
    }

    public static function record(
        int $serviceId,
        ?int $templateId,
        string $operation,
        string $status,
        string $message,
        string $requestId = '',
        string $actorType = 'system',
        string $actor = ''
    ): void {
        Capsule::table(Schema::OPERATIONS_TABLE)->insert([
            'service_id' => $serviceId > 0 ? $serviceId : null,
            'template_id' => $templateId,
            'operation' => $operation,
            'actor_type' => $actorType,
            'actor' => $actor !== '' ? $actor : null,
            'status' => $status,
            'request_id' => $requestId !== '' ? $requestId : null,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recentForService(int $serviceId, int $limit = 8): array
    {
        return array_map(
            static fn ($row): array => (array) $row,
            Capsule::table(Schema::OPERATIONS_TABLE)
                ->where('service_id', $serviceId)
                ->orderBy('id', 'desc')
                ->limit(max(1, $limit))
                ->get()
                ->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminSummary(): array
    {
        return [
            'version' => Config::version(),
            'tables' => Schema::tables(),
            'apiStatus' => 'Client available; guarded CreateAccount, lifecycle controls, password reset, and read-only status sync are wired',
            'liveCalls' => 'Write calls are blocked by dry-run by default; termination also requires explicit addon approval',
        ];
    }
}
