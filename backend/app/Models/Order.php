<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['order_number', 'customer_id', 'garment_id', 'karigar_id', 'status', 'created_via', 'promised_at', 'ready_at', 'delivered_at', 'total_minor', 'paid_minor'];

    protected $casts = ['promised_at' => 'datetime', 'ready_at' => 'datetime', 'delivered_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (Order $order) => $order->public_id ??= (string) Str::ulid());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class);
    }

    public function karigar(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'karigar_id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(OrderMeasurement::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
