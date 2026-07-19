<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class WebhookEvent extends Model { use BelongsToTenant, HasUuids; protected $guarded=[]; protected function casts(): array { return ['payload'=>'array','processed_at'=>'datetime']; } }
