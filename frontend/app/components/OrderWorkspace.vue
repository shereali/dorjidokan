<script setup lang="ts">
interface Part {
  id: string;
  name: string;
  unit: string;
  display_order: number;
  svg_asset_ref?: string;
}
interface Measurement {
  part_id: string;
  value: number;
  unit: string;
}
interface Customer {
  id?: string;
  public_id?: string;
  name: string;
}
interface Garment {
  id?: string;
  public_id?: string;
  name: string;
  active?: boolean;
  parts?: Part[];
}
interface Employee {
  id: string;
  name: string;
  active: boolean;
  employee_type: string;
}
interface Timeline {
  status: string;
  from_status?: string;
  note?: string;
  created_at: string;
}
interface Order {
  id: string;
  number?: string;
  order_number?: string;
  barcode?: string;
  status: string;
  customer: { name: string };
  garment: { name: string; parts?: Part[] };
  karigar?: { id: string; name: string };
  measurements?: Measurement[];
  timeline?: Timeline[];
  items?: Array<{ id:string; garment:{name:string}; quantity:number; making_cost_minor:number; design_cost_minor:number; group_name?:string }>;
  total_minor: number;
  paid_minor: number;
  promised_at?: string;
  archived_at?: string;
}
const api = useTailorsApi(),
  realtime = useOrderChannel(),
  items = ref<Order[]>([]),
  customers = ref<Customer[]>([]),
  garments = ref<Garment[]>([]),
  employees = ref<Employee[]>([]),
  selected = ref<Order | null>(null),
  status = ref(""),
  query = ref(""),
  archived = ref(false),
  loading = ref(true),
  live = ref(false),
  notice = ref(""),
  error = ref(""),
  measurementValues = reactive<Record<string, number | null>>({}),
  form = reactive({
    customer_id: "",
    garment_id: "",
    promised_at: "",
    total_minor: 0,
    paid_minor: 0,
  }),
  assignment = ref(""),
  payment = reactive({ amount_minor: 0, method: "cash", reference: "" });
