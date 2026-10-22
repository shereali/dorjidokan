<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A subscription payment recorded by the platform (manual bank/bKash/cash or gateway). */
class SubscriptionPayment extends Model
{
    use HasPublicId;

    protected $fillable = ['tenant_id', 'subscription_id', 'plan_id', 'amount_minor', 'currency', 'method', 'reference', 'status', 'months', 'period_start', 'period_end', 'paid_at', 'recorded_by', 'note'];

    protected $casts = ['paid_at' => 'datetime', 'period_start' => 'datetime', 'period_end' => 'datetime'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
