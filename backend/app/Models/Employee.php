<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'mobile_number', 'employee_type', 'active'];

    protected $casts = ['active' => 'boolean'];
}
