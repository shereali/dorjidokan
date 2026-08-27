<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['sale_id', 'inventory_item_id', 'quantity', 'unit_price_minor', 'total_minor'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
