import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import * as authApi from '../api/auth';
import { setLocale } from '../i18n';

let initializationPromise = null;

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const initialized = ref(false);
    const loading = ref(false);
    const loginLoading = ref(false);
    const logoutLoading = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const isGuest = computed(() => !isAuthenticated.value);
    const roles = computed(() => user.value?.roles ?? []);
    const permissions = computed(() => user.value?.permissions ?? []);
    const instructorProfile = computed(() => user.value?.instructor_profile ?? null);

    function hasRole(role) {
        return roles.value.includes(role);
    }

    function hasAnyRole(requiredRoles = []) {
        return requiredRoles.some(hasRole);
    }

    function can(permission) {
        return permissions.value.includes(permission);
    }

    function canAny(requiredPermissions = []) {
        return requiredPermissions.some(can);
    }

    const hasPermission = can;
    const hasAnyPermission = canAny;

    function setUser(authenticatedUser) {
        user.value = authenticatedUser;
        if (authenticatedUser?.preferred_locale === 'ar' || authenticatedUser?.preferred_locale === 'en') {
            setLocale(authenticatedUser.preferred_locale);
        }
    }

    function clearSession() {
        user.value = null;
    }

    async function initialize({ force = false } = {}) {
        if (initialized.value && !force) {
            return user.value;
        }

        if (initializationPromise) {
            return initializationPromise;
        }

        loading.value = true;
        initializationPromise = authApi.fetchAuthenticatedUser()
            .then((authenticatedUser) => {
                setUser(authenticatedUser);
                return authenticatedUser;
            })
            .catch((error) => {
                if (error.status === 401) {
                    clearSession();
                    return null;
                }

                throw error;
            })
            .finally(() => {
                initialized.value = true;
                loading.value = false;
                initializationPromise = null;
            });

        return initializationPromise;
    }

    async function signIn(credentials) {
        loginLoading.value = true;

        try {
            const authenticatedUser = await authApi.login(credentials);
            setUser(authenticatedUser);
            initialized.value = true;

            return authenticatedUser;
        } finally {
            loginLoading.value = false;
        }
    }

    async function signOut() {
        logoutLoading.value = true;

        try {
            await authApi.logout();
        } finally {
            clearSession();
            initialized.value = true;
            logoutLoading.value = false;
        }
    }

    return { user, roles, permissions, instructorProfile, initialized, loading, loginLoading, logoutLoading, isAuthenticated, isGuest, hasRole, hasAnyRole, hasPermission, hasAnyPermission, can, canAny, setUser, clearSession, initialize, signIn, signOut };
});

export function resetAuthInitializationForTests() {
    initializationPromise = null;
}
