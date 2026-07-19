<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Payment extends Model { use BelongsToTenant, HasUuids; protected $fillable=['invoice_id','amount','method','external_id','paid_at','payload']; protected function casts(): array { return ['amount'=>'decimal:2','paid_at'=>'datetime','payload'=>'array']; } public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); } public function refunds(): HasMany { return $this->hasMany(PaymentRefund::class); } }
