<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'price_minor', 'currency', 'billing_interval', 'feature_limits', 'active'];

    protected $casts = ['feature_limits' => 'array', 'active' => 'boolean'];
}
