<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OrderStatusEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['order_id', 'from_status', 'status', 'actor_type', 'actor_id', 'note', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
