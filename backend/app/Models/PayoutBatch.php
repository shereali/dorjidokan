<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutBatch extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['batch_number', 'employee_id', 'period_from', 'period_to', 'total_minor', 'payment_method', 'reference', 'paid_by', 'paid_at'];

    protected $casts = ['period_from' => 'date', 'period_to' => 'date', 'paid_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
