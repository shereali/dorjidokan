<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['customer_id', 'balance', 'total_earned', 'total_redeemed'];

    protected $casts = ['balance' => 'integer', 'total_earned' => 'integer', 'total_redeemed' => 'integer'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }
}
