# Roadmap

OWP Tencent CVM is a WHMCS module suite for selling and managing Tencent Cloud
CVM instances. The first goal is practical: a customer can control the CVM from
the WHMCS product page, and an administrator can define which Tencent Cloud
resources are safe to sell.

All implementation work after the bootstrap commit must use pull requests.

---

## Version Rules

This project follows the ONC version rhythm:

- Large operation: `+0.1`
- Medium operation: `+0.01`
- Small operation: `+0.001`

PR titles should start with the target version, for example:

```text
v0.12 · Client-area design prompt
v0.2 · Core WHMCS module skeleton
v0.31 · Tencent Cloud CVM API client
```

Branches should be scoped and readable:

```text
docs/v0.11-roadmap
feat/v0.2-module-skeleton
fix/v0.311-signature-edgecase
```

---

## Hard Boundaries

- Do not touch the live WHMCS installation from this repository workflow.
- Do not upload or install the module on production WHMCS from Codex; the operator handles that manually.
- Do not put Tencent Cloud credentials, WHMCS credentials, customer data, service IDs, or production host values in git.
- Store local private values only in `SECRETS.local.md`, which is ignored by git.
- Use Tencent Cloud CAM sub-user credentials, not root account API keys.
- Design work means producing prompts/specs for the external design tool; implementation starts only after the design output is accepted.

---

## Product Shape

The package has two WHMCS modules with shared library code:

```text
modules/servers/owp_tencentcvm/
  Provisioning module:
  CreateAccount, SuspendAccount, UnsuspendAccount, TerminateAccount,
  ChangePassword, ChangePackage, ClientArea, custom buttons.

modules/addons/owp_tencentcvm/
  Admin addon:
  Tencent Cloud credentials, sellable resource templates, regions/zones,
  VPC/subnet/security group/image policy, dry-run, operation logs, repair tools.
```

The module should call Tencent Cloud API 3.0 directly through a small bundled
client, avoiding Composer as a hard install-time dependency.

---

## Phase 0 · Design And Specs

### v0.11 · Roadmap and PR plan

Status: current PR.

Deliverables:

- `ROADMAP.md`
- README link to the roadmap
- version/changelog update

Acceptance:

- The module boundary is clear.
- PR/version rules are clear.
- Design prompt policy is explicit.

### v0.12 · Client-area design prompt

Deliverable: a prompt for the external design tool, not code.

The prompt must cover:

- Instance overview: status, region, zone, public IP, private IP, package, due date.
- Primary controls: start, stop, reboot.
- Dangerous controls: reinstall OS, reset password, terminate-like actions if exposed.
- VNC/console action with short-lived URL behavior.
- Async task states: pending, running, success, failed, retryable.
- Operation log visible to the customer.
- WHMCS embedding constraints: product detail page, no full standalone app shell.
- Confirmation copy for destructive actions.
- Mobile and desktop layout.

Acceptance:

- The operator can paste the prompt into the design tool.
- The design output can be mapped to `clientarea.tpl` without guessing.

### v0.13 · Admin-addon design prompt

Deliverable: a prompt for the external design tool, not code.

The prompt must cover:

- Credential status and CAM safety warnings.
- Region/zone/template management.
- Image, VPC, subnet, security group, bandwidth, and billing template fields.
- Dry-run mode and test connection.
- Inventory/quotas and template availability.
- Operation logs and failed-operation recovery.
- Clear distinction between safe read actions and billable/destructive writes.

Acceptance:

- Admin screens can be implemented in the WHMCS addon page without inventing UI structure later.

---

## Phase 1 · Module Skeleton

### v0.2 · WHMCS module skeleton

Status: current PR.

Deliverables:

- `modules/servers/owp_tencentcvm/owp_tencentcvm.php`
- `modules/addons/owp_tencentcvm/owp_tencentcvm.php`
- shared `lib/` loader
- `install/schema.sql`
- `docs/INSTALL.md`

Initial behavior:

- Addon activates idempotently.
- Tables can be created without secrets.
- Provisioning functions exist and return readable "not implemented" errors where needed.
- ClientArea renders a minimal placeholder.

Acceptance:

- PHP syntax checks pass in the available PHP environment.
- No live Tencent Cloud API calls are made.
- No live WHMCS install is touched.

