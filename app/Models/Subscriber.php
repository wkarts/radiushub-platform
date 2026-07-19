<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscriber extends Model
{
    use BelongsToTenant, BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['company_id', 'type', 'name', 'document', 'email', 'phone', 'whatsapp', 'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state', 'gateway_customer_id', 'notes', 'status'];

    public function contracts(): HasMany { return $this->hasMany(ServiceContract::class); }
    public function accesses(): HasMany { return $this->hasMany(NetworkAccess::class); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function billingCustomerLinks(): HasMany { return $this->hasMany(BillingCustomerLink::class); }
}
