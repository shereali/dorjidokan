<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'url', 'signing_secret', 'events', 'active'];

    protected $casts = ['signing_secret' => 'encrypted', 'events' => 'array', 'active' => 'boolean'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
