<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class CsrfGuard
{
    public const FIELD_NAME = 'owp_tencentcvm_csrf_token';

    private const SESSION_KEY = 'owp_tencentcvm_csrf_token';

    public static function token(): string
    {
        self::ensureSession();

        $token = $_SESSION[self::SESSION_KEY] ?? '';
        if (!is_string($token) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            $token = bin2hex(random_bytes(16));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public static function input(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validatePost(): bool
    {
        self::ensureSession();

        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        $submitted = $_POST[self::FIELD_NAME] ?? '';

        return is_string($expected)
            && is_string($submitted)
            && $expected !== ''
            && $submitted !== ''
            && hash_equals($expected, $submitted);
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!headers_sent()) {
            session_start();
        }
    }
}
