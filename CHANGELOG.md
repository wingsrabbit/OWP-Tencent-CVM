# Changelog

This project increments versions by operation size, following the ONC convention:

- Large operation: `+0.1`
- Medium operation: `+0.01`
- Small operation: `+0.001`

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.6] - 2026-06-17

### Added

- Added `ClientAreaController` for embedded WHMCS product-page CVM controls.
- Added customer start, stop, reboot, password reset, console, refresh, and blocked reinstall actions.
- Added recent operation history to `clientarea.tpl`.
- Added customer-facing instance overview with status, IPs, region, template, instance type, image, bandwidth, due date, WHMCS service ID, and Tencent instance ID.
- Added scoped responsive CSS for the accepted client-area design.

### Changed

- Replaced the placeholder client-area template with the accepted CVM control panel layout.
- Hid WHMCS default client-area custom buttons to avoid duplicate controls outside the embedded panel.
- Kept customer reinstall blocked with an audit log until an explicit reinstall policy exists.
- Updated `README.md`, `VERSION`, install docs, and Tencent API docs for the v0.6 client-area implementation stage.

## [0.52] - 2026-06-16

### Added

- Added guarded WHMCS ChangePassword support through Tencent `ResetInstancesPassword`.
- Added local password complexity checks before calling Tencent Cloud.
- Added `ForceStop = false` to password reset calls by default.
- Added audited ChangePackage rejection until a planned resize workflow exists.

### Changed

- Updated Tencent client password reset support to include `ForceStop` and optional username parameters.
- Updated `README.md`, `VERSION`, install docs, and Tencent API docs for the v0.52 password and package policy stage.
- Kept customer power buttons, customer reset-password button, and console actions returning readable "not implemented" messages.

## [0.51] - 2026-06-16

### Added

- Added `LifecycleManager` for WHMCS SuspendAccount, UnsuspendAccount, and TerminateAccount.
- Added dry-run blocking for suspend, unsuspend, and terminate API calls.
- Added addon `allow_terminate` safety setting for destructive Tencent CVM termination.
- Added lifecycle operation audit rows for start, stop, terminate, blocked, skipped, and dry-run outcomes.

### Changed

- Wired WHMCS SuspendAccount to guarded Tencent `StopInstances`.
- Wired WHMCS UnsuspendAccount to guarded Tencent `StartInstances`.
- Wired WHMCS TerminateAccount to guarded Tencent `TerminateInstances` with an extra explicit addon approval gate.
- Updated `README.md`, `VERSION`, install docs, and Tencent API docs for the v0.51 guarded service lifecycle stage.
- Kept customer power buttons, password reset, console, and package change returning readable "not implemented" messages.

## [0.5] - 2026-06-16

### Added

- Added guarded `CreateAccount` provisioning through `Provisioner`.
- Added WHMCS service-instance persistence helpers in `Instances`.
- Added operation audit inserts through `Operations::record`.
- Added Tencent `DescribeInstances` by ID support for read-only status sync.
- Added admin `Sync Instance Status` wiring for stored Tencent CVM instances.
- Added client-area display for stored instance ID, state, public IP, and private IP.

### Changed

- Updated `CreateAccount` to require an enabled admin template with `validation_status = valid`.
- Kept dry-run enabled by default through addon settings and product module options.
- Added Tencent `ClientToken` idempotency to `RunInstances` payloads.
- Updated `README.md`, `VERSION`, install docs, and Tencent API docs for the v0.5 guarded provisioning stage.
- Kept suspend, unsuspend, terminate, power, password reset, package change, and console actions returning readable "not implemented" messages.

## [0.41] - 2026-06-16

### Added

- Added `TemplateValidator` for admin-triggered read-only resource template checks.
- Added template validation actions to the WHMCS admin addon table.
- Added CVM read-only methods for zones, images, and zone instance configuration.
- Added VPC read-only methods for subnets and security groups.
- Added persistent validation status and messages for sellable templates.

### Changed

- Reset template validation status when a template is edited.
- Updated `README.md`, `VERSION`, install docs, and Tencent API docs for the v0.41 template validation stage.
- Kept WHMCS provisioning and lifecycle entrypoints returning readable "not implemented" messages, so validation cannot automatically create, stop, reboot, or destroy CVMs.

