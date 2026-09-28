<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ rating: { type: [Number, String], default: null }, count: { type: Number, default: 0 }, compact: Boolean });
const { t } = useI18n();
const numericRating = computed(() => Number(props.rating) || 0);
</script>

<template>
    <div class="flex items-center gap-2" :aria-label="t('catalog.ratingLabel', { rating: numericRating.toFixed(1), count })">
        <span class="flex gap-0.5 text-accent" aria-hidden="true"><span v-for="star in 5" :key="star" :class="star <= Math.round(numericRating) ? 'text-accent' : 'text-slate-200'">★</span></span>
        <span v-if="rating !== null" class="font-bold text-slate-700" :class="compact ? 'text-xs' : 'text-sm'">{{ numericRating.toFixed(1) }}</span>
        <span v-if="!compact" class="text-xs text-slate-500">({{ count }})</span>
    </div>
</template>
