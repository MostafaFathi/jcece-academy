<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminCourses } from '../api/admin';
import { addPackageCourse, fetchAdminPackage, fetchPackageCourses, removePackageCourse, reorderPackageCourses, updatePackageCourse } from '../api/admin-packages';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';

const route = useRoute();
const auth = useAuthStore();
const { t, locale } = useI18n();
const packageId = route.params.id;
const item = ref(null);
const memberships = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const options = ref([]);
const optionSearch = ref('');
const optionPage = ref(0);
const optionLastPage = ref(1);
const optionError = ref(false);
const selectedCourseId = ref('');
const selectedCourse = ref(null);
const isRequired = ref(true);
const memberIds = computed(() => new Set(memberships.value.map((membership) => membership.course_id)));
const courseOptions = computed(() => {
    const choices = [...options.value];
    if (selectedCourse.value && !choices.some((course) => course.id === selectedCourse.value.id)) choices.unshift(selectedCourse.value);
    return choices;
});
let optionSequence = 0;
async function refreshMemberships() { memberships.value = await fetchPackageCourses(packageId); }
async function load() {
    loading.value = true; error.value = null;
    try { [item.value, memberships.value] = await Promise.all([fetchAdminPackage(packageId), fetchPackageCourses(packageId)]); if (auth.can('packages.update') && auth.can('courses.view')) await loadOptions(); }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
async function loadOptions(reset = false) {
    if (reset) { optionPage.value = 0; optionLastPage.value = 1; options.value = []; }
    if (optionPage.value >= optionLastPage.value) return;
    const current = ++optionSequence;
    optionError.value = false;
    try {
        const result = await fetchAdminCourses({ page: optionPage.value + 1, ...(optionSearch.value ? { search: optionSearch.value } : {}) });
        if (current !== optionSequence) return;
        options.value.push(...result.items.filter((course) => !options.value.some((existing) => existing.id === course.id)));
        optionPage.value = result.meta?.current_page ?? optionPage.value + 1;
        optionLastPage.value = result.meta?.last_page ?? optionPage.value;
    } catch { if (current === optionSequence) optionError.value = true; }
}
function chooseCourse() { selectedCourse.value = courseOptions.value.find((course) => course.id === Number(selectedCourseId.value)) ?? null; }
async function add() {
    if (busy.value || !selectedCourseId.value || memberIds.value.has(Number(selectedCourseId.value))) return;
    busy.value = true; error.value = null;
    try { await addPackageCourse(packageId, { course_id: Number(selectedCourseId.value), is_required: isRequired.value }); await refreshMemberships(); selectedCourseId.value = ''; selectedCourse.value = null; }
    catch (failure) { error.value = failure; await refreshMemberships(); }
    finally { busy.value = false; }
}
async function remove(membership) {
    if (busy.value || !window.confirm(t('packages.confirmRemoveCourse'))) return;
    busy.value = true; error.value = null;
    try { await removePackageCourse(packageId, membership.id); await refreshMemberships(); }
    catch (failure) { error.value = failure; await refreshMemberships(); }
    finally { busy.value = false; }
}
async function toggleRequired(membership) {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try { await updatePackageCourse(packageId, membership.id, { is_required: !membership.is_required }); await refreshMemberships(); }
    catch (failure) { error.value = failure; await refreshMemberships(); }
    finally { busy.value = false; }
}
async function move(index, direction) {
    if (busy.value) return;
    const target = index + direction;
    if (target < 0 || target >= memberships.value.length) return;
    const ids = memberships.value.map((membership) => membership.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];
    busy.value = true; error.value = null;
    try { await reorderPackageCourses(packageId, ids); await refreshMemberships(); }
    catch (failure) { error.value = failure; await refreshMemberships(); }
    finally { busy.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'admin.packages.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink>
        <PageHeading :title="t('packages.manageCourses')" :description="item?.title ?? ''"><template #actions><RouterLink v-if="item && auth.can('packages.update')" :to="{ name: 'admin.packages.edit', params: { id: item.id } }" class="inline-flex min-h-11 items-center rounded-xl border border-brand px-5 py-2.5 text-sm font-bold text-brand">{{ t('packages.edit') }}</RouterLink></template></PageHeading>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="error && !item" tone="danger">{{ t(error.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <template v-else-if="item">
            <BaseAlert>{{ t('packages.futurePurchases') }}</BaseAlert>
            <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : error.status === 403 ? 'admin.notAllowed' : 'admin.saveError') }}<span v-if="error?.errors?.course_id"> {{ error.errors.course_id[0] }}</span><span v-if="error?.errors?.ids"> {{ error.errors.ids[0] }}</span></BaseAlert>
            <section v-if="auth.can('packages.update')" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('packages.addCourse') }}</h2><div class="flex flex-wrap gap-2"><label class="sr-only" for="package-course-search">{{ t('packages.searchCourse') }}</label><input id="package-course-search" v-model="optionSearch" type="search" :placeholder="t('packages.searchCourse')" class="min-h-11 min-w-48 flex-1 rounded-xl border border-slate-300 px-4"><button type="button" class="rounded-xl border border-slate-300 px-5 text-sm font-bold" @click="loadOptions(true)">{{ t('packages.searchAction') }}</button></div><BaseAlert v-if="optionError" tone="danger">{{ t('packages.optionError') }} <button type="button" class="font-bold underline" @click="loadOptions">{{ t('common.retry') }}</button></BaseAlert><div class="flex flex-wrap items-end gap-3"><div class="min-w-48 flex-1"><label for="package-course-select" class="mb-1 block text-sm font-bold">{{ t('admin.courses') }}</label><select id="package-course-select" v-model="selectedCourseId" class="w-full rounded-xl border border-slate-300 px-4 py-3" @change="chooseCourse"><option value="">{{ t('packages.selectCourse') }}</option><option v-for="course in courseOptions" :key="course.id" :value="course.id" :disabled="memberIds.has(course.id)">{{ course.title }} — {{ t(`admin.courseStatus.${course.status}`) }}{{ memberIds.has(course.id) ? ` (${t('packages.alreadyAdded')})` : '' }}</option></select></div><label class="flex min-h-11 items-center gap-2 text-sm"><input v-model="isRequired" type="checkbox" class="size-5 accent-brand">{{ t('packages.requiredCourse') }}</label><BaseButton :loading="busy" :disabled="!selectedCourseId || memberIds.has(Number(selectedCourseId))" @click="add">{{ t('packages.addCourse') }}</BaseButton></div><button v-if="optionPage < optionLastPage" type="button" class="min-h-11 text-sm font-bold text-brand underline" @click="loadOptions">{{ t('packages.loadMoreCourses') }}</button></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('packages.currentCourses') }}</h2><p v-if="!memberships.length" class="mt-4 text-sm text-slate-600">{{ t('packages.noSelectedCourses') }}</p><ol class="mt-4 space-y-3"><li v-for="(membership, index) in memberships" :key="membership.id" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 p-4"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-black text-white">{{ index + 1 }}</span><div class="min-w-0 flex-1"><p class="font-bold">{{ membership.course?.title ?? t('packages.unavailableCourse') }}</p><p class="text-xs text-slate-600">{{ membership.course?.status ? t(`admin.courseStatus.${membership.course.status}`) : '—' }} · {{ membership.is_required ? t('packages.requiredCourse') : t('packages.optionalCourse') }}</p></div><div v-if="auth.can('packages.update')" class="flex flex-wrap gap-1"><button type="button" class="min-h-11 rounded-xl border px-3 disabled:opacity-40" :disabled="busy || index === 0" :aria-label="t('curriculum.moveEarlier')" @click="move(index, -1)">↑</button><button type="button" class="min-h-11 rounded-xl border px-3 disabled:opacity-40" :disabled="busy || index === memberships.length - 1" :aria-label="t('curriculum.moveLater')" @click="move(index, 1)">↓</button><button type="button" class="min-h-11 px-2 text-sm font-bold text-brand" :disabled="busy" @click="toggleRequired(membership)">{{ membership.is_required ? t('packages.makeOptional') : t('packages.makeRequired') }}</button><button type="button" class="min-h-11 px-2 text-sm font-bold text-red-700" :disabled="busy" @click="remove(membership)">{{ t('admin.delete') }}</button></div></li></ol></section>
        </template>
    </div>
</template>
