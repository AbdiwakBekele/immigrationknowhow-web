<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    adPostingPrice: { type: Object, default: () => ({ amount_cents: 0, currency: 'USD' }) },
    adsRouteNamePrefix: { type: String, default: 'advertiser.ads' },
    adPortal: { type: Object, default: () => ({ portal: 'advertiser' }) },
});

const layoutComponent = computed(() => {
    if (props.adPortal?.portal === 'provider') return ProviderLayout;
    if (props.adPortal?.portal === 'user') return AppLayout;
    return AdvertiserLayout;
});

const form = useForm({
    title: '',
    description: '',
    cta_url: '',
    image_file: null,
});

const submit = () => {
    form.post(route(`${props.adsRouteNamePrefix}.store`), {
        forceFormData: true,
    });
};

const onImageSelected = (event) => {
    const [file] = event.target.files || [];
    form.image_file = file ?? null;
};
</script>

<template>
    <Head title="Create Ad" />

    <component :is="layoutComponent">
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">Create ad</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Publish fee:
                    {{ adPostingPrice.currency }} {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }}.
                </p>
            </div>

            <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                    <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-rose-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                    <textarea v-model="form.description" rows="5" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-sm text-rose-600">{{ form.errors.description }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">CTA URL</label>
                    <input v-model="form.cta_url" type="url" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="https://example.com" />
                    <p v-if="form.errors.cta_url" class="mt-1 text-sm text-rose-600">{{ form.errors.cta_url }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ad image (optional)</label>
                    <input type="file" accept="image/*" class="w-full rounded-lg border border-slate-300 px-3 py-2" @change="onImageSelected" />
                    <p v-if="form.image_file" class="mt-1 text-xs text-slate-500">Selected: {{ form.image_file.name }}</p>
                    <p v-if="form.errors.image_file" class="mt-1 text-sm text-rose-600">{{ form.errors.image_file }}</p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Create ad' }}
                    </button>
                    <Link :href="route(`${adsRouteNamePrefix}.index`)" class="text-sm font-medium text-slate-600 hover:text-slate-800">Cancel</Link>
                </div>
            </form>
        </div>
    </component>
</template>

