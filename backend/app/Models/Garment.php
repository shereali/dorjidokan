<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Garment extends Model
{
    use BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = ['name', 'slug', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function parts(): HasMany
    {
        return $this->hasMany(GarmentPart::class)->orderBy('display_order');
    }
}
