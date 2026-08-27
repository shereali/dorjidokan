<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class NotificationCampaign extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['name', 'occasion', 'channel', 'body', 'status', 'recipient_count', 'created_by', 'queued_at', 'scheduled_at'];

    protected $casts = ['queued_at' => 'datetime', 'scheduled_at' => 'datetime'];
}
