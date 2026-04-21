<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    ad: { type: Object, required: true },
    adPostingPrice: { type: Object, default: () => ({ amount_cents: 0, currency: 'USD' }) },
    publicUrl: { type: String, default: '' },
    adsRouteNamePrefix: { type: String, default: 'advertiser.ads' },
    adPortal: { type: Object, default: () => ({ portal: 'advertiser' }) },
});

const layoutComponent = computed(() => {
    if (props.adPortal?.portal === 'provider') return ProviderLayout;
    if (props.adPortal?.portal === 'user') return AppLayout;
    return AdvertiserLayout;
});

const form = useForm({});

const checkout = () => {
    form.post(route(`${props.adsRouteNamePrefix}.checkout`, props.ad.uuid), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Pay & Publish - ${ad.title}`" />

    <component :is="layoutComponent">
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">Pay & publish</h1>
                <p class="mt-1 text-sm text-slate-500">Review your ad details before completing one-time payment.</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <h2 class="text-lg font-semibold text-slate-900">Ad preview</h2>
                    <div class="mt-4 space-y-4">
                        <img
                            v-if="ad.image_url"
                            :src="ad.image_url"
                            alt="Ad preview image"
                            class="h-52 w-full rounded-xl object-cover"
                        />
                        <div>
                            <p class="text-sm text-slate-500">Title</p>
                            <p class="text-base font-semibold text-slate-900">{{ ad.title }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Description</p>
                            <p class="whitespace-pre-line text-sm text-slate-700">{{ ad.description }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Destination URL</p>
                            <p class="break-all text-sm text-slate-800">{{ ad.cta_url }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Public listing URL</p>
                            <a :href="publicUrl" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-medium text-primary-700 hover:text-primary-800">
                                {{ publicUrl }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <p class="text-sm text-amber-800">One-time publish fee</p>
                        <p class="mt-1 text-2xl font-semibold text-amber-900">
                            {{ adPostingPrice.currency }} {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }}
                        </p>
                        <button
                            type="button"
                            class="mt-4 w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            :disabled="form.processing"
                            @click="checkout"
                        >
                            {{ form.processing ? 'Redirecting...' : 'Pay and publish' }}
                        </button>
                    </div>

                    <Link :href="route(`${adsRouteNamePrefix}.edit`, ad.uuid)" class="inline-flex text-sm font-medium text-slate-600 hover:text-slate-800">
                        Back to edit
                    </Link>
                </div>
            </div>
        </div>
    </component>
</template>

