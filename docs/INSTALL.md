# Install Guide

OWP Tencent CVM v0.8.1 is a WHMCS module with a bundled Tencent Cloud API client,
encrypted credential storage, admin-side resource templates, read-only template
validation, automatic VPC/subnet/security-group reuse, direct EIP and Anycast
EIP modes, guarded CreateAccount provisioning, guarded service lifecycle
actions, guarded password reset, package-change rejection, and read-only status
sync. It also includes the customer-facing CVM control panel under the WHMCS
product detail page.

For release package assembly, see [RELEASE.md](RELEASE.md). For Tencent Cloud
CAM permissions, see [CAM_POLICY.md](CAM_POLICY.md).

## What To Upload

Copy these directories into the WHMCS root, preserving paths:

```text
modules/servers/owp_tencentcvm/
modules/addons/owp_tencentcvm/
```

The optional SQL reference is available at:

```text
install/schema.sql
```

The addon activation routine creates the same tables idempotently through the
WHMCS Capsule database layer.

## Activation

1. In WHMCS admin, go to Setup -> Addon Modules.
2. Activate `OWP Tencent CVM`.
3. Open Addons -> OWP Tencent CVM.
4. Confirm the admin page shows version `0.8.1` in the module metadata.
5. Save credentials and create at least one resource template.
6. Use `Validate` on each template to check zone, image, instance type, fixed subnet, fixed security group, public IP mode, and bandwidth policy before enabling sales. Blank VPC, subnet, or security group fields are skipped during validation and auto-created or reused during provisioning.

## Product Module

1. Create or edit a WHMCS product.
2. Set Module Name to `OWP Tencent CVM`.
3. Fill the placeholder module options:
   - Template Name
   - Default Region
   - Dry Run
4. Save the product.

CreateAccount requires the selected admin template to be enabled and validated
as `valid`. Dry-run is enabled by default and blocks live CreateAccount,
suspend, unsuspend, and terminate API calls. CreateAccount may still send
Tencent `RunInstances` with `DryRun = true`, but no billable or state-changing
Tencent Cloud action is made until dry-run is explicitly disabled for the
product and addon settings.

For resource templates, VPC ID, Subnet ID, and Security Group ID can be left
blank. In live provisioning, the module auto-creates or reuses shared
VPC, subnet, and security group resources and stores their IDs in the addon
config table. Auto-created names use `Auto Resource Name Prefix` from the addon
settings, defaulting to `owp-whmcs`. Changing the prefix affects newly created
resources only; clear the relevant `auto_vpc_*`, `auto_subnet_*`, and
`auto_sg_*` config keys manually if the operator intentionally wants the module
to rebuild auto resources with the new prefix. In dry-run, those network and EIP
resources are not created.

TerminateAccount has an additional addon safety checkbox and remains blocked
unless `Allow destructive TerminateAccount API calls` is explicitly enabled.

Customer start, stop, reboot, password reset, console, and refresh controls are
available in the embedded product-page panel. They remain blocked by dry-run
until the addon and product safety settings are intentionally changed.

ChangePassword is wired and calls Tencent `ResetInstancesPassword`
without `ForceStop`. If Tencent reports that the instance must be stopped first,
suspend the service before retrying or wait for a later explicit force-stop
policy switch. ChangePackage is intentionally rejected with an audit log until a
planned resize workflow exists.

## Secrets

Do not commit Tencent Cloud credentials, WHMCS credentials, service IDs, or
customer data to git.

Use local private notes such as `SECRETS.local.md` outside public commits. That
file is ignored by this repository.

## Safety Boundary

Codex does not upload, install, or activate this module on live WHMCS. The
operator handles WHMCS installation manually.

Keep a local install note outside git for the exact WHMCS host, Tencent account,
CAM user, test service ID, and any approval record used when dry-run is disabled.
