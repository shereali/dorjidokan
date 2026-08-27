<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = ['name', 'mobile_number', 'address', 'created_via', 'marketing_consent'];

    protected $casts = ['marketing_consent' => 'boolean'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
