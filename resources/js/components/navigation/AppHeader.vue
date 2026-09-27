<script setup>
import LanguageSwitcher from './LanguageSwitcher.vue';
import UserMenu from './UserMenu.vue';
defineEmits(['open-menu']);
</script>

<template>
    <header class="sticky top-0 z-20 flex min-h-18 items-center justify-between gap-4 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6"><button type="button" class="grid size-11 place-items-center rounded-xl border border-slate-200 lg:hidden" aria-label="Open menu" @click="$emit('open-menu')">☰</button><div class="hidden h-8 w-1 rounded-full bg-accent lg:block" /><div class="ms-auto flex items-center gap-2"><LanguageSwitcher /><UserMenu /></div></header>
</template>
