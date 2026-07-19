<?php

declare(strict_types=1);

namespace App\Services\Billing\Asaas;

use App\Exceptions\BillingGatewayException;
use Asaas\Sdk\Exception\ApiException;
use Asaas\Sdk\Exception\TransportException;
use Throwable;

final class AsaasExceptionMapper
{
    public static function rethrow(Throwable $exception, string $operation): never
    {
        if ($exception instanceof BillingGatewayException) {
            throw $exception;
        }

        if ($exception instanceof ApiException) {
            throw new BillingGatewayException(
                message: "Asaas: {$operation} falhou. {$exception->getMessage()}",
                statusCode: $exception->getStatusCode(),
                errors: $exception->getErrors(),
                previous: $exception,
            );
        }

        if ($exception instanceof TransportException) {
            throw new BillingGatewayException(
                message: "Asaas: não foi possível comunicar com a API durante {$operation}.",
                previous: $exception,
            );
        }

        throw new BillingGatewayException(
            message: "Asaas: erro inesperado durante {$operation}. {$exception->getMessage()}",
            previous: $exception,
        );
    }
}
