import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';
import * as commerce from '../api/commerce';
import { useAuthStore } from './auth';
import { createRequestId } from '../utils/request-id';

const intentStorageKey = 'jcec.checkout-intent';
const guestCartStorageKey = 'jcec.guest-cart.v1';
function storedIntent() { try { return sessionStorage.getItem(intentStorageKey); } catch { return null; } }
function saveIntent(key) { try { if (key) sessionStorage.setItem(intentStorageKey, key); else sessionStorage.removeItem(intentStorageKey); } catch {} }
function readGuestCart() {
    try {
        const entries = JSON.parse(sessionStorage.getItem(guestCartStorageKey) || '[]');
        if (!Array.isArray(entries)) return [];
        return entries.filter((entry, index) => entry && ['course', 'package'].includes(entry.type)
            && Number.isSafeInteger(entry.id) && entry.id > 0
            && entries.findIndex((candidate) => candidate?.type === entry.type && candidate?.id === entry.id) === index).slice(0, 20)
            .map(({ type, id }) => ({ type, id }));
    } catch { return []; }
}
function writeGuestCart(entries) {
    try {
        if (entries.length) sessionStorage.setItem(guestCartStorageKey, JSON.stringify(entries));
        else sessionStorage.removeItem(guestCartStorageKey);
        return true;
    } catch { return false; }
}

