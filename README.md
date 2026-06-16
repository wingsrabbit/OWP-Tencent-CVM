# OWP Tencent CVM

> **WHMCS Tencent Cloud CVM provisioning suite** -- a paired server module and addon module for selling, provisioning, and managing Tencent Cloud CVM instances from WHMCS.

![version](https://img.shields.io/badge/version-v0.1-blue)
![WHMCS](https://img.shields.io/badge/WHMCS-9.x-2a9fd6)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![license](https://img.shields.io/badge/license-MIT-orange)

---

## Status

This repository is in the initial planning/bootstrap stage. The first implementation target is a WHMCS module suite:

- `modules/servers/owp_tencentcvm/`: WHMCS provisioning module for service lifecycle and client-area controls.
- `modules/addons/owp_tencentcvm/`: WHMCS addon module for admin-side Tencent Cloud credentials, sellable templates, resource policy, logs, and recovery tools.

No Tencent Cloud credentials, WHMCS production values, customer data, or deployment secrets belong in this repository.

See [ROADMAP.md](ROADMAP.md) for the staged implementation plan, PR/version rules, and design-prompt workflow.

---

## Target Features

| Area | Scope |
|------|-------|
| Provisioning | Create Tencent Cloud CVM instances after paid WHMCS orders, store `InstanceId`, public IPs, region, image, and template metadata. |
| Lifecycle | Start, stop, reboot, reset password, suspend, unsuspend, terminate, and poll async operation status. |
| Client area | Product-detail page controls for power actions, password reset, VNC/console link, instance status, IPs, and operation history. |
| Admin addon | Configure Tencent Cloud API credentials, regions, zones, instance templates, images, VPC/subnet/security group policy, dry-run mode, and module logs. |
| Safety | CAM least-privilege credentials, no hardcoded secrets, idempotent create flow, operation audit log, destructive-action confirmation. |
| Packaging | Public white-label package following the ONC-style README, VERSION, CHANGELOG, and MIT license pattern. |

---

## Architecture

```text
WHMCS order/payment/cron
        |
        v
modules/servers/owp_tencentcvm
        |
        | shared lib + database tables
        v
modules/addons/owp_tencentcvm
        |
        v
Tencent Cloud CVM API
        |
        v
Customer CVM instance
```

The module should call Tencent Cloud API 3.0 directly with a small self-contained client for the CVM actions required by this package. This keeps WHMCS installation simple and avoids requiring Composer on production systems.

---

## Planned Directory Structure

```text
modules/
  servers/owp_tencentcvm/
    owp_tencentcvm.php
    clientarea.tpl
    lib/
      TencentClient.php
      Schema.php
      Config.php
      Templates.php
      Instances.php
      Operations.php
  addons/owp_tencentcvm/
    owp_tencentcvm.php
install/
  schema.sql
docs/
  INSTALL.md
```

---

## Local Secrets

Use `SECRETS.local.md` in the local project directory for private notes such as Tencent Cloud `SecretId`, `SecretKey`, region choices, test service IDs, and WHMCS deployment values.

That file is ignored by git and must never be copied into issues, pull requests, logs, screenshots, or public docs.

---

## License

[MIT](LICENSE) (c) 2026 wingsrabbit
