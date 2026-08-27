<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use BelongsToTenant,HasPublicId,SoftDeletes;

    protected $fillable = ['sale_number', 'customer_id', 'sale_type', 'status', 'subtotal_minor', 'discount_minor', 'total_minor', 'paid_minor'];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
