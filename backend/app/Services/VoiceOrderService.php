<?php

namespace App\Services;

use App\Events\MeasurementRecorded;
use App\Events\OrderReady;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Garment;
use App\Models\GarmentPart;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoiceOrderService
{
    private const NEXT = ['measuring' => ['pending_assignment', 'cancelled'], 'pending_assignment' => ['in_progress', 'cancelled'], 'in_progress' => ['ready', 'cancelled'], 'ready' => ['delivered'], 'delivered' => [], 'cancelled' => []];

    public function customer(array $data): array
    {
        try {
            $mobile = MobileNumber::normalize($data['mobile_number']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['mobile_number' => $e->getMessage()]);
        } $found = Customer::where('mobile_number', $mobile)->first();

        return $found ? [$found, false] : [Customer::create(['name' => trim($data['name']), 'mobile_number' => $mobile, 'created_via' => 'voice']), true];
    }

    public function order(array $data, string $createdVia = 'voice', string $actorType = 'service_token'): Order
    {
        $customer = Customer::where('public_id', $data['customer_id'])->firstOrFail();
        $garment = Garment::where('public_id', $data['garment_id'])->where('active', true)->firstOrFail();

        return DB::transaction(function () use ($customer, $garment, $createdVia, $actorType) {
            $order = Order::create(['order_number' => 'ORD-'.now()->format('ymd').'-'.strtoupper(substr((string) Str::ulid(), -6)), 'customer_id' => $customer->id, 'garment_id' => $garment->id, 'status' => 'measuring', 'created_via' => $createdVia]);
            $order->items()->create(['garment_id' => $garment->id, 'quantity' => 1]);
            $order->statusEvents()->create(['status' => 'measuring', 'actor_type' => $actorType, 'created_at' => now()]);

            return $order;
        });
    }

    public function measurement(Order $order, array $data, string $enteredVia = 'voice', string $actorType = 'service_token'): array
    {
        $part = GarmentPart::where('garment_id', $order->garment_id)->when($data['garment_part_id'] ?? null, fn ($q, $v) => $q->where('public_id', $v))->when($data['garment_part_name'] ?? null, fn ($q, $v) => $q->where('name', $v))->first();
        if (! $part) {
            throw ValidationException::withMessages(['garment_part' => 'Part does not belong to this garment.']);
        } $m = $order->measurements()->updateOrCreate(['garment_part_id' => $part->id], ['value' => $data['value'], 'unit' => $data['unit'] ?? $part->unit, 'entered_via' => $enteredVia, 'entered_at' => now()]);
        event(new MeasurementRecorded($order, $m->load('part')));
        $missing = $this->missing($order);
        if ($missing->isEmpty() && $order->status === 'measuring') {
            $this->status($order, 'pending_assignment', null, $actorType);
        }

        return [$m, $missing];
    }

    public function assign(Order $order, array $data, string $actorType = 'service_token'): Employee
    {
        $q = Employee::where('employee_type', 'karigar')->where('active', true);
        $employee = isset($data['karigar_id']) ? $q->where('public_id', $data['karigar_id'])->first() : $q->where('name', 'like', '%'.addcslashes($data['karigar_name'], '%_').'%')->first();
        if (! $employee) {
            throw ValidationException::withMessages(['karigar' => 'No active karigar matched.']);
        }$order->update(['karigar_id' => $employee->id]);
        if ($order->status === 'pending_assignment') {
            $this->status($order, 'in_progress', null, $actorType);
        }

        return $employee;
    }

    public function status(Order $order, string $status, ?string $note, string $actorType = 'service_token'): Order
    {
        if ($status === $order->status) {
            return $order;
        }if (! in_array($status, self::NEXT[$order->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Invalid transition from {$order->status} to {$status}."]);
        }

        return DB::transaction(function () use ($order, $status, $note, $actorType) {
            $from = $order->status;
            $order->update(['status' => $status, 'ready_at' => $status === 'ready' ? now() : $order->ready_at, 'delivered_at' => $status === 'delivered' ? now() : $order->delivered_at]);
            $order->statusEvents()->create(['from_status' => $from, 'status' => $status, 'actor_type' => $actorType, 'note' => $note, 'created_at' => now()]);
            if ($status === 'ready') {
                DB::afterCommit(fn () => event(new OrderReady($order->fresh())));
            }

            return $order->fresh();
        });
    }

    public function missing(Order $order)
    {
        $ids = $order->measurements()->pluck('garment_part_id');

        return GarmentPart::where('garment_id', $order->garment_id)->where('required', true)->whereNotIn('id', $ids)->orderBy('display_order')->get();
    }
}
