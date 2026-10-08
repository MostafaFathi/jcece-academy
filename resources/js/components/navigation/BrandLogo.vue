<script setup>
defineProps({ compact: Boolean });
</script>

<template>
    <RouterLink to="/" aria-label="JCEC Academy" class="flex items-center gap-3 rounded-xl focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">
        <span class="relative size-12 shrink-0 overflow-hidden rounded-xl border border-white/20 bg-white shadow-[0_8px_24px_rgba(0,0,0,.18)]" :class="compact ? 'lg:size-10' : ''"><img :src="'/assets/images/logo-1.png'" alt="" class="absolute left-1/2 top-0 w-16 max-w-none -translate-x-1/2"></span>
        <span v-if="!compact" class="min-w-0"><strong class="block truncate text-[15px] font-black tracking-tight text-white">JCEC Academy</strong><small class="mt-0.5 block truncate text-[9px] font-bold tracking-[0.2em] text-white/50">LEARN · BUILD · LEAD</small></span>
    </RouterLink>
</template>
