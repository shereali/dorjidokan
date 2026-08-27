<script setup lang="ts">interface I{public_id:string;sku:string;name:string;unit:string;movements_sum_quantity:number}const api=useTailorsApi(),items=ref<I[]>([]),error=ref(''),form=reactive({sku:'',name:'',unit:'yard',reorder_level:0});async function load(){try{items.value=(await api.request<{items:I[]}>('/inventory')).data.items}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not load inventory.'}}async function save(){try{await api.request('/inventory',{method:'POST',body:form});Object.assign(form,{sku:'',name:'',unit:'yard',reorder_level:0});await load()}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not save item.'}}async function adjust(item:I){const raw=prompt(`Adjustment for ${item.name} (${item.unit})`,'1');if(!raw)return;await api.request(`/inventory/${item.public_id}/adjust`,{method:'POST',body:{quantity:Number(raw),reason:'Manual stock count'}});await load()}onMounted(load)</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        STOCK LEDGER
      </p><h1>Inventory</h1><p>Every receipt, sale, rental and adjustment is auditable.</p>
    </div>
  </header><div class="workspace-grid">
    <form class="panel form" @submit.prevent="save">
      <h2>New stock item</h2><label>SKU<input v-model="form.sku" required></label><label>Name<input v-model="form.name" required></label><label>Unit<select v-model="form.unit"><option>yard</option><option>meter</option><option>piece</option></select></label><label>Reorder level<input v-model.number="form.reorder_level" type="number" min="0"></label><button class="primary">
        Add item
      </button>
    </form><section class="panel">
      <p v-if="error" class="error">
        {{ error }}
      </p><p v-if="!items.length" class="empty">
        No inventory items yet.
      </p><div v-for="item in items" :key="item.public_id" class="record record--stock">
        <span><strong>{{ item.name }}</strong><small>{{ item.sku }}</small></span><b>{{ item.movements_sum_quantity||0 }} {{ item.unit }}</b><button @click="adjust(item)">
          Adjust
        </button>
      </div>
    </section>
  </div>
</template>
