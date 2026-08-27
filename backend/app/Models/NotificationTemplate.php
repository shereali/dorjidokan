<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['event', 'channel', 'name', 'body', 'active'];

    protected $casts = ['active' => 'boolean'];
}
