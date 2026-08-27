<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }
}
