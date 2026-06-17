<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class TencentResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $action,
        private readonly string $requestId,
        private readonly array $data,
        private readonly int $httpStatus,
        private readonly array $raw
    ) {
    }

    public function action(): string
    {
        return $this->action;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'request_id' => $this->requestId,
            'http_status' => $this->httpStatus,
            'data' => $this->data,
        ];
    }
}
