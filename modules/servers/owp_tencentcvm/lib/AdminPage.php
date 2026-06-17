<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use Throwable;

final class AdminPage
{
    private ConfigStore $configStore;

    public function __construct()
    {
        $this->configStore = new ConfigStore();
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function render(array $vars): string
    {
        $messages = $this->handlePost();
        $templates = Templates::all();
        $moduleLink = isset($vars['modulelink']) ? (string) $vars['modulelink'] : '';

        return $this->renderPage($moduleLink, $messages, $templates);
    }

    /**
     * @return list<array{type:string, text:string}>
     */
    private function handlePost(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return [];
        }

        $action = (string) ($_POST['owp_action'] ?? '');

        try {
            return match ($action) {
                'save_credentials' => [$this->saveCredentials()],
                'test_connection' => [$this->testConnection()],
                'save_template' => [$this->saveTemplate()],
                'validate_template' => [$this->validateTemplate()],
                'delete_template' => [$this->deleteTemplate()],
                'toggle_template' => [$this->toggleTemplate()],
                default => [['type' => 'warning', 'text' => 'Unknown admin action.']],
            };
        } catch (Throwable $exception) {
            return [[
                'type' => 'danger',
                'text' => Redactor::redactString($exception->getMessage()),
            ]];
        }
    }

    /**
     * @return array{type:string, text:string}
     */
    private function saveCredentials(): array
    {
        $this->configStore->setSecret('secret_id', trim((string) ($_POST['secret_id'] ?? '')));
        $this->configStore->setSecret('secret_key', trim((string) ($_POST['secret_key'] ?? '')));
        $this->configStore->set('default_region', trim((string) ($_POST['default_region'] ?? 'ap-guangzhou')));
        $this->configStore->set('endpoint', trim((string) ($_POST['endpoint'] ?? 'cvm.tencentcloudapi.com')));
        $this->configStore->setBool('dry_run', !empty($_POST['dry_run']));
        $this->configStore->set('timeout_seconds', (string) max(1, (int) ($_POST['timeout_seconds'] ?? 20)), 'int');

        return ['type' => 'success', 'text' => 'Credentials and safety settings saved. Blank secret fields were left unchanged.'];
    }

    /**
     * @return array{type:string, text:string}
     */
    private function testConnection(): array
    {
        $region = $this->configStore->get('default_region', 'ap-guangzhou');
        $client = new TencentClient($this->configStore->apiSettings());
        $response = $client->describeInstances($region, [], 1, 0);

        return [
            'type' => 'success',
            'text' => 'Read-only DescribeInstances succeeded. Request ID: ' . $response->requestId(),
        ];
    }

    /**
     * @return array{type:string, text:string}
     */
    private function saveTemplate(): array
    {
        $id = Templates::save([
            'id' => (int) ($_POST['template_id'] ?? 0),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'enabled' => !empty($_POST['enabled']),
            'region' => trim((string) ($_POST['region'] ?? '')),
            'zone' => trim((string) ($_POST['zone'] ?? '')),
            'instance_type' => trim((string) ($_POST['instance_type'] ?? '')),
            'image_id' => trim((string) ($_POST['image_id'] ?? '')),
            'vpc_id' => trim((string) ($_POST['vpc_id'] ?? '')),
            'subnet_id' => trim((string) ($_POST['subnet_id'] ?? '')),
            'security_group_id' => trim((string) ($_POST['security_group_id'] ?? '')),
            'bandwidth_mbps' => (int) ($_POST['bandwidth_mbps'] ?? 1),
            'charge_type' => trim((string) ($_POST['charge_type'] ?? 'POSTPAID_BY_HOUR')),
            'system_disk_type' => trim((string) ($_POST['system_disk_type'] ?? 'CLOUD_BSSD')),
            'system_disk_size_gb' => (int) ($_POST['system_disk_size_gb'] ?? 50),
        ]);

        return ['type' => 'success', 'text' => 'Template saved. ID: ' . $id . '.'];
    }

    /**
     * @return array{type:string, text:string}
     */
    private function deleteTemplate(): array
    {
        Templates::delete((int) ($_POST['template_id'] ?? 0));

        return ['type' => 'success', 'text' => 'Template deleted.'];
    }

    /**
     * @return array{type:string, text:string}
     */
    private function validateTemplate(): array
    {
        $result = (new TemplateValidator($this->configStore))->validate((int) ($_POST['template_id'] ?? 0));
        $type = $result['status'] === 'valid' ? 'success' : ($result['status'] === 'warning' ? 'warning' : 'danger');

        return [
            'type' => $type,
            'text' => 'Template validation ' . $result['status'] . ': ' . $result['message'],
        ];
    }

    /**
     * @return array{type:string, text:string}
     */
    private function toggleTemplate(): array
    {
        Templates::setEnabled((int) ($_POST['template_id'] ?? 0), !empty($_POST['enabled']));

        return ['type' => 'success', 'text' => 'Template sales status updated.'];
    }