---

## Phase 2 · Tencent Cloud API Foundation

### v0.3 · Self-contained Tencent Cloud API client

Deliverables:

- TC3-HMAC-SHA256 signing client.
- JSON POST request path for Tencent Cloud API 3.0.
- Response normalization and Tencent `RequestId` capture.
- Safe logging with SecretId/SecretKey redaction.

Supported first actions:

- `DescribeInstances`
- `DescribeInstancesStatus`
- `RunInstances` with `DryRun`
- `StartInstances`
- `StopInstances`
- `RebootInstances`
- `ResetInstancesPassword`
- `DescribeInstanceVncUrl`
- `TerminateInstances`

Acceptance:

- Unit-style signature tests use fixed sample values.
- Dry-run/read-only actions can be exercised before any billable create.
- Errors are readable in WHMCS module logs.

---

## Phase 3 · Admin Resource Management

### v0.4 · Admin addon resource templates

Deliverables:

- API credential storage through WHMCS encrypted fields or module config helpers.
- Template CRUD for sellable CVM plans.
- Region, zone, image, instance type, VPC, subnet, security group, bandwidth, charge type fields.
- Dry-run toggle.
- Test API credential/read-only query action.

Acceptance:

- Admin can define at least one sellable template.
- Test action can query Tencent Cloud without creating resources.
- Secrets are never printed back in plaintext.

### v0.41 · Quota and template validation

Deliverables:

- Read-only checks for region/zone/template compatibility.
- Clear admin warnings for missing image, subnet, security group, or insufficient quota.

Acceptance:

- Bad templates fail before customer order provisioning.

---

## Phase 4 · Provisioning Lifecycle

### v0.5 · CreateAccount and status persistence

Deliverables:

- Map WHMCS product/configurable options to an admin template.
- Call `RunInstances` with idempotency protection.
- Store `InstanceId`, region, IPs, task state, and template snapshot.
- Poll until `RUNNING` or timeout.

Acceptance:

- Duplicate WHMCS retries do not create duplicate CVMs.
- Failure states are recoverable from admin logs.

### v0.51 · Suspend, unsuspend, terminate

Deliverables:

- Suspend policy: default stop instance, optionally security-group isolation later.
- Unsuspend policy: start instance.
- Terminate policy: call Tencent Cloud termination action only with explicit admin/module lifecycle trigger.

Acceptance:

- Lifecycle aligns with WHMCS overdue/payment/cancellation flows.
- Destructive action logs include WHMCS service ID and Tencent `RequestId`.

### v0.52 · ChangePassword and ChangePackage

Deliverables:

- Reset password path.
- Package/template change policy.

Acceptance:

- Password reset handles running-instance force-stop requirements safely.
- Unsupported package changes fail with a readable message instead of partial changes.

---

## Phase 5 · Client Area

### v0.6 · Client-area implementation from accepted design

Deliverables:

- `clientarea.tpl`
- safe action handlers/buttons
- operation status display
- recent operation log

Controls:

- Start
- Stop
- Reboot
- Reset password
- VNC/console
- Reinstall OS only if the admin enables it in a later policy switch

Acceptance:

- Dangerous actions require explicit confirmation.
- In-flight operations disable conflicting buttons.
- Customer never sees Tencent Cloud API credentials or raw internal errors.

---

## Phase 6 · Packaging And Operator Docs

### v0.7 · Install guide and release package

Deliverables:

- Complete `docs/INSTALL.md`
- Packaging checklist
- Manual WHMCS install instructions
- CAM policy example
- Test checklist

Acceptance:

- Operator can upload/install the module manually.
- The guide separates public docs from private deployment values.

### v0.71 · First real-environment validation notes

Deliverables:

- Documented validation checklist results.
- Known limitations.
- Next hardening list.

Acceptance:

- No secrets in validation notes.
- Any live API calls are tied to explicit operator approval and recorded only with safe identifiers.

---

## Later Work

- Multi-account Tencent Cloud support.
- Billing reconciliation or usage import.
- Traffic graphs if Tencent Cloud metrics integration becomes necessary.
- Reinstall OS flow with image policy.
- Security group self-service firewall rules.
- Snapshots/backups if productized.
- Multi-cloud abstraction only after Tencent Cloud CVM is stable.
