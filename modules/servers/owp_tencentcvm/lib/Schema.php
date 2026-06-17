<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;
use WHMCS\Database\Capsule;

final class Schema
{
    public const CONFIG_TABLE = 'mod_owp_tencentcvm_config';
    public const TEMPLATES_TABLE = 'mod_owp_tencentcvm_templates';
    public const INSTANCES_TABLE = 'mod_owp_tencentcvm_instances';
    public const OPERATIONS_TABLE = 'mod_owp_tencentcvm_operations';

    /**
     * @return array<string, list<string>>
     */
    public static function install(): array
    {
        self::assertCapsuleAvailable();

        $created = [];

        if (!Capsule::schema()->hasTable(self::CONFIG_TABLE)) {
            Capsule::schema()->create(self::CONFIG_TABLE, static function ($table): void {
                $table->increments('id');
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->string('value_type', 40)->default('string');
                $table->timestamps();
            });
            $created[] = self::CONFIG_TABLE;
        }

        if (!Capsule::schema()->hasTable(self::TEMPLATES_TABLE)) {
            Capsule::schema()->create(self::TEMPLATES_TABLE, static function ($table): void {
                $table->increments('id');
                $table->string('name', 100)->unique();
                $table->boolean('enabled')->default(false);
                $table->string('region', 40);
                $table->string('zone', 80);
                $table->string('instance_type', 100);
                $table->string('image_id', 100);
                $table->string('vpc_id', 100)->nullable();
                $table->string('subnet_id', 100)->nullable();
                $table->string('security_group_id', 100)->nullable();
                $table->integer('bandwidth_mbps')->default(1);
                $table->string('charge_type', 60)->default('POSTPAID_BY_HOUR');
                $table->string('public_ip_mode', 40)->default('direct');
                $table->string('anycast_zone', 80)->default('ANYCAST_ZONE_OVERSEAS');
                $table->string('eip_internet_charge_type', 80)->default('TRAFFIC_POSTPAID_BY_HOUR');
                $table->string('system_disk_type', 60)->default('CLOUD_BSSD');
                $table->integer('system_disk_size_gb')->default(50);
                $table->string('validation_status', 40)->default('not_checked');
                $table->text('validation_message')->nullable();
                $table->timestamps();
            });
            $created[] = self::TEMPLATES_TABLE;
        }

        if (!Capsule::schema()->hasTable(self::INSTANCES_TABLE)) {
            Capsule::schema()->create(self::INSTANCES_TABLE, static function ($table): void {
                $table->increments('id');
                $table->integer('service_id')->unique();
                $table->integer('template_id')->nullable();
                $table->string('instance_id', 100)->nullable()->index();
                $table->string('eip_address_id', 100)->nullable()->index();
                $table->string('region', 40)->nullable();
                $table->string('zone', 80)->nullable();
                $table->string('public_ip', 100)->nullable();
                $table->string('private_ip', 100)->nullable();
                $table->string('state', 60)->default('pending');
                $table->text('template_snapshot')->nullable();
                $table->timestamps();
            });
            $created[] = self::INSTANCES_TABLE;
        }

        if (!Capsule::schema()->hasTable(self::OPERATIONS_TABLE)) {
            Capsule::schema()->create(self::OPERATIONS_TABLE, static function ($table): void {
                $table->increments('id');
                $table->integer('service_id')->nullable()->index();
                $table->integer('template_id')->nullable()->index();
                $table->string('operation', 80);
                $table->string('actor_type', 40)->default('system');
                $table->string('actor', 120)->nullable();
                $table->string('status', 40)->default('pending');
                $table->string('request_id', 120)->nullable();
                $table->text('message')->nullable();
                $table->timestamps();
            });
            $created[] = self::OPERATIONS_TABLE;
        }

        return ['created' => $created, 'upgraded' => self::upgradeExistingTables()];
    }

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            self::CONFIG_TABLE,
            self::TEMPLATES_TABLE,
            self::INSTANCES_TABLE,
            self::OPERATIONS_TABLE,
        ];
    }

    private static function assertCapsuleAvailable(): void
    {
        if (!class_exists(Capsule::class)) {
            throw new RuntimeException('WHMCS Capsule database layer is not available.');
        }
    }

    /**
     * @return list<string>
     */
    private static function upgradeExistingTables(): array
    {
        $upgraded = [];
        $schema = Capsule::schema();

        if ($schema->hasTable(self::TEMPLATES_TABLE)) {
            foreach (['vpc_id', 'subnet_id', 'security_group_id'] as $column) {
                if ($schema->hasColumn(self::TEMPLATES_TABLE, $column)) {
                    Capsule::statement('ALTER TABLE `' . self::TEMPLATES_TABLE . '` MODIFY `' . $column . '` VARCHAR(100) NULL');
                }
            }

            if (!$schema->hasColumn(self::TEMPLATES_TABLE, 'public_ip_mode')) {
                $schema->table(self::TEMPLATES_TABLE, static function ($table): void {
                    $table->string('public_ip_mode', 40)->default('direct')->after('charge_type');
                });
                $upgraded[] = self::TEMPLATES_TABLE . '.public_ip_mode';
            }

            if (!$schema->hasColumn(self::TEMPLATES_TABLE, 'anycast_zone')) {
                $schema->table(self::TEMPLATES_TABLE, static function ($table): void {
                    $table->string('anycast_zone', 80)->default('ANYCAST_ZONE_OVERSEAS')->after('public_ip_mode');
                });
                $upgraded[] = self::TEMPLATES_TABLE . '.anycast_zone';
            }

            if (!$schema->hasColumn(self::TEMPLATES_TABLE, 'eip_internet_charge_type')) {
                $schema->table(self::TEMPLATES_TABLE, static function ($table): void {
                    $table->string('eip_internet_charge_type', 80)->default('TRAFFIC_POSTPAID_BY_HOUR')->after('anycast_zone');
                });
                $upgraded[] = self::TEMPLATES_TABLE . '.eip_internet_charge_type';
            }
        }

        if ($schema->hasTable(self::INSTANCES_TABLE) && !$schema->hasColumn(self::INSTANCES_TABLE, 'eip_address_id')) {
            $schema->table(self::INSTANCES_TABLE, static function ($table): void {
                $table->string('eip_address_id', 100)->nullable()->index()->after('instance_id');
            });
            $upgraded[] = self::INSTANCES_TABLE . '.eip_address_id';
        }

        return $upgraded;
    }
}
