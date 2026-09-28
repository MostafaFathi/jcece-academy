<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { homeRouteFor } from '../router/access';
import LoadingState from '../components/ui/LoadingState.vue';

const auth = useAuthStore();
const router = useRouter();

onMounted(() => router.replace(homeRouteFor(auth)));
</script>

<template>
    <LoadingState class="min-h-screen" />
</template>
