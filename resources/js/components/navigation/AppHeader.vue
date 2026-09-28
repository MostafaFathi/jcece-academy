<script setup>
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from './LanguageSwitcher.vue';
import UserMenu from './UserMenu.vue';
defineEmits(['open-menu']);
const { t } = useI18n();
</script>

<template>
    <header class="sticky top-0 z-20 flex min-h-18 items-center justify-between gap-4 border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6"><button type="button" class="grid size-11 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm lg:hidden" :aria-label="t('common.menu')" @click="$emit('open-menu')">☰</button><div class="hidden items-center gap-3 lg:flex"><span class="h-8 w-1 rounded-full bg-accent" /><span class="text-xs font-black tracking-[0.16em] text-slate-400">JCEC ACADEMY</span></div><div class="ms-auto flex items-center gap-2"><LanguageSwitcher /><UserMenu /></div></header>
</template>
