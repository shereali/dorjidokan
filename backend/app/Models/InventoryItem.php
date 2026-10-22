<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use BelongsToTenant,HasPublicId,SoftDeletes;

    protected $fillable = ['sku', 'name', 'unit', 'reorder_level', 'active'];

    protected $casts = ['reorder_level' => 'decimal:3', 'active' => 'boolean'];

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** Items at or below their reorder level, computed in SQL so large catalogs are never loaded into memory. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('COALESCE((SELECT SUM(m.quantity) FROM inventory_movements m WHERE m.inventory_item_id = inventory_items.id), 0) <= inventory_items.reorder_level');
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->movements()->sum('quantity');
    }
}
