# Release Package

OWP Tencent CVM v0.7.1 is intended to be shipped as a public source package that
an operator uploads into WHMCS manually. The package must contain module code and
public operator docs only. It must not contain local secrets, WHMCS deployment
values, customer data, screenshots with private data, or live validation notes.

## Package Contents

Include:

- `modules/servers/owp_tencentcvm/`
- `modules/addons/owp_tencentcvm/`
- `install/schema.sql`
- `docs/INSTALL.md`
- `docs/TENCENT_API.md`
- `docs/CAM_POLICY.md`
- `docs/RELEASE.md`
- `README.md`
- `CHANGELOG.md`
- `LICENSE`
- `VERSION`

Exclude:

- `.git/`
- `SECRETS.local.md`
- `dist/`
- live WHMCS configuration files
- local validation notes with account IDs, service IDs, request IDs, IPs, or
  customer data

## Build Command

Run this from a clean git checkout after the release PR is merged:

```bash
VERSION="$(cat VERSION)"
mkdir -p dist
git archive \
  --format=zip \
  --prefix="owp-tencent-cvm-v${VERSION}/" \
  --output="dist/owp-tencent-cvm-v${VERSION}.zip" \
  HEAD \
  modules/servers/owp_tencentcvm \
  modules/addons/owp_tencentcvm \
  install/schema.sql \
  docs/INSTALL.md \
  docs/TENCENT_API.md \
  docs/CAM_POLICY.md \
  docs/RELEASE.md \
  README.md CHANGELOG.md LICENSE VERSION
```

Optional local inspection:

```bash
unzip -l "dist/owp-tencent-cvm-v${VERSION}.zip"
```

## Preflight Checklist

- `git status --short` is clean.
- `VERSION`, `README.md`, `modules/servers/owp_tencentcvm/lib/bootstrap.php`,
  and `modules/servers/owp_tencentcvm/lib/Config.php` show the same version.
- `SECRETS.local.md` exists only locally and is not staged.
- Public docs do not contain Tencent Cloud keys, WHMCS credentials, customer
  names, customer IPs, live service IDs, or private request IDs.
- The package is built from the intended merge commit.
- The operator has reviewed [CAM_POLICY.md](CAM_POLICY.md) and created a CAM
  user or role with only the required permissions.

## Manual WHMCS Upload

1. Extract the release zip locally.
2. Upload `modules/servers/owp_tencentcvm/` into the WHMCS root at the same path.
3. Upload `modules/addons/owp_tencentcvm/` into the WHMCS root at the same path.
4. In WHMCS admin, activate `OWP Tencent CVM` under Addon Modules.
5. Open the addon page and confirm version `0.7.1`.
6. Enter Tencent Cloud credentials in the addon page.
7. Keep addon dry-run enabled.
8. Create a resource template and run template validation.
9. Configure a WHMCS product to use module `OWP Tencent CVM`.
10. Keep the product `Dry Run` checkbox enabled until the operator approves a
    real Tencent Cloud provisioning test.

## Operator Validation Checklist

Use this checklist for the first manual install. Record results only in a
private operator note unless all identifiers are redacted.

- Addon activates without a WHMCS fatal error.
- Addon page shows version `0.7.1`.
- Addon-created tables exist or `install/schema.sql` matches the expected table
  shape.
- Saved Tencent Cloud SecretId is displayed only in masked form.
- Template validation can check zone, image, instance type, subnet, security
  group, and bandwidth policy.
- A WHMCS product can select an enabled and validated template by name.
- With dry-run enabled, CreateAccount does not create a billable Tencent Cloud
  CVM.
- Client-area page shows instance metadata, control buttons, and recent
  operations without exposing Tencent Cloud credentials or raw internal errors.
- Stop, reboot, password reset, and reinstall paths require explicit
  confirmation before handler execution.
- Reinstall remains blocked and makes no Tencent Cloud API call in v0.7.1.

## Live-Call Boundary

Disable dry-run only after the operator approves the exact WHMCS product,
Tencent Cloud account, region, template, rollback path, and validation record.
The first live provisioning pass belongs in v0.71 validation notes, not in the
public release package.
