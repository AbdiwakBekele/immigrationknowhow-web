<script setup>
import ReviewsIndex from '@/Pages/Provider/Reviews/Index.vue';

defineProps({
    reviews: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});
</script>

<template>
    <ReviewsIndex
        :reviews="reviews"
        :stats="stats"
        :filters="filters"
    />
</template>
