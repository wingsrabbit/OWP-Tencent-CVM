# Tencent Cloud CAM Policy

This is a starter CAM policy for OWP Tencent CVM v0.8.0. Review it in Tencent
Cloud CAM before production use. Tencent Cloud recommends using the policy
generator for custom policies, and the CAM policy language is JSON-based with
`version`, `statement`, `action`, `resource`, and `effect` fields.

Official references:

- Policy syntax: <https://www.tencentcloud.com/document/product/598/10604>
- Custom policy generator: <https://www.tencentcloud.com/document/product/598/35596>
- CVM authorization granularity: <https://www.tencentcloud.com/document/product/598/57095>

## Baseline Policy

Some CVM API actions are operation-level permissions and must use
`"resource": "*"` in CAM. If Tencent Cloud later exposes tighter resource-level
authorization for a specific action, prefer the narrower resource scope.

```json
{
  "version": "2.0",
  "statement": [
    {
      "effect": "allow",
      "action": [
        "cvm:DescribeInstances",
        "cvm:DescribeInstancesStatus",
        "cvm:DescribeZones",
        "cvm:DescribeImages",
        "cvm:DescribeZoneInstanceConfigInfos",
        "vpc:DescribeVpcs",
        "vpc:DescribeSubnets",
        "vpc:DescribeSecurityGroups",
        "vpc:DescribeAddresses"
      ],
      "resource": "*"
    },
    {
      "effect": "allow",
      "action": [
        "cvm:RunInstances",
        "cvm:StartInstances",
        "cvm:StopInstances",
        "cvm:RebootInstances",
        "cvm:ResetInstancesPassword",
        "cvm:DescribeInstanceVncUrl",
        "vpc:CreateVpc",
        "vpc:CreateSubnet",
        "vpc:CreateSecurityGroup",
        "vpc:CreateSecurityGroupPolicies",
        "vpc:AllocateAddresses",
        "vpc:AssociateAddress",
        "vpc:DisassociateAddress",
        "vpc:ReleaseAddresses"
      ],
      "resource": "*"
    }
  ]
}
```

## Optional Terminate Permission

Add this statement only if the WHMCS addon setting
`Allow destructive TerminateAccount API calls` is intentionally enabled and the
operator has a rollback plan.

```json
{
  "effect": "allow",
  "action": [
    "cvm:TerminateInstances"
  ],
  "resource": "*"
}
```

## Not Required In v0.8.0

Do not grant these actions for the current module:

- `cvm:ResetInstance` or other reinstall/reimage APIs
- route table mutation APIs
- image creation/deletion APIs
- disk snapshot or disk mutation APIs

## Operator Notes

- Use a dedicated CAM user or role for WHMCS.
- Store only the SecretId and SecretKey through the WHMCS addon page.
- Keep dry-run enabled until the exact product, template, region, and approval
  record are known.
- Re-check this policy whenever the module adds new Tencent Cloud API methods.
