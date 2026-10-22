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
    error.value = e?.data?.errors?.[0]?.message || t('app.workforce.could_not_load_the_team');
  } finally {
    loading.value = false;
  }
}

async function save() {
  error.value = "";
  try {
    await api.request("/employees", { method: "POST", body: form });
    toast.success(t('app.workforce.p0_added', { p0: form.name }));
    Object.assign(form, { name: "", mobile_number: "", employee_type: "karigar" });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('app.workforce.could_not_save_the_team_member');
  }
}

async function toggle(employee: Employee) {
  await api.request(`/employees/${employee.id}`, {
    method: "PATCH",
    body: { active: !employee.active },
  });
  toast.info(t(employee.active ? "app.workforce.member_deactivated" : "app.workforce.member_activated"));
  await load();
}

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

    <div class="workspace-grid">
      <!-- Add Member Form -->
      <form class="panel form" @submit.prevent="save">
        <h2>{{ t('app.workforce.add_team_member') }}</h2>
        <label>{{ t('app.workforce.full_name') }}
          <input v-model="form.name" :placeholder="t('app.common.name')" required />
        </label>
        <label>{{ t('app.common.mobile_number') }}
          <input v-model="form.mobile_number" :placeholder="t('app.common.01xxxxxxxxx')" />
        </label>
        <label>{{ t('app.workforce.role_specialization') }}
          <select v-model="form.employee_type">
            <option value="karigar">{{ t('app.workforce.karigar_tailor') }}</option>
            <option value="staff">{{ t('app.workforce.staff_assistant') }}</option>
            <option value="manager">{{ t('app.workforce.manager_master_cutter') }}</option>
          </select>
        </label>
        <p v-if="error" class="error">{{ error }}</p>
        <button class="primary">{{ t('app.workforce.save_team_member') }}</button>
      </form>

      <!-- Member Directory List -->
      <section class="panel">
        <h2>{{ t('app.common.team_members') }}</h2>
        <p v-if="loading">{{ t('app.workforce.loading_team') }}</p>
        <p v-else-if="!items.length" class="empty">{{ t('app.workforce.no_team_members_yet') }}</p>

        <div class="workforce-list">
          <div v-for="employee in items" :key="employee.id" class="record record--stock">
            <div style="display:flex;align-items:center;gap:0.75rem">
              <div class="employee-avatar">✂</div>
              <div>
                <strong>{{ employee.name }}</strong>
                <small style="display:block">📞 {{ employee.mobile_number || t('app.workforce.no_phone') }} · <span class="role-badge">{{ roleLabel(employee.employee_type) }}</span></small>
              </div>
            </div>
            <span class="status" :class="employee.active ? 'status--ready' : 'status--cancelled'">
              {{ employee.active ? t('app.workforce.active') : t('app.workforce.inactive') }}
            </span>
            <button class="mini-btn" @click="toggle(employee)">
              {{ employee.active ? t('app.workforce.deactivate') : t('app.workforce.activate') }}
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
