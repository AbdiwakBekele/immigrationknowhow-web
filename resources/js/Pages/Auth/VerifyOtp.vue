<script setup>
/**
 * Legacy route `/address-detail/otp` redirects server-side to `/onboarding?step=3`.
 * This page remains as a client fallback if the old component is ever rendered.
 */
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

onMounted(() => {
    router.replace(route('onboarding.index', { step: 3 }));
});
</script>

<template>
    <div class="min-h-[30vh]" aria-hidden="true" />
</template>
