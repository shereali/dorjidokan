<script setup lang="ts">
import { ref } from 'vue'

const open = ref(false)
</script>

<template>
  <Story title="Design system/Foundation">
    <Variant title="Controls and feedback">
      <section class="panel form o-stack">
        <h2>Workshop controls</h2>
        <label>Customer name<input value="Rahim Uddin"></label>
        <label>Status<select><option>Ready</option><option>In progress</option></select></label>
        <div class="tabs">
          <button class="primary" @click="open = !open">Primary action</button>
          <button>Secondary action</button>
        </div>
        <p class="success" role="status">{{ open ? 'Action completed.' : 'Ready for input.' }}</p>
        <p class="error" role="alert">Validation errors use this treatment.</p>
      </section>
    </Variant>
  </Story>
</template>
