<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExportRequest extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['requested_by', 'type', 'filters', 'email', 'status', 'storage_path', 'mime_type', 'failure_message', 'completed_at'];

    protected $casts = ['filters' => 'array', 'completed_at' => 'datetime'];
}
