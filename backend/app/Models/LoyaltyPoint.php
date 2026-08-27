<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPoint extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['loyalty_account_id', 'customer_id', 'order_id', 'type', 'points', 'reason', 'created_by'];

    protected $casts = ['points' => 'integer'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
