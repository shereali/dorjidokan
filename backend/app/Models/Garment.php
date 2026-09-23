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

    protected $fillable = [
        'name',
        'slug',
        'category',
        'group_name',
        'base_making_minor',
        'master_rate_minor',
        'karigar_rate_minor',
        'description',
        'loose_allowances',
        'display_order',
        'illustration_url',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'base_making_minor' => 'integer',
        'master_rate_minor' => 'integer',
        'karigar_rate_minor' => 'integer',
        'display_order' => 'integer',
        'loose_allowances' => 'array',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(GarmentPart::class)->orderBy('display_order');
    }

    public function designOptions(): HasMany
    {
        return $this->hasMany(GarmentDesignOption::class)->orderBy('display_order');
    }
}
