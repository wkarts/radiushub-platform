<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case Available = 'available';
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';
}
