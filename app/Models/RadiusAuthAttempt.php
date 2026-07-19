<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class RadiusAuthAttempt extends Model { use BelongsToTenant; protected $guarded=[]; public $timestamps=false; protected function casts(): array { return ['created_at'=>'datetime','request_attributes'=>'array']; } }
