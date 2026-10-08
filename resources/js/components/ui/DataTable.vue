<script setup>
defineProps({ columns: { type: Array, default: () => [] }, rows: { type: Array, default: () => [] } });
</script>

<template>
    <div tabindex="0" class="overflow-x-auto rounded-2xl border border-[#e9e2e5] bg-white shadow-[0_6px_24px_rgba(36,20,28,.04)] focus-visible:outline-3 focus-visible:outline-brand"><table class="min-w-full text-start text-sm"><thead class="bg-[#faf8f8] text-slate-600"><tr><th v-for="column in columns" :key="column.key" scope="col" class="whitespace-nowrap border-b border-[#ebe5e7] px-5 py-4 text-start font-extrabold">{{ column.label }}</th></tr></thead><tbody class="divide-y divide-[#f0ecee]"><tr v-for="(row, index) in rows" :key="row.id ?? index" class="transition-colors hover:bg-[#fdf9fa]"><td v-for="column in columns" :key="column.key" class="px-5 py-4"><slot :name="column.key" :row="row">{{ row[column.key] }}</slot></td></tr></tbody></table></div>
</template>
