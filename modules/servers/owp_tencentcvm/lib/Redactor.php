<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class Redactor
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public static function redact($value)
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $keyString = is_string($key) ? $key : (string) $key;
                $redacted[$key] = self::isSensitiveKey($keyString)
                    ? self::maskScalar($item)
                    : self::redact($item);
            }

            return $redacted;
        }

        if (is_string($value)) {
            return self::redactString($value);
        }

        return $value;
    }

    public static function redactString(string $value): string
    {
        $patterns = [
            '/AKID[0-9A-Za-z]{8,}/' => 'AKID********',
            '/TC3-HMAC-SHA256 Credential=[^,]+/' => 'TC3-HMAC-SHA256 Credential=********',
            '/(SecretId|SecretKey|Token|Authorization|Password)([\\s:=]+)([^\\s,&]+)/i' => '$1$2********',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $value);
    }

    /**
     * @param mixed $value
     */
    private static function maskScalar($value): string
    {
        if (!is_scalar($value)) {
            return '********';
        }

        $text = (string) $value;
        $length = strlen($text);

        if ($length <= 8) {
            return '********';
        }

        return substr($text, 0, 4) . '********' . substr($text, -4);
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/secret|token|authorization|password|passwd|pwd/i', $key);
    }
}
