<script setup lang="ts">
interface PickerCustomer {
  id?: string;
  public_id?: string;
  name: string;
  mobile_number?: string;
}

const props = withDefaults(defineProps<{
  modelValue: string;
  placeholder?: string;
  emptyLabel?: string;
  required?: boolean;
}>(), { placeholder: "Search name or mobile…", emptyLabel: "", required: false });

const emit = defineEmits<{ "update:modelValue": [id: string]; select: [customer: PickerCustomer | null] }>();

const api = useTailorsApi();
const listId = useId();
const term = ref("");
const results = ref<PickerCustomer[]>([]);
const chosen = ref<PickerCustomer | null>(null);
const open = ref(false);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let sequence = 0;

const idOf = (c: PickerCustomer) => c.public_id || c.id || "";

async function search(value: string) {
  const ticket = ++sequence;
  loading.value = true;
  try {
    const res = await api.request<{ items: PickerCustomer[] }>(`/customers?query=${encodeURIComponent(value.trim())}`);
    // Ignore answers that arrive after a newer keystroke.
    if (ticket === sequence) results.value = res.data.items;
  } catch {
    if (ticket === sequence) results.value = [];
  } finally {
    if (ticket === sequence) loading.value = false;
  }
}

function onInput() {
  open.value = true;
  clearTimeout(timer);
  timer = setTimeout(() => search(term.value), 250);
}

function choose(customer: PickerCustomer | null) {
  chosen.value = customer;
  term.value = customer ? `${customer.name}${customer.mobile_number ? ` (${customer.mobile_number})` : ""}` : "";
  open.value = false;
  emit("update:modelValue", customer ? idOf(customer) : "");
  emit("select", customer);
}

function onFocus() {
  open.value = true;
  if (!results.value.length) search(term.value);
}

function onBlur() {
  // Delay so a click on an option registers before the list closes.
  setTimeout(() => (open.value = false), 150);
}

watch(() => props.modelValue, (value) => {
  if (!value) {
    chosen.value = null;
    term.value = "";
  }
});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
  <div class="picker">
    <input
      v-model="term"
      type="search"
      role="combobox"
      autocomplete="off"
      :aria-expanded="open"
      :aria-controls="listId"
      :placeholder="placeholder"
      :required="required && !modelValue"
      @input="onInput"
      @focus="onFocus"
      @blur="onBlur"
      @keydown.esc="open = false"
    />
    <ul v-if="open" :id="listId" class="picker__list" role="listbox">
      <li v-if="emptyLabel" role="option" :aria-selected="!modelValue" @mousedown.prevent="choose(null)">{{ emptyLabel }}</li>
      <li v-if="loading && !results.length" class="picker__note">…</li>
      <li v-for="c in results" :key="idOf(c)" role="option" :aria-selected="idOf(c) === modelValue" @mousedown.prevent="choose(c)">
        <strong>{{ c.name }}</strong> <small>{{ c.mobile_number }}</small>
      </li>
      <li v-if="!loading && !results.length" class="picker__note">—</li>
    </ul>
  </div>
</template>

<style scoped>
.picker { position: relative; }
.picker input { width: 100%; }
.picker__list { position: absolute; z-index: 20; inset-inline: 0; top: calc(100% + 2px); margin: 0; padding: 0.25rem; list-style: none; background: var(--surface); border: 1px solid var(--line-strong, var(--line)); border-radius: var(--radius-sm); box-shadow: var(--shadow-lg, 0 8px 24px rgb(0 0 0 / 15%)); max-height: 15rem; overflow-y: auto; }
.picker__list li { padding: 0.5rem 0.65rem; border-radius: var(--radius-xs, 4px); cursor: pointer; }
.picker__list li:hover, .picker__list li[aria-selected="true"] { background: var(--primary-soft, rgb(0 0 0 / 5%)); }
.picker__list small { color: var(--muted); margin-left: 0.4rem; }
.picker__note { color: var(--muted); cursor: default !important; }
</style>
