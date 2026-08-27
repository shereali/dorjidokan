<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryReminder extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['order_id', 'customer_id', 'scheduled_at', 'channel', 'status', 'provider_reference', 'error', 'sent_at'];

    protected $casts = ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
