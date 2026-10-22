<script setup lang="ts">
interface RoleRow {
  key: string;
  name: string;
  name_bn: string;
  locked: boolean;
  customized: boolean;
  permissions: string[];
}

interface MatrixPayload {
  roles: RoleRow[];
  groups: Record<string, string[]>;
  owner_only: string[];
}

const api = useTailorsApi();
const toast = useToast();
const { locale } = useTailorsI18n();

const roles = ref<RoleRow[]>([]);
const groups = ref<Record<string, string[]>>({});
const ownerOnly = ref<string[]>([]);
const draft = reactive<Record<string, Set<string>>>({});
const saving = ref<string | null>(null);
const loading = ref(true);
const canEdit = computed(() => api.can('roles.manage'));

const lang = computed<'bn' | 'en'>(() => (locale.value === 'bn' ? 'bn' : 'en'));
const editableRoles = computed(() => roles.value.filter((r) => !r.locked));
const lockedRoles = computed(() => roles.value.filter((r) => r.locked));

function isDirty(role: RoleRow) {
  const current = draft[role.key];
  if (!current) return false;
  return current.size !== role.permissions.length || role.permissions.some((p) => !current.has(p));
}

function isLocked(role: RoleRow, permission: string) {
  return role.locked || !canEdit.value || ownerOnly.value.includes(permission);
}

function has(role: RoleRow, permission: string) {
  return role.locked || draft[role.key]?.has(permission) === true;
}

function toggle(role: RoleRow, permission: string) {
  if (isLocked(role, permission)) return;
  const set = draft[role.key] ?? (draft[role.key] = new Set(role.permissions));
  if (set.has(permission)) set.delete(permission);
  else set.add(permission);
}

function toggleGroup(role: RoleRow, group: string) {
  const keys = (groups.value[group] ?? []).filter((p) => !isLocked(role, p));
  if (!keys.length) return;
  const set = draft[role.key] ?? (draft[role.key] = new Set(role.permissions));
  const allOn = keys.every((p) => set.has(p));
  keys.forEach((p) => (allOn ? set.delete(p) : set.add(p)));
}

function load(payload: MatrixPayload) {
  roles.value = payload.roles;
  groups.value = payload.groups;
  ownerOnly.value = payload.owner_only;
  payload.roles.forEach((r) => (draft[r.key] = new Set(r.permissions)));
}

async function fetchMatrix() {
  loading.value = true;
  try {
    load((await api.request<MatrixPayload>('/roles')).data);
  } catch {
    toast.error(lang.value === 'bn' ? 'রোল ম্যাট্রিক্স লোড করা যায়নি।' : 'Could not load the role matrix.');
  } finally {
    loading.value = false;
  }
}

async function save(role: RoleRow) {
  saving.value = role.key;
  try {
    await api.request(`/roles/${role.key}`, { method: 'PUT', body: { permissions: [...(draft[role.key] ?? [])] } });
    toast.success(lang.value === 'bn' ? `${role.name_bn} রোল সংরক্ষিত হয়েছে।` : `${role.name} role saved.`);
    await fetchMatrix();
    await api.refreshMe();
  } catch {
    toast.error(lang.value === 'bn' ? 'রোল সংরক্ষণ করা যায়নি।' : 'Could not save this role.');
  } finally {
    saving.value = null;
  }
}

async function reset(role: RoleRow) {
  saving.value = role.key;
  try {
    await api.request(`/roles/${role.key}`, { method: 'DELETE' });
    toast.info(lang.value === 'bn' ? `${role.name_bn} ডিফল্টে ফিরেছে।` : `${role.name} restored to defaults.`);
    await fetchMatrix();
    await api.refreshMe();
  } catch {
    toast.error(lang.value === 'bn' ? 'রিসেট করা যায়নি।' : 'Could not reset this role.');
  } finally {
    saving.value = null;
  }
}

const label = (k: string) => PERMISSION_LABELS[k]?.[lang.value] ?? k;

onMounted(fetchMatrix);
</script>

