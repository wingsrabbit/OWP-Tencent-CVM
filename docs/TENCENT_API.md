# Tencent Cloud API Client

v0.8.1 includes a self-contained Tencent Cloud API 3.0 client for CVM, VPC,
security group, EIP, Anycast EIP, read-only validation calls needed by the admin
template workflow, guarded CreateAccount provisioning, guarded service lifecycle
actions, guarded password reset, and read-only instance status sync. It is
bundled inside the WHMCS module and does not require Composer.

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

Empty Tencent Cloud API payloads are JSON-encoded as `{}` before signing. This
matches Tencent API 3.0 examples and avoids signing `[]` for actions with no
body parameters.

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
`false`. CreateAccount requires the selected admin template to be enabled and
validated as `valid`; with dry-run enabled it sends only a dry-run validation
request, and with dry-run disabled it sends the live provisioning request.

When a template uses `public_ip_mode = eip` or `anycast_eip`, CreateAccount sets
`PublicIpAssigned = false` in `RunInstances`, then allocates and associates the
EIP after Tencent returns the instance ID. Direct public IP mode keeps
`PublicIpAssigned = true`.

WHMCS SuspendAccount and UnsuspendAccount call `StopInstances` and
`StartInstances` only when dry-run is disabled. WHMCS TerminateAccount calls
`TerminateInstances` only when dry-run is disabled and the addon
`allow_terminate` setting is explicitly enabled. Customer start, stop, reboot,
reset-password, and VNC controls are wired through the embedded client-area
panel in v0.8.1 and remain blocked by dry-run where they can change instance
state. Customer reinstall remains blocked.

WHMCS ChangePassword calls `ResetInstancesPassword` only when dry-run is
disabled and a local Tencent CVM instance ID exists. It sends `ForceStop =
false` by default, so running instances may need to be suspended before password
reset. WHMCS ChangePackage is intentionally rejected with an audit log in v0.8.1.

Custom endpoints are restricted to `*.tencentcloudapi.com`. Invalid stored or
submitted endpoint values fall back to `cvm.tencentcloudapi.com`.

See [CAM_POLICY.md](CAM_POLICY.md) for a starter CAM policy that covers these
actions.

## Supported VPC And EIP Methods

`TemplateValidator`, `NetworkResourceManager`, and `ElasticIpManager` use a
VPC-scoped client with endpoint
`vpc.tencentcloudapi.com`, service `vpc`, and API version `2017-03-12` for:

- `describeVpcs`
- `describeSubnets`
- `describeSecurityGroups`
- `describeSecurityGroupPolicies`
- `createVpc`
- `createSubnet`
- `createSecurityGroup`
- `createSecurityGroupPolicies`
- `allocateAddresses`
- `associateAddress`
- `describeAddresses`
- `disassociateAddress`
- `releaseAddresses`

Template validation remains read-only. If VPC, subnet, or security group IDs
are blank, validation skips those reference checks and records that runtime
provisioning will auto-create or reuse shared resources.

Runtime auto-created resources use these defaults:

- VPC CIDR: `10.0.0.0/16`
- Subnet CIDR: `10.0.0.0/24`
- Security group ingress and egress: allow all, `Protocol = ALL`, `Port = ALL`,
  `CidrBlock = 0.0.0.0/0`, `Action = ACCEPT`
- Security group policies are checked with `DescribeSecurityGroupPolicies` and
  missing ingress or egress rules are created in separate
  `CreateSecurityGroupPolicies` requests.
- Auto-created resource name prefix: `auto_resource_prefix`, default
  `owp-whmcs`. Changing the prefix affects newly created resources only.
- Reuse keys in the addon config table: `auto_vpc_{region}`,
  `auto_subnet_{region}_{zone}`, and `auto_sg_{region}`

Dry-run never calls VPC, security group, allocation, association, disassociation,
or release APIs. It only attempts guarded `RunInstances` dry-run validation and
records that no CVM, network resource, or EIP was created.

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
