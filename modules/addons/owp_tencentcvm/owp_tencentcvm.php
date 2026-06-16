<?php

declare(strict_types=1);

use OwpTencentCvm\Config;
use OwpTencentCvm\Operations;
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
    unset($vars);

    $summary = Operations::adminSummary();
    $tables = array_map(
        static fn (string $table): string => '<code>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . '</code>',
        $summary['tables']
    );

    $html = '<div class="container-fluid owp-tencentcvm-admin">';
    $html .= '<h2>OWP Tencent CVM</h2>';
    $html .= '<p class="text-muted">WHMCS admin addon skeleton installed. Live Tencent Cloud API calls are disabled in v0.2.</p>';
    $html .= '<div class="alert alert-warning">';
    $html .= 'This version only creates local WHMCS module structure and database tables. Configure no production secrets here yet.';
    $html .= '</div>';
    $html .= '<table class="table table-striped table-bordered">';
    $html .= '<tbody>';
    $html .= '<tr><th>Version</th><td>' . htmlspecialchars((string) $summary['version'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>API Status</th><td>' . htmlspecialchars((string) $summary['apiStatus'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Live Calls</th><td>' . htmlspecialchars((string) $summary['liveCalls'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Tables</th><td>' . implode(', ', $tables) . '</td></tr>';
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '<h3>Next implementation phases</h3>';
    $html .= '<ul>';
    $html .= '<li>v0.3: self-contained Tencent Cloud API client.</li>';
    $html .= '<li>v0.4: admin credential and template CRUD.</li>';
    $html .= '<li>v0.5: provisioning lifecycle and instance persistence.</li>';
    $html .= '</ul>';
    $html .= '</div>';

    echo $html;
}