<template>
  <section class="panel matrix" aria-labelledby="matrix-title">
    <div class="matrix__head">
      <div>
        <h2 id="matrix-title">{{ lang === 'bn' ? 'রোল ও পারমিশন ম্যাট্রিক্স' : 'Role & permission matrix' }}</h2>
        <p class="matrix__help">
          {{ lang === 'bn'
            ? 'কোন রোল কোন মেনু দেখবে ও কোন কাজ করতে পারবে তা এখানে ঠিক করুন। মালিক সবসময় সব পারে; বিলিং, টিম, রোল ও ওয়েবহুক শুধু মালিকের।'
            : 'Choose which menus each role sees and what it can do. Owners always have everything; billing, team, roles and webhooks stay owner-only.' }}
        </p>
      </div>
    </div>

    <p v-if="loading">{{ lang === 'bn' ? 'লোড হচ্ছে…' : 'Loading…' }}</p>

    <div v-else class="matrix__scroll" tabindex="0" role="region" :aria-label="lang === 'bn' ? 'পারমিশন টেবিল' : 'Permission table'">
      <table>
        <thead>
          <tr>
            <th scope="col" class="matrix__perm">{{ lang === 'bn' ? 'পারমিশন' : 'Permission' }}</th>
            <th v-for="role in [...lockedRoles, ...editableRoles]" :key="role.key" scope="col">
              <span class="matrix__role">{{ lang === 'bn' ? role.name_bn : role.name }}</span>
              <small v-if="role.customized" class="matrix__tag">{{ lang === 'bn' ? 'কাস্টম' : 'Custom' }}</small>
            </th>
          </tr>
        </thead>
        <tbody v-for="(keys, group) in groups" :key="group">
          <tr class="matrix__group">
            <th scope="rowgroup" class="matrix__perm">{{ GROUP_LABELS[group]?.[lang] ?? group }}</th>
            <td v-for="role in [...lockedRoles, ...editableRoles]" :key="role.key">
              <button
                v-if="!role.locked && canEdit"
                type="button"
                class="matrix__all"
                :aria-label="`${lang === 'bn' ? 'সব নির্বাচন' : 'Toggle all'}: ${GROUP_LABELS[group]?.[lang]} / ${lang === 'bn' ? role.name_bn : role.name}`"
                @click="toggleGroup(role, String(group))"
              >
                {{ lang === 'bn' ? 'সব' : 'All' }}
              </button>
            </td>
          </tr>
          <tr v-for="permission in keys" :key="permission">
            <th scope="row" class="matrix__perm">{{ label(permission) }}</th>
            <td v-for="role in [...lockedRoles, ...editableRoles]" :key="role.key">
              <input
                type="checkbox"
                :checked="has(role, permission)"
                :disabled="isLocked(role, permission)"
                :aria-label="`${label(permission)} — ${lang === 'bn' ? role.name_bn : role.name}`"
                @change="toggle(role, permission)"
              />
            </td>
          </tr>
        </tbody>
        <tfoot v-if="canEdit">
          <tr>
            <th scope="row" class="matrix__perm">{{ lang === 'bn' ? 'সংরক্ষণ' : 'Save' }}</th>
            <td v-for="role in [...lockedRoles, ...editableRoles]" :key="role.key">
              <template v-if="!role.locked">
                <button type="button" class="primary matrix__save" :disabled="!isDirty(role) || saving === role.key" @click="save(role)">
                  {{ lang === 'bn' ? 'সেভ' : 'Save' }}
                </button>
                <button v-if="role.customized" type="button" class="mini-btn" :disabled="saving === role.key" @click="reset(role)">
                  {{ lang === 'bn' ? 'ডিফল্ট' : 'Reset' }}
                </button>
              </template>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </section>
</template>

<style scoped>
.matrix { margin-top: 1.5rem; }
.matrix__help { margin: 0.25rem 0 1rem; color: var(--ink-soft, inherit); font-size: 0.85rem; }
.matrix__scroll { overflow-x: auto; max-width: 100%; }
.matrix table { border-collapse: collapse; min-width: 44rem; width: 100%; font-size: 0.85rem; }
.matrix th, .matrix td { padding: 0.45rem 0.6rem; text-align: center; border-bottom: 1px solid var(--line); vertical-align: middle; }
.matrix thead th { position: sticky; top: 0; background: var(--surface, var(--paper)); z-index: 1; }
.matrix__perm { text-align: left; position: sticky; left: 0; background: var(--surface, var(--paper)); min-width: 13rem; font-weight: 500; }
.matrix__group .matrix__perm { font-weight: 700; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em; }
.matrix__group td, .matrix__group th { background: color-mix(in srgb, var(--paper) 70%, transparent); }
.matrix__role { display: block; font-weight: 700; }
.matrix__tag { display: inline-block; font-size: 0.65rem; padding: 0 0.35rem; border-radius: 99px; border: 1px solid var(--line); }
.matrix input[type="checkbox"] { width: 1.15rem; height: 1.15rem; cursor: pointer; }
.matrix input[type="checkbox"]:disabled { cursor: not-allowed; opacity: 0.55; }
.matrix__all { font-size: 0.7rem; padding: 0.1rem 0.5rem; border: 1px solid var(--line); background: transparent; border-radius: 99px; cursor: pointer; min-height: 1.5rem; }
.matrix__save { padding: 0.35rem 0.7rem; font-size: 0.8rem; }
</style>
