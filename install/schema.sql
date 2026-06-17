CREATE TABLE IF NOT EXISTS `mod_owp_tencentcvm_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `value_type` VARCHAR(40) NOT NULL DEFAULT 'string',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mod_owp_tencentcvm_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `region` VARCHAR(40) NOT NULL,
  `zone` VARCHAR(80) NOT NULL,
  `instance_type` VARCHAR(100) NOT NULL,
  `image_id` VARCHAR(100) NOT NULL,
  `vpc_id` VARCHAR(100) NULL,
  `subnet_id` VARCHAR(100) NULL,
  `security_group_id` VARCHAR(100) NULL,
  `bandwidth_mbps` INT NOT NULL DEFAULT 1,
  `charge_type` VARCHAR(60) NOT NULL DEFAULT 'POSTPAID_BY_HOUR',
  `public_ip_mode` VARCHAR(40) NOT NULL DEFAULT 'direct',
  `anycast_zone` VARCHAR(80) NOT NULL DEFAULT 'ANYCAST_ZONE_OVERSEAS',
  `eip_internet_charge_type` VARCHAR(80) NOT NULL DEFAULT 'TRAFFIC_POSTPAID_BY_HOUR',
  `system_disk_type` VARCHAR(60) NOT NULL DEFAULT 'CLOUD_BSSD',
  `system_disk_size_gb` INT NOT NULL DEFAULT 50,
  `validation_status` VARCHAR(40) NOT NULL DEFAULT 'not_checked',
  `validation_message` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_template_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mod_owp_tencentcvm_instances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT NOT NULL,
  `template_id` INT UNSIGNED NULL,
  `instance_id` VARCHAR(100) NULL,
  `eip_address_id` VARCHAR(100) NULL,
  `region` VARCHAR(40) NULL,
  `zone` VARCHAR(80) NULL,
  `public_ip` VARCHAR(100) NULL,
  `private_ip` VARCHAR(100) NULL,
  `state` VARCHAR(60) NOT NULL DEFAULT 'pending',
  `template_snapshot` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_service_id` (`service_id`),
  KEY `idx_instance_id` (`instance_id`),
  KEY `idx_eip_address_id` (`eip_address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mod_owp_tencentcvm_operations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT NULL,
  `template_id` INT UNSIGNED NULL,
  `operation` VARCHAR(80) NOT NULL,
  `actor_type` VARCHAR(40) NOT NULL DEFAULT 'system',
  `actor` VARCHAR(120) NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `request_id` VARCHAR(120) NULL,
  `message` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
