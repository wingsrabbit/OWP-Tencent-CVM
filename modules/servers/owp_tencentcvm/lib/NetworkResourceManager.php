<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;

final class NetworkResourceManager
{
    private const VPC_CIDR = '10.0.0.0/16';
    private const SUBNET_CIDR = '10.0.0.0/24';
    private const NAME_PREFIX = 'owp-whmcs-auto';

    public function __construct(private readonly ConfigStore $configStore)
    {
    }

    /**
     * @param array<string, mixed> $template
     * @return array{vpc_id:string, subnet_id:string, security_group_id:string, auto_network:bool}
     */
    public function resolve(int $serviceId, int $templateId, array $template, bool $dryRun): array
    {
        $region = trim((string) ($template['region'] ?? ''));
        $zone = trim((string) ($template['zone'] ?? ''));
        $vpcId = trim((string) ($template['vpc_id'] ?? ''));
        $subnetId = trim((string) ($template['subnet_id'] ?? ''));
        $securityGroupId = trim((string) ($template['security_group_id'] ?? ''));
        $autoNetwork = $vpcId === '' || $subnetId === '' || $securityGroupId === '';

        if ($dryRun) {
            return [
                'vpc_id' => $vpcId !== '' ? $vpcId : 'vpc-dryrun-auto',
                'subnet_id' => $subnetId !== '' ? $subnetId : 'subnet-dryrun-auto',
                'security_group_id' => $securityGroupId !== '' ? $securityGroupId : 'sg-dryrun-auto',
                'auto_network' => $autoNetwork,
            ];
        }

        $client = $this->vpcClient();
        $autoVpc = $vpcId === '';

        if ($autoVpc) {
            $vpcId = $this->ensureVpc($client, $serviceId, $templateId, $region);
        }

        if ($subnetId === '') {
            $subnetId = $this->ensureSubnet($client, $serviceId, $templateId, $region, $zone, $vpcId, $autoVpc);
        }

        if ($securityGroupId === '') {
            $securityGroupId = $this->ensureSecurityGroup($client, $serviceId, $templateId, $region);
        }

        return [
            'vpc_id' => $vpcId,
            'subnet_id' => $subnetId,
            'security_group_id' => $securityGroupId,
            'auto_network' => $autoNetwork,
        ];
    }

    private function ensureVpc(TencentClient $client, int $serviceId, int $templateId, string $region): string
    {
        $key = $this->key('auto_vpc', [$region]);
        $cached = trim($this->configStore->get($key));
        if ($cached !== '') {
            return $cached;
        }

        $name = $this->name('vpc', [$region]);
        $response = $client->describeVpcs($region, [
            ['Name' => 'vpc-name', 'Values' => [$name]],
        ]);
        $vpcId = $this->firstId($response->data()['VpcSet'] ?? [], ['VpcId']);
        if ($vpcId !== '') {
            $this->configStore->set($key, $vpcId);
            Operations::record($serviceId, $templateId, 'DescribeVpcs', 'success', 'Reused auto VPC ' . $vpcId . '.', $response->requestId());
            return $vpcId;
        }

        $response = $client->createVpc($region, $name, self::VPC_CIDR);
        $vpcId = $this->firstCreatedVpcId($response);
        if ($vpcId === '') {
            throw new RuntimeException('CreateVpc returned no VpcId.');
        }

        $this->configStore->set($key, $vpcId);
        Operations::record($serviceId, $templateId, 'CreateVpc', 'success', 'Created auto VPC ' . $vpcId . '.', $response->requestId());

        return $vpcId;
    }

    private function ensureSubnet(TencentClient $client, int $serviceId, int $templateId, string $region, string $zone, string $vpcId, bool $useCache): string
    {
        $key = $this->key('auto_subnet', [$region, $zone]);
        if ($useCache) {
            $cached = trim($this->configStore->get($key));
            if ($cached !== '') {
                return $cached;
            }
        }

        $name = $this->name('subnet', [$region, $zone]);
        $response = $client->describeSubnetsByFilters($region, [
            ['Name' => 'subnet-name', 'Values' => [$name]],
            ['Name' => 'vpc-id', 'Values' => [$vpcId]],
        ]);
        $subnetId = $this->firstId($response->data()['SubnetSet'] ?? [], ['SubnetId']);
        if ($subnetId !== '') {
            if ($useCache) {
                $this->configStore->set($key, $subnetId);
            }
            Operations::record($serviceId, $templateId, 'DescribeSubnets', 'success', 'Reused auto subnet ' . $subnetId . '.', $response->requestId());
            return $subnetId;
        }

        $response = $client->createSubnet($region, $vpcId, $name, self::SUBNET_CIDR, $zone);
        $subnetId = $this->firstCreatedSubnetId($response);
        if ($subnetId === '') {
            throw new RuntimeException('CreateSubnet returned no SubnetId.');
        }

        if ($useCache) {
            $this->configStore->set($key, $subnetId);
        }
        Operations::record($serviceId, $templateId, 'CreateSubnet', 'success', 'Created auto subnet ' . $subnetId . '.', $response->requestId());

        return $subnetId;
    }

