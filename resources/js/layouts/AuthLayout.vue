<script setup>
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '../components/navigation/LanguageSwitcher.vue';
const { locale, t } = useI18n();
</script>

<template>
    <div class="grid min-h-screen bg-slate-50 lg:grid-cols-[1.08fr_0.92fr]">
        <aside class="relative hidden overflow-hidden bg-brand-dark p-12 text-white lg:flex lg:flex-col lg:justify-between xl:p-16"><div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_15%,rgba(255,210,30,.16),transparent_28%),radial-gradient(circle_at_85%_70%,rgba(255,255,255,.08),transparent_30%)]" /><div class="absolute inset-0 opacity-[0.06] [background-image:linear-gradient(rgba(255,255,255,.4)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.4)_1px,transparent_1px)] [background-size:52px_52px]" /><RouterLink to="/" class="relative flex w-fit items-center gap-4 rounded-2xl focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="size-20 rounded-2xl bg-white object-contain p-1 shadow-xl"><span><strong class="block text-2xl font-black">JCEC Academy</strong><small class="tracking-[0.2em] text-white/55">LEARN · BUILD · LEAD</small></span></RouterLink><div class="relative max-w-xl"><span class="mb-7 block h-1.5 w-24 rounded-full bg-accent" /><p class="text-4xl font-black leading-tight xl:text-5xl">{{ t('brand.promise') }}</p><p class="mt-6 max-w-lg text-base leading-8 text-white/60">{{ t('brand.description') }}</p></div><small class="relative text-white/45">© JCEC Academy</small></aside>
        <main class="relative flex min-h-screen flex-col"><div class="flex items-center justify-between p-4 sm:p-6"><RouterLink :to="{ name: 'home' }" class="rounded-xl px-3 py-2 text-sm font-black text-brand hover:bg-brand-soft"><span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span> {{ t('common.backHome') }}</RouterLink><LanguageSwitcher /></div><div class="flex flex-1 items-center justify-center px-4 pb-12 sm:px-8"><div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_25px_70px_rgba(15,23,42,.1)] sm:p-9"><RouterView /></div></div></main>
    </div>
</template>
