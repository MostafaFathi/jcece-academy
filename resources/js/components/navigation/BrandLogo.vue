<script setup>
defineProps({ compact: Boolean });
</script>

<template>
    <RouterLink to="/" class="flex items-center gap-3 rounded-xl focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">
        <img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="size-12 rounded-xl bg-white object-contain p-1 shadow-sm" :class="compact ? 'lg:size-10' : ''">
        <span v-if="!compact" class="min-w-0"><strong class="block truncate text-base font-black text-white">JCEC Academy</strong><small class="block truncate text-[10px] tracking-wider text-white/70">LEARN · BUILD · LEAD</small></span>
    </RouterLink>
</template>
