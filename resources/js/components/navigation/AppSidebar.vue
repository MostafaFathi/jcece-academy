<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { navigationByArea, visibleNavigation } from '../../composables/navigation';
import BrandLogo from './BrandLogo.vue';

const props = defineProps({ area: { type: String, required: true } });
const auth = useAuthStore();
const { t, locale } = useI18n();
const items = computed(() => visibleNavigation(navigationByArea[props.area] ?? [], auth));
const areaLabel = computed(() => t({ admin: 'admin.dashboard', instructor: 'instructor.dashboard', student: 'dashboard.workspace', support: 'operations.workspace' }[props.area] ?? 'common.dashboard'));

function iconFor(route) {
    if (route.includes('report')) return 'M3 3v18h18M7 16l4-4 3 2 5-7';
    if (route.includes('dashboard')) return 'M3 3h8v8H3zM13 3h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z';
    if (route.includes('courses') || route.includes('curriculum')) return 'M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21zM4 5.5V21M8 8h8M8 12h7';
    if (route.includes('users') || route.includes('instructors') || route.includes('roles')) return 'M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75';
    if (route.includes('orders') || route.includes('cart') || route.includes('payments') || route.includes('coupons')) return 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h5';
    if (route.includes('support') || route.includes('tickets')) return 'M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.5a8.5 8.5 0 1 1 16.1-4z';
    if (route.includes('certificates')) return 'M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12ZM8 14l-1 7 5-3 5 3-1-7';
    if (route.includes('audit') || route.includes('policy')) return 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM9 12l2 2 4-4';
    if (route.includes('packages')) return 'M3 7l9-4 9 4-9 4-9-4ZM3 7v10l9 4 9-4V7M12 11v10';
    if (route.includes('categories') || route.includes('site-content')) return 'M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z';
    return 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h5';
}
</script>

<template>
    <aside class="relative flex h-full min-h-0 flex-col overflow-hidden bg-[#26131c] text-white">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_0%_0%,rgba(255,210,30,.09),transparent_42%),linear-gradient(180deg,#351522_0%,#24141c_100%)]" />
        <div class="relative h-1 shrink-0 bg-[linear-gradient(90deg,#ffd21e_0%,#ffd21e_32%,#8b213e_32%,#8b213e_100%)]" />
        <div class="relative shrink-0 px-5 pb-5 pt-6"><BrandLogo /><p class="mt-5 rounded-xl border border-white/10 bg-white/[0.05] px-3 py-2 text-xs font-semibold text-white/65">{{ areaLabel }}</p></div>
        <nav class="sidebar-scroll relative flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 pb-5" :aria-label="t('nav.main')">
            <p class="px-3 pb-2 pt-2 text-[10px] font-extrabold uppercase tracking-[0.22em] text-white/40">{{ t('nav.main') }}</p>
            <template v-for="(item, index) in items" :key="item.route">
                <p v-if="item.group && items[index - 1]?.group !== item.group" class="px-3 pb-1 pt-5 text-[10px] font-extrabold uppercase tracking-[0.18em] text-accent/75">{{ t(item.group) }}</p>
                <RouterLink :to="{ name: item.route }" class="group relative flex min-h-11 items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold leading-5 text-white/65 transition-colors duration-150 hover:bg-white/[0.08] hover:text-white focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent" exact-active-class="!bg-white/[0.12] !text-white before:absolute before:inset-y-2 before:start-0 before:w-0.5 before:rounded-full before:bg-accent [&_svg]:!text-accent">
                    <svg aria-hidden="true" class="size-[19px] shrink-0 text-white/45 transition-colors group-hover:text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path :d="iconFor(item.route)" /></svg>
                    <span class="min-w-0 flex-1">{{ t(item.label) }}</span>
                    <span aria-hidden="true" class="text-xs text-white/25 opacity-0 transition-opacity group-hover:opacity-100">{{ locale === 'ar' ? '‹' : '›' }}</span>
                </RouterLink>
            </template>
        </nav>
        <div class="relative shrink-0 border-t border-white/10 p-4"><p class="rounded-xl bg-white/[0.04] px-4 py-3 text-xs leading-5 text-white/50"><span class="mb-1 block h-0.5 w-8 rounded-full bg-accent" />{{ t('brand.tagline') }}</p></div>
    </aside>
</template>
