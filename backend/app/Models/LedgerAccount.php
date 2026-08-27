<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['customer_id', 'code', 'name', 'type', 'system'];

    protected $casts = ['system' => 'boolean'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