    private function ensureSecurityGroup(TencentClient $client, int $serviceId, int $templateId, string $region): string
    {
        $key = $this->key('auto_sg', [$region]);
        $cached = trim($this->configStore->get($key));
        if ($cached !== '') {
            return $cached;
        }

        $name = $this->name('sg', [$region]);
        $response = $client->describeSecurityGroupsByFilters($region, [
            ['Name' => 'security-group-name', 'Values' => [$name]],
        ]);
        $securityGroupId = $this->firstId($response->data()['SecurityGroupSet'] ?? [], ['SecurityGroupId']);
        if ($securityGroupId !== '') {
            $this->configStore->set($key, $securityGroupId);
            Operations::record($serviceId, $templateId, 'DescribeSecurityGroups', 'success', 'Reused auto security group ' . $securityGroupId . '.', $response->requestId());
            return $securityGroupId;
        }

        $response = $client->createSecurityGroup($region, $name, 'OWP WHMCS shared auto-created security group');
        $securityGroupId = $this->firstCreatedSecurityGroupId($response);
        if ($securityGroupId === '') {
            throw new RuntimeException('CreateSecurityGroup returned no SecurityGroupId.');
        }

        $this->configStore->set($key, $securityGroupId);
        Operations::record($serviceId, $templateId, 'CreateSecurityGroup', 'success', 'Created auto security group ' . $securityGroupId . '.', $response->requestId());

        $policyResponse = $client->createSecurityGroupPolicies($region, $securityGroupId, [
            'Ingress' => [$this->allowAllPolicy()],
            'Egress' => [$this->allowAllPolicy()],
        ]);
        Operations::record($serviceId, $templateId, 'CreateSecurityGroupPolicies', 'success', 'Applied allow-all ingress and egress policies to auto security group.', $policyResponse->requestId());

        return $securityGroupId;
    }

    /**
     * @return array<string, string>
     */
    private function allowAllPolicy(): array
    {
        return [
            'Protocol' => 'ALL',
            'Port' => 'ALL',
            'CidrBlock' => '0.0.0.0/0',
            'Action' => 'ACCEPT',
        ];
    }

    private function vpcClient(): TencentClient
    {
        return new TencentClient(array_merge($this->configStore->apiSettings(), [
            'endpoint' => 'vpc.tencentcloudapi.com',
            'service' => 'vpc',
            'version' => '2017-03-12',
        ]));
    }

    /**
     * @param list<string> $parts
     */
    private function key(string $prefix, array $parts): string
    {
        return $prefix . '_' . implode('_', array_map(static fn (string $part): string => trim($part) !== '' ? trim($part) : 'default', $parts));
    }

    /**
     * @param list<string> $parts
     */
    private function name(string $type, array $parts): string
    {
        return self::NAME_PREFIX . '-' . $type . '-' . implode('-', array_map([$this, 'slug'], $parts));
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';

        return trim($value, '-') ?: 'default';
    }

    /**
     * @param mixed $items
     * @param list<string> $keys
     */
    private function firstId($items, array $keys): string
    {
        if (!is_array($items)) {
            return '';
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach ($keys as $key) {
                $value = trim((string) ($item[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function firstCreatedVpcId(TencentResponse $response): string
    {
        $data = $response->data();
        $vpc = is_array($data['Vpc'] ?? null) ? $data['Vpc'] : [];

        return trim((string) ($data['VpcId'] ?? $vpc['VpcId'] ?? ''));
    }

    private function firstCreatedSubnetId(TencentResponse $response): string
    {
        $data = $response->data();
        $subnet = is_array($data['Subnet'] ?? null) ? $data['Subnet'] : [];

        return trim((string) ($data['SubnetId'] ?? $subnet['SubnetId'] ?? ''));
    }

    private function firstCreatedSecurityGroupId(TencentResponse $response): string
    {
        $data = $response->data();
        $group = is_array($data['SecurityGroup'] ?? null) ? $data['SecurityGroup'] : [];

        return trim((string) ($data['SecurityGroupId'] ?? $group['SecurityGroupId'] ?? ''));
    }
}
