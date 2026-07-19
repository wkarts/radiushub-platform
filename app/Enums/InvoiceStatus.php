<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Chargeback = 'chargeback';
}
