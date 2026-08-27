<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoiceMutationRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Services\MobileNumber;
use App\Services\VoiceOrderService;
use Illuminate\Http\JsonResponse;

class VoiceController extends Controller
{
    public function __construct(private VoiceOrderService $service) {}

    public function register(VoiceMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        [$c,$new] = $this->service->customer($d);

        return $this->ok(['customer' => $this->customer($c), 'confirmation' => __($new ? 'voice.customer_created' : 'voice.customer_exists', ['name' => $c->name])], ['was_created' => $new], $new ? 201 : 200);
    }

    public function store(VoiceMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        $o = $this->service->order($d);

        return $this->ok(['order' => $this->order($o), 'confirmation' => __('voice.order_created', ['number' => $o->order_number])], [], 201);
    }

    public function measurement(VoiceMutationRequest $r, Order $order): JsonResponse
    {
        $d = $r->validated();
        [$m,$missing] = $this->service->measurement($order, $d);

        return $this->ok(['measurement' => ['part_id' => $m->part->public_id, 'part_name' => $m->part->name, 'value' => (float) $m->value, 'unit' => $m->unit], 'missing_measurements' => $missing->map(fn ($p) => $this->part($p))->values(), 'is_complete' => $missing->isEmpty(), 'confirmation' => __('voice.measurement_entered', ['part' => $m->part->name, 'value' => $m->value, 'unit' => $m->unit])]);
    }

    public function assign(VoiceMutationRequest $r, Order $order): JsonResponse
    {
        $d = $r->validated();
        $e = $this->service->assign($order, $d);

        return $this->ok(['confirmation' => __('voice.assigned', ['name' => $e->name])]);
    }

    public function status(VoiceMutationRequest $r, Order $order): JsonResponse
    {
        $d = $r->validated();
        $o = $this->service->status($order, $d['status'], $d['note'] ?? null);

        return $this->ok(['order' => $this->order($o), 'confirmation' => __('voice.status', ['number' => $o->order_number, 'status' => $o->status])]);
    }

    public function search(VoiceMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        try {
            $mobile = MobileNumber::normalize($d['query']);
        } catch (\InvalidArgumentException) {
            $mobile = null;
        }$cs = Customer::with(['orders.garment', 'orders.karigar'])->when($mobile, fn ($q) => $q->where('mobile_number', $mobile), fn ($q) => $q->where('name', 'like', '%'.addcslashes($d['query'], '%_').'%'))->limit(10)->get();

        return $this->ok(['matches' => $cs->map(fn ($c) => ['customer' => $this->customer($c), 'orders' => $c->orders->map(fn ($o) => $this->order($o))]), 'confirmation' => $cs->isEmpty() ? __('voice.no_match') : trans_choice('voice.matches', $cs->count(), ['count' => $cs->count()])]);
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }

    private function customer($c): array
    {
        return ['id' => $c->public_id, 'name' => $c->name, 'mobile_number' => $c->mobile_number, 'created_via' => $c->created_via];
    }

    private function part($p): array
    {
        return ['id' => $p->public_id, 'name' => $p->name, 'unit' => $p->unit, 'display_order' => $p->display_order, 'svg_asset_ref' => $p->svg_asset_ref];
    }

    private function order($o): array
    {
        $o->loadMissing(['customer', 'garment.parts', 'measurements.part', 'karigar']);

        return ['id' => $o->public_id, 'order_number' => $o->order_number, 'status' => $o->status, 'customer' => $this->customer($o->customer), 'garment' => ['id' => $o->garment->public_id, 'name' => $o->garment->name, 'parts' => $o->garment->parts->map(fn ($p) => $this->part($p))], 'karigar' => $o->karigar ? ['id' => $o->karigar->public_id, 'name' => $o->karigar->name] : null, 'measurements' => $o->measurements->map(fn ($m) => ['part_id' => $m->part->public_id, 'value' => (float) $m->value, 'unit' => $m->unit]), 'missing_measurements' => $this->service->missing($o)->map(fn ($p) => $this->part($p))->values()];
    }
}
