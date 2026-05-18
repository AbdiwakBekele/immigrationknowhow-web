<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatAdStatus } from '@/utils/formatAdStatus';

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
const adsIndexHref = computed(() => route(`${props.adsRouteNamePrefix}.index`));

const form = useForm({
    title: props.ad.title || '',
    description: props.ad.description || '',
    cta_url: props.ad.cta_url || '',
    image_file: null,
    clear_image: false,
});

const previewFile = ref(null);
const clearExisting = ref(false);
const objectUrl = ref('');
const fileInputRef = ref(null);

watch(previewFile, (file) => {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
        objectUrl.value = '';
    }
    if (file) {
        objectUrl.value = URL.createObjectURL(file);
    }
});

onBeforeUnmount(() => {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
    }
});

const resolvedStoredImageSrc = (url) => {
    const u = (url || '').trim();
    if (!u) return '';
    if (u.startsWith('http://') || u.startsWith('https://') || u.startsWith('/')) {
        return u;
    }
    return `/storage/${u}`;
};

const displayImageSrc = computed(() => {
    if (objectUrl.value) return objectUrl.value;
    if (!clearExisting.value && props.ad.image_url) {
        return resolvedStoredImageSrc(props.ad.image_url);
    }
    return '';
});

const onImageSelected = (event) => {
    const [file] = event.target.files || [];
    previewFile.value = file ?? null;
    if (file) {
        clearExisting.value = false;
    }
};

const removeImage = () => {
    if (previewFile.value) {
        previewFile.value = null;
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
        return;
    }
    if (props.ad.image_url && !clearExisting.value) {
        clearExisting.value = true;
    }
};

const openFilePicker = () => {
    fileInputRef.value?.click();
};

const submit = () => {
    form.image_file = previewFile.value;
    form.clear_image = Boolean(!previewFile.value && clearExisting.value);
    form.patch(route(`${props.adsRouteNamePrefix}.update`, props.ad.uuid), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            previewFile.value = null;
            clearExisting.value = false;
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
        },
    });
};

const rejectionReason = computed(() => {
    const m = props.ad.meta;
    if (!m || typeof m !== 'object') return '';
    return (m.rejection_reason && String(m.rejection_reason).trim()) || '';
});

const resubmitForReview = () => {
    router.post(route(`${props.adsRouteNamePrefix}.resubmit`, props.ad.uuid), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit ${ad.title}`" />

    <component :is="layoutComponent">
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <Link
                        :href="adsIndexHref"
                        class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Back to my ads"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <h1 class="text-2xl font-semibold text-slate-900">Edit ad</h1>
                </div>
                <p class="mt-1 text-sm text-slate-500">Status: {{ formatAdStatus(ad.status) }}</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2" @submit.prevent="submit">
                    <div
                        v-if="ad.status === 'suspended'"
                        class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900"
                    >
                        This ad was <span class="font-semibold">suspended</span> by an administrator and is hidden from the public site. Saving changes will submit it for approval again.
                    </div>
                    <div
                        v-if="ad.status === 'published'"
                        class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
                    >
                        This ad is <span class="font-semibold">live</span>. Saving changes will remove it from the public site until an administrator approves it again.
                    </div>
                    <div
                        v-if="ad.status === 'pending_approval'"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        This ad is <span class="font-semibold">awaiting administrator approval</span> before it can appear on the public site.
                    </div>
                    <div
                        v-if="ad.status === 'rejected'"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
                    >
                        <p class="font-semibold">This ad was not approved.</p>
                        <p v-if="rejectionReason" class="mt-1 text-rose-800">{{ rejectionReason }}</p>
                        <p v-else class="mt-1 text-rose-800">Update your content if needed, then resubmit for review.</p>
                    </div>
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
                        <label class="mb-1 block text-sm font-medium text-slate-700">Ad image (optional)</label>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/*"
                            class="sr-only"
                            @change="onImageSelected"
                        >
                        <div class="space-y-3">
                            <div
                                v-if="displayImageSrc"
                                class="relative inline-block max-w-full"
                            >
                                <img
                                    :src="displayImageSrc"
                                    alt=""
                                    class="max-h-56 max-w-full rounded-xl border border-slate-200 object-contain"
                                >
                                <button
                                    type="button"
                                    class="absolute -right-2 -top-2 inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-md ring-2 ring-white transition hover:bg-slate-50"
                                    aria-label="Remove image"
                                    title="Remove image"
                                    @click="removeImage"
                                >
                                    <XMarkIcon class="h-4 w-4" />
                                </button>
                            </div>
                            <div>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                    @click="openFilePicker"
                                >
                                    {{ displayImageSrc ? 'Replace image' : 'Upload image' }}
                                </button>
                                <p v-if="form.errors.image_file" class="mt-1 text-sm text-rose-600">{{ form.errors.image_file }}</p>
                            </div>
                        </div>
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
                        <template v-if="ad.is_publicly_visible && publicUrl">
                            <a :href="publicUrl" target="_blank" rel="noopener noreferrer" class="mt-2 block break-all text-sm font-medium text-primary-700 hover:text-primary-800">
                                {{ publicUrl }}
                            </a>
                        </template>
                        <p v-else-if="ad.status === 'suspended'" class="mt-2 text-sm text-slate-500">
                            Hidden while suspended. Contact support if you have questions.
                        </p>
                        <p v-else class="mt-2 text-sm text-slate-500">
                            Available after your ad is published.
                        </p>
                    </div>

                    <div v-if="ad.status === 'pending_payment'" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <p class="text-sm text-amber-800">
                            Publish fee:
                            {{ adPostingPrice.currency }} {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }}
                        </p>
                        <Link
                            :href="route(`${adsRouteNamePrefix}.pay`, ad.uuid)"
                            class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Continue to pay & publish
                        </Link>
                    </div>

                    <div v-if="ad.status === 'rejected'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                            @click="resubmitForReview"
                        >
                            Resubmit for review
                        </button>
                    </div>

                    <Link :href="adsIndexHref" class="inline-flex text-sm font-medium text-slate-600 hover:text-slate-800">
                        Back to ads
                    </Link>
                </div>
            </div>
        </div>
    </component>
</template>
