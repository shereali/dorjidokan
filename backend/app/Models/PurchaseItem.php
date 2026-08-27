<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['purchase_id', 'inventory_item_id', 'quantity', 'unit_cost_minor', 'total_minor'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
