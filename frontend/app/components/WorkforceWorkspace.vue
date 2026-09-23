<script setup lang="ts">
interface Employee {
  id: string;
  name: string;
  mobile_number?: string;
  employee_type: "karigar" | "staff" | "manager";
  active: boolean;
}

const api = useTailorsApi();
const toast = useToast();

const items = ref<Employee[]>([]);
const loading = ref(true);
const error = ref("");
const form = reactive({
  name: "",
  mobile_number: "",
  employee_type: "karigar" as "karigar" | "staff" | "manager",
});

async function load() {
  loading.value = true;
  try {
    items.value = (await api.request<{ employees: Employee[] }>("/employees")).data.employees;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not load team.";
  } finally {
    loading.value = false;
  }
}

async function save() {
  error.value = "";
  try {
    await api.request("/employees", { method: "POST", body: form });
    toast.success(`Team member ${form.name} added.`);
    Object.assign(form, { name: "", mobile_number: "", employee_type: "karigar" });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not save team member.";
  }
}

async function toggle(employee: Employee) {
  await api.request(`/employees/${employee.id}`, {
    method: "PATCH",
    body: { active: !employee.active },
  });
  toast.info(`Team member ${employee.active ? "deactivated" : "activated"}.`);
  await load();
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">WORKFORCE & ARTISANS</p>
        <h1>Karigars & Workshop Team</h1>
        <p>Manage masters, cutters, sewing karigars and workshop staff members.</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Add Member Form -->
      <form class="panel form" @submit.prevent="save">
        <h2>Add Team Member (কারিগর / স্টাফ যোগ করুন)</h2>
        <label>Full Name *
          <input v-model="form.name" placeholder="Artisan name" required />
        </label>
        <label>Mobile Number
          <input v-model="form.mobile_number" placeholder="01XXXXXXXXX" />
        </label>
        <label>Role / Specialization *
          <select v-model="form.employee_type">
            <option value="karigar">Karigar / Tailor (কারিগর / দর্জি)</option>
            <option value="staff">Staff / Assistant (সহকারী)</option>
            <option value="manager">Manager / Master Cutter (মাস্টার / কাটিং মাস্টার)</option>
          </select>
        </label>
        <p v-if="error" class="error">{{ error }}</p>
        <button class="primary">Save Team Member</button>
      </form>

      <!-- Member Directory List -->
      <section class="panel">
        <h2>Active Workshop Workforce</h2>
        <p v-if="loading">Loading team members…</p>
        <p v-else-if="!items.length" class="empty">No team members registered yet.</p>

        <div class="workforce-list">
          <div v-for="employee in items" :key="employee.id" class="record record--stock">
            <div style="display:flex;align-items:center;gap:0.75rem">
              <div class="employee-avatar">✂</div>
              <div>
                <strong>{{ employee.name }}</strong>
                <small style="display:block">📞 {{ employee.mobile_number || 'No phone' }} · <span class="role-badge">{{ employee.employee_type }}</span></small>
              </div>
            </div>
            <span class="status" :class="employee.active ? 'status--ready' : 'status--cancelled'">
              {{ employee.active ? 'Active' : 'Inactive' }}
            </span>
            <button class="mini-btn" @click="toggle(employee)">
              {{ employee.active ? 'Deactivate' : 'Activate' }}
            </button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.workforce-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.employee-avatar {
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 50%;
  background: var(--paper);
  border: 1px solid var(--line);
  display: grid;
  place-items: center;
  font-size: 1.1rem;
}

.role-badge {
  text-transform: capitalize;
  font-weight: 600;
  color: var(--primary);
}
</style>
