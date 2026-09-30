<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { createAdminCategory, fetchAdminCategories, fetchAdminCategory, updateAdminCategory } from '../api/admin';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const isEdit = computed(() => Boolean(route.params.id));
const form = reactive({ name: '', slug: '', description: '', parent_id: '', image: '', icon: '', sort_order: 0, is_active: true });
const parents = ref([]);
const parentPage = ref(0);
const parentLastPage = ref(1);
const loading = ref(true);
const categoryLoaded = ref(false);
const optionsError = ref(false);
const error = ref(null);
const busy = ref(false);

async function loadParents() {
    if (parentPage.value >= parentLastPage.value) return;
    try {
        const result = await fetchAdminCategories(parentPage.value + 1);
        parents.value.push(...result.items.filter((item) => item.id !== Number(route.params.id) && !parents.value.some((parent) => parent.id === item.id)));
        parentPage.value = result.meta?.current_page ?? parentPage.value + 1;
        parentLastPage.value = result.meta?.last_page ?? parentPage.value;
    } catch { optionsError.value = true; }
}
onMounted(async () => {
    try {
        if (isEdit.value) {
            const category = await fetchAdminCategory(route.params.id);
            categoryLoaded.value = true;
            Object.assign(form, { name: category.name, slug: category.slug, description: category.description ?? '', parent_id: category.parent_id ?? '', image: category.image ?? '', icon: category.icon ?? '', sort_order: category.sort_order, is_active: category.is_active });
            if (category.parent_id) {
                const parent = await fetchAdminCategory(category.parent_id);
                parents.value.push(parent);
            }
        }
        if (!isEdit.value) { categoryLoaded.value = true; await loadParents(); }
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
});
async function submit() {
    if (busy.value) return;
    busy.value = true;
    error.value = null;
    const payload = { ...form, name: form.name.trim(), slug: form.slug.trim(), parent_id: form.parent_id || null, description: form.description || null, image: form.image || null, icon: form.icon || null };
    if (isEdit.value && payload.parent_id !== null) delete payload.parent_id;
    try {
        if (isEdit.value) await updateAdminCategory(route.params.id, payload);
        else await createAdminCategory(payload);
        await router.push({ name: 'admin.categories.index' });
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'admin.categories.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink>
        <PageHeading :title="t(isEdit ? 'admin.editCategory' : 'admin.addCategory')" />
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="!categoryLoaded" tone="danger">{{ t(error?.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }}</BaseAlert>
        <template v-else>
            <BaseAlert v-if="optionsError">{{ t('admin.loadError') }}</BaseAlert>
            <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : error.status === 403 ? 'admin.notAllowed' : 'admin.saveError') }}</BaseAlert>
            <form class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" @submit.prevent="submit">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="category-name" class="mb-2 block text-sm font-bold">{{ t('admin.name') }}</label><input id="category-name" v-model="form.name" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3" :aria-invalid="Boolean(error?.errors?.name)"><p v-if="error?.errors?.name" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.name[0] }}</p></div>
                    <div><label for="category-slug" class="mb-2 block text-sm font-bold">{{ t('admin.slug') }}</label><input id="category-slug" v-model="form.slug" required maxlength="255" pattern="[A-Za-z0-9_-]+" class="w-full rounded-xl border border-slate-300 px-4 py-3" :aria-invalid="Boolean(error?.errors?.slug)"><p class="mt-1 text-xs text-slate-500">{{ t('admin.slugHint') }}</p><p v-if="error?.errors?.slug" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.slug[0] }}</p></div>
                    <div><label for="category-parent" class="mb-2 block text-sm font-bold">{{ t('admin.parent') }}</label><select id="category-parent" v-model="form.parent_id" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option value="">{{ t('admin.noParent') }}</option><option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option></select><button v-if="!isEdit && parentPage < parentLastPage" type="button" class="mt-2 min-h-11 text-sm font-bold text-brand underline" @click="loadParents">{{ t('admin.loadMoreCategories') }}</button><p v-if="isEdit" class="mt-1 text-xs text-slate-500">{{ t('admin.parentSafety') }}</p><p v-if="error?.errors?.parent_id" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.parent_id[0] }}</p></div>
                    <div><label for="category-sort" class="mb-2 block text-sm font-bold">{{ t('admin.sortOrder') }}</label><input id="category-sort" v-model.number="form.sort_order" type="number" min="0" required class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.sort_order" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.sort_order[0] }}</p></div>
                    <div><label for="category-image" class="mb-2 block text-sm font-bold">{{ t('admin.image') }}</label><input id="category-image" v-model="form.image" maxlength="2048" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.image" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.image[0] }}</p></div>
                    <div><label for="category-icon" class="mb-2 block text-sm font-bold">{{ t('admin.icon') }}</label><input id="category-icon" v-model="form.icon" maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.icon" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.icon[0] }}</p></div>
                </div>
                <div><label for="category-description" class="mb-2 block text-sm font-bold">{{ t('admin.description') }}</label><textarea id="category-description" v-model="form.description" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><p v-if="error?.errors?.description" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.description[0] }}</p></div>
                <label class="flex items-center gap-3 text-sm font-bold"><input v-model="form.is_active" type="checkbox" class="size-5 accent-brand">{{ t('admin.active') }}</label>
                <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5"><BaseButton type="submit" :loading="busy">{{ t(isEdit ? 'admin.save' : 'admin.create') }}</BaseButton><RouterLink :to="{ name: 'admin.categories.index' }" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-bold">{{ t('admin.cancel') }}</RouterLink></div>
            </form>
        </template>
    </div>
</template>
