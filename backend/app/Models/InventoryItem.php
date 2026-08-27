<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
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

    public function getBalanceAttribute(): float
    {
        return (float) $this->movements()->sum('quantity');
    }
}
