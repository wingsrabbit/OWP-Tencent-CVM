<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class Loader
{
    private static bool $registered = false;

    public static function register(string $baseDir): void
    {
        if (self::$registered) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($baseDir): void {
            $prefix = __NAMESPACE__ . '\\';

            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $path = rtrim($baseDir, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
                . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        });

        self::$registered = true;
    }
}