    /**
     * @param list<array{type:string, text:string}> $messages
     * @param list<array<string, mixed>> $templates
     */
    private function renderPage(string $moduleLink, array $messages, array $templates): string
    {
        $html = '<div class="container-fluid owp-tencentcvm-admin">';
        $html .= '<h2>OWP Tencent CVM</h2>';
        $html .= '<p class="text-muted">Version ' . $this->escape(Config::version()) . ' · Tencent Cloud credentials, dry-run policy, and sellable CVM templates.</p>';
        $html .= $this->renderMessages($messages);
        $html .= $this->renderCredentialPanel($moduleLink);
        $html .= $this->renderTemplateForm($moduleLink);
        $html .= $this->renderTemplateTable($moduleLink, $templates);
        $html .= '</div>';

        return $html;
    }

    /**
     * @param list<array{type:string, text:string}> $messages
     */
    private function renderMessages(array $messages): string
    {
        $html = '';
        foreach ($messages as $message) {
            $type = $this->escape($message['type']);
            $html .= '<div class="alert alert-' . $type . '">' . $this->escape($message['text']) . '</div>';
        }

        return $html;
    }

    private function renderCredentialPanel(string $moduleLink): string
    {
        $defaultRegion = $this->configStore->get('default_region', 'ap-guangzhou');
        $endpoint = $this->configStore->get('endpoint', 'cvm.tencentcloudapi.com');
        $timeout = $this->configStore->get('timeout_seconds', '20');
        $dryRunChecked = $this->configStore->bool('dry_run', true) ? ' checked' : '';

        $html = '<div class="panel panel-default card">';
        $html .= '<div class="panel-heading card-header"><strong>Credentials And Safety</strong></div>';
        $html .= '<div class="panel-body card-body">';
        $html .= '<p>SecretId: <code>' . $this->escape($this->configStore->maskedSecretId()) . '</code></p>';
        $html .= '<form method="post" action="' . $this->escape($moduleLink) . '">';
        $html .= '<input type="hidden" name="owp_action" value="save_credentials">';
        $html .= $this->input('SecretId', 'secret_id', '', 'Leave blank to keep current value');
        $html .= $this->input('SecretKey', 'secret_key', '', 'Leave blank to keep current value', 'password');
        $html .= $this->input('Default Region', 'default_region', $defaultRegion);
        $html .= $this->input('Endpoint', 'endpoint', $endpoint);
        $html .= $this->input('Timeout Seconds', 'timeout_seconds', $timeout, '', 'number');
        $html .= '<div class="checkbox"><label><input type="checkbox" name="dry_run" value="1"' . $dryRunChecked . '> Keep Dry-run enabled by default</label></div>';
        $html .= '<button type="submit" class="btn btn-primary">Save Settings</button>';
        $html .= '</form>';
        $html .= '<form method="post" action="' . $this->escape($moduleLink) . '" style="margin-top:10px">';
        $html .= '<input type="hidden" name="owp_action" value="test_connection">';
        $html .= '<button type="submit" class="btn btn-default">Test Read-only Connection</button>';
        $html .= '</form>';
        $html .= '</div></div>';

        return $html;
    }

