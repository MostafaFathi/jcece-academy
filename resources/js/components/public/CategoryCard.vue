<script setup>
defineProps({ category: { type: Object, required: true } });
</script>

<template>
    <RouterLink :to="{ name: 'courses.index', query: { category: category.slug } }" class="group relative min-h-44 overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-brand/25 hover:shadow-xl focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent"><div class="absolute -end-8 -top-8 size-28 rounded-full bg-brand-soft transition group-hover:scale-125" /><div class="relative flex h-full flex-col justify-between gap-8"><div class="grid size-12 place-items-center rounded-2xl bg-brand text-xl font-black text-white shadow-lg shadow-brand/20">{{ category.name?.charAt(0) }}</div><div><h3 class="text-lg font-black text-slate-950 transition group-hover:text-brand">{{ category.name }}</h3><p v-if="category.description" class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">{{ category.description }}</p></div></div></RouterLink>
</template>
