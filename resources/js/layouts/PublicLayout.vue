<script setup>
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { homeRouteFor } from '../router/access';
import LanguageSwitcher from '../components/navigation/LanguageSwitcher.vue';

const auth = useAuthStore();
const { t } = useI18n();
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900"><header class="border-b border-slate-200"><div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6"><RouterLink to="/" class="flex items-center gap-3"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="size-14 object-contain"><span class="hidden sm:block"><strong class="block text-lg font-black text-brand">{{ t('brand.name') }}</strong><small class="text-slate-500">{{ t('brand.tagline') }}</small></span></RouterLink><nav class="flex items-center gap-2"><LanguageSwitcher /><RouterLink :to="auth.isAuthenticated ? homeRouteFor(auth) : { name: 'login' }" class="rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-dark">{{ auth.isAuthenticated ? t('common.dashboard') : t('common.login') }}</RouterLink></nav></div></header><main><RouterView /></main>
    </div>
</template>
