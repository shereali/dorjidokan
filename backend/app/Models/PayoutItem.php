<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['payout_batch_id', 'work_entry_id', 'amount_minor'];

    public function workEntry(): BelongsTo
    {
        return $this->belongsTo(WorkEntry::class);
    }
}
