<script setup lang="ts">
const guides = [
  ["First order", "Add a customer, open Orders, select a garment and delivery date, then save each measurement in sequence."],
  ["Production", "Assign an active karigar, move the order through in progress and ready, and print its workshop label when needed."],
  ["Fabric sale", "Receive stock in Operations, add multiple lines to the sale cart, record payment, then print the generated invoice."],
  ["Customer dues", "Open Accounting, select the customer, review the dated running balance, and post a documented charge or credit adjustment."],
  ["Daily control", "Use Reports for date presets, profitability, receivables, overdue orders, low stock, CSV exports, and emailed summaries."],
  ["Security", "Administrators should enable two-factor authentication in Settings and store recovery codes away from the browser."],
];
</script>

<template>
  <header><div><p class="eyebrow">WORKSHOP GUIDE</p><h1>Help</h1><p>Short instructions for the workflows used every day.</p></div></header>
  <section class="panel"><article v-for="guide in guides" :key="guide[0]" class="record"><strong>{{ guide[0] }}</strong><span>{{ guide[1] }}</span></article></section>
</template>
