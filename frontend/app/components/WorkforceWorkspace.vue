<script setup lang="ts">
const { tr } = usePlatformFormat();
interface Employee {
  id: string;
  name: string;
  mobile_number?: string;
  employee_type: "karigar" | "staff" | "manager";
  active: boolean;
}

const api = useTailorsApi();
const toast = useToast();
const roleLabels: Record<string, [string, string]> = {
  karigar: ['Karigar', 'কারিগর'],
  staff: ['Staff', 'স্টাফ'],
  manager: ['Master / manager', 'মাস্টার / ম্যানেজার'],
  master_cutter: ['Master cutter', 'কাটিং মাস্টার'],
  finisher: ['Finisher', 'ফিনিশার'],
};
const roleLabel = (type: string) => (roleLabels[type] ? tr(roleLabels[type][0], roleLabels[type][1]) : type);

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
    error.value = e?.data?.errors?.[0]?.message || tr("Could not load the team.", "টিম লোড করা যায়নি।");
  } finally {
    loading.value = false;
  }
}

async function save() {
  error.value = "";
  try {
    await api.request("/employees", { method: "POST", body: form });
    toast.success(tr(`${form.name} added.`, `${form.name} যোগ হয়েছে।`));
    Object.assign(form, { name: "", mobile_number: "", employee_type: "karigar" });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || tr("Could not save the team member.", "সদস্য সংরক্ষণ করা যায়নি।");
  }
}

async function toggle(employee: Employee) {
  await api.request(`/employees/${employee.id}`, {
    method: "PATCH",
    body: { active: !employee.active },
  });
  toast.info(tr(`Team member ${employee.active ? "deactivated" : "activated"}.`, `সদস্য ${employee.active ? "নিষ্ক্রিয়" : "সক্রিয়"} করা হয়েছে।`));
  await load();
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ tr("TEAM", "টিম") }}</p>
        <h1>{{ tr("Karigars & workshop team", "কারিগর ও ওয়ার্কশপ টিম") }}</h1>
        <p>{{ tr("Manage masters, cutters, karigars and staff.", "মাস্টার, কাটার, কারিগর ও স্টাফ পরিচালনা করুন।") }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Add Member Form -->
      <form class="panel form" @submit.prevent="save">
        <h2>{{ tr("Add team member", "কারিগর / স্টাফ যোগ করুন") }}</h2>
        <label>{{ tr("Full name *", "পুরো নাম *") }}
          <input v-model="form.name" :placeholder="tr('Name', 'নাম')" required />
        </label>
        <label>{{ tr("Mobile number", "মোবাইল নম্বর") }}
          <input v-model="form.mobile_number" :placeholder="tr('01XXXXXXXXX', '০১XXXXXXXXX')" />
        </label>
        <label>{{ tr("Role / specialization *", "দায়িত্ব / দক্ষতা *") }}
          <select v-model="form.employee_type">
            <option value="karigar">{{ tr("Karigar / tailor", "কারিগর / দর্জি") }}</option>
            <option value="staff">{{ tr("Staff / assistant", "স্টাফ / সহকারী") }}</option>
            <option value="manager">{{ tr("Manager / master cutter", "ম্যানেজার / মাস্টার কাটার") }}</option>
          </select>
        </label>
        <p v-if="error" class="error">{{ error }}</p>
        <button class="primary">{{ tr("Save team member", "সদস্য সংরক্ষণ") }}</button>
      </form>

      <!-- Member Directory List -->
      <section class="panel">
        <h2>{{ tr("Team members", "টিমের সদস্য") }}</h2>
        <p v-if="loading">{{ tr("Loading team…", "টিম লোড হচ্ছে…") }}</p>
        <p v-else-if="!items.length" class="empty">{{ tr("No team members yet.", "এখনো কোনো সদস্য নেই।") }}</p>

        <div class="workforce-list">
          <div v-for="employee in items" :key="employee.id" class="record record--stock">
            <div style="display:flex;align-items:center;gap:0.75rem">
              <div class="employee-avatar">✂</div>
              <div>
                <strong>{{ employee.name }}</strong>
                <small style="display:block">📞 {{ employee.mobile_number || tr('No phone', 'ফোন নেই') }} · <span class="role-badge">{{ roleLabel(employee.employee_type) }}</span></small>
              </div>
            </div>
            <span class="status" :class="employee.active ? 'status--ready' : 'status--cancelled'">
              {{ employee.active ? tr('Active', 'সক্রিয়') : tr('Inactive', 'নিষ্ক্রিয়') }}
            </span>
            <button class="mini-btn" @click="toggle(employee)">
              {{ employee.active ? tr('Deactivate', 'নিষ্ক্রিয় করুন') : tr('Activate', 'সক্রিয় করুন') }}
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
