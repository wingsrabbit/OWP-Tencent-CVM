<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;

final class TemplateValidator
{
    public function __construct(private readonly ConfigStore $configStore)
    {
    }

    /**
     * @return array{status:string, message:string}
     */
    public function validate(int $templateId): array
    {
        $template = Templates::find($templateId);
        if ($template === null) {
            throw new RuntimeException('Template not found.');
        }

        $messages = [];
        $status = 'valid';
        $cvm = new TencentClient($this->configStore->apiSettings());
        $vpc = new TencentClient(array_merge($this->configStore->apiSettings(), [
            'endpoint' => 'vpc.tencentcloudapi.com',
            'service' => 'vpc',
            'version' => '2017-03-12',
        ]));

        $this->checkZone($cvm, $template, $messages, $status);
        $this->checkImage($cvm, $template, $messages, $status);
        $this->checkInstanceType($cvm, $template, $messages, $status);
        $this->checkSubnet($vpc, $template, $messages, $status);
        $this->checkSecurityGroup($vpc, $template, $messages, $status);
        $this->checkBandwidth($template, $messages, $status);
        $this->checkPublicIpSettings($template, $messages, $status);

        $message = implode(' ', $messages);
        Templates::markValidation($templateId, $status, $message);

        return ['status' => $status, 'message' => $message];
    }

    /**
     * @param array<string, mixed> $template
     * @param list<string> $messages
     */
    private function checkZone(TencentClient $client, array $template, array &$messages, string &$status): void
    {
        $response = $client->describeZones((string) $template['region']);
        $zones = $response->data()['ZoneSet'] ?? [];
        $found = $this->listContainsValue($zones, 'Zone', (string) $template['zone']);

        if ($found) {
            $messages[] = 'Zone OK.';
            return;
        }

        $status = 'invalid';
        $messages[] = 'Zone not found in region.';
    }

    /**
     * @param array<string, mixed> $template
     * @param list<string> $messages
     */
    private function checkImage(TencentClient $client, array $template, array &$messages, string &$status): void
    {
        $response = $client->describeImages((string) $template['region'], [(string) $template['image_id']]);
        $images = $response->data()['ImageSet'] ?? [];

        if (!is_array($images) || count($images) === 0) {
            $status = 'invalid';
            $messages[] = 'Image not found.';
            return;
        }

        $image = is_array($images[0] ?? null) ? $images[0] : [];
        $imageState = (string) ($image['ImageState'] ?? '');
        if ($imageState !== '' && $imageState !== 'NORMAL') {
            $status = $status === 'invalid' ? $status : 'warning';
            $messages[] = 'Image state is ' . $imageState . '.';
            return;
        }

        $messages[] = 'Image OK.';
    }

    /**
     * @param array<string, mixed> $template
     * @param list<string> $messages
     */
    private function checkInstanceType(TencentClient $client, array $template, array &$messages, string &$status): void
    {
        $response = $client->describeZoneInstanceConfigInfos((string) $template['region']);
        $configs = $response->data()['InstanceTypeQuotaSet'] ?? [];
        $matched = null;

        if (is_array($configs)) {
            foreach ($configs as $config) {
                if (!is_array($config)) {
                    continue;
                }

                if (($config['Zone'] ?? '') === $template['zone'] && ($config['InstanceType'] ?? '') === $template['instance_type']) {
                    $matched = $config;
                    break;
                }
            }
        }

        if ($matched === null) {
            $status = $status === 'invalid' ? $status : 'warning';
            $messages[] = 'Instance type was not found in zone quota list.';
            return;
        }

        $saleStatus = (string) ($matched['Status'] ?? '');
        if ($saleStatus !== '' && $saleStatus !== 'SELL') {
            $status = $status === 'invalid' ? $status : 'warning';
            $messages[] = 'Instance type status is ' . $saleStatus . '.';
            return;
        }

        $messages[] = 'Instance type OK.';
    }

