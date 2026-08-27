<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UsageCounter extends Model
{
    use BelongsToTenant;

    protected $fillable = ['metric', 'period', 'value'];
}