export const useCartStore = defineStore('cart', () => {
    const auth = useAuthStore();
    const cart = ref(null);
    const guestItems = ref(readGuestCart());
    const loading = ref(false);
    const mutating = ref(false);
    const error = ref(null);
    const checkoutLoading = ref(false);
    const checkoutKey = ref(storedIntent());
    const uncertain = ref(Boolean(checkoutKey.value));
    const intentCustomer = ref(null);
    let submittedPayload = null;
    let generation = 0;
    let loadPromise = null;
    let mergePromise = null;

    const items = computed(() => cart.value?.items ?? []);
    const count = computed(() => cart.value?.item_count ?? 0);
    const guestCount = computed(() => guestItems.value.length);
    const total = computed(() => cart.value?.estimated_total ?? null);
    const currency = computed(() => cart.value?.currency ?? null);
    const ready = computed(() => Boolean(cart.value && count.value && currency.value && !guestCount.value && !cart.value.coupon_error && items.value.every((item) => item.available && item.product)));
    const hasItem = (type, id) => items.value.some((item) => item.purchasable_type === type && item.purchasable_id === id);
    const hasGuestItem = (type, id) => guestItems.value.some((item) => item.type === type && item.id === id);

    function addGuest(type, id) {
        if (!['course', 'package'].includes(type) || !Number.isSafeInteger(id) || id < 1) return false;
        if (hasGuestItem(type, id)) return true;
        if (guestCount.value >= 20) return false;
        const next = [...guestItems.value, { type, id }];
        if (!writeGuestCart(next)) return false;
        guestItems.value = next;
        return true;
    }

    function removeGuest(type, id) {
        const next = guestItems.value.filter((item) => item.type !== type || item.id !== id);
        if (writeGuestCart(next)) guestItems.value = next;
    }

    function accept(result) {
        if (!result || !Array.isArray(result.items) || result.item_count !== result.items.length
            || !/^\d+\.\d{2}$/.test(result.estimated_total)
            || (result.currency !== null && !/^[A-Z]{3}$/.test(result.currency))) {
            cart.value = null;
            throw new Error('Invalid cart response.');
        }
        cart.value = result;
        return result;
    }

    async function load() {
        if (loadPromise) return loadPromise;
        if (mutating.value) return cart.value;
        const currentGeneration = generation;
        loading.value = true;
        error.value = null;
        loadPromise = commerce.fetchCart().then((result) => {
            if (generation === currentGeneration) return accept(result);
        }).catch((requestError) => {
            if (generation === currentGeneration) { cart.value = null; error.value = requestError; }
            throw requestError;
        }).finally(() => {
            if (generation === currentGeneration) { loading.value = false; loadPromise = null; }
        });
        return loadPromise;
    }

    async function mutate(operation) {
        if (mutating.value || checkoutLoading.value || uncertain.value) return null;
        mutating.value = true;
        error.value = null;
        const currentGeneration = generation;
        try {
            if (loadPromise) await loadPromise;
            if (generation !== currentGeneration) return null;
            const result = await operation();
            return generation === currentGeneration ? accept(result) : null;
        } catch (requestError) {
            if (generation === currentGeneration) error.value = requestError;
            throw requestError;
        } finally {
            if (generation === currentGeneration) mutating.value = false;
        }
    }

    function add(type, id) { return hasItem(type, id) ? Promise.resolve(cart.value) : mutate(() => commerce.addCartItem(type, id)); }
    async function mergeGuest() {
        if (mergePromise) return mergePromise;
        mergePromise = (async () => {
            if (!cart.value) await load();
            if (!auth.isAuthenticated || !guestCount.value) return 0;
            if (uncertain.value) return 0;
            let skipped = 0;
            for (const chosen of [...guestItems.value]) {
                if (hasItem(chosen.type, chosen.id)) {
                    removeGuest(chosen.type, chosen.id);
                    continue;
                }
                try {
                    const merged = await add(chosen.type, chosen.id);
                    if (!merged) throw new Error('Cart merge is busy.');
                    removeGuest(chosen.type, chosen.id);
                } catch (requestError) {
                    if (requestError.status === 404 || requestError.status === 422) {
                        removeGuest(chosen.type, chosen.id);
                        skipped++;
                        continue;
                    }
                    error.value = requestError;
                    throw requestError;
                }
            }
            error.value = null;
            return skipped;
        })();
        try { return await mergePromise; } finally { mergePromise = null; }
    }
    function remove(id) { return mutate(() => commerce.removeCartItem(id)); }
    function clear() { return mutate(commerce.clearCart); }
    function applyCoupon(code) { return mutate(() => commerce.applyCartCoupon(code)); }
    function removeCoupon() { return mutate(commerce.removeCartCoupon); }

    function newIntent() {
        if (checkoutLoading.value) return;
        checkoutKey.value = null;
        submittedPayload = null;
        intentCustomer.value = null;
        uncertain.value = false;
        saveIntent(null);
    }

    async function placeOrder(customer) {
        if (checkoutLoading.value || mutating.value || loading.value) return null;
        checkoutLoading.value = true;
        error.value = null;
        const currentGeneration = generation;
        try {
            if (!checkoutKey.value) { checkoutKey.value = createRequestId(); saveIntent(checkoutKey.value); }
            submittedPayload ??= { ...customer, idempotency_key: checkoutKey.value, expected_total: cart.value?.estimated_total, expected_coupon_code: cart.value?.coupon_code ?? null };
            intentCustomer.value = { ...submittedPayload };
            const order = await commerce.checkout(submittedPayload);
            if (generation !== currentGeneration) return null;
            if (!order?.id) throw new Error('Invalid checkout response.');
            cart.value = null;
            checkoutKey.value = null;
            submittedPayload = null;
            intentCustomer.value = null;
            uncertain.value = false;
            saveIntent(null);
            await load().catch(() => {});
            return order;
        } catch (requestError) {
            if (generation === currentGeneration) {
                error.value = requestError;
                uncertain.value = !requestError.status || requestError.status >= 500;
                if (!uncertain.value) { submittedPayload = null; intentCustomer.value = null; }
                if (requestError.status === 409 || (requestError.status === 422 && requestError.errors?.coupon_code)) {
                    await load().catch(() => {});
                    error.value = requestError;
                }
            }
            throw requestError;
        } finally {
            if (generation === currentGeneration) checkoutLoading.value = false;
        }
    }

    watch(() => auth.user?.id, (id, previousId) => {
        generation++;
        cart.value = null;
        error.value = null;
        loading.value = false;
        mutating.value = false;
        checkoutLoading.value = false;
        loadPromise = null;
        if (previousId !== undefined) newIntent();
    }, { flush: 'sync' });

    return { cart, items, count, guestCount, total, currency, ready, loading, mutating, error, hasItem, hasGuestItem, addGuest, mergeGuest, load, add, remove, clear, applyCoupon, removeCoupon, placeOrder, checkoutLoading, checkoutKey, uncertain, intentCustomer, newIntent };
});
