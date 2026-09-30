<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { fetchMyCourses } from '../api/learning';
import { fetchOrders } from '../api/commerce';
import { createTicket } from '../api/support';
import { attachmentExtensions, hasAttachmentErrors, ticketCategories, ticketFormData, validateAttachments } from '../utils/support';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import PageHeading from '../components/ui/PageHeading.vue';

const { t, locale } = useI18n();
const router = useRouter();
const form = reactive({ subject: '', category: '', body: '', related_course_id: '', related_order_id: '' });
const files = ref([]);
const courses = ref([]);
const orders = ref([]);
const coursePage = ref(0);
const orderPage = ref(0);
const courseLastPage = ref(1);
const orderLastPage = ref(1);
const optionsError = ref(false);
const validation = ref(null);
const error = ref(null);
const busy = ref(false);
let courseLoading = false;
let orderLoading = false;

async function loadCourses() {
    if (courseLoading || coursePage.value >= courseLastPage.value) return;
    courseLoading = true;
    try { const result = await fetchMyCourses(coursePage.value + 1); courses.value.push(...result.items.filter((item) => item.has_access).map((item) => item.course)); coursePage.value = result.meta?.current_page ?? coursePage.value + 1; courseLastPage.value = result.meta?.last_page ?? coursePage.value; }
    catch { optionsError.value = true; }
    finally { courseLoading = false; }
}
async function loadOrders() {
    if (orderLoading || orderPage.value >= orderLastPage.value) return;
    orderLoading = true;
    try { const result = await fetchOrders(orderPage.value + 1); orders.value.push(...result.items); orderPage.value = result.meta?.current_page ?? orderPage.value + 1; orderLastPage.value = result.meta?.last_page ?? orderPage.value; }
    catch { optionsError.value = true; }
    finally { orderLoading = false; }
}
onMounted(() => { loadCourses(); loadOrders(); });

function selectFiles(event) { files.value = Array.from(event.target.files ?? []); validation.value = validateAttachments(files.value); }
async function submit() {
    if (busy.value) return;
    validation.value = !form.subject.trim() || form.subject.length > 255 ? 'subjectRequired' : !ticketCategories.includes(form.category) ? 'categoryRequired' : !form.body.trim() || form.body.length > 10000 ? 'bodyRequired' : validateAttachments(files.value);
    if (validation.value) return;
    busy.value = true;
    error.value = null;
    try {
        const ticket = await createTicket(ticketFormData({ subject: form.subject.trim(), category: form.category, body: form.body.trim(), related_course_id: form.related_course_id, related_order_id: form.related_order_id }, files.value));
        await router.push({ name: 'student.support.show', params: { id: ticket.id } });
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><RouterLink :to="{ name: 'student.support.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink><PageHeading :title="t('support.createHeading')" :description="t('support.createDescription')" />
        <BaseAlert v-if="optionsError">{{ t('support.relatedLoadError') }}</BaseAlert><BaseAlert v-if="error" tone="danger">{{ t('support.createFailed') }}</BaseAlert>
        <form class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" @submit.prevent="submit"><div><label for="ticket-subject" class="mb-2 block text-sm font-bold">{{ t('support.subject') }}</label><input id="ticket-subject" v-model="form.subject" maxlength="255" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand focus:outline-none" :disabled="busy"><p v-if="validation === 'subjectRequired' || error?.errors?.subject" role="alert" class="mt-1 text-sm text-red-700">{{ t('support.subjectRequired') }}</p></div>
            <div><label for="ticket-category" class="mb-2 block text-sm font-bold">{{ t('support.category') }}</label><select id="ticket-category" v-model="form.category" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand focus:outline-none" :disabled="busy"><option value="" disabled>{{ t('support.categoryRequired') }}</option><option v-for="category in ticketCategories" :key="category" :value="category">{{ t(`support.categories.${category}`) }}</option></select><p v-if="validation === 'categoryRequired' || error?.errors?.category" role="alert" class="mt-1 text-sm text-red-700">{{ t('support.categoryRequired') }}</p></div>
            <div><label for="ticket-body" class="mb-2 block text-sm font-bold">{{ t('support.message') }}</label><textarea id="ticket-body" v-model="form.body" rows="6" maxlength="10000" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand focus:outline-none" :disabled="busy" /><p v-if="validation === 'bodyRequired' || error?.errors?.body" role="alert" class="mt-1 text-sm text-red-700">{{ t('support.bodyRequired') }}</p></div>
            <div class="grid gap-5 sm:grid-cols-2"><div><label for="related-course" class="mb-2 block text-sm font-bold">{{ t('support.relatedCourse') }}</label><select id="related-course" v-model="form.related_course_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" :disabled="busy"><option value="">{{ t('support.noRelated') }}</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option></select><button v-if="coursePage < courseLastPage" type="button" class="mt-2 min-h-11 text-sm font-bold text-brand underline" @click="loadCourses">{{ t('support.loadMore') }}</button><p v-if="error?.errors?.related_course_id" role="alert" class="mt-1 text-sm text-red-700">{{ t('support.relatedLoadError') }}</p></div><div><label for="related-order" class="mb-2 block text-sm font-bold">{{ t('support.relatedOrder') }}</label><select id="related-order" v-model="form.related_order_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" :disabled="busy"><option value="">{{ t('support.noRelated') }}</option><option v-for="order in orders" :key="order.id" :value="order.id">{{ order.order_number }}</option></select><button v-if="orderPage < orderLastPage" type="button" class="mt-2 min-h-11 text-sm font-bold text-brand underline" @click="loadOrders">{{ t('support.loadMore') }}</button><p v-if="error?.errors?.related_order_id" role="alert" class="mt-1 text-sm text-red-700">{{ t('support.relatedLoadError') }}</p></div></div>
            <div><label for="ticket-files" class="mb-2 block text-sm font-bold">{{ t('support.files') }}</label><input id="ticket-files" type="file" multiple :accept="attachmentExtensions" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm file:me-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-3 file:py-2 file:font-bold file:text-brand" :disabled="busy" @change="selectFiles"><p class="mt-1 text-xs text-slate-500">{{ t('support.fileHint') }}</p><p v-if="['tooManyFiles', 'invalidFile'].includes(validation) || hasAttachmentErrors(error?.errors)" role="alert" class="mt-1 text-sm text-red-700">{{ t(`support.${validation === 'tooManyFiles' ? validation : 'invalidFile'}`) }}</p></div>
            <BaseButton type="submit" :loading="busy">{{ t('support.sendTicket') }}</BaseButton>
        </form>
    </div>
</template>
