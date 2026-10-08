import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';
import * as commerce from '../api/commerce';
import { useAuthStore } from './auth';

const intentStorageKey = 'jcec.checkout-intent';
function storedIntent() { try { return sessionStorage.getItem(intentStorageKey); } catch { return null; } }
function saveIntent(key) { try { if (key) sessionStorage.setItem(intentStorageKey, key); else sessionStorage.removeItem(intentStorageKey); } catch {} }

export const useCartStore = defineStore('cart', () => {
    const auth = useAuthStore();
    const cart = ref(null);
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

    const items = computed(() => cart.value?.items ?? []);
    const count = computed(() => cart.value?.item_count ?? 0);
    const total = computed(() => cart.value?.estimated_total ?? null);
    const currency = computed(() => cart.value?.currency ?? null);
    const ready = computed(() => Boolean(cart.value && count.value && currency.value && !cart.value.coupon_error && items.value.every((item) => item.available && item.product)));
    const hasItem = (type, id) => items.value.some((item) => item.purchasable_type === type && item.purchasable_id === id);

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
            if (!checkoutKey.value) { checkoutKey.value = crypto.randomUUID(); saveIntent(checkoutKey.value); }
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

    return { cart, items, count, total, currency, ready, loading, mutating, error, hasItem, load, add, remove, clear, applyCoupon, removeCoupon, placeOrder, checkoutLoading, checkoutKey, uncertain, intentCustomer, newIntent };
});
