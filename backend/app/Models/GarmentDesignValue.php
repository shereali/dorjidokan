<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GarmentDesignValue extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'tenant_id',
        'garment_design_option_id',
        'name',
        'extra_price_minor',
        'is_default',
        'display_order',
    ];

    protected $casts = [
        'extra_price_minor' => 'integer',
        'is_default' => 'boolean',
        'display_order' => 'integer',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(GarmentDesignOption::class, 'garment_design_option_id');
    }
}
