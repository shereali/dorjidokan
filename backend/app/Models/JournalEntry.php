<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['reference_type', 'reference_id', 'memo', 'occurred_at', 'posted_at', 'created_by'];

    protected $casts = ['occurred_at' => 'datetime', 'posted_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
