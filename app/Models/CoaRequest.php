<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class CoaRequest extends Model { use BelongsToTenant, BelongsToCompany, HasUuids; protected $guarded=[]; protected function casts(): array { return ['attributes'=>'array','response'=>'array','requested_at'=>'datetime','completed_at'=>'datetime']; } }
