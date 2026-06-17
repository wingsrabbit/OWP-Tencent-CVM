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
                        <td>{$statusLabel|escape}</td>
                    </tr>
                </tbody>
            </table>

            <p class="text-muted">
                Power controls, reinstall, password reset, VNC console, IPs, and operation history will be wired after the Tencent Cloud API client and instance persistence are implemented.
            </p>
        </div>
    </div>
</div>
