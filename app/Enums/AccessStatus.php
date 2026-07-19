<?php
namespace App\Enums;
enum AccessStatus: string { case Active = 'active'; case Suspended = 'suspended'; case Blocked = 'blocked'; case Disabled = 'disabled'; }