## [0.4] - 2026-06-16

### Added

- Added encrypted admin config storage through `ConfigStore` for Tencent Cloud credentials, endpoint, default region, timeout, and dry-run policy.
- Added the WHMCS admin addon page controller for credentials, read-only connection testing, and resource template management.
- Added resource template CRUD helpers backed by `mod_owp_tencentcvm_templates`.
- Added admin-side create/update, enable/disable, and delete forms for sellable CVM templates.
- Added read-only `DescribeInstances` credential test action.

### Changed

- Updated `README.md`, `VERSION`, and install docs for the v0.4 admin resource management stage.
- Kept WHMCS provisioning and lifecycle entrypoints returning readable "not implemented" messages, so template setup cannot automatically create or destroy CVMs.

## [0.3] - 2026-06-16

### Added

- Added a self-contained Tencent Cloud API 3.0 client using TC3-HMAC-SHA256 signing and POST JSON requests.
- Added normalized Tencent API response handling through `TencentResponse`.
- Added Tencent API error context through `TencentApiException`.
- Added `Redactor` for masking credentials, authorization headers, tokens, and passwords before logging.
- Added CVM library methods for describe, dry-run run, start, stop, reboot, reset password, VNC URL, and terminate actions.
- Added `docs/TENCENT_API.md` documenting the client behavior and safety boundaries.

### Changed

- Updated `README.md`, `VERSION`, and install docs for the v0.3 API foundation stage.
- Kept WHMCS provisioning and lifecycle entrypoints returning readable "not implemented" messages, so the new client is not invoked automatically.

## [0.2] - 2026-06-16

### Added

- Added the WHMCS server provisioning module skeleton at `modules/servers/owp_tencentcvm/owp_tencentcvm.php`.
- Added the WHMCS admin addon module skeleton at `modules/addons/owp_tencentcvm/owp_tencentcvm.php`.
- Added shared PHP library classes for loading, configuration, schema creation, template placeholders, instance placeholders, operations, and a non-networked Tencent client placeholder.
- Added the minimal client-area Smarty template at `modules/servers/owp_tencentcvm/clientarea.tpl`.
- Added idempotent database schema creation through `install/schema.sql` and the addon activation routine.
- Added `docs/INSTALL.md` with the manual WHMCS upload and activation checklist.

### Changed

- Updated `README.md` and `VERSION` for the v0.2 module skeleton stage.
- Kept all Tencent Cloud actions as readable "not implemented" responses so no live API calls can occur in this version.

## [0.13] - 2026-06-16

### Added

- Added the Claude Design prompt for the WHMCS admin addon page at `docs/design/admin-addon-prompt.md`.
- Added a shorter Claude Design revision prompt at `docs/design/admin-addon-revision-prompt.md` for iterative artifact fixes.
- Linked both design prompts from `README.md`.

### Changed

- Tightened the admin addon prompt after reviewing Claude Design outputs, requiring visible template copy action, billing/disk fields, confirmation before disabling templates, and no clipped row actions at 1280px width.

## [0.12] - 2026-06-16

### Added

- Added the client-area CVM control panel design prompt at `docs/design/client-area-prompt.md`.
- Documented the external design-tool workflow in `README.md`.

### Changed

- Refined the client-area prompt after reviewing the first `docs/design/tx-cvm.zip` output, emphasizing a production-like WHMCS embedded module over a design-showcase page.
- Reworked the client-area prompt for pure design AI output after reviewing `docs/design/tx-cvm 2`, limiting the next iteration to one customer-facing desktop main panel.
- Retargeted the client-area prompt for Claude Design / Artifacts H5 output, allowing hidden interactive modals while keeping the rendered page to one customer-facing WHMCS panel.

## [0.11] - 2026-06-16

### Added

- Added `ROADMAP.md` with staged implementation plan, PR/version rules, hard boundaries, and acceptance criteria.
- Documented that customer/admin page design is produced as external design-tool prompts before implementation.
- Linked the roadmap from `README.md`.

## [0.1] - 2026-06-16

### Added

- Initial public repository baseline.
- ONC-style `README.md`, `VERSION`, `CHANGELOG.md`, `.gitignore`, and MIT license.
- Defined first implementation target: paired WHMCS server module and addon module for Tencent Cloud CVM provisioning and management.
