<script setup lang="ts">
interface Member { id: string; name: string; email: string; role: string }
const api = useTailorsApi()
const members = ref<Member[]>([])
const message = ref("")
const error = ref("")
const settings = reactive({ name: "", default_locale: "bn", currency: "BDT", preferences: { order_prefix: "ORD", default_delivery_days: 7, low_stock_alerts: true, order_ready_notifications: true } })
const member = reactive({ name: "", email: "", role: "staff" })
const twoFactor = reactive({ enabled: false, pending: false, secret: "", otpauth_url: "", code: "", recovery_codes: [] as string[], password: "" })
const deletion = ref<{id:string;scheduled_for:string}|null>(null)
const deletionConfirmation = reactive({ tenant_slug: "", password: "" })
async function load() {
  const result = await api.request<{ settings: typeof settings }>("/settings")
  Object.assign(settings, result.data.settings)
  if (api.role.value === "admin") members.value = (await api.request<{ items: Member[] }>("/members")).data.items
  Object.assign(twoFactor, await api.request<{ enabled: boolean; pending: boolean }>("/auth/two-factor").then(result => result.data))
  deletion.value = (await api.request<{deletion:{id:string;scheduled_for:string}|null}>("/tenant-deletion")).data.deletion
}
async function saveSettings() {
  await api.request("/settings", { method: "PATCH", body: { name: settings.name, default_locale: settings.default_locale, currency: settings.currency, settings: settings.preferences } })
  message.value = "Workshop settings saved."
}
async function addMember() {
  error.value = ""
  try {
    await api.request("/members", { method: "POST", body: member })
    Object.assign(member, { name: "", email: "", role: "staff" })
    message.value = "Invitation sent. The staff member can set their own password from the emailed link."
    await load()
  } catch (exception: any) { error.value = exception?.data?.errors?.[0]?.message || "Could not add member." }
}
async function changeRole(item: Member) { await api.request(`/members/${item.id}`, { method: "PATCH", body: { role: item.role } }) }
async function removeMember(item: Member) { await api.request(`/members/${item.id}`, { method: "DELETE" }); await load() }
async function setupTwoFactor() { Object.assign(twoFactor, (await api.request<{ secret: string; otpauth_url: string }>("/auth/two-factor/setup", { method: "POST" })).data); twoFactor.pending = true }
async function confirmTwoFactor() { const result = await api.request<{ enabled: boolean; recovery_codes: string[] }>("/auth/two-factor/confirm", { method: "POST", body: { code: twoFactor.code } }); twoFactor.enabled = true; twoFactor.pending = false; twoFactor.recovery_codes = result.data.recovery_codes; api.hasTwoFactor.value = true }
async function disableTwoFactor() { await api.request("/auth/two-factor", { method: "DELETE", body: { password: twoFactor.password } }); Object.assign(twoFactor, { enabled: false, pending: false, secret: "", recovery_codes: [], password: "" }); api.hasTwoFactor.value = false }
async function scheduleDeletion(){await api.request("/tenant-deletion",{method:"POST",body:deletionConfirmation});Object.assign(deletionConfirmation,{tenant_slug:"",password:""});await load()}
async function cancelDeletion(){if(!deletion.value)return;await api.request(`/tenant-deletion/${deletion.value.id}`,{method:"DELETE"});await load()}
onMounted(load)
</script>

<template>
  <header>
    <div>
      <p class="eyebrow">
        WORKSHOP ADMINISTRATION
      </p><h1>Settings</h1><p>Business defaults and staff access.</p>
    </div>
  </header>
  <p v-if="message" class="success" role="status">
    {{ message }}
  </p>
  <div class="workspace-grid">
    <form class="panel form" @submit.prevent="saveSettings">
      <h2>Workshop preferences</h2>
      <label>Business name<input v-model="settings.name" required></label>
      <label>Language<select v-model="settings.default_locale"><option value="bn">বাংলা</option><option value="en">English</option></select></label>
      <label>Currency<input v-model="settings.currency" minlength="3" maxlength="3" required></label>
      <label>Order prefix<input v-model="settings.preferences.order_prefix"></label>
      <label>Default delivery days<input v-model.number="settings.preferences.default_delivery_days" type="number" min="0"></label>
      <label><input v-model="settings.preferences.low_stock_alerts" type="checkbox"> Low-stock alerts</label>
      <label><input v-model="settings.preferences.order_ready_notifications" type="checkbox"> Order-ready notifications</label>
      <button class="primary">
        Save settings
      </button>
    </form>
    <section v-if="api.role.value === 'admin'" class="panel">
      <form class="form" @submit.prevent="addMember">
        <h2>Invite staff</h2>
        <label>Name<input v-model="member.name" required></label><label>Email<input v-model="member.email" type="email" required></label>
        <label>Role<select v-model="member.role"><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Admin</option></select></label>
        <p v-if="error" class="error" role="alert">
          {{ error }}
        </p><button class="primary">
          Send invitation
        </button>
      </form>
      <article v-for="item in members" :key="item.id" class="record record--stock">
        <span><strong>{{ item.name }}</strong><small>{{ item.email }}</small></span>
        <select v-model="item.role" :aria-label="`Role for ${item.name}`" @change="changeRole(item)">
          <option value="staff">
            Staff
          </option><option value="manager">
            Manager
          </option><option value="admin">
            Admin
          </option>
        </select>
        <button @click="removeMember(item)">
          Remove
        </button>
      </article>
    </section>
    <section class="panel">
      <h2>Two-factor authentication</h2>
      <p>{{ twoFactor.enabled ? "Authenticator protection is enabled." : "Protect administrative access with a TOTP authenticator." }}</p>
      <button v-if="!twoFactor.enabled && !twoFactor.pending" class="primary" @click="setupTwoFactor">
        Set up authenticator
      </button>
      <form v-if="twoFactor.pending" class="form" @submit.prevent="confirmTwoFactor">
        <p>Enter this secret in your authenticator app:</p><code>{{ twoFactor.secret }}</code><a :href="twoFactor.otpauth_url">Open authenticator link</a>
        <label>Six-digit code<input v-model="twoFactor.code" inputmode="numeric" required></label><button class="primary">
          Confirm 2FA
        </button>
      </form>
      <div v-if="twoFactor.recovery_codes.length">
        <h3>Recovery codes</h3><p>Save these once in a secure password manager.</p><code v-for="code in twoFactor.recovery_codes" :key="code">{{ code }}</code>
      </div>
      <form v-if="twoFactor.enabled" class="form" @submit.prevent="disableTwoFactor">
        <label>Current password<input v-model="twoFactor.password" type="password" required></label><button>Disable 2FA</button>
      </form>
    </section>
    <section v-if="api.role.value === 'admin'" class="panel">
      <h2>Data portability and offboarding</h2><p>A full JSON export is generated before deletion. Deletion has a seven-day cancellation window.</p>
      <div v-if="deletion">
        <p class="error">
          Deletion scheduled for {{ new Date(deletion.scheduled_for).toLocaleString() }}.
        </p><button @click="cancelDeletion">
          Cancel deletion
        </button>
      </div>
      <form v-else class="form" @submit.prevent="scheduleDeletion">
        <label>Type workshop slug<input v-model="deletionConfirmation.tenant_slug" required></label><label>Current password<input v-model="deletionConfirmation.password" type="password" required></label><button>Schedule permanent deletion</button>
      </form>
    </section>
  </div>
</template>
