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
        $response = $client->describeSubnets((string) $template['region'], [(string) $template['subnet_id']]);
        $subnets = $response->data()['SubnetSet'] ?? [];

        if (!is_array($subnets) || count($subnets) === 0) {
            $status = 'invalid';
            $messages[] = 'Subnet not found.';
            return;
        }

        $subnet = is_array($subnets[0] ?? null) ? $subnets[0] : [];
        if (($subnet['VpcId'] ?? $template['vpc_id']) !== $template['vpc_id']) {
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
        $response = $client->describeSecurityGroups((string) $template['region'], [(string) $template['security_group_id']]);
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
