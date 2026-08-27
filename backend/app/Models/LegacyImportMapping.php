<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LegacyImportMapping extends Model
{
    use BelongsToTenant;

    protected $fillable = ['source_table', 'source_id', 'target_type', 'target_id', 'checksum'];
}
