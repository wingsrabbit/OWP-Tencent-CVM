<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;

final class ElasticIpManager
{
    private const ADDRESS_READY_STATUS = 'UNBIND';
    private const ADDRESS_WAIT_ATTEMPTS = 15;
    private const ADDRESS_WAIT_SECONDS = 3;

    public function __construct(private readonly ConfigStore $configStore)
    {
    }

    /**
     * @param array<string, mixed> $template
     * @return array{address_id:string, public_ip:string}
     */
    public function ensureAssociated(int $serviceId, int $templateId, array $template, string $instanceId, string $existingAddressId = ''): array
    {
        $mode = Templates::publicIpMode((string) ($template['public_ip_mode'] ?? Templates::PUBLIC_IP_DIRECT));
        if ($mode === Templates::PUBLIC_IP_DIRECT) {
            return ['address_id' => '', 'public_ip' => ''];
        }

        $region = (string) $template['region'];
        $client = $this->vpcClient();
        $addressId = trim($existingAddressId);

        if ($addressId === '') {
            $response = $client->allocateAddresses($region, $this->allocationPayload($template, $mode));
            $addressId = $this->firstAddressId($response);
            if ($addressId === '') {
                throw new RuntimeException('AllocateAddresses returned no AddressId.');
            }

            Operations::record($serviceId, $templateId, 'AllocateAddresses', 'success', 'Allocated ' . $mode . ' address ' . $addressId . '.', $response->requestId());
            Instances::upsertForService($serviceId, [
                'template_id' => $templateId,
                'eip_address_id' => $addressId,
            ]);
        }

        $this->waitAddressAvailable($client, $region, $addressId, $serviceId, $templateId);

        $response = $client->associateAddress($region, $addressId, $instanceId);
        Operations::record($serviceId, $templateId, 'AssociateAddress', 'success', 'Associated EIP ' . $addressId . ' to instance ' . $instanceId . '.', $response->requestId());

        $publicIp = $this->publicIp($client, $region, $addressId, $serviceId, $templateId);

        return ['address_id' => $addressId, 'public_ip' => $publicIp];
    }

    public function publicIpForAddress(string $region, string $addressId, int $serviceId, ?int $templateId): string
    {
        if (trim($addressId) === '') {
            return '';
        }

        return $this->publicIp($this->vpcClient(), $region, $addressId, $serviceId, $templateId);
    }

    public function releaseAddress(int $serviceId, ?int $templateId, string $region, string $addressId): void
    {
        $addressId = trim($addressId);
        if ($addressId === '') {
            return;
        }

        $client = $this->vpcClient();
        $response = $client->disassociateAddress($region, $addressId);
        Operations::record($serviceId, $templateId, 'DisassociateAddress', 'success', 'Disassociated EIP ' . $addressId . ' before instance termination.', $response->requestId());

        $response = $client->releaseAddresses($region, [$addressId]);
        Operations::record($serviceId, $templateId, 'ReleaseAddresses', 'success', 'Released EIP ' . $addressId . ' before instance termination.', $response->requestId());

        Instances::upsertForService($serviceId, [
            'eip_address_id' => null,
            'public_ip' => '',
        ]);
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    private function allocationPayload(array $template, string $mode): array
    {
        $payload = [
            'AddressCount' => 1,
            'InternetMaxBandwidthOut' => max(1, (int) ($template['bandwidth_mbps'] ?? 1)),
        ];

        if ($mode === Templates::PUBLIC_IP_ANYCAST_EIP) {
            $payload['AddressType'] = 'AnycastEIP';
            $payload['AnycastZone'] = Templates::anycastZone((string) ($template['anycast_zone'] ?? Templates::DEFAULT_ANYCAST_ZONE));
            return $payload;
        }

        $payload['InternetChargeType'] = Templates::eipInternetChargeType((string) ($template['eip_internet_charge_type'] ?? Templates::DEFAULT_EIP_CHARGE_TYPE));

        return $payload;
    }

    private function waitAddressAvailable(TencentClient $client, string $region, string $addressId, int $serviceId, int $templateId): void
    {
        $lastStatus = '';

        for ($attempt = 1; $attempt <= self::ADDRESS_WAIT_ATTEMPTS; $attempt++) {
            $response = $client->describeAddresses($region, [$addressId]);
            $address = $this->firstAddress($response);
            $lastStatus = $this->addressStatus($address);

            if ($lastStatus === self::ADDRESS_READY_STATUS) {
                Operations::record(
                    $serviceId,
                    $templateId,
                    'DescribeAddresses',
                    'success',
                    'EIP ' . $addressId . ' is UNBIND and ready for association.',
                    $response->requestId()
                );
                return;
            }

            if ($attempt < self::ADDRESS_WAIT_ATTEMPTS) {
                sleep(self::ADDRESS_WAIT_SECONDS);
            }
        }

        throw new RuntimeException(
            'EIP ' . $addressId . ' did not become UNBIND before AssociateAddress. Last status: ' . ($lastStatus !== '' ? $lastStatus : 'unknown') . '.'
        );
    }

    private function publicIp(TencentClient $client, string $region, string $addressId, int $serviceId, ?int $templateId): string
    {
        $response = $client->describeAddresses($region, [$addressId]);
        Operations::record($serviceId, $templateId, 'DescribeAddresses', 'success', 'Synchronized EIP address metadata.', $response->requestId());

        $address = $this->firstAddress($response);
        if ($address === []) {
            return '';
        }

        return trim((string) ($address['AddressIp'] ?? $address['PublicIpAddress'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function firstAddress(TencentResponse $response): array
    {
        $addresses = $response->data()['AddressSet'] ?? [];
        if (!is_array($addresses) || !is_array($addresses[0] ?? null)) {
            return [];
        }

        return $addresses[0];
    }

    /**
     * @param array<string, mixed> $address
     */
    private function addressStatus(array $address): string
    {
        return strtoupper(trim((string) ($address['AddressStatus'] ?? $address['Status'] ?? '')));
    }

    private function firstAddressId(TencentResponse $response): string
    {
        $data = $response->data();
        $ids = $data['AddressIdSet'] ?? [];
        if (is_array($ids) && count($ids) > 0) {
            return trim((string) $ids[0]);
        }

        $addresses = $data['AddressSet'] ?? [];
        if (is_array($addresses) && count($addresses) > 0) {
            $first = $addresses[0];
            if (is_string($first) && trim($first) !== '') {
                return trim($first);
            }

            if (is_array($first)) {
                return trim((string) ($first['AddressId'] ?? ''));
            }
        }

        return trim((string) ($data['AddressId'] ?? ''));
    }

    private function vpcClient(): TencentClient
    {
        return new TencentClient(array_merge($this->configStore->apiSettings(), [
            'endpoint' => 'vpc.tencentcloudapi.com',
            'service' => 'vpc',
            'version' => '2017-03-12',
        ]));
    }
}
