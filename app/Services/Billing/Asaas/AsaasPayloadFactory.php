<?php

declare(strict_types=1);

namespace App\Services\Billing\Asaas;

use App\Exceptions\BillingGatewayException;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscriber;

final class AsaasPayloadFactory
{
    /** @return array<string, mixed> */
    public function customer(Subscriber $subscriber, PaymentGatewayConfig $gateway): array
    {
        $document = $this->digits($subscriber->document);

        if ($document === null || ! in_array(strlen($document), [11, 14], true)) {
            throw new BillingGatewayException(
                'O cliente precisa possuir CPF ou CNPJ válido em formato numérico para ser sincronizado com o Asaas.',
            );
        }

        return array_filter([
            'name' => $subscriber->name,
            'cpfCnpj' => $document,
            'email' => $subscriber->email,
            'phone' => $this->digits($subscriber->phone),
            'mobilePhone' => $this->digits($subscriber->whatsapp ?: $subscriber->phone),
            'postalCode' => $this->digits($subscriber->zip_code),
            'address' => $subscriber->street,
            'addressNumber' => $subscriber->number,
            'complement' => $subscriber->complement,
            'province' => $subscriber->district,
            'externalReference' => 'RADIUSHUB:subscriber:'.$subscriber->id,
            'notificationDisabled' => (bool) data_get($gateway->settings, 'notification_disabled', false),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array<string, mixed> */
    public function payment(Invoice $invoice, string $customerId, string $billingType): array
    {
        return [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => (float) $invoice->amount,
            'dueDate' => $invoice->due_date->format('Y-m-d'),
            'description' => $invoice->description,
            'externalReference' => 'RADIUSHUB:invoice:'.$invoice->id,
        ];
    }

    private function digits(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }
}
