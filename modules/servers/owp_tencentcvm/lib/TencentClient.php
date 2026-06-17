<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;

final class TencentClient
{
    public function __construct(private readonly array $settings = [])
    {
    }

    public function isConfigured(): bool
    {
        return !empty($this->settings['secret_id']) && !empty($this->settings['secret_key']);
    }

    /**
     * Placeholder for v0.3. This method must stay non-networked in v0.2.
     *
     * @param array<string, mixed> $payload
     */
    public function request(string $action, array $payload = []): array
    {
        unset($payload);

        throw new RuntimeException(sprintf(
            '%s is not implemented in OWP Tencent CVM v0.2; no Tencent Cloud API call was made.',
            $action
        ));
    }
}
