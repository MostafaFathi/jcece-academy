<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminCategories, fetchAdminCourse, updateAdminCourse } from '../api/admin';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const form = reactive({ title: '', slug: '', short_description: '', description: '', category_id: '', level: 'beginner', language: 'ar', duration_minutes: '', access_duration_days: '', price: '0.00', compare_price: '', discount_starts_at: '', discount_ends_at: '', status: 'draft', thumbnail: '', promo_video_url: '', certificate_enabled: false, discussion_enabled: false, is_featured: false, published_at: '' });
const lists = reactive({ learning_outcomes: '', requirements: '', target_audiences: '', required_tools: '' });
const originalLists = {};
const course = ref(null);
const categories = ref([]);
const categoryPage = ref(0);
const categoryLastPage = ref(1);
const limitedAccess = ref(false);
const loading = ref(true);
const optionsError = ref(false);
const error = ref(null);
const busy = ref(false);
const statuses = ['draft', 'published', 'hidden', 'coming_soon', 'archived'];
const levels = ['beginner', 'intermediate', 'advanced', 'all_levels'];
const listFields = { learning_outcomes: 'outcome', requirements: 'requirement', target_audiences: 'audience', required_tools: 'tool' };
function dateInput(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
}
function datePayload(value) { return value ? new Date(value).toISOString() : null; }
async function loadCategories() {
    if (categoryPage.value >= categoryLastPage.value) return;
    try {
        const result = await fetchAdminCategories(categoryPage.value + 1);
        categories.value.push(...result.items.filter((item) => !categories.value.some((existing) => existing.id === item.id)));
        categoryPage.value = result.meta?.current_page ?? categoryPage.value + 1;
        categoryLastPage.value = result.meta?.last_page ?? categoryPage.value;
    } catch { optionsError.value = true; }
}
onMounted(async () => {
    try {
        course.value = await fetchAdminCourse(route.params.id);
        const value = course.value;
        Object.assign(form, {
            title: value.title, slug: value.slug, short_description: value.short_description ?? '', description: value.description ?? '', category_id: value.category?.id ?? '', level: value.level, language: value.language ?? 'ar', duration_minutes: value.duration_minutes ?? '', access_duration_days: value.access_duration_days ?? '', price: String(value.price ?? '0.00'), compare_price: value.compare_price === null ? '' : String(value.compare_price), discount_starts_at: dateInput(value.discount_starts_at), discount_ends_at: dateInput(value.discount_ends_at), status: value.status, thumbnail: value.thumbnail ?? '', promo_video_url: value.promo_video_url ?? '', certificate_enabled: value.certificate_enabled, discussion_enabled: value.discussion_enabled, is_featured: value.is_featured, published_at: dateInput(value.published_at),
        });
        limitedAccess.value = value.access_duration_days !== null;
        for (const [key, itemKey] of Object.entries(listFields)) {
            lists[key] = (value[key] ?? []).map((item) => item[itemKey]).join('\n');
            originalLists[key] = lists[key];
        }
        if (value.category) categories.value.push(value.category);
        await loadCategories();
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
});
async function submit() {
    if (busy.value || !course.value) return;
    busy.value = true;
    error.value = null;
    const payload = {
        ...form,
        title: form.title.trim(), slug: form.slug.trim(), category_id: Number(form.category_id),
        short_description: form.short_description || null, description: form.description || null,
        thumbnail: form.thumbnail || null, promo_video_url: form.promo_video_url || null,
        duration_minutes: form.duration_minutes === '' ? null : Number(form.duration_minutes),
        access_duration_days: limitedAccess.value ? Number(form.access_duration_days) : null,
        compare_price: form.compare_price === '' ? null : form.compare_price,
        discount_starts_at: datePayload(form.discount_starts_at), discount_ends_at: datePayload(form.discount_ends_at),
        published_at: datePayload(form.published_at),
    };
    for (const key of Object.keys(listFields)) {
        if (lists[key] !== originalLists[key]) payload[key] = lists[key].split('\n').map((item) => item.trim()).filter(Boolean);
    }
    if (payload.category_id === course.value.category?.id) delete payload.category_id;
    if (form.status === course.value.status || (form.status === 'published' && !auth.can('courses.publish'))) delete payload.status;
    try { await updateAdminCourse(route.params.id, payload); await router.push({ name: 'admin.courses.index' }); }
    catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'admin.courses.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink>
        <PageHeading :title="t('admin.editCourse')" />
        <LoadingState v-if="loading" />
        <template v-else-if="course">
            <BaseAlert v-if="optionsError">{{ t('admin.loadError') }}</BaseAlert>
            <BaseAlert>{{ t('admin.instructorUnavailable') }} <strong>{{ course.instructor?.name ?? '—' }}</strong></BaseAlert>
            <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : error.status === 403 ? 'admin.notAllowed' : 'admin.saveError') }}</BaseAlert>
            <form class="space-y-6" @submit.prevent="submit">
                <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.basic') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="course-title" class="mb-2 block text-sm font-bold">{{ t('admin.title') }}</label><input id="course-title" v-model="form.title" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.title" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.title[0] }}</p></div><div><label for="course-slug" class="mb-2 block text-sm font-bold">{{ t('admin.slug') }}</label><input id="course-slug" v-model="form.slug" required maxlength="255" pattern="[A-Za-z0-9_-]+" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.slug" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.slug[0] }}</p></div></div><div><label for="course-short" class="mb-2 block text-sm font-bold">{{ t('admin.shortDescription') }}</label><textarea id="course-short" v-model="form.short_description" maxlength="1000" rows="2" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><p v-if="error?.errors?.short_description" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.short_description[0] }}</p></div><div><label for="course-description" class="mb-2 block text-sm font-bold">{{ t('admin.description') }}</label><textarea id="course-description" v-model="form.description" rows="5" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><p v-if="error?.errors?.description" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.description[0] }}</p></div></section>
                <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.classification') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="course-category" class="mb-2 block text-sm font-bold">{{ t('admin.category') }}</label><select id="course-category" v-model="form.category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3"><option v-for="category in categories" :key="category.id" :value="category.id" :disabled="!category.is_active && category.id !== course.category?.id">{{ category.name }}</option></select><button v-if="categoryPage < categoryLastPage" type="button" class="mt-2 min-h-11 text-sm font-bold text-brand underline" @click="loadCategories">{{ t('admin.loadMoreCategories') }}</button><p v-if="error?.errors?.category_id" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.category_id[0] }}</p></div><div><label for="course-level" class="mb-2 block text-sm font-bold">{{ t('admin.level') }}</label><select id="course-level" v-model="form.level" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option v-for="level in levels" :key="level" :value="level">{{ t(`labels.levels.${level}`) }}</option></select></div><div><label for="course-language" class="mb-2 block text-sm font-bold">{{ t('admin.language') }}</label><input id="course-language" v-model="form.language" maxlength="10" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div><div><label for="course-duration" class="mb-2 block text-sm font-bold">{{ t('admin.duration') }}</label><input id="course-duration" v-model="form.duration_minutes" type="number" min="1" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div></div></section>
                <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.pricing') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="course-price" class="mb-2 block text-sm font-bold">{{ t('admin.price') }} ({{ course.currency }})</label><input id="course-price" v-model="form.price" type="text" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.price" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.price[0] }}</p></div><div><label for="course-compare" class="mb-2 block text-sm font-bold">{{ t('admin.comparePrice') }}</label><input id="course-compare" v-model="form.compare_price" type="text" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.compare_price" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.compare_price[0] }}</p></div><div><label for="discount-start" class="mb-2 block text-sm font-bold">{{ t('admin.discountStart') }}</label><input id="discount-start" v-model="form.discount_starts_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div><div><label for="discount-end" class="mb-2 block text-sm font-bold">{{ t('admin.discountEnd') }}</label><input id="discount-end" v-model="form.discount_ends_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div></div><div class="space-y-3"><p class="text-sm font-bold">{{ t('admin.access') }}</p><label class="flex items-center gap-3 text-sm"><input v-model="limitedAccess" type="radio" :value="false" class="size-4 accent-brand">{{ t('admin.lifetime') }}</label><label class="flex items-center gap-3 text-sm"><input v-model="limitedAccess" type="radio" :value="true" class="size-4 accent-brand">{{ t('admin.limited') }}</label><div v-if="limitedAccess"><label for="course-days" class="mb-2 block text-sm font-bold">{{ t('admin.accessDays') }}</label><input id="course-days" v-model="form.access_duration_days" type="number" min="1" required class="w-full max-w-xs rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.access_duration_days" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.access_duration_days[0] }}</p></div><p class="text-xs text-slate-500">{{ t('admin.accessNote') }}</p></div></section>
                <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.publishing') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="course-status" class="mb-2 block text-sm font-bold">{{ t('admin.status') }}</label><select id="course-status" v-model="form.status" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option v-for="status in statuses" :key="status" :value="status" :disabled="status === 'published' && status !== course.status && !auth.can('courses.publish')">{{ t(`admin.courseStatus.${status}`) }}</option></select><p v-if="error?.errors?.status" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.status[0] }}</p></div><div><label for="course-published-at" class="mb-2 block text-sm font-bold">{{ t('admin.publishedAt') }}</label><input id="course-published-at" v-model="form.published_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div></div><div class="flex flex-wrap gap-5"><label class="flex items-center gap-2 text-sm font-bold"><input v-model="form.certificate_enabled" type="checkbox" class="size-5 accent-brand">{{ t('admin.certificate') }}</label><label class="flex items-center gap-2 text-sm font-bold"><input v-model="form.discussion_enabled" type="checkbox" class="size-5 accent-brand">{{ t('admin.discussion') }}</label><label class="flex items-center gap-2 text-sm font-bold"><input v-model="form.is_featured" type="checkbox" class="size-5 accent-brand">{{ t('admin.featured') }}</label></div></section>
                <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.media') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="course-thumbnail" class="mb-2 block text-sm font-bold">{{ t('admin.thumbnail') }}</label><input id="course-thumbnail" v-model="form.thumbnail" maxlength="2048" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.thumbnail" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.thumbnail[0] }}</p></div><div><label for="course-video" class="mb-2 block text-sm font-bold">{{ t('admin.promoVideo') }}</label><input id="course-video" v-model="form.promo_video_url" type="url" maxlength="2048" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.promo_video_url" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.promo_video_url[0] }}</p></div></div></section>
                <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.settings') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div v-for="key in Object.keys(listFields)" :key="key"><label :for="`course-${key}`" class="mb-2 block text-sm font-bold">{{ t(`admin.${key}`) }}</label><textarea :id="`course-${key}`" v-model="lists[key]" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><p v-if="error?.errors?.[key]" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors[key][0] }}</p></div></div></section>
                <div class="flex flex-wrap gap-3"><BaseButton type="submit" :loading="busy">{{ t('admin.save') }}</BaseButton><RouterLink :to="{ name: 'admin.courses.index' }" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-bold">{{ t('admin.cancel') }}</RouterLink></div>
            </form>
        </template>
        <BaseAlert v-else tone="danger">{{ t(error?.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }}</BaseAlert>
    </div>
</template>
