<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['notification_template_id', 'event', 'channel', 'recipient', 'body', 'status', 'provider_reference', 'error', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