    /**
     * @param array<string, mixed> $template
     * @param list<string> $messages
     */
    private function checkSubnet(TencentClient $client, array $template, array &$messages, string &$status): void
    {
        $vpcId = trim((string) ($template['vpc_id'] ?? ''));
        $subnetId = trim((string) ($template['subnet_id'] ?? ''));

        if ($subnetId === '') {
            $messages[] = $vpcId === ''
                ? 'VPC and subnet will be auto-created or reused at provisioning; subnet validation skipped.'
                : 'Subnet will be auto-created or reused at provisioning; subnet validation skipped.';
            return;
        }

        if ($vpcId === '') {
            $status = 'invalid';
            $messages[] = 'VPC ID is required when a fixed subnet ID is provided.';
            return;
        }

        $response = $client->describeSubnets((string) $template['region'], [$subnetId]);
        $subnets = $response->data()['SubnetSet'] ?? [];

        if (!is_array($subnets) || count($subnets) === 0) {
            $status = 'invalid';
            $messages[] = 'Subnet not found.';
            return;
        }

        $subnet = is_array($subnets[0] ?? null) ? $subnets[0] : [];
        if (($subnet['VpcId'] ?? $vpcId) !== $vpcId) {
            $status = 'invalid';
            $messages[] = 'Subnet VPC mismatch.';
            return;
        }

        if (($subnet['Zone'] ?? $template['zone']) !== $template['zone']) {
            $status = 'invalid';
            $messages[] = 'Subnet zone mismatch.';
            return;
        }

        $messages[] = 'Subnet OK.';
    }

    /**
     * @param array<string, mixed> $template
     * @param list<string> $messages
     */
    private function checkSecurityGroup(TencentClient $client, array $template, array &$messages, string &$status): void
    {
        $securityGroupId = trim((string) ($template['security_group_id'] ?? ''));
        if ($securityGroupId === '') {
            $messages[] = 'Security group will be auto-created or reused at provisioning; security group validation skipped.';
            return;
        }

        $response = $client->describeSecurityGroups((string) $template['region'], [$securityGroupId]);
        $groups = $response->data()['SecurityGroupSet'] ?? [];

        if (is_array($groups) && count($groups) > 0) {
            $messages[] = 'Security group OK.';
            return;
        }

        $status = 'invalid';
        $messages[] = 'Security group not found.';
    }

    /**
     * @param array<string, mixed> $template
     * @param list<string> $messages
     */
    private function checkBandwidth(array $template, array &$messages, string &$status): void
    {
        $bandwidth = (int) ($template['bandwidth_mbps'] ?? 0);

        if ($bandwidth >= 1 && $bandwidth <= 1000) {
            $messages[] = 'Bandwidth policy OK.';
            return;
        }

        $status = $status === 'invalid' ? $status : 'warning';
        $messages[] = 'Bandwidth is outside the conservative 1-1000 Mbps policy range.';
    }

    /**
     * @param array<string, mixed> $template
     * @param list<string> $messages
     */
    private function checkPublicIpSettings(array $template, array &$messages, string &$status): void
    {
        $mode = Templates::publicIpMode((string) ($template['public_ip_mode'] ?? Templates::PUBLIC_IP_DIRECT));
        if ($mode !== (string) ($template['public_ip_mode'] ?? Templates::PUBLIC_IP_DIRECT)) {
            $status = 'invalid';
            $messages[] = 'Public IP mode is invalid.';
            return;
        }

        $chargeType = Templates::eipInternetChargeType((string) ($template['eip_internet_charge_type'] ?? Templates::DEFAULT_EIP_CHARGE_TYPE));
        if ($chargeType !== (string) ($template['eip_internet_charge_type'] ?? Templates::DEFAULT_EIP_CHARGE_TYPE)) {
            $status = 'invalid';
            $messages[] = 'EIP internet charge type is invalid.';
            return;
        }

        if ($mode === Templates::PUBLIC_IP_ANYCAST_EIP && trim((string) ($template['anycast_zone'] ?? '')) === '') {
            $status = 'invalid';
            $messages[] = 'Anycast zone is required for Anycast Elastic IP mode.';
            return;
        }

        $messages[] = $mode === Templates::PUBLIC_IP_DIRECT
            ? 'Direct public IP mode OK.'
            : 'EIP mode OK; allocation is deferred until provisioning.';
    }

    /**
     * @param mixed $items
     */
    private function listContainsValue($items, string $key, string $value): bool
    {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) && (string) ($item[$key] ?? '') === $value) {
                return true;
            }
        }

        return false;
    }
}
