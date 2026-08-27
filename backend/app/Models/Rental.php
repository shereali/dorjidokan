<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rental extends Model
{
    use BelongsToTenant,HasPublicId,SoftDeletes;

    protected $fillable = ['rental_number', 'customer_id', 'status', 'starts_on', 'due_on', 'returned_on', 'rent_minor', 'deposit_minor', 'damage_charge_minor', 'deposit_refunded_minor', 'settlement_due_minor', 'settlement_note'];

    protected $casts = ['starts_on' => 'date', 'due_on' => 'date', 'returned_on' => 'date'];

    public function items()
    {
        return $this->hasMany(RentalItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
