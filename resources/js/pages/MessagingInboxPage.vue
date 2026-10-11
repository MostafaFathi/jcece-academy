<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import CourseMessenger from '../components/messaging/CourseMessenger.vue';
const route = useRoute();
const courseId = computed(() => typeof route.query.course === 'string' && /^[1-9]\d*$/.test(route.query.course) ? route.query.course : null);
const conversationId = computed(() => typeof route.query.conversation === 'string' && /^[1-9]\d*$/.test(route.query.conversation) ? route.query.conversation : null);
</script>
<template><CourseMessenger :course-id="courseId" :conversation-id="conversationId" /></template>
