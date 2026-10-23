<script setup lang="ts">
const { t } = useTailorsI18n();

interface Employee {
  id: string;
  name: string;
  mobile_number?: string;
  employee_type: "karigar" | "staff" | "manager";
  active: boolean;
}

const api = useTailorsApi();
const toast = useToast();
const knownRoles = ["karigar", "staff", "manager", "master_cutter", "finisher"];
const roleLabel = (type: string) => (knownRoles.includes(type) ? t(`app.workforce.role_${type}`) : type);

const items = ref<Employee[]>([]);
const loading = ref(true);
const error = ref("");
const query = ref("");
const showInactive = ref(false);
const showForm = ref(false);
const editingEmployee = ref<Employee | null>(null);

const canManage = computed(() => api.can("employees.manage"));

const form = reactive({
  name: "",
  mobile_number: "",
  employee_type: "karigar" as "karigar" | "staff" | "manager",
});

async function load() {
  loading.value = true;
  error.value = "";
  try {
    items.value = (await api.request<{ employees: Employee[] }>("/employees")).data.employees;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t("app.workforce.could_not_load_the_team");
  } finally {
    loading.value = false;
  }
}

function openAdd() {
  editingEmployee.value = null;
  Object.assign(form, { name: "", mobile_number: "", employee_type: "karigar" });
  error.value = "";
  showForm.value = true;
}

function openEdit(emp: Employee) {
  editingEmployee.value = emp;
  Object.assign(form, {
    name: emp.name,
    mobile_number: emp.mobile_number || "",
    employee_type: emp.employee_type,
  });
  error.value = "";
  showForm.value = true;
}

function cancelForm() {
  showForm.value = false;
  editingEmployee.value = null;
  error.value = "";
  Object.assign(form, { name: "", mobile_number: "", employee_type: "karigar" });
}

async function save() {
  error.value = "";
  try {
    if (editingEmployee.value) {
      await api.request(`/employees/${editingEmployee.value.id}`, {
        method: "PATCH",
        body: form,
      });
      toast.success(t("app.workforce.p0_added", { p0: form.name }));
    } else {
      await api.request("/employees", { method: "POST", body: form });
      toast.success(t("app.workforce.p0_added", { p0: form.name }));
    }
    cancelForm();
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t("app.workforce.could_not_save_the_team_member");
  }
}

async function toggle(employee: Employee) {
  try {
    await api.request(`/employees/${employee.id}`, {
      method: "PATCH",
      body: { active: !employee.active },
    });
    toast.info(t(employee.active ? "app.workforce.member_deactivated" : "app.workforce.member_activated"));
    await load();
  } catch {
    toast.error(t("app.workforce.could_not_save_the_team_member"));
  }
}

function getWhatsAppUrl(mobile: string) {
  const digits = mobile.replace(/\D/g, "");
  const num = digits.startsWith("88") ? digits : digits.startsWith("0") ? "88" + digits : digits;
  return `https://wa.me/${num}?text=${encodeURIComponent("Hello from Tailors OS")}`;
}

