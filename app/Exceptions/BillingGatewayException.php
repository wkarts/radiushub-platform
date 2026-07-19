<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class BillingGatewayException extends RuntimeException
{
    /** @param array<int, array<string, mixed>> $errors */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
