<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RentalItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['rental_id', 'inventory_item_id', 'quantity', 'condition_out', 'condition_in'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
