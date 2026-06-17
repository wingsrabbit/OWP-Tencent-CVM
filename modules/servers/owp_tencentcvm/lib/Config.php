<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class Config
{
    public const MODULE_NAME = 'owp_tencentcvm';
    public const DISPLAY_NAME = 'OWP Tencent CVM';

    public static function version(): string
    {
        return defined('OWP_TENCENTCVM_VERSION') ? OWP_TENCENTCVM_VERSION : '0.8.1';
    }

    /**
     * Server module product options shown on the WHMCS product module settings page.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function serverModuleOptions(): array
    {
        return [
            'Template Name' => [
                'Type' => 'text',
                'Size' => '40',
                'Description' => 'Admin-defined enabled and validated CVM resource template name.',
            ],
            'Default Region' => [
                'Type' => 'text',
                'Size' => '20',
                'Default' => 'ap-guangzhou',
                'Description' => 'Tencent Cloud region code, for example ap-guangzhou.',
            ],
            'Dry Run' => [
                'Type' => 'yesno',
                'Description' => 'Keep enabled until this product is ready to create billable Tencent Cloud CVMs.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function clientAreaVariables(array $params): array
    {
        return [
            'moduleVersion' => self::version(),
            'serviceId' => isset($params['serviceid']) ? (string) $params['serviceid'] : '',
            'productName' => isset($params['productname']) ? (string) $params['productname'] : (isset($params['producttype']) ? (string) $params['producttype'] : 'server'),
            'templateName' => isset($params['configoption1']) ? (string) $params['configoption1'] : '',
            'region' => isset($params['configoption2']) ? (string) $params['configoption2'] : '',
            'dryRun' => !empty($params['configoption3']),
            'dueDate' => isset($params['nextduedate']) ? (string) $params['nextduedate'] : '',
            'statusLabel' => 'Client controls available',
            'notice' => 'Client-area controls are guarded by admin safety settings, CSRF protection, and dry-run policy in v0.8.1.',
        ];
    }
}
