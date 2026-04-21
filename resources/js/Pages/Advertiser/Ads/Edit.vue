<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';

const props = defineProps({
    ad: { type: Object, required: true },
    adPostingPrice: { type: Object, default: () => ({ amount_cents: 0, currency: 'USD' }) },
    publicUrl: { type: String, default: '' },
});

const form = useForm({
    title: props.ad.title || '',
    description: props.ad.description || '',
    cta_url: props.ad.cta_url || '',
    image_url: props.ad.image_url || '',
});

const submit = () => {
    form.patch(route('advertiser.ads.update', props.ad.uuid));
};

</script>

<template>
    <Head :title="`Edit ${ad.title}`" />

    <AdvertiserLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">Edit ad</h1>
                <p class="mt-1 text-sm text-slate-500">Status: {{ ad.status }}</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                        <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        <p v-if="form.errors.title" class="mt-1 text-sm text-rose-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                        <textarea v-model="form.description" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-sm text-rose-600">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">CTA URL</label>
                        <input v-model="form.cta_url" type="url" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        <p v-if="form.errors.cta_url" class="mt-1 text-sm text-rose-600">{{ form.errors.cta_url }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Image URL (optional)</label>
                        <input v-model="form.image_url" type="url" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        <p v-if="form.errors.image_url" class="mt-1 text-sm text-rose-600">{{ form.errors.image_url }}</p>
                    </div>

                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </button>
                </form>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-slate-500">Views</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900">{{ ad.analytics?.views ?? 0 }}</p>
                        <p class="mt-2 text-sm text-slate-500">Clicks</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900">{{ ad.analytics?.clicks ?? 0 }}</p>
                        <p class="mt-2 text-sm text-slate-500">CTR</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900">{{ ad.analytics?.ctr ?? 0 }}%</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-slate-500">Public ad URL</p>
                        <a :href="publicUrl" target="_blank" rel="noopener noreferrer" class="mt-2 block break-all text-sm font-medium text-primary-700 hover:text-primary-800">
                            {{ publicUrl }}
                        </a>
                    </div>

                    <div v-if="ad.status !== 'published'" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <p class="text-sm text-amber-800">
                            Publish fee:
                            {{ adPostingPrice.currency }} {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }}
                        </p>
                        <Link
                            :href="route('advertiser.ads.pay', ad.uuid)"
                            class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Continue to pay & publish
                        </Link>
                    </div>

                    <Link href="/advertiser/ads" class="inline-flex text-sm font-medium text-slate-600 hover:text-slate-800">
                        Back to ads
                    </Link>
                </div>
            </div>
        </div>
    </AdvertiserLayout>
</template>

