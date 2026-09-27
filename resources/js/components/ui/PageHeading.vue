<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, default: '' } });
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">{{ title }}</h1><p v-if="description" class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ description }}</p></div><slot name="actions" /></div>
</template>