const filteredItems = computed(() => {
  let list = items.value;
  if (!showInactive.value) {
    list = list.filter((i) => i.active);
  }
  if (!query.value.trim()) return list;
  const q = query.value.toLowerCase();
  return list.filter(
    (i) =>
      i.name.toLowerCase().includes(q) ||
      (i.mobile_number && i.mobile_number.includes(q)) ||
      i.employee_type.toLowerCase().includes(q)
  );
});

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('app.workforce.team') }}</p>
        <h1>{{ t('app.workforce.karigars_workshop_team') }}</h1>
        <p>{{ t('app.workforce.manage_masters_cutters_karigars_and_staff') }}</p>
      </div>
    </header>

    <section class="panel">
      <!-- Toolbar: Search, Add button, Inactive toggle -->
      <div class="toolbar">
        <input
          v-model="query"
          :placeholder="t('app.workforce.full_name')"
          @keyup.enter="load"
        />
        <button type="button" @click="load">
          <NavIcon name="Search" /> {{ t('common.search') }}
        </button>
        <button v-if="canManage" type="button" class="primary" @click="openAdd">
          <NavIcon name="Plus" /> {{ t('app.workforce.add_team_member') }}
        </button>
        <label class="check-label check-label--end">
          <input v-model="showInactive" type="checkbox" />
          <span>{{ t('status.archived') }} / {{ t('app.workforce.inactive') }}</span>
        </label>
      </div>

      <p v-if="loading && !items.length">{{ t('app.workforce.loading_team') }}</p>
      <p v-else-if="!filteredItems.length" class="empty">{{ t('app.workforce.no_team_members_yet') }}</p>

      <div v-else class="table-scroll">
        <table class="customer-table">
          <thead>
            <tr>
              <th>{{ t('app.workforce.full_name') }}</th>
              <th>{{ t('app.common.mobile_number') }}</th>
              <th>{{ t('app.workforce.role_specialization') }}</th>
              <th>{{ t('status.title') || 'Status' }}</th>
              <th class="actions-col">{{ t('app.common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="employee in filteredItems" :key="employee.id" class="clickable-row">
              <td>
                <div class="name-cell">
                  <div class="employee-avatar">{{ employee.name.slice(0, 1).toUpperCase() }}</div>
                  <div>
                    <strong class="name-link">{{ employee.name }}</strong>
                  </div>
                </div>
              </td>
              <td class="nowrap">
                <span v-if="employee.mobile_number">{{ employee.mobile_number }}</span>
                <span v-else class="text-muted">{{ t('app.workforce.no_phone') }}</span>
                <a
                  v-if="employee.mobile_number"
                  :href="getWhatsAppUrl(employee.mobile_number)"
                  target="_blank"
                  rel="noopener"
                  class="whatsapp-link"
                  :title="t('app.customer.chat_on_whatsapp')"
                >
                  <NavIcon name="WhatsApp" />
                </a>
              </td>
              <td>
                <span class="role-badge" :class="'role-badge--' + employee.employee_type">
                  {{ roleLabel(employee.employee_type) }}
                </span>
              </td>
              <td>
                <span class="status" :class="employee.active ? 'status--ready' : 'status--cancelled'">
                  {{ employee.active ? t('app.workforce.active') : t('app.workforce.inactive') }}
                </span>
              </td>
              <td class="actions-col nowrap">
                <button v-if="canManage" class="mini-btn" @click="openEdit(employee)">
                  {{ t('app.common.edit') }}
                </button>
                <button v-if="canManage" class="mini-btn" @click="toggle(employee)">
                  {{ employee.active ? t('app.workforce.deactivate') : t('app.workforce.activate') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ADD / EDIT TEAM MEMBER MODAL -->
    <div v-if="showForm" class="modal-backdrop" @click.self="cancelForm">
      <form class="modal-window form" style="max-width:30rem" @submit.prevent="save">
        <div class="modal-header">
          <h2>{{ editingEmployee ? t('app.common.edit') : t('app.workforce.add_team_member') }}</h2>
          <button type="button" class="modal-close-btn" :aria-label="t('app.common.close')" @click="cancelForm">✕</button>
        </div>
        <div class="modal-body">
          <label>{{ t('app.workforce.full_name') }} *
            <input v-model="form.name" :placeholder="t('app.common.name')" required />
          </label>
          <label>{{ t('app.common.mobile_number') }}
            <input v-model="form.mobile_number" :placeholder="t('app.common.01xxxxxxxxx')" />
          </label>
          <label>{{ t('app.workforce.role_specialization') }} *
            <select v-model="form.employee_type" required>
              <option value="karigar">{{ t('app.workforce.karigar_tailor') }}</option>
              <option value="staff">{{ t('app.workforce.staff_assistant') }}</option>
              <option value="manager">{{ t('app.workforce.manager_master_cutter') }}</option>
            </select>
          </label>
          <p v-if="error" class="error">{{ error }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="secondary" @click="cancelForm">{{ t('common.cancel') }}</button>
          <button class="primary">
            {{ editingEmployee ? t('common.save') : t('app.workforce.save_team_member') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.check-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
}

.check-label input {
  flex: none;
  width: 1.1rem;
  min-width: 0;
  min-height: 0;
  height: 1.1rem;
  padding: 0;
  accent-color: var(--accent, currentcolor);
}

.toolbar {
  flex-wrap: wrap;
  align-items: center;
}

.toolbar > input[type="text"],
.toolbar > input:not([type]) {
  flex: 1 1 14rem;
  width: auto;
  min-width: 0;
}

.check-label--end {
  margin-left: auto;
  white-space: nowrap;
}

.table-scroll {
  overflow-x: auto;
}

.customer-table {
  width: 100%;
  min-width: 45rem;
  border-collapse: collapse;
  font-size: 0.88rem;
}

.customer-table th,
.customer-table td {
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--line);
  text-align: left;
  vertical-align: middle;
}

.customer-table th {
  color: var(--muted);
  font-weight: 600;
  font-size: 0.78rem;
  white-space: nowrap;
}

.customer-table .actions-col {
  text-align: right;
}

.nowrap {
  white-space: nowrap;
}

.clickable-row {
  transition: background 120ms ease;
}

.clickable-row:hover {
  background: var(--paper);
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.name-link {
  font-weight: 700;
  color: var(--ink);
}

.employee-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.role-badge {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: var(--radius-sm);
  font-size: 0.8rem;
  font-weight: 600;
  background: var(--paper);
  border: 1px solid var(--line);
  text-transform: capitalize;
}

.role-badge--karigar {
  background: rgb(59 130 246 / 10%);
  color: #2563eb;
  border-color: rgb(59 130 246 / 25%);
}

.role-badge--manager {
  background: rgb(139 92 246 / 10%);
  color: #7c3aed;
  border-color: rgb(139 92 246 / 25%);
}

.role-badge--staff {
  background: rgb(100 116 139 / 10%);
  color: #475569;
  border-color: rgb(100 116 139 / 25%);
}

.whatsapp-link {
  color: #25d366;
  display: inline-flex;
  align-items: center;
  margin-left: 0.4rem;
  text-decoration: none;
  background: rgb(37 211 102 / 10%);
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
}

.whatsapp-link:hover {
  background: rgb(37 211 102 / 20%);
}

.text-muted {
  color: var(--muted);
  font-size: 0.85rem;
}
</style>
