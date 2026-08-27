<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['employee_id', 'order_id', 'work_type', 'quantity', 'rate_minor', 'amount_minor', 'status', 'completed_at'];

    protected $casts = ['quantity' => 'decimal:2', 'completed_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
