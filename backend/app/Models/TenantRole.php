<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-workshop override of a role's permission list. Absent row = config defaults. */
class TenantRole extends Model
{
    protected $fillable = ['tenant_id', 'role_key', 'permissions'];

    protected $casts = ['permissions' => 'array'];
}
