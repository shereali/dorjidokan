<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarmentPart extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['garment_id', 'name', 'slug', 'unit', 'display_order', 'svg_asset_ref', 'required'];

    protected $casts = ['required' => 'boolean'];

    public function measurements(): HasMany
    {
        return $this->hasMany(OrderMeasurement::class);
    }
}
