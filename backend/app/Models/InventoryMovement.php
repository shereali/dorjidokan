<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['inventory_item_id', 'type', 'quantity', 'unit_cost_minor', 'reference_type', 'reference_id', 'reason', 'created_by', 'occurred_at'];

    protected $casts = ['quantity' => 'decimal:3', 'occurred_at' => 'datetime'];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
