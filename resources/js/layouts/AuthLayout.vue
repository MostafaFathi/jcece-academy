<script setup>
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '../components/navigation/LanguageSwitcher.vue';
const { t } = useI18n();
</script>

<template>
    <div class="grid min-h-screen bg-slate-50 lg:grid-cols-[1.05fr_0.95fr]"><aside class="relative hidden overflow-hidden bg-brand-dark p-12 text-white lg:flex lg:flex-col lg:justify-between"><div class="absolute -end-24 -top-24 size-80 rounded-full bg-accent/20" /><RouterLink to="/" class="relative flex items-center gap-4"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="size-20 rounded-2xl bg-white object-contain p-1"><span><strong class="block text-2xl font-black">JCEC Academy</strong><small class="tracking-[0.2em] text-white/65">LEARN · BUILD · LEAD</small></span></RouterLink><div class="relative"><div class="mb-6 h-1 w-24 rounded-full bg-accent" /><p class="max-w-md text-4xl font-black leading-tight">{{ t('brand.promise') }}</p></div><small class="relative text-white/55">© JCEC Academy</small></aside><main class="flex min-h-screen flex-col"><div class="flex justify-end p-4"><LanguageSwitcher /></div><div class="flex flex-1 items-center justify-center p-4 sm:p-8"><div class="w-full max-w-md"><RouterView /></div></div></main></div>
</template>
