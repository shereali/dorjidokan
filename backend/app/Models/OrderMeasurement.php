<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMeasurement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['order_id', 'garment_part_id', 'value', 'unit', 'entered_via', 'entered_at'];

    protected $casts = ['value' => 'decimal:2', 'entered_at' => 'datetime'];

    public function part(): BelongsTo
    {
        return $this->belongsTo(GarmentPart::class, 'garment_part_id');
    }
}
