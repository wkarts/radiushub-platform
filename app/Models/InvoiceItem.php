<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InvoiceItem extends Model { use HasUuids; protected $fillable=['invoice_id','description','quantity','unit_price','total']; protected function casts(): array { return ['quantity'=>'decimal:3','unit_price'=>'decimal:2','total'=>'decimal:2']; } public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); } }
