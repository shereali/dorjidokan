<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'webhook_endpoint_id', 'event_id', 'event_name', 'payload', 'attempt', 'response_status', 'error', 'delivered_at', 'next_retry_at'];

    protected $casts = ['payload' => 'array', 'delivered_at' => 'datetime', 'next_retry_at' => 'datetime'];
}
