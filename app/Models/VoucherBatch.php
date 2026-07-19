<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherBatch extends Model
{
    use BelongsToTenant, BelongsToCompany, HasUuids;

    protected $fillable = ['tenant_id', 'company_id', 'generated_by', 'name', 'quantity', 'settings'];
    protected function casts(): array { return ['settings' => 'array']; }
    public function vouchers(): HasMany { return $this->hasMany(Voucher::class); }
}
