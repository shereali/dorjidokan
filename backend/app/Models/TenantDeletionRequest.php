<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TenantDeletionRequest extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['requested_by', 'status', 'scheduled_for', 'cancelled_at', 'completed_at'];

    protected $casts = ['scheduled_for' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime'];
}
