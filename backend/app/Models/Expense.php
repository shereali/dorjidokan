<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToTenant,HasPublicId,SoftDeletes;

    protected $fillable = ['expense_category_id', 'amount_minor', 'note', 'expense_date', 'status', 'created_by', 'approved_by', 'approved_at'];

    protected $casts = ['expense_date' => 'date', 'approved_at' => 'datetime'];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function attachments()
    {
        return $this->hasMany(ExpenseAttachment::class);
    }
}
