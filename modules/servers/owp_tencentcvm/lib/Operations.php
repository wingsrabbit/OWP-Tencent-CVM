<?php

declare(strict_types=1);

namespace OwpTencentCvm;

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
        return [
            'templatefile' => 'clientarea',
            'vars' => array_merge(
                Config::clientAreaVariables($params),
                ['instance' => Instances::placeholderFromParams($params)]
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminSummary(): array
    {
        return [
            'version' => Config::version(),
            'tables' => Schema::tables(),
            'apiStatus' => 'Client available; credentials and lifecycle wiring are pending',
            'liveCalls' => 'not invoked by WHMCS lifecycle entrypoints',
        ];
    }
}
