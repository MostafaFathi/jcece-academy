<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const { t } = useI18n();
const open = ref(false);
const root = ref(null);

function closeOnOutsideClick(event) {
    if (open.value && !root.value?.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', closeOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutsideClick));

async function signOut() {
    try {
        await auth.signOut();
    } catch {} finally {
        await router.push({ name: 'home' });
    }
}
</script>

<template>
    <div ref="root" class="relative" @keydown.esc="open = false">
        <button type="button" class="flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm hover:bg-slate-50 focus-visible:outline-3 focus-visible:outline-brand" aria-haspopup="menu" :aria-label="t('common.accountMenu')" :aria-expanded="open" @click="open = !open"><span class="grid size-8 place-items-center rounded-full bg-brand text-sm font-black text-white">{{ auth.user?.name?.charAt(0) }}</span><span class="hidden max-w-32 truncate text-sm font-bold sm:block">{{ auth.user?.name }}</span><span aria-hidden="true">⌄</span></button>
        <div v-if="open" role="menu" class="absolute end-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl"><div class="border-b border-slate-100 px-3 py-2"><p class="truncate text-sm font-bold">{{ auth.user?.name }}</p><p class="truncate text-xs text-slate-500">{{ auth.user?.email }}</p></div><button type="button" role="menuitem" class="mt-1 w-full rounded-lg px-3 py-2 text-start text-sm font-bold text-red-700 hover:bg-red-50 focus-visible:outline-3 focus-visible:outline-red-600 disabled:opacity-50" :disabled="auth.logoutLoading" @click="signOut">{{ t('common.logout') }}</button></div>
    </div>
</template>
