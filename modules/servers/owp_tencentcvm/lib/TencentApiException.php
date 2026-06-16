<?php

declare(strict_types=1);

namespace OwpTencentCvm;

use RuntimeException;
use Throwable;

final class TencentApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = '',
        private readonly string $requestId = '',
        private readonly string $action = '',
        private readonly int $httpStatus = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'action' => $this->action,
            'code' => $this->errorCode,
            'request_id' => $this->requestId,
            'http_status' => $this->httpStatus,
            'message' => $this->getMessage(),
        ];
    }
}
