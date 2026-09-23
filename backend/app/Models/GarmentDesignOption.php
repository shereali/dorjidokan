<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarmentDesignOption extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'tenant_id',
        'garment_id',
        'name',
        'type',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(GarmentDesignValue::class)->orderBy('display_order');
    }
}
