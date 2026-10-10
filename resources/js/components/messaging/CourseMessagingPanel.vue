<script setup>
import { useAuthStore } from '../../stores/auth';
import CourseMessenger from './CourseMessenger.vue';
defineProps({ courseId: { type: [String, Number], required: true } });
const auth = useAuthStore();
</script>
<template><CourseMessenger v-if="auth.can('messaging.view')" :course-id="courseId" /></template>
