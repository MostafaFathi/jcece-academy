<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const year = new Date().getFullYear();
</script>

<template>
    <footer class="relative overflow-hidden bg-brand-dark text-white">
        <div class="absolute -end-32 -top-32 size-80 rounded-full border-[45px] border-white/[0.03]" />
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
            <div class="lg:col-span-2"><div class="flex items-center gap-4"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="size-20 rounded-2xl bg-white object-contain p-1"><div><strong class="text-xl font-black">JCEC Academy</strong><p class="mt-1 text-xs tracking-[0.18em] text-white/55">LEARN · BUILD · LEAD</p></div></div><p class="mt-5 max-w-md text-sm leading-7 text-white/65">{{ t('brand.description') }}</p></div>
            <div><h2 class="text-sm font-black text-accent">{{ t('footer.explore') }}</h2><nav class="mt-4 flex flex-col gap-3 text-sm text-white/65"><RouterLink :to="{ name: 'courses.index' }" class="hover:text-white">{{ t('nav.courses') }}</RouterLink><RouterLink :to="{ name: 'packages.index' }" class="hover:text-white">{{ t('nav.packages') }}</RouterLink><RouterLink :to="{ name: 'register' }" class="hover:text-white">{{ t('auth.registerLink') }}</RouterLink><RouterLink :to="{ name: 'login' }" class="hover:text-white">{{ t('common.login') }}</RouterLink></nav></div>
            <div><h2 class="text-sm font-black text-accent">{{ t('footer.learning') }}</h2><p class="mt-4 text-sm leading-7 text-white/65">{{ t('footer.learningText') }}</p><nav class="mt-4 flex flex-col gap-3 text-sm text-white/65"><RouterLink :to="{ name: 'policies.privacy' }" class="hover:text-white">{{ t('policies.privacy') }}</RouterLink><RouterLink :to="{ name: 'policies.terms' }" class="hover:text-white">{{ t('policies.terms') }}</RouterLink><RouterLink :to="{ name: 'policies.refund' }" class="hover:text-white">{{ t('policies.refund') }}</RouterLink></nav></div>
        </div>
        <div class="border-t border-white/10"><div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-white/45 sm:px-6 lg:px-8"><p>© {{ year }} JCEC Academy. {{ t('footer.rights') }}</p><p>Skills today · A brighter tomorrow</p></div></div>
    </footer>
</template>
