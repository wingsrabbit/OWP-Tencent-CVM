{literal}
<style>
.owp-tencentcvm-clientarea {
    --owp-border: #e5e7eb;
    --owp-muted: #6b7280;
    --owp-text: #111827;
    --owp-bg: #f6f7f9;
    --owp-card: #ffffff;
    --owp-blue: #2563eb;
    --owp-green: #16a34a;
    --owp-red: #b42318;
    --owp-amber: #92400e;
    background: var(--owp-bg);
    color: var(--owp-text);
    padding: 18px 0 28px;
}
.owp-tencentcvm-clientarea * { box-sizing: border-box; }
.owp-cvm-shell { max-width: 1040px; margin: 0 auto; padding: 0 14px; }
.owp-cvm-breadcrumb { color: var(--owp-muted); font-size: 13px; margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
.owp-cvm-card { background: var(--owp-card); border: 1px solid var(--owp-border); border-radius: 8px; overflow: hidden; margin-bottom: 16px; }
.owp-cvm-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px; border-bottom: 1px solid var(--owp-border); }
.owp-cvm-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
.owp-cvm-icon { width: 40px; height: 40px; border-radius: 8px; display: grid; place-items: center; background: #f1f5f9; }
.owp-cvm-title strong { display: block; font-size: 18px; line-height: 1.2; }
.owp-cvm-title span { color: var(--owp-muted); font-size: 12px; }
.owp-cvm-status { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #bbf7d0; color: #166534; background: #f0fdf4; border-radius: 6px; padding: 6px 10px; font-size: 13px; white-space: nowrap; }
.owp-cvm-dot { width: 8px; height: 8px; border-radius: 999px; background: var(--owp-green); display: inline-block; }
.owp-cvm-sync { display: flex; align-items: center; gap: 12px; color: var(--owp-muted); font-size: 12px; }
.owp-cvm-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); }
.owp-cvm-fact { min-height: 62px; padding: 14px 16px; border-right: 1px solid var(--owp-border); border-bottom: 1px solid var(--owp-border); }
.owp-cvm-fact:nth-child(6n) { border-right: 0; }
.owp-cvm-label { color: var(--owp-muted); font-size: 12px; margin-bottom: 4px; }
.owp-cvm-value { font-weight: 700; word-break: break-word; }
.owp-cvm-strip { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border: 1px solid var(--owp-border); border-radius: 8px; background: #fff; margin-bottom: 16px; color: var(--owp-muted); }
.owp-cvm-actions { padding: 18px; }
.owp-cvm-section-label { color: #334155; font-size: 13px; font-weight: 700; margin-bottom: 12px; }
.owp-cvm-action-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
.owp-cvm-action-row + .owp-cvm-section-label { border-top: 1px solid var(--owp-border); padding-top: 18px; }
.owp-cvm-btn { border: 1px solid var(--owp-border); background: #fff; color: #111827; border-radius: 6px; min-height: 38px; padding: 8px 14px; font-weight: 700; }
.owp-cvm-btn-primary { background: #7c9cf4; border-color: #7c9cf4; color: #fff; }
.owp-cvm-btn-danger { color: var(--owp-red); border-color: #fed7aa; background: #fff7ed; }
.owp-cvm-btn-warning { color: var(--owp-amber); border-color: #fde68a; background: #fffbeb; }
.owp-cvm-btn:disabled { opacity: .55; cursor: not-allowed; }
.owp-cvm-details { border: 1px solid var(--owp-border); border-radius: 8px; background: #fff; margin-bottom: 16px; overflow: hidden; }
.owp-cvm-details summary { cursor: pointer; list-style: none; padding: 14px 16px; font-weight: 700; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.owp-cvm-details summary::-webkit-details-marker { display: none; }
.owp-cvm-details-danger { border-color: #fecaca; background: #fffafa; }
.owp-cvm-details-body { padding: 0 16px 16px; }
.owp-cvm-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.owp-cvm-field label { color: #374151; display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; }
.owp-cvm-field input, .owp-cvm-field select { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; min-height: 38px; padding: 8px 10px; }
.owp-cvm-help { color: var(--owp-muted); font-size: 12px; margin: 8px 0 12px; }
.owp-cvm-alert { border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; border: 1px solid var(--owp-border); background: #fff; }
.owp-cvm-alert-success { border-color: #bbf7d0; background: #f0fdf4; color: #166534; }
.owp-cvm-alert-warning { border-color: #fde68a; background: #fffbeb; color: #92400e; }
.owp-cvm-alert-danger { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
.owp-cvm-table-wrap { overflow-x: auto; }
.owp-cvm-table { width: 100%; border-collapse: collapse; min-width: 680px; }
.owp-cvm-table th, .owp-cvm-table td { border-top: 1px solid var(--owp-border); padding: 12px 16px; text-align: left; vertical-align: top; font-size: 13px; }
.owp-cvm-table th { color: var(--owp-muted); font-weight: 700; background: #f8fafc; }
.owp-cvm-pill { border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; font-size: 12px; padding: 3px 8px; border: 1px solid var(--owp-border); }
.owp-cvm-pill-success { border-color: #bbf7d0; background: #f0fdf4; color: #166534; }
.owp-cvm-pill-danger { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
.owp-cvm-pill-warning { border-color: #fde68a; background: #fffbeb; color: #92400e; }
@media (max-width: 760px) {
    .owp-cvm-head { align-items: flex-start; flex-direction: column; }
    .owp-cvm-sync { width: 100%; justify-content: space-between; }
    .owp-cvm-grid { grid-template-columns: 1fr; }
    .owp-cvm-fact { border-right: 0; }
    .owp-cvm-form-grid { grid-template-columns: 1fr; }
}
</style>
{/literal}

{assign var=state value=$instance.state|default:'not_created'}
{assign var=instanceId value=$instance.instance_id|default:''}

<div class="owp-tencentcvm-clientarea">
    <div class="owp-cvm-shell">
        <div class="owp-cvm-breadcrumb">
            <span>Client Area</span><span>/</span><span>My Services</span><span>/</span><strong>OWP Tencent CVM</strong>
        </div>

        {if $clientMessage.text}
            <div class="owp-cvm-alert owp-cvm-alert-{$clientMessage.type|escape}">
                {$clientMessage.text|escape}
                {if $clientMessage.consoleUrl}
                    <div style="margin-top:8px">
                        <a class="btn btn-primary btn-sm" href="{$clientMessage.consoleUrl|escape}" target="_blank" rel="noopener">Open Console</a>
                    </div>
                {/if}
            </div>
        {/if}

        <div class="owp-cvm-card">
            <div class="owp-cvm-head">
                <div class="owp-cvm-title">
                    <div class="owp-cvm-icon">CVM</div>
                    <div>
                        <strong>{$instance.package_name|default:'Tencent CVM'|escape}</strong>
                        <span>{$instanceId|default:'not created'|escape}</span>
                    </div>
                    <span class="owp-cvm-status"><span class="owp-cvm-dot"></span>{$state|escape}</span>
                </div>
                <div class="owp-cvm-sync">
                    <span>Last sync follows the latest operation record</span>
                    <form method="post">
                        <input type="hidden" name="owp_client_action" value="sync">
                        <input type="hidden" name="owp_tencentcvm_csrf_token" value="{$csrfToken|escape}">
                        <button type="submit" class="owp-cvm-btn">Refresh</button>
                    </form>
                </div>
            </div>
            <div class="owp-cvm-grid">
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Public IPv4</div><div class="owp-cvm-value">{$instance.public_ip|default:'pending'|escape}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Private IPv4</div><div class="owp-cvm-value">{$instance.private_ip|default:'pending'|escape}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Region / Zone</div><div class="owp-cvm-value">{$instance.region|default:$region|escape}{if $instance.zone} / {$instance.zone|escape}{/if}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Plan</div><div class="owp-cvm-value">{$templateName|default:'unassigned'|escape}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Instance Type</div><div class="owp-cvm-value">{$instance.instance_type|default:'pending'|escape}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Image</div><div class="owp-cvm-value">{$instance.image_id|default:'pending'|escape}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Bandwidth</div><div class="owp-cvm-value">{if $instance.bandwidth_mbps}{$instance.bandwidth_mbps|escape} Mbps{else}pending{/if}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Due Date</div><div class="owp-cvm-value">{$instance.due_date|default:'not set'|escape}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">WHMCS Service ID</div><div class="owp-cvm-value">#{$serviceId|escape}</div></div>
                <div class="owp-cvm-fact"><div class="owp-cvm-label">Tencent Instance ID</div><div class="owp-cvm-value">{$instanceId|default:'not created'|escape}</div></div>
            </div>
        </div>

        <div class="owp-cvm-strip"><span class="owp-cvm-dot"></span><span>{$notice|escape}</span></div>

        <div class="owp-cvm-card">
            <div class="owp-cvm-actions">
                <div class="owp-cvm-section-label">Common</div>
                <div class="owp-cvm-action-row">
                    <form method="post">
                        <input type="hidden" name="owp_client_action" value="start">
                        <input type="hidden" name="owp_tencentcvm_csrf_token" value="{$csrfToken|escape}">
                        <button type="submit" class="owp-cvm-btn owp-cvm-btn-primary" {if $instance.can_start neq '1'}disabled="disabled"{/if}>Start</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="owp_client_action" value="console">
                        <input type="hidden" name="owp_tencentcvm_csrf_token" value="{$csrfToken|escape}">
                        <button type="submit" class="owp-cvm-btn" {if $instance.can_console neq '1'}disabled="disabled"{/if}>VNC Console</button>
                    </form>
                </div>

                <div class="owp-cvm-section-label">Needs Confirmation</div>
                <div class="owp-cvm-action-row">
                    <form method="post" onsubmit="return confirm('Stop this CVM instance? Services on the instance may become unavailable.');">
                        <input type="hidden" name="owp_client_action" value="stop">
                        <input type="hidden" name="owp_tencentcvm_csrf_token" value="{$csrfToken|escape}">
                        <input type="hidden" name="confirm_value" value="STOP">
                        <button type="submit" class="owp-cvm-btn owp-cvm-btn-danger" {if $instance.can_stop neq '1'}disabled="disabled"{/if}>Stop</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Reboot this CVM instance? Unsaved data may be lost.');">
                        <input type="hidden" name="owp_client_action" value="reboot">
                        <input type="hidden" name="owp_tencentcvm_csrf_token" value="{$csrfToken|escape}">
                        <input type="hidden" name="confirm_value" value="REBOOT">
                        <button type="submit" class="owp-cvm-btn owp-cvm-btn-warning" {if $instance.can_reboot neq '1'}disabled="disabled"{/if}>Reboot</button>
                    </form>
                </div>

                <details class="owp-cvm-details">
                    <summary><span>Reset Password</span><span>expand</span></summary>
                    <div class="owp-cvm-details-body">
                        <form method="post">
                            <input type="hidden" name="owp_client_action" value="reset_password">
                            <input type="hidden" name="owp_tencentcvm_csrf_token" value="{$csrfToken|escape}">
                            <div class="owp-cvm-form-grid">
                                <div class="owp-cvm-field">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" autocomplete="new-password" placeholder="8-30 chars, 3+ categories">
                                </div>
                                <div class="owp-cvm-field">
                                    <label>Confirm Password</label>
                                    <input type="password" name="confirm_password" autocomplete="new-password" placeholder="repeat new password">
                                </div>
                            </div>
                            <p class="owp-cvm-help">If Tencent Cloud requires a stopped instance for password reset, stop the service first or wait for a later explicit force-stop policy.</p>
                            <button type="submit" class="owp-cvm-btn owp-cvm-btn-warning" {if $instance.can_reset_password neq '1'}disabled="disabled"{/if}>Reset Password</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>

        <details class="owp-cvm-details owp-cvm-details-danger">
            <summary><span>Dangerous Operations</span><span>expand</span></summary>
            <div class="owp-cvm-details-body">
                <p class="owp-cvm-help">Reinstall is intentionally isolated and blocked in v0.8.3. Future support will require an admin policy switch and typed confirmation.</p>
                <form method="post">
                    <input type="hidden" name="owp_client_action" value="reinstall">
                    <input type="hidden" name="owp_tencentcvm_csrf_token" value="{$csrfToken|escape}">
                    <div class="owp-cvm-field">
                        <label>Type REINSTALL to confirm</label>
                        <input type="text" name="confirm_value" placeholder="REINSTALL">
                    </div>
                    <br>
                    <button type="submit" class="owp-cvm-btn owp-cvm-btn-danger" {if $instance.can_reinstall_request neq '1'}disabled="disabled"{/if}>Request Reinstall</button>
                </form>
            </div>
        </details>

        <div class="owp-cvm-card">
            <div class="owp-cvm-actions">
                <div class="owp-cvm-section-label">Recent Operations</div>
            </div>
            <div class="owp-cvm-table-wrap">
                <table class="owp-cvm-table">
                    <thead>
                        <tr>
                            <th>Operation</th>
                            <th>Actor</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Result</th>
                            <th>Request ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $operations|@count > 0}
                            {foreach from=$operations item=operation}
                                <tr>
                                    <td>{$operation.operation|escape}</td>
                                    <td>{$operation.actor_type|default:'system'|escape}{if $operation.actor}<br><small>{$operation.actor|escape}</small>{/if}</td>
                                    <td>
                                        <span class="owp-cvm-pill {if $operation.status eq 'success'}owp-cvm-pill-success{elseif $operation.status eq 'failed'}owp-cvm-pill-danger{else}owp-cvm-pill-warning{/if}">
                                            {$operation.status|escape}
                                        </span>
                                    </td>
                                    <td>{$operation.created_at|escape}</td>
                                    <td>{$operation.message|escape}</td>
                                    <td>{$operation.request_id|default:'-'|escape}</td>
                                </tr>
                            {/foreach}
                        {else}
                            <tr><td colspan="6">No operation records yet.</td></tr>
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
