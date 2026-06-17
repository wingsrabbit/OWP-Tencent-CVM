<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use InvalidArgumentException;
use WHMCS\Database\Capsule;

final class Instances
{
    /**
     * @param array<string, mixed> $params
     */
    public static function serviceId(array $params): int
    {
        return isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    }

    /**
     * @return null|array<string, mixed>
     */
    public static function findByServiceId(int $serviceId): ?array
    {
        self::assertServiceId($serviceId);

        $row = Capsule::table(Schema::INSTANCES_TABLE)
            ->where('service_id', $serviceId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsertForService(int $serviceId, array $data): void
    {
        self::assertServiceId($serviceId);

        $now = date('Y-m-d H:i:s');
        $record = array_merge($data, [
            'service_id' => $serviceId,
            'updated_at' => $now,
        ]);

        $existing = Capsule::table(Schema::INSTANCES_TABLE)
            ->where('service_id', $serviceId)
            ->first();

        if ($existing) {
            Capsule::table(Schema::INSTANCES_TABLE)
                ->where('service_id', $serviceId)
                ->update($record);
            return;
        }

        $record['created_at'] = $now;
        Capsule::table(Schema::INSTANCES_TABLE)->insert($record);
    }

    /**
     * @param array<string, mixed> $instance
     */
    public static function updateFromTencentInstance(int $serviceId, array $instance, string $region = ''): void
    {
        $publicIps = $instance['PublicIpAddresses'] ?? [];
        $privateIps = $instance['PrivateIpAddresses'] ?? [];

        self::upsertForService($serviceId, [
            'instance_id' => (string) ($instance['InstanceId'] ?? ''),
            'region' => (string) ($instance['Placement']['Region'] ?? $region),
            'zone' => (string) ($instance['Placement']['Zone'] ?? ''),
            'public_ip' => is_array($publicIps) ? implode(',', $publicIps) : '',
            'private_ip' => is_array($privateIps) ? implode(',', $privateIps) : '',
            'state' => strtolower((string) ($instance['InstanceState'] ?? 'unknown')),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function placeholderFromParams(array $params): array
    {
        return [
            'service_id' => self::serviceId($params),
            'template_name' => Templates::selectedNameFromParams($params),
            'instance_id' => '',
            'state' => 'not_created',
        ];
    }

    private static function assertServiceId(int $serviceId): void
    {
        if ($serviceId <= 0) {
            throw new InvalidArgumentException('WHMCS service ID is required.');
        }
    }
}
