<?php

declare(strict_types=1);

use OwpTencentCvm\Config;
use OwpTencentCvm\AdminPage;
use OwpTencentCvm\Schema;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/../../servers/owp_tencentcvm/lib/bootstrap.php';

function owp_tencentcvm_config(): array
{
    return [
        'name' => Config::DISPLAY_NAME,
        'description' => 'Admin addon for Tencent Cloud CVM credentials, sellable templates, resource checks, and operation logs.',
        'version' => Config::version(),
        'author' => 'wingsrabbit',
        'language' => 'english',
        'fields' => [],
    ];
}

function owp_tencentcvm_activate(): array
{
    try {
        $result = Schema::install();
        $created = $result['created'] ?? [];
        $description = empty($created)
            ? 'OWP Tencent CVM tables already exist.'
            : 'Created tables: ' . implode(', ', $created) . '.';

        return [
            'status' => 'success',
            'description' => $description,
        ];
    } catch (Throwable $exception) {
        return [
            'status' => 'error',
            'description' => $exception->getMessage(),
        ];
    }
}

function owp_tencentcvm_deactivate(): array
{
    return [
        'status' => 'success',
        'description' => 'OWP Tencent CVM was deactivated. Database tables were preserved.',
    ];
}

function owp_tencentcvm_upgrade(array $vars): void
{
    unset($vars);
    Schema::install();
}

function owp_tencentcvm_output(array $vars): void
{
    echo (new AdminPage())->render($vars);
}
