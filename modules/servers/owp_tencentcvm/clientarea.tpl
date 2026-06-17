<div class="owp-tencentcvm-clientarea">
    <div class="alert alert-info">
        <strong>OWP Tencent CVM</strong>
        <span>{$notice|escape}</span>
    </div>

    <div class="panel panel-default card">
        <div class="panel-heading card-header">
            <strong>CVM Service</strong>
        </div>
        <div class="panel-body card-body">
            <table class="table table-striped">
                <tbody>
                    <tr>
                        <th>Module Version</th>
                        <td>{$moduleVersion|escape}</td>
                    </tr>
                    <tr>
                        <th>Service ID</th>
                        <td>{$serviceId|escape}</td>
                    </tr>
                    <tr>
                        <th>Template</th>
                        <td>{$templateName|default:'unassigned'|escape}</td>
                    </tr>
                    <tr>
                        <th>Region</th>
                        <td>{$region|default:'unassigned'|escape}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>{$instance.state|default:$statusLabel|escape}</td>
                    </tr>
                    <tr>
                        <th>Instance ID</th>
                        <td>{$instance.instance_id|default:'not created'|escape}</td>
                    </tr>
                    <tr>
                        <th>Public IP</th>
                        <td>{$instance.public_ip|default:'pending'|escape}</td>
                    </tr>
                    <tr>
                        <th>Private IP</th>
                        <td>{$instance.private_ip|default:'pending'|escape}</td>
                    </tr>
                </tbody>
            </table>

            <p class="text-muted">
                CreateAccount and read-only status sync are available. Power controls, reinstall, password reset, VNC console, and operation history will be wired in later lifecycle phases.
            </p>
        </div>
    </div>
</div>
