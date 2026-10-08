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
    { label: 'discovery.instructors', route: 'instructors.index' },
    { label: 'site.about', route: 'site.about' },
    { label: 'nav.howItWorks', route: 'home', hash: '#how-it-works' },
];

watch(() => route.fullPath, () => { menuOpen.value = false; });
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-[#ece7e9] bg-white/95 shadow-[0_8px_28px_rgba(36,20,28,.045)] backdrop-blur-xl">
        <div class="h-1 bg-[linear-gradient(90deg,#6b1d32_0%,#6b1d32_68%,#ffd21e_68%,#ffd21e_100%)]" />
        <div class="mx-auto flex min-h-19 max-w-7xl items-center gap-5 px-4 sm:px-6 lg:px-8">
            <RouterLink :to="{ name: 'home' }" aria-label="JCEC Academy" class="flex shrink-0 items-center gap-2.5 rounded-xl focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent"><span class="relative size-11 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:size-12"><img :src="'/assets/images/logo-1.png'" alt="" class="absolute left-1/2 top-0 w-16 max-w-none -translate-x-1/2"></span><span><strong class="block text-[13px] font-black leading-tight tracking-tight text-brand sm:text-[15px]">JCEC Academy</strong><small class="mt-0.5 hidden text-[9px] font-extrabold tracking-[0.18em] text-slate-500 sm:block">LEARN · BUILD · LEAD</small></span></RouterLink>
            <nav class="hidden items-center gap-0.5 xl:flex" :aria-label="t('nav.primary')"><RouterLink v-for="item in navigation" :key="`${item.route}-${item.hash ?? ''}`" :to="{ name: item.route, hash: item.hash }" class="rounded-xl px-3 py-2.5 text-[13px] font-extrabold text-slate-600 transition hover:bg-brand-soft hover:text-brand" exact-active-class="!bg-brand-soft !text-brand">{{ t(item.label) }}</RouterLink></nav>
            <div class="ms-auto hidden items-center gap-2 sm:flex"><LanguageSwitcher /><RouterLink v-if="auth.isAuthenticated" :to="{ name: 'student.cart' }" class="rounded-xl px-3 py-2.5 text-sm font-bold text-brand transition hover:bg-brand-soft">{{ t('commerce.cart') }}</RouterLink><RouterLink :to="auth.isAuthenticated ? homeRouteFor(auth) : { name: 'login' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-extrabold text-white shadow-[0_6px_16px_rgba(107,29,50,.18)] transition hover:-translate-y-0.5 hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">{{ auth.isAuthenticated ? t('common.dashboard') : t('common.login') }}</RouterLink></div>
            <button type="button" class="ms-auto grid size-11 place-items-center rounded-xl border border-[#e9e1e4] bg-white text-brand shadow-sm transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand sm:ms-0 xl:hidden" :aria-label="t('common.menu')" :aria-expanded="menuOpen" aria-controls="public-mobile-menu" @click="menuOpen = !menuOpen"><svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path v-if="menuOpen" d="M5 5l14 14M19 5 5 19" /><path v-else d="M4 7h16M4 12h16M4 17h16" /></svg></button>
        </div>
        <div v-if="menuOpen" id="public-mobile-menu" class="border-t border-[#eee8eb] bg-white p-4 shadow-[0_16px_28px_rgba(36,20,28,.08)] xl:hidden"><nav class="mx-auto flex max-w-7xl flex-col gap-1" :aria-label="t('nav.mobile')"><RouterLink v-for="item in navigation" :key="`${item.route}-${item.hash ?? ''}`" :to="{ name: item.route, hash: item.hash }" class="rounded-xl px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-brand-soft hover:text-brand">{{ t(item.label) }}</RouterLink><RouterLink v-if="auth.isAuthenticated" :to="{ name: 'student.cart' }" class="rounded-xl px-4 py-3 text-sm font-bold text-brand">{{ t('commerce.cart') }}</RouterLink><div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-4"><LanguageSwitcher /><RouterLink :to="auth.isAuthenticated ? homeRouteFor(auth) : { name: 'login' }" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-black text-white">{{ auth.isAuthenticated ? t('common.dashboard') : t('common.login') }}</RouterLink></div></nav></div>
    </header>
</template>
