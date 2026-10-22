<script setup lang="ts">
interface AuditRow {
  id: string;
  action: string;
  tenant: { id: string; name: string } | null;
  actor: string | null;
  ip_address: string | null;
  created_at: string;
}

const api = useTailorsApi();
const toast = useToast();
const { dateTime } = usePlatformFormat();
const { t } = useTailorsI18n();

const rows = ref<AuditRow[]>([]);
const loading = ref(true);
const meta = reactive({ total: 0, page: 1, last_page: 1 });
const action = ref("");
const groups = ["tenant.", "subscription.", "plan.", "billing.", "role.", "voice_token."];

async function load(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "25" });
    if (action.value) params.set("action", action.value);
    const res = await api.request<{ items: AuditRow[] }>(`/super-admin/audits?${params}`);
    rows.value = res.data.items;
    Object.assign(meta, res.meta as typeof meta);
  } catch {
    toast.error(t('app.super_admin_audit.could_not_load_the_audit_trail'));
  } finally {
    loading.value = false;
  }
}

onMounted(() => load(1));
</script>

<template>
  <div>
    <div class="filters">
      <label>{{ t('app.super_admin_audit.event_type') }}
        <select v-model="action" @change="load(1)">
          <option value="">{{ t('app.super_admin_audit.all_events') }}</option>
          <option v-for="g in groups" :key="g" :value="g">{{ g }}*</option>
        </select>
      </label>
    </div>
    <div class="table-responsive">
      <table>
        <thead>
          <tr><th>{{ t('app.super_admin_audit.when') }}</th><th>{{ t('app.super_admin_audit.event') }}</th><th>{{ t('app.common.workshop') }}</th><th>{{ t('app.super_admin_audit.actor') }}</th><th>IP</th></tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="5">{{ t('app.common.loading') }}</td></tr>
          <tr v-else-if="!rows.length"><td colspan="5">{{ t('app.super_admin_audit.no_events_yet') }}</td></tr>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ dateTime(row.created_at) }}</td>
            <td><code>{{ row.action }}</code></td>
            <td>{{ row.tenant?.name ?? "—" }}</td>
            <td>{{ row.actor ?? t('app.super_admin_audit.system') }}</td>
            <td>{{ row.ip_address ?? "—" }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <nav v-if="meta.last_page > 1" class="pager" :aria-label="t('app.common.pagination')">
      <button type="button" class="mini-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">‹ {{ t('app.common.prev') }}</button>
      <span>{{ meta.page }} / {{ meta.last_page }}</span>
      <button type="button" class="mini-btn" :disabled="meta.page >= meta.last_page" @click="load(meta.page + 1)">{{ t('app.common.next') }} ›</button>
    </nav>
  </div>
</template>

<style scoped>
.filters { margin-bottom: 0.75rem; }
.filters label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.8rem; font-weight: 600; max-width: 16rem; }
.pager { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 0.85rem; }
</style>
