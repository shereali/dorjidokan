<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use BelongsToTenant,HasPublicId,SoftDeletes;

    protected $fillable = ['purchase_number', 'supplier_id', 'status', 'total_minor', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
