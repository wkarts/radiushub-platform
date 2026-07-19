<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceContract extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['subscriber_id', 'network_access_id', 'internet_plan_id', 'number', 'amount', 'billing_day', 'grace_days', 'status', 'started_at', 'ended_at', 'suspended_at', 'next_invoice_at', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'status' => ContractStatus::class, 'started_at' => 'date', 'ended_at' => 'date', 'suspended_at' => 'datetime', 'next_invoice_at' => 'date'];
    }

    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }
    public function access(): BelongsTo { return $this->belongsTo(NetworkAccess::class, 'network_access_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(InternetPlan::class, 'internet_plan_id'); }
}
