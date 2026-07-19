<?php

declare(strict_types=1);

return [
    'webhook' => [
        'name' => env('ASAAS_WEBHOOK_NAME', 'RadiusHub - Pagamentos'),
        'send_type' => env('ASAAS_WEBHOOK_SEND_TYPE', 'SEQUENTIALLY'),
        'events' => [
            'PAYMENT_CREATED',
            'PAYMENT_UPDATED',
            'PAYMENT_CONFIRMED',
            'PAYMENT_RECEIVED',
            'PAYMENT_OVERDUE',
            'PAYMENT_DELETED',
            'PAYMENT_RESTORED',
            'PAYMENT_REFUNDED',
            'PAYMENT_PARTIALLY_REFUNDED',
            'PAYMENT_REFUND_IN_PROGRESS',
            'PAYMENT_REFUND_DENIED',
            'PAYMENT_CHARGEBACK_REQUESTED',
            'PAYMENT_CHARGEBACK_DISPUTE',
            'PAYMENT_AWAITING_CHARGEBACK_REVERSAL',
        ],
    ],
];
