<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;
use WHMCS\Database\Capsule;

final class ConfigStore
{
    private const DEFAULT_ENDPOINT = 'cvm.tencentcloudapi.com';
    private const DEFAULT_AUTO_RESOURCE_PREFIX = 'owp-whmcs';
    private const MAX_AUTO_RESOURCE_PREFIX_LENGTH = 32;

    public function get(string $key, string $default = ''): string
    {
        $row = Capsule::table(Schema::CONFIG_TABLE)
            ->where('setting_key', $key)
            ->first();

        if (!$row || !isset($row->setting_value)) {
            return $default;
        }

        return (string) $row->setting_value;
    }

    public function set(string $key, string $value, string $type = 'string'): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = Capsule::table(Schema::CONFIG_TABLE)
            ->where('setting_key', $key)
            ->first();

        $data = [
            'setting_key' => $key,
            'setting_value' => $value,
            'value_type' => $type,
            'updated_at' => $now,
        ];

        if ($existing) {
            Capsule::table(Schema::CONFIG_TABLE)
                ->where('setting_key', $key)
                ->update($data);
            return;
        }

        $data['created_at'] = $now;
        Capsule::table(Schema::CONFIG_TABLE)->insert($data);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = strtolower($this->get($key, $default ? '1' : '0'));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public function setBool(string $key, bool $value): void
    {
        $this->set($key, $value ? '1' : '0', 'bool');
    }

    public function setSecret(string $key, string $value): void
    {
        if ($value === '') {
            return;
        }

        if (!function_exists('encrypt')) {
            throw new RuntimeException('WHMCS encrypt() helper is required before storing secrets.');
        }

        $this->set($key, 'enc:' . \encrypt($value), 'secret');
    }

    public function getSecret(string $key, string $default = ''): string
    {
        $value = $this->get($key, '');
        if ($value === '') {
            return $default;
        }

        if (str_starts_with($value, 'enc:')) {
            if (!function_exists('decrypt')) {
                throw new RuntimeException('WHMCS decrypt() helper is required before reading secrets.');
            }

            return (string) \decrypt(substr($value, 4));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function apiSettings(): array
    {
        return [
            'secret_id' => $this->getSecret('secret_id'),
            'secret_key' => $this->getSecret('secret_key'),
            'endpoint' => self::normalizeEndpoint($this->get('endpoint', self::DEFAULT_ENDPOINT)),
            'timeout_seconds' => (int) $this->get('timeout_seconds', '20'),
        ];
    }

    public function autoResourcePrefix(): string
    {
        return self::normalizeAutoResourcePrefix($this->get('auto_resource_prefix', self::DEFAULT_AUTO_RESOURCE_PREFIX));
    }

    public static function normalizeAutoResourcePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        $prefix = preg_replace('/[^A-Za-z0-9._-]+/', '-', $prefix) ?? '';
        $prefix = trim($prefix, '-_.');

        if ($prefix === '') {
            return self::DEFAULT_AUTO_RESOURCE_PREFIX;
        }

        return substr($prefix, 0, self::MAX_AUTO_RESOURCE_PREFIX_LENGTH);
    }

    public static function normalizeEndpoint(string $endpoint, string $default = self::DEFAULT_ENDPOINT): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return $default;
        }

        if (preg_match('#^https?://#i', $endpoint) === 1) {
            $host = parse_url($endpoint, PHP_URL_HOST);
            $endpoint = is_string($host) ? $host : '';
        } else {
            $endpoint = preg_split('/[\\/:?#]/', $endpoint, 2)[0] ?? '';
        }

        $endpoint = strtolower(rtrim(trim($endpoint), '.'));

        if (preg_match('/^(?:[a-z0-9-]+\\.)*tencentcloudapi\\.com$/', $endpoint) !== 1) {
            return $default;
        }

        return $endpoint;
    }

    public function maskedSecretId(): string
    {
        $secretId = $this->getSecret('secret_id');
        if ($secretId === '') {
            return 'not configured';
        }

        return (string) Redactor::redact($secretId);
    }
}