    private function renderTemplateForm(string $moduleLink): string
    {
        $html = '<div class="panel panel-default card">';
        $html .= '<div class="panel-heading card-header"><strong>Create Or Update Resource Template</strong></div>';
        $html .= '<div class="panel-body card-body">';
        $html .= '<form method="post" action="' . $this->escape($moduleLink) . '">';
        $html .= '<input type="hidden" name="owp_action" value="save_template">';
        $html .= $this->input('Template ID For Update', 'template_id', '', 'Leave blank to create');
        $html .= $this->input('Name', 'name', 'cn-gz-basic-2c4g');
        $html .= '<div class="checkbox"><label><input type="checkbox" name="enabled" value="1"> Enable for sales</label></div>';
        $html .= $this->input('Region', 'region', 'ap-guangzhou');
        $html .= $this->input('Zone', 'zone', 'ap-guangzhou-3');
        $html .= $this->input('Instance Type', 'instance_type', 'S5.MEDIUM4');
        $html .= $this->input('Image ID', 'image_id', 'img-placeholder');
        $html .= $this->input('VPC ID', 'vpc_id', 'vpc-placeholder');
        $html .= $this->input('Subnet ID', 'subnet_id', 'subnet-placeholder');
        $html .= $this->input('Security Group ID', 'security_group_id', 'sg-placeholder');
        $html .= $this->input('Bandwidth Mbps', 'bandwidth_mbps', '5', '', 'number');
        $html .= $this->input('Charge Type', 'charge_type', 'POSTPAID_BY_HOUR');
        $html .= $this->input('System Disk Type', 'system_disk_type', 'CLOUD_BSSD');
        $html .= $this->input('System Disk Size GB', 'system_disk_size_gb', '50', '', 'number');
        $html .= '<button type="submit" class="btn btn-primary">Save Template</button>';
        $html .= '</form>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param list<array<string, mixed>> $templates
     */
    private function renderTemplateTable(string $moduleLink, array $templates): string
    {
        $html = '<div class="panel panel-default card">';
        $html .= '<div class="panel-heading card-header"><strong>Sellable Resource Templates</strong></div>';
        $html .= '<div class="panel-body card-body">';
        $html .= '<table class="table table-striped table-bordered">';
        $html .= '<thead><tr><th>ID</th><th>Name</th><th>Sales</th><th>Region / Zone</th><th>Instance</th><th>Image</th><th>Network</th><th>Disk</th><th>Validation</th><th>Actions</th></tr></thead><tbody>';

        if ($templates === []) {
            $html .= '<tr><td colspan="10" class="text-muted">No templates yet.</td></tr>';
        }

        foreach ($templates as $template) {
            $enabled = !empty($template['enabled']);
            $html .= '<tr>';
            $html .= '<td>' . $this->escape((string) $template['id']) . '</td>';
            $html .= '<td><strong>' . $this->escape((string) $template['name']) . '</strong><br><small>' . $this->escape((string) $template['charge_type']) . '</small></td>';
            $html .= '<td>' . ($enabled ? '<span class="label label-success">Enabled</span>' : '<span class="label label-default">Disabled</span>') . '</td>';
            $html .= '<td>' . $this->escape((string) $template['region']) . '<br><small>' . $this->escape((string) $template['zone']) . '</small></td>';
            $html .= '<td>' . $this->escape((string) $template['instance_type']) . '</td>';
            $html .= '<td>' . $this->escape((string) $template['image_id']) . '</td>';
            $html .= '<td>' . $this->escape((string) $template['vpc_id']) . '<br><small>' . $this->escape((string) $template['subnet_id']) . ' / ' . $this->escape((string) $template['security_group_id']) . '</small></td>';
            $html .= '<td>' . $this->escape((string) $template['system_disk_type']) . '<br><small>' . $this->escape((string) $template['system_disk_size_gb']) . ' GB</small></td>';
            $html .= '<td>' . $this->validationBadge((string) ($template['validation_status'] ?? 'not_checked'), (string) ($template['validation_message'] ?? '')) . '</td>';
            $html .= '<td>' . $this->rowActions($moduleLink, (int) $template['id'], !$enabled) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div></div>';

        return $html;
    }

    private function rowActions(string $moduleLink, int $id, bool $enable): string
    {
        $html = '<form method="post" action="' . $this->escape($moduleLink) . '" style="display:inline">';
        $html .= '<input type="hidden" name="owp_action" value="validate_template">';
        $html .= '<input type="hidden" name="template_id" value="' . $id . '">';
        $html .= '<button type="submit" class="btn btn-xs btn-info">Validate</button>';
        $html .= '</form> ';
        $confirm = $enable ? '' : ' onsubmit="return confirm(\'Disable this template for sales?\')"';
        $html .= '<form method="post" action="' . $this->escape($moduleLink) . '" style="display:inline"' . $confirm . '>';
        $html .= '<input type="hidden" name="owp_action" value="toggle_template">';
        $html .= '<input type="hidden" name="template_id" value="' . $id . '">';
        $html .= '<input type="hidden" name="enabled" value="' . ($enable ? '1' : '0') . '">';
        $html .= '<button type="submit" class="btn btn-xs btn-default">' . ($enable ? 'Enable' : 'Disable') . '</button>';
        $html .= '</form> ';
        $html .= '<form method="post" action="' . $this->escape($moduleLink) . '" style="display:inline" onsubmit="return confirm(\'Delete this template?\')">';
        $html .= '<input type="hidden" name="owp_action" value="delete_template">';
        $html .= '<input type="hidden" name="template_id" value="' . $id . '">';
        $html .= '<button type="submit" class="btn btn-xs btn-danger">Delete</button>';
        $html .= '</form>';

        return $html;
    }

    private function validationBadge(string $status, string $message): string
    {
        $class = match ($status) {
            'valid' => 'label-success',
            'warning' => 'label-warning',
            'invalid' => 'label-danger',
            default => 'label-default',
        };

        $html = '<span class="label ' . $class . '">' . $this->escape($status) . '</span>';
        if ($message !== '') {
            $html .= '<br><small>' . $this->escape($message) . '</small>';
        }

        return $html;
    }

    private function input(string $label, string $name, string $value, string $placeholder = '', string $type = 'text'): string
    {
        $html = '<div class="form-group">';
        $html .= '<label>' . $this->escape($label) . '</label>';
        $html .= '<input class="form-control" type="' . $this->escape($type) . '" name="' . $this->escape($name) . '" value="' . $this->escape($value) . '" placeholder="' . $this->escape($placeholder) . '">';
        $html .= '</div>';

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
