# Tencent Cloud API Client

v0.3 adds a self-contained Tencent Cloud API 3.0 client for CVM. It is bundled
inside the WHMCS module and does not require Composer.

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
- `describeInstancesStatus`
- `runInstances`
- `startInstances`
- `stopInstances`
- `rebootInstances`
- `resetInstancesPassword`
- `describeInstanceVncUrl`
- `terminateInstances`

`runInstances()` defaults to `DryRun = true` unless the caller explicitly passes
`false`. WHMCS lifecycle entrypoints still return "not implemented", so
installing the module does not automatically create, stop, reboot, or terminate
cloud resources.

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
