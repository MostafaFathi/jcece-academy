<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, default: '' } });
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4 pb-1"><div class="max-w-3xl border-s-[3px] border-accent ps-4"><h1 class="text-2xl font-black tracking-tight text-[#281d23] sm:text-[2rem]">{{ title }}</h1><p v-if="description" class="mt-2 max-w-2xl text-sm leading-7 text-slate-600">{{ description }}</p></div><div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div></div>
</template>
