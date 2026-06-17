<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use InvalidArgumentException;
use WHMCS\Database\Capsule;

final class Templates
{
    public const PUBLIC_IP_DIRECT = 'direct';
    public const PUBLIC_IP_EIP = 'eip';
    public const PUBLIC_IP_ANYCAST_EIP = 'anycast_eip';
    public const DEFAULT_ANYCAST_ZONE = 'ANYCAST_ZONE_OVERSEAS';
    public const DEFAULT_EIP_CHARGE_TYPE = 'TRAFFIC_POSTPAID_BY_HOUR';

    /**
     * @param array<string, mixed> $params
     */
    public static function selectedNameFromParams(array $params): string
    {
        $name = isset($params['configoption1']) ? trim((string) $params['configoption1']) : '';

        return $name !== '' ? $name : 'unassigned';
    }

    /**
     * @return list<array<string, string>>
     */
    public static function placeholderRows(): array
    {
        return [
            [
                'name' => 'cn-gz-basic-2c4g',
                'region' => 'ap-guangzhou',
                'zone' => 'Guangzhou Zone 3',
                'state' => 'planned',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return array_map(
            static fn ($row): array => (array) $row,
            Capsule::table(Schema::TEMPLATES_TABLE)
                ->orderBy('id', 'asc')
                ->get()
                ->all()
        );
    }

    /**
     * @return null|array<string, mixed>
     */
    public static function find(int $id): ?array
    {
        self::assertId($id);

        $row = Capsule::table(Schema::TEMPLATES_TABLE)
            ->where('id', $id)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return null|array<string, mixed>
     */
    public static function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $row = Capsule::table(Schema::TEMPLATES_TABLE)
            ->where('name', $name)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data): int
    {
        $id = max(0, (int) ($data['id'] ?? 0));
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Template name is required.');
        }

        $now = date('Y-m-d H:i:s');
        $record = [
            'name' => $name,
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'region' => trim((string) ($data['region'] ?? '')),
            'zone' => trim((string) ($data['zone'] ?? '')),
            'instance_type' => trim((string) ($data['instance_type'] ?? '')),
            'image_id' => trim((string) ($data['image_id'] ?? '')),
            'vpc_id' => trim((string) ($data['vpc_id'] ?? '')),
            'subnet_id' => trim((string) ($data['subnet_id'] ?? '')),
            'security_group_id' => trim((string) ($data['security_group_id'] ?? '')),
            'bandwidth_mbps' => max(1, (int) ($data['bandwidth_mbps'] ?? 1)),
            'charge_type' => trim((string) ($data['charge_type'] ?? 'POSTPAID_BY_HOUR')),
            'public_ip_mode' => self::publicIpMode((string) ($data['public_ip_mode'] ?? self::PUBLIC_IP_DIRECT)),
            'anycast_zone' => self::anycastZone((string) ($data['anycast_zone'] ?? self::DEFAULT_ANYCAST_ZONE)),
            'eip_internet_charge_type' => self::eipInternetChargeType((string) ($data['eip_internet_charge_type'] ?? self::DEFAULT_EIP_CHARGE_TYPE)),
            'system_disk_type' => trim((string) ($data['system_disk_type'] ?? 'CLOUD_BSSD')),
            'system_disk_size_gb' => max(20, (int) ($data['system_disk_size_gb'] ?? 50)),
            'validation_status' => 'not_checked',
            'validation_message' => '',
            'updated_at' => $now,
        ];

        self::assertRequired($record);

        if ($id > 0) {
            Capsule::table(Schema::TEMPLATES_TABLE)->where('id', $id)->update($record);
            return $id;
        }

        $record['created_at'] = $now;

        return (int) Capsule::table(Schema::TEMPLATES_TABLE)->insertGetId($record);
    }

    public static function setEnabled(int $id, bool $enabled): void
    {
        self::assertId($id);

        Capsule::table(Schema::TEMPLATES_TABLE)
            ->where('id', $id)
            ->update([
                'enabled' => $enabled ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public static function delete(int $id): void
    {
        self::assertId($id);

        Capsule::table(Schema::TEMPLATES_TABLE)->where('id', $id)->delete();
    }

    public static function markValidation(int $id, string $status, string $message): void
    {
        self::assertId($id);

        Capsule::table(Schema::TEMPLATES_TABLE)
            ->where('id', $id)
            ->update([
                'validation_status' => $status,
                'validation_message' => $message,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function assertRequired(array $record): void
    {
        foreach (['region', 'zone', 'instance_type', 'image_id'] as $field) {
            if (($record[$field] ?? '') === '') {
                throw new InvalidArgumentException($field . ' is required.');
            }
        }
    }

    public static function publicIpMode(string $value): string
    {
        $value = trim($value);

        return in_array($value, [self::PUBLIC_IP_DIRECT, self::PUBLIC_IP_EIP, self::PUBLIC_IP_ANYCAST_EIP], true)
            ? $value
            : self::PUBLIC_IP_DIRECT;
    }

    public static function anycastZone(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? $value : self::DEFAULT_ANYCAST_ZONE;
    }

    public static function eipInternetChargeType(string $value): string
    {
        $value = trim($value);

        return in_array($value, ['TRAFFIC_POSTPAID_BY_HOUR', 'BANDWIDTH_POSTPAID_BY_HOUR'], true)
            ? $value
            : self::DEFAULT_EIP_CHARGE_TYPE;
    }

    private static function assertId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Template ID is required.');
        }
    }
}
