<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Connectors;

/**
 * Standardized response from every SystemConnector method.
 * Wraps both success and failure so the orchestrator never needs to read
 * connector-specific error shapes.
 */
class ConnectorResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly ?string $errorCode = null,
        public readonly ?string $externalId = null,
        public readonly mixed $payload = null,
    ) {}

    public static function ok(string $message = '', ?string $externalId = null, mixed $payload = null): self
    {
        return new self(true, $message, null, $externalId, $payload);
    }

    public static function fail(string $message, ?string $errorCode = null, mixed $payload = null): self
    {
        return new self(false, $message, $errorCode, null, $payload);
    }

    public function toArray(): array
    {
        return [
            'success'     => $this->success,
            'message'     => $this->message,
            'error_code'  => $this->errorCode,
            'external_id' => $this->externalId,
            'payload'     => $this->payload,
        ];
    }
}
