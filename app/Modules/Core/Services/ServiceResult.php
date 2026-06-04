<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

class ServiceResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly array $errors = [],
        public readonly string $message = ''
    ) {}

    public static function ok(mixed $data = null, string $message = ''): self
    {
        return new self(true, $data, [], $message);
    }

    public static function fail(array|string $errors, mixed $data = null): self
    {
        $errors = is_string($errors) ? [$errors] : $errors;

        return new self(false, $data, $errors, implode('. ', $errors));
    }
}
