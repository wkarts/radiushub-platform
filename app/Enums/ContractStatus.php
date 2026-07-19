<?php
namespace App\Enums;
enum ContractStatus: string { case Draft = 'draft'; case Active = 'active'; case Suspended = 'suspended'; case Cancelled = 'cancelled'; }
