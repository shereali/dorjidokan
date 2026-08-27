<script setup lang="ts">import GarmentPrototypeBuilder from './GarmentPrototypeBuilder.vue';const parts=[{id:'body',name:'Body'},{id:'chest',name:'Chest'},{id:'sleeve',name:'Sleeve'},{id:'collar',name:'Collar'}];const partial=[{part_id:'body',value:42,unit:'inch'},{part_id:'sleeve',value:24,unit:'inch'}]</script>
<template>
  <Story title="Garment/Prototype Builder">
    <Variant title="Partial measurement">
      <div class="panel">
        <GarmentPrototypeBuilder garment-name="Panjabi" :parts="parts" :measurements="partial" />
      </div>
    </Variant><Variant title="Empty">
      <div class="panel">
        <GarmentPrototypeBuilder garment-name="Panjabi" :parts="parts" :measurements="[]" />
      </div>
    </Variant>
  </Story>
</template>
