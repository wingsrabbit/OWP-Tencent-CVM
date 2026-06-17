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

Status: merged.

Deliverables:

- `ROADMAP.md`
- README link to the roadmap
- version/changelog update

Acceptance:

- The module boundary is clear.
- PR/version rules are clear.
- Design prompt policy is explicit.

### v0.12 · Client-area design prompt

Status: merged.

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
- Claude Design H5 output is accepted as a desktop main-panel prototype first;
  responsive/mobile treatment remains part of the client-area implementation
  acceptance.

Acceptance:

- The operator can paste the prompt into the design tool.
- The design output can be mapped to `clientarea.tpl` without guessing.

### v0.13 · Admin-addon design prompt

Status: current PR.

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

Status: current stacked PR.

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

Status: current stacked PR.

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

Status: stacked PR.

Deliverables:

- Read-only checks for region/zone/template compatibility.
- Clear admin warnings for missing image, subnet, security group, or insufficient quota.

Acceptance:

- Bad templates fail before customer order provisioning.

---

## Phase 4 · Provisioning Lifecycle

### v0.5 · CreateAccount and status persistence

Status: stacked PR.

Deliverables:

- Map WHMCS product/configurable options to an admin template.
- Call `RunInstances` with idempotency protection.
- Store `InstanceId`, region, IPs, task state, and template snapshot.
- Poll until `RUNNING` or timeout.

Acceptance:

- Duplicate WHMCS retries do not create duplicate CVMs.
- Failure states are recoverable from admin logs.

### v0.51 · Suspend, unsuspend, terminate

Status: stacked PR.

Deliverables:

- Suspend policy: default stop instance, optionally security-group isolation later.
- Unsuspend policy: start instance.
- Terminate policy: call Tencent Cloud termination action only with explicit admin/module lifecycle trigger.

Acceptance:

- Lifecycle aligns with WHMCS overdue/payment/cancellation flows.
- Destructive action logs include WHMCS service ID and Tencent `RequestId`.

### v0.52 · ChangePassword and ChangePackage

Status: stacked PR.

Deliverables:

- Reset password path.
- Package/template change policy.

Acceptance:

- Password reset handles running-instance force-stop requirements safely.
- Unsupported package changes fail with a readable message instead of partial changes.

### v0.8.0 · Automatic network and EIP modes

Status: merged.

Deliverables:

- Allow templates to leave VPC, subnet, and security group IDs blank.
- Auto-create or reuse shared `owp-whmcs-auto-*` VPC, subnet, and security group resources.
- Add direct public IP, Elastic IP, and Anycast Elastic IP template modes.
- Store EIP address IDs and release associated EIPs before instance termination.
- Keep dry-run limited to CVM dry-run validation with no real network or EIP mutations.

Acceptance:

- Existing WHMCS addon installations upgrade schema idempotently without dropping tables.
- Blank network IDs validate as runtime auto-resource intent instead of failing read-only checks.
- Non-direct public IP modes run `RunInstances` with direct public IP assignment disabled.
- TerminateAccount releases recorded EIPs before calling `TerminateInstances`.

### v0.8.1 · Security group policy self-heal and prefix setting

Status: merged.

Deliverables:

- Split auto security group ingress and egress policy creation into separate Tencent Cloud API calls.
- Check cached or name-matched auto security groups for required allow-all ingress and egress policies before reuse.
- Cache newly created auto security groups only after required policies are confirmed.
- Add admin-configurable auto resource name prefix for new VPC, subnet, and security group names.

Acceptance:

- A v0.8.0-created security group with missing policies is repaired automatically on the next provisioning attempt.
- Reused security groups do not skip policy verification.
- Changing the prefix does not delete or rename existing Tencent Cloud resources.

### v0.8.2 · EIP and Anycast provisioning fixes

Status: merged.

Deliverables:

- Keep `RunInstances` bandwidth settings only for direct public IP mode.
- Allocate ordinary EIPs with charge type and bandwidth cap.
- Allocate Anycast EIPs without `InternetChargeType`, while keeping the bandwidth cap.
- Parse Tencent Anycast EIP `AddressSet` string-array responses.

Acceptance:

- EIP and Anycast templates do not send CVM public-bandwidth parameters while direct public IP templates still do.
- Anycast EIP allocation can read the returned EIP ID when `AddressSet` contains strings.
- Anycast bandwidth cap is applied during allocation without requiring a later bandwidth mutation API.

### v0.8.3 · EIP association readiness wait

Status: merged.

Deliverables:

- Poll `DescribeAddresses` after ordinary EIP and Anycast EIP allocation.
- Wait until `AddressStatus` reaches `UNBIND` before calling `AssociateAddress`.
- Record a successful readiness check and fail with a clear timeout if the EIP never becomes attachable.

Acceptance:

- Anycast EIP provisioning does not call `AssociateAddress` while the address is still `CREATING`.
- Ordinary EIP provisioning uses the same bounded readiness wait without changing allocation payloads.
- Timeout errors identify the EIP ID and last observed address status.

### v0.8.4 · ClientToken ghost instance recovery

Status: merged.

Deliverables:

- Verify the `RunInstances` instance ID with a short bounded `DescribeInstances` check before persisting it or binding EIP.
- Keep the normal ClientToken stable for ordinary WHMCS duplicate CreateAccount calls.
- When Tencent Cloud idempotency returns a missing terminated instance, retry with a deterministic differentiated ClientToken derived from the ghost instance ID.
- Cap ghost recovery retries and record validation/rebuild operation logs.

Acceptance:

- A missing `InstanceSet` after `RunInstances` does not flow into `AssociateAddress`.
- A terminated ghost instance returned by the base ClientToken triggers at most two differentiated-token rebuild attempts.
- Failure messages include the last ghost `InstanceId` instead of the later EIP association error.

### v0.8.5 · CVM-ready EIP association retry

Status: current PR.

Deliverables:

- Wait for the target CVM to reach `RUNNING` before `AssociateAddress`.
- Check whether a recorded EIP is actually associated to the recorded instance before skipping existing-instance CreateAccount.
- Reuse a recorded allocated-but-unbound EIP on retry instead of allocating a new address.

Acceptance:

- `AssociateAddress` is not called while the target CVM is still `PENDING`.
- Existing service records with `instance_id` plus unbound `eip_address_id` enter EIP association instead of returning skipped success.
- Retry for service 19 can reuse the recorded CVM and EIP resources without requiring cleanup.

---

## Phase 5 · Client Area

### v0.6 · Client-area implementation from accepted design

Status: stacked PR.

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

Status: current stacked PR.

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