const nextStatus = computed(() =>
  selected.value
    ? (
        {
          measuring: "pending_assignment",
          pending_assignment: "in_progress",
          in_progress: "ready",
          ready: "delivered",
        } as Record<string, string>
      )[selected.value.status]
    : undefined,
);
async function load() {
  loading.value = true;
  const [orders, customerList, garmentList, employeeList] = await Promise.all([
    api.request<{ items: Order[] }>(
      `/orders?archived=${archived.value ? 1 : 0}${status.value ? "&status=" + status.value : ""}&query=${encodeURIComponent(query.value)}`,
    ),
    api.request<{ items: Customer[] }>("/customers"),
    api.request<{ garments: Garment[] }>("/garments"),
    api.request<{ employees: Employee[] }>("/employees"),
  ]);
  items.value = orders.data.items;
  customers.value = customerList.data.items;
  garments.value = garmentList.data.garments.filter((g) => g.active !== false);
  employees.value = employeeList.data.employees.filter(
    (e) => e.active && e.employee_type === "karigar",
  );
  loading.value = false;
}
async function refreshSelected() {
  if (!selected.value) return;
  selected.value = (
    await api.request<{ order: Order }>(`/orders/${selected.value.id}`)
  ).data.order;
  for (const measurement of selected.value.measurements || [])
    measurementValues[measurement.part_id] = measurement.value;
}
async function select(order: Order) {
  selected.value = (
    await api.request<{ order: Order }>(`/orders/${order.id}`)
  ).data.order;
  for (const measurement of selected.value.measurements || [])
    measurementValues[measurement.part_id] = measurement.value;
  assignment.value = selected.value.karigar?.id || "";
  live.value = await realtime.join(order.id, async (name) => {
    notice.value =
      name === "order.ready"
        ? "Order marked ready."
        : "New measurement received.";
    await refreshSelected();
    await load();
  });
}
async function createOrder() {
  error.value = "";
  try {
    const result = await api.request<{ order: Order }>("/orders", {
      method: "POST",
      body: form,
    });
    Object.assign(form, {
      customer_id: "",
      garment_id: "",
      promised_at: "",
      total_minor: 0,
      paid_minor: 0,
    });
    await load();
    await select(result.data.order);
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not create order.";
  }
}
async function saveMeasurement(part: Part) {
  const value = measurementValues[part.id];
  if (!selected.value || !value) return;
  await api.request(`/orders/${selected.value.id}/measurements`, {
    method: "POST",
    body: { garment_part_id: part.id, value, unit: part.unit },
  });
  notice.value = `${part.name} saved.`;
  await refreshSelected();
}
async function assign() {
  if (!selected.value || !assignment.value) return;
  await api.request(`/orders/${selected.value.id}/assign`, {
    method: "POST",
    body: { karigar_id: assignment.value },
  });
  notice.value = "Karigar assigned.";
  await refreshSelected();
  await load();
}
async function advance() {
  if (!selected.value || !nextStatus.value) return;
  await api.request(`/orders/${selected.value.id}/status`, {
    method: "PATCH",
    body: { status: nextStatus.value },
  });
  notice.value = `Order moved to ${nextStatus.value}.`;
  await refreshSelected();
  await load();
}
async function pay() {
  if (!selected.value || payment.amount_minor < 1) return;
  await api.request(`/orders/${selected.value.id}/payments`, {
    method: "POST",
    body: payment,
  });
  Object.assign(payment, { amount_minor: 0, method: "cash", reference: "" });
  notice.value = "Payment recorded.";
  await refreshSelected();
}
async function archiveSelected() {
  if (!selected.value) return;
  await api.request(`/orders/${selected.value.id}`, { method: "DELETE" });
  selected.value = null;
  notice.value = "Order archived.";
  await load();
}
async function restore(order: Order) {
  await api.request(`/orders/${order.id}/restore`, { method: "POST" });
  notice.value = "Order restored.";
  await load();
}
function printLabel() {
  window.print();
}
onMounted(load);
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        PRODUCTION
      </p>
      <h1>Orders</h1>
      <p>Create orders, capture measurements and manage delivery.</p>
    </div>
    <div class="tabs">
      <button :class="{ active: !archived }" @click="archived = false; selected = null; load()">
        Active
      </button>
      <button :class="{ active: archived }" @click="archived = true; selected = null; load()">
        Archive
      </button>
    </div>
    <select v-if="!archived" v-model="status" aria-label="Filter orders by status" @change="load">
      <option value="">
        All statuses
      </option>
      <option
        v-for="s in [
          'measuring',
          'pending_assignment',
          'in_progress',
          'ready',
          'delivered',
        ]"
        :key="s"
      >
        {{ s }}
      </option>
    </select>
    <form class="toolbar" @submit.prevent="load"><label>Scan barcode or search<input v-model="query" autofocus inputmode="search" placeholder="Order no, mobile or customer" /></label><button>Find order</button></form>
  </header>
  <div class="workspace-grid orders-layout">
    <div>
      <form v-if="!archived" class="panel form" @submit.prevent="createOrder">
        <h2>New tailoring order</h2>
        <label>Customer<select v-model="form.customer_id" required>
          <option value="">Select customer</option>
          <option
            v-for="customer in customers"
            :key="customer.id || customer.public_id"
            :value="customer.id || customer.public_id"
          >
            {{ customer.name }}
          </option>
        </select></label><label>Garment<select v-model="form.garment_id" required>
          <option value="">Select garment</option>
          <option
            v-for="garment in garments"
            :key="garment.id || garment.public_id"
            :value="garment.id || garment.public_id"
          >
            {{ garment.name }}
          </option>
        </select></label><label>Delivery promise<input
          v-model="form.promised_at"
          type="datetime-local"
        /></label><label>Total (paisa)<input
          v-model.number="form.total_minor"
          type="number"
          min="0"
          required
        /></label><label>Advance (paisa)<input
          v-model.number="form.paid_minor"
          type="number"
          min="0"
          :max="form.total_minor"
          required
        /></label>
        <p v-if="error" class="error" role="alert">
          {{ error }}
        </p>
        <button class="primary">
          Create order
        </button>
      </form>
      <section class="panel order-list">
        <p v-if="loading">
          Loading…
        </p>
        <p v-else-if="!items.length" class="empty">
          No matching orders.
        </p>
        <button
          v-for="order in items"
          :key="order.id"
          class="record order-select"
          :class="{ active: selected?.id === order.id }"
          @click="archived ? restore(order) : select(order)"
        >
          <strong>{{ order.customer.name }}</strong><span>{{ order.number }} · {{ order.garment.name }}</span><small>{{ archived ? "Restore order" : order.status }}</small>
        </button>
      </section>
    </div>
    <section class="panel order-detail">
      <template v-if="selected">
        <div class="record record--stock order-label">
          <span><strong>{{ selected.customer.name }}</strong><small>{{ selected.order_number }} · {{ selected.garment.name }}</small></span><span :class="live ? 'success' : ''">{{
            live ? "● Live" : "Realtime idle"
          }}</span><b>{{ selected.barcode }}</b>
        </div>
        <p v-if="notice" class="success" role="status">
          {{ notice }}
        </p>
        <GarmentPrototypeBuilder
          :garment-name="selected.garment.name"
          :parts="selected.garment.parts || []"
          :measurements="selected.measurements || []"
        />
        <section v-if="selected.items?.length" class="panel">
          <h2>Garment lines</h2><div v-for="line in selected.items" :key="line.id" class="record"><strong>{{ line.garment.name }} × {{ line.quantity }}</strong><small>{{ line.group_name || "Standard" }} · ৳ {{ ((line.making_cost_minor + line.design_cost_minor)/100).toFixed(2) }}</small></div>
        </section>
        <div class="measurement-grid">
          <form
            v-for="part in selected.garment.parts || []"
            :key="part.id"
            class="inline-form"
            @submit.prevent="saveMeasurement(part)"
          >
            <label>{{ part.name
            }}<input
              v-model.number="measurementValues[part.id]"
              type="number"
              step="0.01"
              min="0.01"
              :aria-label="part.name"
            /></label><span>{{ part.unit }}</span><button>Save</button>
          </form>
        </div>
        <form class="inline-form" @submit.prevent="assign">
          <label>Karigar<select v-model="assignment">
            <option value="">Select karigar</option>
            <option
              v-for="employee in employees"
              :key="employee.id"
              :value="employee.id"
            >
              {{ employee.name }}
            </option>
          </select></label><button>Assign</button>
        </form>
        <form class="inline-form payment-form" @submit.prevent="pay">
          <label>Payment (paisa)<input
            v-model.number="payment.amount_minor"
            type="number"
            min="1"
            :max="selected.total_minor - selected.paid_minor"
          /></label><select v-model="payment.method" aria-label="Payment method">
            <option value="cash">
              Cash
            </option>
            <option value="card">
              Card
            </option>
            <option value="mobile_banking">
              Mobile banking
            </option>
            <option value="bank_transfer">
              Bank transfer
            </option>
          </select><button>Record payment</button>
        </form>
        <p>
          Paid ৳ {{ (selected.paid_minor / 100).toFixed(2) }} · Due ৳
          {{ ((selected.total_minor - selected.paid_minor) / 100).toFixed(2) }}
        </p>
        <div class="order-actions">
          <button v-if="nextStatus" class="primary" @click="advance">
            Move to {{ nextStatus }}
          </button><button @click="printLabel">
            Print label
          </button><button v-if="!['ready', 'delivered'].includes(selected.status)" @click="archiveSelected">
            Archive order
          </button>
        </div>
        <ol class="timeline">
          <li v-for="event in selected.timeline || []" :key="event.created_at">
            <strong>{{ event.status }}</strong><small>{{ new Date(event.created_at).toLocaleString() }}</small><span v-if="event.note">{{ event.note }}</span>
          </li>
        </ol>
      </template>
      <p v-else class="empty">
        Select an order to manage its live production record.
      </p>
    </section>
  </div>
</template>
