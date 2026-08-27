<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['payable_type', 'payable_id', 'amount_minor', 'currency', 'method', 'reference', 'received_by', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];

    public function payable()
    {
        return $this->morphTo();
    }
}
