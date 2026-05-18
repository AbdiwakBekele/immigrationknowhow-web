<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    ad: { type: Object, required: true },
    isAdminPreview: { type: Boolean, default: false },
});
</script>

<template>
    <Head :title="ad.title" />

    <div class="min-h-screen bg-slate-100 py-10">
        <div
            v-if="isAdminPreview"
            class="mx-auto mb-4 max-w-3xl rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            <p class="font-semibold">Admin preview</p>
            <p class="mt-1 text-amber-800">
                This is how the ad will appear when live. Analytics are not recorded.
                Status: <span class="font-medium capitalize">{{ (ad.status || '').replace(/_/g, ' ') }}</span>
            </p>
            <Link href="/admin/ads" class="mt-2 inline-block text-sm font-medium text-amber-900 underline hover:text-amber-950">
                Back to ads
            </Link>
        </div>
        <div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <img v-if="ad.image_url" :src="ad.image_url" alt="" class="mb-6 h-64 w-full rounded-xl object-cover" />
            <h1 class="text-3xl font-semibold text-slate-900">{{ ad.title }}</h1>
            <p class="mt-4 whitespace-pre-line text-slate-700">{{ ad.description }}</p>
            <a
                :href="ad.cta_url"
                :target="isAdminPreview ? '_blank' : undefined"
                :rel="isAdminPreview ? 'noopener noreferrer' : undefined"
                class="mt-6 inline-flex rounded-lg bg-fuchsia-600 px-5 py-3 text-sm font-medium text-white hover:bg-fuchsia-700"
            >
                Learn more
            </a>
        </div>
    </div>
</template>
