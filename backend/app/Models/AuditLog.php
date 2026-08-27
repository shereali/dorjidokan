<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['tenant_id', 'user_id', 'action', 'subject_type', 'subject_id', 'context', 'ip_address', 'created_at'];

    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
