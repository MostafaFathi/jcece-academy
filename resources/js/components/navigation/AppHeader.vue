<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import LanguageSwitcher from './LanguageSwitcher.vue';
import UserMenu from './UserMenu.vue';

const props = defineProps({ area: { type: String, required: true } });
defineEmits(['open-menu']);
const { t } = useI18n();
const route = useRoute();
const areaLabel = computed(() => t({ admin: 'admin.dashboard', instructor: 'instructor.dashboard', student: 'dashboard.workspace', support: 'operations.workspace' }[props.area] ?? 'common.dashboard'));
const pageLabel = computed(() => {
    const titledRoute = [...(route.matched ?? [])].reverse().find((item) => item.meta?.title);
    return titledRoute ? t(titledRoute.meta.title) : areaLabel.value;
});
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-[#e9e4e6] bg-white/95 shadow-[0_6px_24px_rgba(45,26,34,.035)] backdrop-blur-xl">
        <div class="mx-auto flex min-h-19 max-w-[1600px] items-center gap-3 px-4 sm:px-6 lg:px-9">
            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl border border-[#e8e0e3] bg-white text-brand shadow-sm transition hover:border-brand/30 hover:bg-brand-soft lg:hidden" :aria-label="t('common.menu')" @click="$emit('open-menu')">
                <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            </button>
            <div class="min-w-0 border-s-2 border-accent ps-3 sm:ps-4">
                <p class="truncate text-[10px] font-extrabold uppercase tracking-[0.2em] text-brand/65">{{ areaLabel }}</p>
                <p class="truncate text-sm font-extrabold text-slate-900 sm:text-base">{{ pageLabel }}</p>
            </div>
            <div class="ms-auto flex shrink-0 items-center gap-2 border-s border-[#eee8eb] ps-2 sm:ps-4">
                <LanguageSwitcher />
                <UserMenu />
            </div>
        </div>
    </header>
</template>
