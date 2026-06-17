# Install Guide

OWP Tencent CVM v0.7 is a WHMCS module with a bundled Tencent Cloud API client,
encrypted credential storage, admin-side resource templates, read-only template
validation, guarded CreateAccount provisioning, guarded service lifecycle
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
4. Confirm the admin page shows version `0.7` in the module metadata.
5. Save credentials and create at least one resource template.
6. Use `Validate` on each template to check zone, image, instance type, subnet, security group, and bandwidth policy before enabling sales.

## Product Module

1. Create or edit a WHMCS product.
2. Set Module Name to `OWP Tencent CVM`.
3. Fill the placeholder module options:
   - Template Name
   - Default Region
   - Dry Run
4. Save the product.

CreateAccount requires the selected admin template to be enabled and validated
as `valid`. Dry-run is enabled by default and blocks CreateAccount, suspend,
unsuspend, and terminate API calls, so no billable or state-changing Tencent
Cloud action is made until dry-run is explicitly disabled for the product and
addon settings.

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
