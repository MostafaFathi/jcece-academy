<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchPackage } from '../api/packages';
import { useAuthStore } from '../stores/auth';
import { formatAmount } from '../utils/catalog';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import { usePageMeta } from '../composables/usePageMeta';

const props = defineProps({ slug: { type: String, required: true } });
const auth = useAuthStore();
const route = useRoute();
const { locale, t } = useI18n();
const packageItem = ref(null);
const loading = ref(true);
const error = ref(null);

usePageMeta(() => packageItem.value?.title ?? t('nav.packages'), () => packageItem.value?.description ?? t('catalog.packagesDescription'));

async function loadPackage() {
    loading.value = true;
    error.value = null;
    try { packageItem.value = await fetchPackage(props.slug); } catch (requestError) { error.value = requestError; packageItem.value = null; } finally { loading.value = false; }
}

onMounted(loadPackage);
watch(() => props.slug, loadPackage);
</script>

<template>
    <LoadingState v-if="loading" class="min-h-[60vh]" />
    <main v-else-if="packageItem" class="min-h-screen bg-slate-50"><section class="relative overflow-hidden bg-brand-dark text-white"><div class="absolute -end-20 -top-24 size-96 rounded-full bg-accent/10 blur-2xl" /><div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_390px] lg:px-8 lg:py-20"><div><RouterLink :to="{ name: 'packages.index' }" class="text-sm font-bold text-white/60 hover:text-white"><span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span> {{ t('packages.backToPackages') }}</RouterLink><span class="mt-7 block w-fit rounded-full bg-accent px-3 py-1.5 text-xs font-black text-brand-dark">{{ t(`labels.packageTypes.${packageItem.type}`) }}</span><h1 class="mt-5 text-4xl font-black leading-tight sm:text-5xl">{{ packageItem.title }}</h1><p v-if="packageItem.description" class="mt-5 max-w-3xl whitespace-pre-line text-lg leading-8 text-white/70">{{ packageItem.description }}</p><div class="mt-7 flex flex-wrap gap-3 text-sm"><span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">{{ packageItem.is_sequential ? t('packages.sequential') : t('packages.flexible') }}</span><span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">{{ t('catalog.courseCount', { count: packageItem.course_count ?? packageItem.courses?.length ?? 0 }) }}</span></div></div><div class="overflow-hidden rounded-3xl border border-white/15 bg-white p-3 text-slate-900 shadow-2xl"><MediaFrame :src="packageItem.thumbnail" :alt="packageItem.title" /><div class="space-y-5 p-5"><div><p class="text-xs font-bold text-slate-500">{{ t('catalog.packagePrice') }}</p><div class="mt-1 flex items-baseline gap-2"><strong class="text-3xl font-black text-brand">{{ Number(packageItem.price) === 0 ? t('catalog.free') : formatAmount(packageItem.price, locale) }}</strong><del v-if="packageItem.compare_price && Number(packageItem.compare_price) > Number(packageItem.price)" class="text-sm text-slate-400">{{ formatAmount(packageItem.compare_price, locale) }}</del></div></div><p class="rounded-xl bg-slate-50 p-3 text-sm font-bold text-slate-700">{{ packageItem.is_lifetime ? t('packages.lifetime') : t('packages.accessDays', { count: packageItem.access_duration_days }) }}</p><RouterLink v-if="!auth.isAuthenticated" :to="{ name: 'login', query: { redirect: route.fullPath } }" class="flex min-h-12 items-center justify-center rounded-xl bg-brand px-5 font-black text-white">{{ t('packages.signInCta') }}</RouterLink><button v-else disabled class="min-h-12 w-full cursor-not-allowed rounded-xl bg-brand px-5 font-black text-white opacity-65">{{ t('packages.purchaseSoon') }}</button><p class="text-center text-xs text-slate-500">{{ t('common.comingSoon') }}</p></div></div></div></section><section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24"><div class="mb-8"><h2 class="text-3xl font-black text-slate-950">{{ t('packages.included') }}</h2><p class="mt-3 text-sm leading-6 text-slate-500">{{ t('packages.courseAccessNote') }}</p></div><div v-if="packageItem.courses?.length" class="grid gap-5 md:grid-cols-2"><article v-for="membership in packageItem.courses" :key="membership.id" class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="grid sm:grid-cols-[180px_1fr]"><MediaFrame :src="membership.course?.thumbnail" :alt="membership.course?.title ?? ''" class="h-full" /><div class="p-5"><div class="flex flex-wrap gap-2"><span class="rounded-full bg-brand-soft px-2.5 py-1 text-[10px] font-black text-brand">{{ membership.is_required ? t('packages.required') : t('packages.optional') }}</span><span class="text-xs font-bold text-slate-500">{{ t(`labels.levels.${membership.course?.level}`) }}</span></div><h3 class="mt-3 text-lg font-black text-slate-950">{{ membership.course?.title }}</h3><p v-if="membership.course?.short_description" class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ membership.course.short_description }}</p><RouterLink v-if="membership.course" :to="{ name: 'courses.show', params: { slug: membership.course.slug } }" class="mt-4 inline-flex text-sm font-black text-brand">{{ t('common.viewDetails') }} <span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink></div></div></article></div><EmptyState v-else :title="t('packages.noCourses')" /></section></main>
    <div v-else class="mx-auto max-w-3xl px-4 py-24"><BaseAlert tone="danger"><strong>{{ error?.status === 404 ? t('packages.notFound') : t('packages.detailError') }}</strong><button type="button" class="ms-3 font-black underline" @click="loadPackage">{{ t('common.retry') }}</button></BaseAlert></div>
</template>
