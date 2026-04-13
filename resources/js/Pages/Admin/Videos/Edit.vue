<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { VideoCameraIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    video: { type: Object, required: true },
    maxUploadMb: { type: Number, default: 100 },
});

const isUpload = () => (props.video.source || 'embed') === 'upload';

const form = useForm({
    title: props.video.title,
    video_url: props.video.video_url ?? '',
    video_file: null,
    price: props.video.price != null && props.video.price !== '' ? props.video.price : '',
    currency: (props.video.currency || 'USD').toString().slice(0, 3).toUpperCase(),
    description: props.video.description ?? '',
    category: props.video.category ?? '',
    tags: props.video.tags ?? '',
    is_featured: props.video.is_featured,
    is_active: props.video.is_active,
    sort_order: props.video.sort_order ?? 0,
});

const maxUploadBytes = computed(() => Math.max(1, Number(props.maxUploadMb || 1)) * 1024 * 1024);

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.clearErrors('video_file');

    if (!file) {
        form.video_file = null;
        return;
    }

    if (file.size > maxUploadBytes.value) {
        form.video_file = null;
        event.target.value = '';
        form.setError('video_file', `File is too large. Maximum allowed is about ${props.maxUploadMb} MB.`);
        return;
    }

    form.video_file = file;
};

const submit = () => {
    form.clearErrors('price');
    form.clearErrors('currency');
    if (isUpload() && form.video_file && form.video_file.size > maxUploadBytes.value) {
        form.setError('video_file', `File is too large. Maximum allowed is about ${props.maxUploadMb} MB.`);
        return;
    }
    const rawPrice = form.price;
    if (rawPrice === '' || rawPrice === null || Number.isNaN(Number(rawPrice))) {
        form.setError('price', 'Price is required. Use 0 for free content.');
        return;
    }
    const cur = String(form.currency ?? '').trim().toUpperCase();
    if (!/^[A-Z]{3}$/.test(cur)) {
        form.setError('currency', 'Currency is required (3 letters, e.g. USD).');
        return;
    }

    form
        .transform((data) => ({
            ...data,
            price: data.price === '' || data.price === null ? '' : String(Number(data.price)),
            currency: String(data.currency ?? '')
                .trim()
                .toUpperCase()
                .slice(0, 3),
        }))
        .put(route('admin.videos.update', props.video.slug), {
            forceFormData: isUpload(),
            preserveScroll: true,
        });
};
</script>

<template>
    <Head :title="`Edit: ${video.title}`" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Link
                        :href="route('admin.videos.index')"
                        class="mb-2 inline-flex items-center gap-1 text-sm font-medium text-sky-600 hover:text-sky-700"
                    >
                        <ArrowLeftIcon class="h-4 w-4" />
                        Back to videos
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-900">Edit video</h1>
                    <p v-if="!isUpload()" class="mt-1 text-sm text-slate-500">
                        Detected platform: <span class="font-medium text-slate-700">{{ video.platform }}</span>
                    </p>
                    <p v-else class="mt-1 text-sm text-slate-500">
                        Uploaded file — replace the file below or preview the current video.
                    </p>
                </div>
            </div>

            <div v-if="isUpload()" class="rounded-xl border border-slate-200 bg-slate-900 p-4 shadow-sm">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Preview</p>
                <video
                    class="max-h-72 w-full rounded-lg bg-black"
                    controls
                    preload="metadata"
                    :src="route('admin.videos.stream', video.slug)"
                />
                <p v-if="video.file_name" class="mt-2 text-xs text-slate-400">
                    Current file: {{ video.file_name }}
                </p>
            </div>

            <form class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Title <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div class="lg:col-span-2 grid gap-4 sm:grid-cols-2 rounded-lg border border-slate-100 bg-slate-50/80 p-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Price <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                            >
                            <p class="mt-1 text-xs text-slate-500">Required — enter a number; 0 means free.</p>
                            <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Currency <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.currency"
                                type="text"
                                maxlength="3"
                                required
                                pattern="[A-Za-z]{3}"
                                title="Three letters, e.g. USD"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                            >
                            <p class="mt-1 text-xs text-slate-500">Required — ISO code (3 letters).</p>
                            <p v-if="form.errors.currency" class="mt-1 text-xs text-red-600">{{ form.errors.currency }}</p>
                        </div>
                    </div>

                    <div v-if="!isUpload()" class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Video URL <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.video_url"
                            type="url"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                        <p v-if="form.errors.video_url" class="mt-1 text-xs text-red-600">{{ form.errors.video_url }}</p>
                    </div>

                    <div v-else class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Replace video file</label>
                        <input
                            type="file"
                            accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100"
                            @change="onFileChange"
                        >
                        <p class="mt-1 text-xs text-slate-500">Leave empty to keep the current file. Max ~{{ maxUploadMb }} MB.</p>
                        <p v-if="form.errors.video_file" class="mt-1 text-xs text-red-600">{{ form.errors.video_file }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
                        <input
                            v-model="form.category"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                        <p v-if="form.errors.category" class="mt-1 text-xs text-red-600">{{ form.errors.category }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Sort order</label>
                        <input
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                        <p v-if="form.errors.sort_order" class="mt-1 text-xs text-red-600">{{ form.errors.sort_order }}</p>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Tags</label>
                        <input
                            v-model="form.tags"
                            type="text"
                            placeholder="Comma-separated"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                        <p v-if="form.errors.tags" class="mt-1 text-xs text-red-600">{{ form.errors.tags }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-6 border-t border-slate-100 pt-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_featured" type="checkbox" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        Featured
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        Active
                    </label>
                </div>

                <div class="flex justify-end gap-3">
                    <Link
                        :href="route('admin.videos.index')"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <VideoCameraIcon class="h-4 w-4" />
                        {{ form.processing ? 'Saving…' : 'Update video' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
