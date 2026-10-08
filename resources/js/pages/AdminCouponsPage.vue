<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { createCoupon, fetchCoupons, updateCoupon } from '../api/admin-operations';
import { fetchAdminCourse, fetchAdminCourses } from '../api/admin';
import { fetchAdminPackage, fetchAdminPackages } from '../api/admin-packages';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import AsyncResourceSelect from '../components/ui/AsyncResourceSelect.vue';

const auth = useAuthStore();
const { t, locale } = useI18n();
const coupons = ref([]);
const loading = ref(false);
const busy = ref(false);
const error = ref(null);
const selected = ref(null);
const search = ref('');
const activeFilter = ref('');
const currentPage = ref(1);
const lastPage = ref(1);
const form = reactive({ code: '', is_active: false, discount_type: 'fixed', discount_value: '10.00', starts_at: '', expires_at: '', usage_limit: '', per_user_limit: '', minimum_order_amount: '', applies_to: 'all', product_ids: [] });
const productLoader = computed(() => form.applies_to === 'package' ? fetchAdminPackages : fetchAdminCourses);
const productResolver = computed(() => form.applies_to === 'package' ? fetchAdminPackage : fetchAdminCourse);
const productSelectionError = ref(false);
function dateInput(value) { if (!value) return ''; const d = new Date(value); return Number.isNaN(d.getTime()) ? '' : new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16); }
function edit(coupon) {
    selected.value = coupon;
    Object.assign(form, { code: coupon.code, is_active: coupon.is_active, discount_type: coupon.discount_type, discount_value: coupon.discount_value, starts_at: dateInput(coupon.starts_at), expires_at: dateInput(coupon.expires_at), usage_limit: coupon.usage_limit ?? '', per_user_limit: coupon.per_user_limit ?? '', minimum_order_amount: coupon.minimum_order_amount ?? '', applies_to: coupon.applies_to, product_ids: [...(coupon.product_ids ?? [])] });
    productSelectionError.value = false;
}
function reset() { selected.value = null; Object.assign(form, { code: '', is_active: false, discount_type: 'fixed', discount_value: '10.00', starts_at: '', expires_at: '', usage_limit: '', per_user_limit: '', minimum_order_amount: '', applies_to: 'all', product_ids: [] }); error.value = null; productSelectionError.value = false; }
async function load(page = 1) { loading.value = true; error.value = null; try { const result = await fetchCoupons({ ...(search.value ? { search: search.value } : {}), ...(activeFilter.value !== '' ? { active: activeFilter.value } : {}), page }); coupons.value = result.data ?? []; currentPage.value = result.current_page ?? 1; lastPage.value = result.last_page ?? 1; } catch (failure) { error.value = failure; } finally { loading.value = false; } }
async function save() {
    if (busy.value) return;
    productSelectionError.value = form.applies_to !== 'all' && form.product_ids.length === 0;
    if (productSelectionError.value) return;
    busy.value = true; error.value = null;
    const payload = { code: form.code, is_active: form.is_active, discount_type: form.discount_type, discount_value: form.discount_value, starts_at: form.starts_at ? new Date(form.starts_at).toISOString() : null, expires_at: form.expires_at ? new Date(form.expires_at).toISOString() : null, usage_limit: form.usage_limit === '' ? null : Number(form.usage_limit), per_user_limit: form.per_user_limit === '' ? null : Number(form.per_user_limit), minimum_order_amount: form.minimum_order_amount === '' ? null : form.minimum_order_amount, applies_to: form.applies_to, product_ids: form.applies_to === 'all' ? [] : [...form.product_ids] };
    try { if (selected.value) await updateCoupon(selected.value.id, payload); else await createCoupon(payload); reset(); await load(currentPage.value); } catch (failure) { error.value = failure; } finally { busy.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('commerce.coupons')" />
        <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : 'admin.saveError') }}</BaseAlert>
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><form class="flex flex-wrap gap-3" @submit.prevent="load(1)"><input v-model="search" type="search" :aria-label="t('common.search')" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3"><select v-model="activeFilter" :aria-label="t('admin.status')" class="rounded-xl border border-slate-300 px-4 py-3"><option value="">{{ t('admin.allStatuses') }}</option><option value="1">{{ t('admin.active') }}</option><option value="0">{{ t('admin.inactive') }}</option></select><BaseButton type="submit">{{ t('common.search') }}</BaseButton></form><LoadingState v-if="loading" /><p v-else-if="!coupons.length" class="mt-5 text-sm text-slate-500">{{ t('commerce.coupons') }}: 0</p><div v-else class="mt-5 grid gap-3 sm:grid-cols-2"><button v-for="coupon in coupons" :key="coupon.id" type="button" class="rounded-2xl border border-slate-200 p-4 text-start hover:border-brand" @click="edit(coupon)"><strong class="block text-lg text-brand"><bdi>{{ coupon.code }}</bdi></strong><span class="text-sm text-slate-600">{{ coupon.discount_value }} {{ coupon.discount_type === 'percentage' ? '%' : '' }} · {{ coupon.is_active ? t('admin.active') : t('admin.inactive') }}</span><span class="mt-2 block text-xs text-slate-500">{{ t('commerce.reserved') }}: {{ coupon.reserved_count }} · {{ t('commerce.consumed') }}: {{ coupon.consumed_count }}</span></button></div><div v-if="lastPage > 1" class="mt-5 flex items-center justify-between gap-3 text-sm"><button type="button" :disabled="currentPage <= 1 || loading" class="font-bold text-brand disabled:opacity-40" @click="load(currentPage - 1)">{{ t('common.previous') }}</button><span>{{ currentPage }} / {{ lastPage }}</span><button type="button" :disabled="currentPage >= lastPage || loading" class="font-bold text-brand disabled:opacity-40" @click="load(currentPage + 1)">{{ t('common.next') }}</button></div></section>
        <section v-if="auth.can('coupons.manage')" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5 flex items-center justify-between gap-3"><h2 class="text-xl font-black">{{ selected ? selected.code : t('commerce.newCoupon') }}</h2><button type="button" class="text-sm font-bold text-brand underline" @click="reset">{{ t('commerce.newCoupon') }}</button></div>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.couponCode') }}<input v-model="form.code" required minlength="3" maxlength="64" pattern="[A-Za-z0-9_-]+" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="error?.errors?.code" role="alert" class="block text-red-700">{{ error.errors.code[0] }}</span></label>
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.couponType') }}<select v-model="form.discount_type" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><option value="fixed">{{ t('commerce.fixed') }}</option><option value="percentage">{{ t('commerce.percentage') }}</option></select></label>
                <label class="space-y-1 text-sm font-bold">{{ form.discount_type === 'percentage' ? t('commerce.discountPercentage') : t('commerce.discountAmount') }}<input v-model="form.discount_value" type="number" required min="0.01" :max="form.discount_type === 'percentage' ? 100 : 9999999999.99" step="0.01" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="error?.errors?.discount_value" role="alert" class="block text-red-700">{{ error.errors.discount_value[0] }}</span></label>
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.minimumOrder') }}<input v-model="form.minimum_order_amount" type="number" min="0" max="9999999999.99" step="0.01" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="error?.errors?.minimum_order_amount" role="alert" class="block text-red-700">{{ error.errors.minimum_order_amount[0] }}</span></label>
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.startsAt') }}<input v-model="form.starts_at" type="datetime-local" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="error?.errors?.starts_at" role="alert" class="block text-red-700">{{ error.errors.starts_at[0] }}</span></label>
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.expiresAt') }}<input v-model="form.expires_at" type="datetime-local" :min="form.starts_at || undefined" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="error?.errors?.expires_at" role="alert" class="block text-red-700">{{ error.errors.expires_at[0] }}</span></label>
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.usageLimit') }}<input v-model="form.usage_limit" type="number" min="1" step="1" class="block w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.perUserLimit') }}<input v-model="form.per_user_limit" type="number" min="1" step="1" class="block w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                <label class="space-y-1 text-sm font-bold">{{ t('commerce.couponScope') }}<select v-model="form.applies_to" class="block w-full rounded-xl border border-slate-300 px-4 py-3" @change="form.product_ids = []; productSelectionError = false"><option value="all">{{ t('commerce.allProducts') }}</option><option value="course">{{ t('commerce.coursesOnly') }}</option><option value="package">{{ t('commerce.packagesOnly') }}</option></select></label>
                <div v-if="form.applies_to !== 'all'" class="min-w-0 sm:col-span-2"><AsyncResourceSelect :key="form.applies_to" v-model="form.product_ids" id="coupon-products" multiple :loader="productLoader" :resolver="productResolver" :label="t(form.applies_to === 'course' ? 'commerce.selectCourses' : 'commerce.selectPackages')" /><p v-if="productSelectionError || error?.errors?.product_ids" role="alert" class="text-sm text-red-700">{{ error?.errors?.product_ids?.[0] ?? t('commerce.chooseProduct') }}</p></div>
                <label class="flex items-center gap-3 text-sm font-bold"><input v-model="form.is_active" type="checkbox" class="size-5 accent-brand">{{ t('admin.active') }}</label>
                <div class="sm:col-span-2"><BaseButton type="submit" :loading="busy">{{ t('commerce.saveCoupon') }}</BaseButton></div>
            </form>
        </section>
    </div>
</template>
