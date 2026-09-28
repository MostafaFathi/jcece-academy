<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { homeRouteFor } from '../../router/access';
import LanguageSwitcher from '../navigation/LanguageSwitcher.vue';

const auth = useAuthStore();
const route = useRoute();
const { t } = useI18n();
const menuOpen = ref(false);

const navigation = [
    { label: 'common.home', route: 'home' },
    { label: 'nav.courses', route: 'courses.index' },
    { label: 'nav.packages', route: 'packages.index' },
    { label: 'nav.howItWorks', route: 'home', hash: '#how-it-works' },
];

watch(() => route.fullPath, () => { menuOpen.value = false; });
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl"><div class="mx-auto flex min-h-20 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8"><RouterLink :to="{ name: 'home' }" class="flex shrink-0 items-center gap-3 rounded-xl focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="size-14 object-contain"><span class="hidden xl:block"><strong class="block text-base font-black leading-none text-brand">JCEC Academy</strong><small class="mt-1 block text-[9px] font-bold tracking-[0.2em] text-slate-500">LEARN · BUILD · LEAD</small></span></RouterLink><nav class="hidden items-center gap-1 lg:flex" :aria-label="t('nav.primary')"><RouterLink v-for="item in navigation" :key="`${item.route}-${item.hash ?? ''}`" :to="{ name: item.route, hash: item.hash }" class="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-brand-soft hover:text-brand" active-class="!bg-brand-soft !text-brand">{{ t(item.label) }}</RouterLink></nav><div class="ms-auto hidden items-center gap-2 sm:flex"><LanguageSwitcher /><RouterLink v-if="auth.isAuthenticated" :to="{ name: 'student.cart' }" class="rounded-xl px-3 py-2.5 text-sm font-bold text-brand">{{ t('commerce.cart') }}</RouterLink><RouterLink :to="auth.isAuthenticated ? homeRouteFor(auth) : { name: 'login' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-brand/15 transition hover:-translate-y-0.5 hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">{{ auth.isAuthenticated ? t('common.dashboard') : t('common.login') }}</RouterLink></div><button type="button" class="ms-auto grid size-11 place-items-center rounded-xl border border-slate-200 text-xl text-slate-700 transition hover:bg-slate-50 focus-visible:outline-3 focus-visible:outline-brand sm:ms-0 lg:hidden" :aria-label="t('common.menu')" :aria-expanded="menuOpen" aria-controls="public-mobile-menu" @click="menuOpen = !menuOpen"><span aria-hidden="true">{{ menuOpen ? '×' : '☰' }}</span></button></div><div v-if="menuOpen" id="public-mobile-menu" class="border-t border-slate-200 bg-white p-4 lg:hidden"><nav class="mx-auto flex max-w-7xl flex-col gap-1" :aria-label="t('nav.mobile')"><RouterLink v-for="item in navigation" :key="`${item.route}-${item.hash ?? ''}`" :to="{ name: item.route, hash: item.hash }" class="rounded-xl px-4 py-3 text-sm font-bold text-slate-700 hover:bg-brand-soft hover:text-brand">{{ t(item.label) }}</RouterLink><RouterLink v-if="auth.isAuthenticated" :to="{ name: 'student.cart' }" class="rounded-xl px-3 py-2.5 text-sm font-bold text-brand">{{ t('commerce.cart') }}</RouterLink><div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-4"><LanguageSwitcher /><RouterLink :to="auth.isAuthenticated ? homeRouteFor(auth) : { name: 'login' }" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-black text-white">{{ auth.isAuthenticated ? t('common.dashboard') : t('common.login') }}</RouterLink></div></nav></div></header>
</template>
