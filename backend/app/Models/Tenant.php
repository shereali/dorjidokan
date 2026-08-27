<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use Billable, HasPublicId, SoftDeletes;

    protected $fillable = ['name', 'slug', 'status', 'default_locale', 'currency', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')->withPivot('role')->withTimestamps();
    }
}
