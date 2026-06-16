# Install Guide

OWP Tencent CVM v0.3 is a WHMCS module skeleton with a bundled Tencent Cloud API
client. It creates the module file layout and local database tables, but WHMCS
lifecycle entrypoints still do not call Tencent Cloud automatically.

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
4. Confirm the skeleton page shows version `0.3` and the four expected table names.

## Product Module

1. Create or edit a WHMCS product.
2. Set Module Name to `OWP Tencent CVM`.
3. Fill the placeholder module options:
   - Template Name
   - Default Region
   - Dry Run
4. Save the product.

Provisioning, lifecycle buttons, password reset, and console links intentionally
return readable "not implemented" messages in v0.3.

## Secrets

Do not commit Tencent Cloud credentials, WHMCS credentials, service IDs, or
customer data to git.

Use local private notes such as `SECRETS.local.md` outside public commits. That
file is ignored by this repository.

## Safety Boundary

Codex does not upload, install, or activate this module on live WHMCS. The
operator handles WHMCS installation manually.
