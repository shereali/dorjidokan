<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExpenseAttachment extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = ['expense_id', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'uploaded_by'];
}
