<script setup lang="ts">
interface Part { public_id:string; name:string; unit:'inch'|'cm'; required:boolean }
interface Garment { public_id:string; name:string; slug:string; active:boolean; parts:Part[] }
const api=useTailorsApi(),items=ref<Garment[]>([]),loading=ref(true),error=ref(''),form=reactive({name:'',slug:'',parts:''}),partForms=reactive<Record<string,{name:string;unit:'inch'|'cm'}>>({})
async function load(){loading.value=true;try{items.value=(await api.request<{garments:Garment[]}>('/garments')).data.garments;for(const garment of items.value)partForms[garment.public_id]??={name:'',unit:'inch'}}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not load garments.'}finally{loading.value=false}}
async function save(){error.value='';const parts=form.parts.split(',').map(name=>name.trim()).filter(Boolean).map(name=>({name,unit:'inch',required:true}));try{await api.request('/garments',{method:'POST',body:{name:form.name,slug:form.slug||undefined,parts}});Object.assign(form,{name:'',slug:'',parts:''});await load()}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not save garment.'}}
async function toggle(garment:Garment){await api.request(`/garments/${garment.public_id}`,{method:'PATCH',body:{active:!garment.active}});await load()}
async function addPart(garment:Garment){const part=partForms[garment.public_id];if(!part.name)return;await api.request(`/garments/${garment.public_id}/parts`,{method:'POST',body:{...part,required:true}});part.name='';await load()}
async function removePart(garment:Garment,part:Part){error.value='';try{await api.request(`/garments/${garment.public_id}/parts/${part.public_id}`,{method:'DELETE'});await load()}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not remove measurement part.'}}
onMounted(load)
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        DESIGN CATALOG
      </p><h1>Garments</h1><p>Configure garment types and their measurement sequence.</p>
    </div>
  </header><div class="workspace-grid">
    <form class="panel form" @submit.prevent="save">
      <h2>Add garment</h2><label>Name<input v-model="form.name" required></label><label>Slug<input v-model="form.slug" placeholder="Generated from name"></label><label>Measurement parts<textarea v-model="form.parts" required placeholder="Body length, Chest, Sleeve, Collar" /></label><p class="hint">
        Separate measurement names with commas. New parts default to inches.
      </p><p v-if="error" class="error" role="alert">
        {{ error }}
      </p><button class="primary">
        Save garment
      </button>
    </form><section class="catalog">
      <p v-if="loading">
        Loading…
      </p><p v-else-if="!items.length" class="panel empty">
        No garments configured.
      </p><article v-for="garment in items" :key="garment.public_id" class="panel garment-card">
        <div class="record record--stock">
          <span><strong>{{ garment.name }}</strong><small>{{ garment.slug }} · {{ garment.parts.length }} parts</small></span><span :class="garment.active?'success':'error'">{{ garment.active?'Active':'Inactive' }}</span><button @click="toggle(garment)">
            {{ garment.active?'Disable':'Enable' }}
          </button>
        </div><ol class="part-list">
          <li v-for="part in garment.parts" :key="part.public_id">
            <span>{{ part.name }}</span><small>{{ part.unit }} · {{ part.required?'required':'optional' }}</small><button class="danger-link" :aria-label="`Remove ${part.name}`" @click="removePart(garment,part)">
              Remove
            </button>
          </li>
        </ol><form class="inline-form" @submit.prevent="addPart(garment)">
          <label><span class="sr-only">New part name for {{ garment.name }}</span><input v-model="partForms[garment.public_id].name" :aria-label="`New part name for ${garment.name}`" placeholder="New measurement part"></label><select v-model="partForms[garment.public_id].unit" :aria-label="`Unit for ${garment.name}`">
            <option>inch</option><option>cm</option>
          </select><button>Add part</button>
        </form>
      </article>
    </section>
  </div>
</template>
