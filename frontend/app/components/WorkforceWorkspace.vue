<script setup lang="ts">
interface Employee{id:string;name:string;mobile_number?:string;employee_type:'karigar'|'staff'|'manager';active:boolean}
const api=useTailorsApi(),items=ref<Employee[]>([]),loading=ref(true),error=ref(''),form=reactive({name:'',mobile_number:'',employee_type:'karigar'})
async function load(){loading.value=true;try{items.value=(await api.request<{employees:Employee[]}>('/employees')).data.employees}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not load team.'}finally{loading.value=false}}
async function save(){error.value='';try{await api.request('/employees',{method:'POST',body:form});Object.assign(form,{name:'',mobile_number:'',employee_type:'karigar'});await load()}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not save team member.'}}
async function toggle(employee:Employee){await api.request(`/employees/${employee.id}`,{method:'PATCH',body:{active:!employee.active}});await load()}
onMounted(load)
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        WORKFORCE
      </p><h1>Karigars & staff</h1><p>Manage production team members and availability.</p>
    </div>
  </header><div class="workspace-grid">
    <form class="panel form" @submit.prevent="save">
      <h2>Add team member</h2><label>Name<input v-model="form.name" required></label><label>Mobile<input v-model="form.mobile_number" placeholder="01XXXXXXXXX"></label><label>Role<select v-model="form.employee_type"><option value="karigar">Karigar</option><option value="staff">Staff</option><option value="manager">Manager</option></select></label><p v-if="error" class="error" role="alert">
        {{ error }}
      </p><button class="primary">
        Save team member
      </button>
    </form><section class="panel">
      <p v-if="loading">
        Loading…
      </p><p v-else-if="!items.length" class="empty">
        No team members configured.
      </p><div v-for="employee in items" :key="employee.id" class="record record--stock">
        <span><strong>{{ employee.name }}</strong><small>{{ employee.mobile_number||'No mobile' }} · {{ employee.employee_type }}</small></span><span :class="employee.active?'success':'error'">{{ employee.active?'Active':'Inactive' }}</span><button @click="toggle(employee)">
          {{ employee.active?'Deactivate':'Activate' }}
        </button>
      </div>
    </section>
  </div>
</template>
