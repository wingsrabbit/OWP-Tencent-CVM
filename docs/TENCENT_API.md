# Tencent Cloud API Client

v0.5 includes a self-contained Tencent Cloud API 3.0 client for CVM, read-only
validation calls needed by the admin template workflow, guarded CreateAccount
provisioning, and read-only instance status sync. It is bundled inside the WHMCS
module and does not require Composer.

## Signing

The client uses TC3-HMAC-SHA256 with POST JSON requests:

- HTTP method: `POST`
- Canonical URI: `/`
- Content type: `application/json`
- Default endpoint: `cvm.tencentcloudapi.com`
- Default service: `cvm`
- Default CVM API version: `2017-03-12`

Common request parameters are sent through `X-TC-*` headers, including action,
version, timestamp, region, and optional session token.

## Supported CVM Methods

The library layer exposes these methods:

- `describeInstances`
- `describeInstancesByIds`
- `describeInstancesStatus`
- `describeZones`
- `describeImages`
- `describeZoneInstanceConfigInfos`
- `runInstances`
- `startInstances`
- `stopInstances`
- `rebootInstances`
- `resetInstancesPassword`
- `describeInstanceVncUrl`
- `terminateInstances`

`runInstances()` defaults to `DryRun = true` unless the caller explicitly passes
`false`. CreateAccount uses `runInstances()` only after the selected admin
template is enabled, validated as `valid`, and dry-run is disabled. Start, stop,
reboot, password reset, VNC, and terminate methods remain library-only in v0.5
and are not wired into customer buttons.

## Supported VPC Validation Methods

`TemplateValidator` uses a VPC-scoped client with endpoint
`vpc.tencentcloudapi.com`, service `vpc`, and API version `2017-03-12` for:

- `describeSubnets`
- `describeSecurityGroups`

Those calls are read-only and are used only from the admin addon template
validation action.

## Responses And Errors

Successful responses are normalized into `TencentResponse`, which exposes:

- action
- request ID
- HTTP status
- response data
- raw decoded response

Tencent Cloud API errors throw `TencentApiException` with:

- Tencent error code
- request ID
- action
- HTTP status
- readable message

## Redaction

`Redactor` masks common sensitive values before they are safe to log or display:

- SecretId / SecretKey
- Authorization
- token values
- passwords

Do not log raw request headers or payloads without passing them through the
redactor first.
