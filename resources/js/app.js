import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import i18n from './i18n';
import router from './router';
import { installUnauthorizedHandler } from './api/client';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(i18n);
app.use(router);

installUnauthorizedHandler(() => {
    const auth = useAuthStore(pinia);
    auth.clearSession();

    if (router.currentRoute.value.meta.requiresAuth && router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
    }
});

app.mount('#app');
