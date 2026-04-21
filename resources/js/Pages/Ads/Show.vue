<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    ad: { type: Object, required: true },
});
</script>

<template>
    <Head :title="ad.title" />

    <div class="min-h-screen bg-slate-100 py-10">
        <div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <img v-if="ad.image_url" :src="ad.image_url" alt="" class="mb-6 h-64 w-full rounded-xl object-cover" />
            <h1 class="text-3xl font-semibold text-slate-900">{{ ad.title }}</h1>
            <p class="mt-4 whitespace-pre-line text-slate-700">{{ ad.description }}</p>
            <a
                :href="ad.cta_url"
                class="mt-6 inline-flex rounded-lg bg-fuchsia-600 px-5 py-3 text-sm font-medium text-white hover:bg-fuchsia-700"
            >
                Learn more
            </a>
        </div>
    </div>
</template>

