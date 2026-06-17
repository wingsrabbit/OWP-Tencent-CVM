<?php

declare(strict_types=1);

if (!defined('OWP_TENCENTCVM_VERSION')) {
    define('OWP_TENCENTCVM_VERSION', '0.8.1');
}

require_once __DIR__ . '/Loader.php';

\OwpTencentCvm\Loader::register(__DIR__);
