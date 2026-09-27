<script setup>
defineProps({ columns: { type: Array, default: () => [] }, rows: { type: Array, default: () => [] } });
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"><table class="min-w-full text-start text-sm"><thead class="bg-slate-50 text-slate-600"><tr><th v-for="column in columns" :key="column.key" scope="col" class="px-4 py-3 text-start font-bold">{{ column.label }}</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(row, index) in rows" :key="row.id ?? index"><td v-for="column in columns" :key="column.key" class="px-4 py-3"><slot :name="column.key" :row="row">{{ row[column.key] }}</slot></td></tr></tbody></table></div>
</template>
