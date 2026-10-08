<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { createAdminPackage, fetchAdminPackage, updateAdminPackage } from '../api/admin-packages';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const isEdit = computed(() => Boolean(route.params.id));
const original = ref(null);
const form = reactive({ title: '', slug: '', description: '', thumbnail: '', type: 'package', price: '0.00', compare_price: '', access_duration_days: '', is_sequential: false, sequential_completion_percentage: '', status: 'draft', published_at: '' });
const promotion = reactive({ price: '', starts_at: '', ends_at: '' });
const limitedAccess = ref(false);
const loading = ref(true);
const loaded = ref(false);
const busy = ref(false);
const error = ref(null);
const accessError = ref(false);
const sequentialError = ref(false);
const statuses = ['draft', 'published', 'hidden', 'archived'];
function dateInput(value) {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}
onMounted(async () => {
    if (!isEdit.value) { loaded.value = true; loading.value = false; return; }
    try {
        original.value = await fetchAdminPackage(route.params.id);
        const item = original.value;
        Object.assign(promotion, { price: item.promotional_price ?? '', starts_at: dateInput(item.discount_starts_at), ends_at: dateInput(item.discount_ends_at) });
        Object.assign(form, { title: item.title, slug: item.slug, description: item.description ?? '', thumbnail: item.thumbnail ?? '', type: item.type, price: String(item.pricing?.regular_price ?? item.price), compare_price: item.legacy_compare_price === null ? '' : String(item.legacy_compare_price), access_duration_days: item.access_duration_days ?? '', is_sequential: item.is_sequential, sequential_completion_percentage: item.sequential_completion_percentage ?? '', status: item.status, published_at: dateInput(item.published_at) });
        limitedAccess.value = item.access_duration_days !== null;
        loaded.value = true;
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
});
async function submit() {
    if (busy.value) return;
    accessError.value = limitedAccess.value && (!Number.isInteger(Number(form.access_duration_days)) || Number(form.access_duration_days) < 1);
    sequentialError.value = form.is_sequential && (!/^\d{1,3}(?:\.\d{1,2})?$/.test(String(form.sequential_completion_percentage)) || Number(form.sequential_completion_percentage) <= 0 || Number(form.sequential_completion_percentage) > 100);
    if (accessError.value || sequentialError.value) return;
    const payload = {
        title: form.title.trim(), slug: form.slug.trim(), description: form.description || null, thumbnail: form.thumbnail || null,
        type: form.type, price: Number(form.price).toFixed(2), compare_price: form.compare_price === '' ? null : Number(form.compare_price).toFixed(2),
        promotional_price: promotion.price === '' ? null : Number(promotion.price).toFixed(2),
        discount_starts_at: promotion.starts_at ? new Date(promotion.starts_at).toISOString() : null,
        discount_ends_at: promotion.ends_at ? new Date(promotion.ends_at).toISOString() : null,
        access_duration_days: limitedAccess.value ? Number(form.access_duration_days) : null,
        is_sequential: form.is_sequential, status: form.status,
        sequential_completion_percentage: form.is_sequential ? String(form.sequential_completion_percentage) : null,
        published_at: form.published_at ? new Date(form.published_at).toISOString() : null,
    };
    if (isEdit.value && form.status === original.value.status) delete payload.status;
    if (form.status === 'published' && !auth.can('packages.publish') && !isEdit.value) delete payload.status;
    busy.value = true; error.value = null;
    try {
        const item = isEdit.value ? await updateAdminPackage(route.params.id, payload) : await createAdminPackage(payload);
        await router.push({ name: 'admin.packages.courses', params: { id: item.id } });
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'admin.packages.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink>
        <PageHeading :title="t(isEdit ? 'packages.edit' : 'packages.add')" :description="t('packages.formDescription')"><template #actions><RouterLink v-if="isEdit" :to="{ name: 'admin.packages.courses', params: { id: route.params.id } }" class="inline-flex min-h-11 items-center rounded-xl border border-brand px-5 py-2.5 text-sm font-bold text-brand">{{ t('packages.manageCourses') }}</RouterLink></template></PageHeading>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="!loaded" tone="danger">{{ t(error?.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }}</BaseAlert>
        <template v-else><BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : error.status === 403 ? 'admin.notAllowed' : 'admin.saveError') }}</BaseAlert><form class="space-y-6" @submit.prevent="submit">
            <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.basic') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="package-title" class="mb-2 block text-sm font-bold">{{ t('admin.title') }}</label><input id="package-title" v-model="form.title" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.title" role="alert" class="text-sm text-red-700">{{ error.errors.title[0] }}</p></div><div><label for="package-slug" class="mb-2 block text-sm font-bold">{{ t('admin.slug') }}</label><input id="package-slug" v-model="form.slug" required maxlength="255" pattern="[A-Za-z0-9_-]+" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.slug" role="alert" class="text-sm text-red-700">{{ error.errors.slug[0] }}</p></div><div><label for="package-type-field" class="mb-2 block text-sm font-bold">{{ t('packages.type') }}</label><select id="package-type-field" v-model="form.type" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option value="package">{{ t('packages.types.package') }}</option><option value="learning_path">{{ t('packages.types.learning_path') }}</option></select></div><div><label for="package-thumbnail" class="mb-2 block text-sm font-bold">{{ t('admin.thumbnail') }}</label><input id="package-thumbnail" v-model="form.thumbnail" maxlength="2048" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div></div><div><label for="package-description" class="mb-2 block text-sm font-bold">{{ t('admin.description') }}</label><textarea id="package-description" v-model="form.description" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3" /></div></section>
            <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.pricing') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="package-price" class="mb-2 block text-sm font-bold">{{ t('admin.price') }} ({{ original?.currency ?? t('admin.serverCurrency') }})</label><input id="package-price" v-model="form.price" type="number" min="0" step="0.01" required class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.price" role="alert" class="text-sm text-red-700">{{ error.errors.price[0] }}</p></div><div><label for="package-compare" class="mb-2 block text-sm font-bold">{{ t('admin.comparePrice') }} ({{ original?.currency ?? t('admin.serverCurrency') }})</label><input id="package-compare" v-model="form.compare_price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.compare_price" role="alert" class="text-sm text-red-700">{{ error.errors.compare_price[0] }}</p></div></div><div class="space-y-3"><p class="text-sm font-bold">{{ t('admin.access') }}</p><label class="flex items-center gap-3 text-sm"><input v-model="limitedAccess" type="radio" :value="false" class="size-4 accent-brand">{{ t('admin.lifetime') }}</label><label class="flex items-center gap-3 text-sm"><input v-model="limitedAccess" type="radio" :value="true" class="size-4 accent-brand">{{ t('admin.limited') }}</label><div v-if="limitedAccess"><label for="package-days" class="mb-2 block text-sm font-bold">{{ t('admin.accessDays') }}</label><input id="package-days" v-model="form.access_duration_days" type="number" min="1" required class="w-full max-w-xs rounded-xl border border-slate-300 px-4 py-3"><p v-if="accessError || error?.errors?.access_duration_days" role="alert" class="text-sm text-red-700">{{ error?.errors?.access_duration_days?.[0] ?? t('admin.positiveDays') }}</p></div></div></section>
            <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.publishing') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="package-status" class="mb-2 block text-sm font-bold">{{ t('admin.status') }}</label><select id="package-status" v-model="form.status" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option v-for="status in statuses" :key="status" :value="status" :disabled="status === 'published' && original?.status !== 'published' && !auth.can('packages.publish')">{{ t(`packages.statuses.${status}`) }}</option></select><p v-if="error?.errors?.status" role="alert" class="text-sm text-red-700">{{ error.errors.status[0] }}</p></div><div><label for="package-published-at" class="mb-2 block text-sm font-bold">{{ t('admin.publishedAt') }}</label><input id="package-published-at" v-model="form.published_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div></div><label class="flex items-center gap-3 text-sm font-bold"><input v-model="form.is_sequential" type="checkbox" class="size-5 accent-brand">{{ t('packages.sequential') }}</label><div v-if="form.is_sequential" class="max-w-sm"><label for="package-threshold" class="mb-2 block text-sm font-bold">{{ t('path.threshold') }}</label><input id="package-threshold" v-model="form.sequential_completion_percentage" type="number" min="0.01" max="100" step="0.01" required class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="sequentialError || error?.errors?.sequential_completion_percentage" role="alert" class="mt-2 text-sm text-red-700">{{ error?.errors?.sequential_completion_percentage?.[0] ?? t('path.thresholdInvalid') }}</p><p class="mt-2 text-sm text-slate-600">{{ t('path.thresholdHint') }}</p></div><p class="text-xs text-slate-500">{{ t('packages.publishNote') }}</p></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('packages.courses') }}</h2><p class="mt-2 text-sm text-slate-600">{{ t('packages.futurePurchases') }}</p><RouterLink v-if="isEdit" :to="{ name: 'admin.packages.courses', params: { id: route.params.id } }" class="mt-3 inline-block font-bold text-brand underline">{{ t('packages.manageCourses') }}</RouterLink><p v-else class="mt-2 text-sm text-slate-500">{{ t('packages.saveFirst') }}</p></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="mb-4 text-lg font-black">{{ t('admin.promotionalPrice') }}</h2><div class="grid gap-4 sm:grid-cols-3"><label class="space-y-1 text-sm font-bold">{{ t('admin.promotionalPrice') }} ({{ original?.currency ?? t('admin.serverCurrency') }})<input v-model="promotion.price" type="number" min="0" step="0.01" class="block w-full rounded-xl border border-slate-300 px-4 py-3"></label><label class="space-y-1 text-sm font-bold">{{ t('admin.discountStart') }}<input v-model="promotion.starts_at" type="datetime-local" class="block w-full rounded-xl border border-slate-300 px-4 py-3"></label><label class="space-y-1 text-sm font-bold">{{ t('admin.discountEnd') }}<input v-model="promotion.ends_at" type="datetime-local" :min="promotion.starts_at || undefined" class="block w-full rounded-xl border border-slate-300 px-4 py-3"></label></div><p v-if="error?.errors?.promotional_price" role="alert" class="mt-2 text-sm text-red-700">{{ error.errors.promotional_price[0] }}</p><p class="mt-2 text-xs text-slate-500">{{ t('admin.promotionHint') }}</p></section>
            <div class="flex flex-wrap gap-3"><BaseButton type="submit" :loading="busy">{{ t(isEdit ? 'admin.save' : 'admin.create') }}</BaseButton><RouterLink :to="{ name: 'admin.packages.index' }" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-bold">{{ t('admin.cancel') }}</RouterLink></div>
        </form></template>
    </div>
</template>
