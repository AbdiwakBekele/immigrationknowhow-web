<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    item: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    authors: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const regionOptions = [
    { value: 'usa', label: 'USA' },
    { value: 'canada', label: 'Canada' },
    { value: 'great_britain', label: 'Great Britain' },
    { value: 'europe', label: 'Europe' },
];

const existingRegions = Array.isArray(props.item.regions) ? props.item.regions : [];

const form = useForm({
    _method: 'put',
    title: props.item.title ?? '',
    type: props.item.type ?? props.types?.[0]?.value ?? 'ebook',
    all_regions: existingRegions.length === 0,
    regions: existingRegions.length ? existingRegions : ['usa'],
    category_id: props.item.category_id ?? '',
    author_id: props.item.author_id ?? props.item.library_author?.id ?? '',
    new_author_name: '',
    description: props.item.description ?? '',
    publisher: props.item.publisher ?? '',
    published_at: props.item.published_at ?? '',
    isbn: props.item.isbn ?? '',
    page_count: props.item.page_count ?? '',
    language: props.item.language ?? '',
    estimated_reading_minutes: props.item.estimated_reading_minutes ?? '',
    difficulty_level: props.item.difficulty_level ?? '',
    recommended_age_group: props.item.recommended_age_group ?? '',
    duration_seconds: props.item.duration_seconds ?? '',
    file: null,
    pdf_file: null,
    audio_file: null,
    cover_image: null,
    price: props.item.price ?? '0',
    currency: props.item.currency ?? 'USD',
    is_active: Boolean(props.item.is_active),
    is_featured: Boolean(props.item.is_featured),
});

watch(
    () => form.type,
    () => {
        form.file = null;
        form.pdf_file = null;
        form.audio_file = null;
    },
);

watch(
    () => form.all_regions,
    (all) => {
        if (all) {
            form.regions = [];
        } else if (!form.regions?.length) {
            form.regions = ['usa'];
        }
    },
);

const selectedTypeLabel = computed(() => props.types.find((type) => type.value === form.type)?.label ?? form.type);

const acceptedPdfTypes = '.pdf,application/pdf';
const acceptedAudiobookTypes = '.mp3,.m4a,.aac,.wav,.ogg,audio/*';

const formatBytes = (bytes) => {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value <= 0) return null;

    if (value >= 1073741824) return `${(value / 1073741824).toFixed(2)} GB`;
    if (value >= 1048576) return `${(value / 1048576).toFixed(2)} MB`;
    if (value >= 1024) return `${(value / 1024).toFixed(2)} KB`;

    return `${value} bytes`;
};

const submit = () => {
    if (form.new_author_name?.trim()) {
        form.author_id = '';
    }

    form
        .transform((data) => {
            const next = { ...data, _method: 'put' };
            if (next.author_id === '' || next.author_id === null || next.author_id === undefined) {
                next.author_id = null;
            } else {
                next.author_id = Number(next.author_id);
            }
            return next;
        })
        .post(route('admin.library.update', props.item.slug), {
            forceFormData: true,
            preserveScroll: true,
        });
};

const destroyItem = () => {
    if (!confirm(`Delete "${props.item.title}" from the library? This removes the stored PDF/audio files too.`)) {
        return;
    }

    router.delete(route('admin.library.destroy', props.item.slug));
};
</script>

<template>
    <Head :title="`Edit ${item.title}`" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link
                            :href="route('admin.library.index')"
                            class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"
                        >
                            <ArrowLeftIcon class="h-4 w-4" />
                            Back to library
                        </Link>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Content library
                        </p>
                        <h1 class="mt-2 admin-title">Edit {{ item.title }}</h1>
                        <p class="admin-subtitle">
                            Update the storefront details, protected files, pricing, and WP-style book metadata.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            :href="route('library.show', item.slug)"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            View public page
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                            @click="destroyItem"
                        >
                            <TrashIcon class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </section>

            <form class="admin-panel space-y-6" @submit.prevent="submit">
                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Book setup</h2>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                            <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                            <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Type</label>
                            <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                                <option v-for="typeOption in types" :key="typeOption.value" :value="typeOption.value">
                                    {{ typeOption.label }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Changing type requires a replacement {{ form.type === 'ebook' ? 'PDF' : 'audio' }} file.
                            </p>
                            <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">{{ form.errors.type }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
                            <select v-model="form.category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Uncategorized</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-600">{{ form.errors.category_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <div class="flex min-h-10 items-center gap-6">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
                                    Active
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="form.is_featured" type="checkbox" class="rounded border-slate-300">
                                    Featured
                                </label>
                            </div>
                            <p v-if="form.errors.is_active" class="mt-1 text-xs text-red-600">{{ form.errors.is_active }}</p>
                            <p v-if="form.errors.is_featured" class="mt-1 text-xs text-red-600">{{ form.errors.is_featured }}</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Author and details</h2>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Author</label>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <select
                                        v-model="form.author_id"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                        :disabled="!!form.new_author_name?.trim()"
                                    >
                                        <option value="">No author</option>
                                        <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
                                    </select>
                                    <p v-if="form.errors.author_id" class="mt-1 text-xs text-red-600">{{ form.errors.author_id }}</p>
                                </div>
                                <div>
                                    <input
                                        v-model="form.new_author_name"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                        placeholder="Or enter a new author name"
                                    >
                                    <p v-if="form.errors.new_author_name" class="mt-1 text-xs text-red-600">{{ form.errors.new_author_name }}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Publisher</label>
                            <input v-model="form.publisher" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <p v-if="form.errors.publisher" class="mt-1 text-xs text-red-600">{{ form.errors.publisher }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Published date</label>
                            <input v-model="form.published_at" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <p v-if="form.errors.published_at" class="mt-1 text-xs text-red-600">{{ form.errors.published_at }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">ISBN</label>
                            <input v-model="form.isbn" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <p v-if="form.errors.isbn" class="mt-1 text-xs text-red-600">{{ form.errors.isbn }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Pages count</label>
                            <input v-model="form.page_count" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <p v-if="form.errors.page_count" class="mt-1 text-xs text-red-600">{{ form.errors.page_count }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Language</label>
                            <input v-model="form.language" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="English">
                            <p v-if="form.errors.language" class="mt-1 text-xs text-red-600">{{ form.errors.language }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Estimated reading time (minutes)</label>
                            <input v-model="form.estimated_reading_minutes" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <p v-if="form.errors.estimated_reading_minutes" class="mt-1 text-xs text-red-600">{{ form.errors.estimated_reading_minutes }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Difficulty level</label>
                            <input v-model="form.difficulty_level" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Beginner">
                            <p v-if="form.errors.difficulty_level" class="mt-1 text-xs text-red-600">{{ form.errors.difficulty_level }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Recommended age group</label>
                            <input v-model="form.recommended_age_group" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="All">
                            <p v-if="form.errors.recommended_age_group" class="mt-1 text-xs text-red-600">{{ form.errors.recommended_age_group }}</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Availability</h2>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.all_regions" type="checkbox" class="rounded border-slate-300">
                        Available worldwide (all regions)
                    </label>
                    <div class="flex flex-wrap gap-3" :class="form.all_regions ? 'pointer-events-none opacity-50' : ''">
                        <label
                            v-for="region in regionOptions"
                            :key="region.value"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                        >
                            <input
                                v-model="form.regions"
                                :value="region.value"
                                type="checkbox"
                                class="rounded border-slate-300"
                                :disabled="form.all_regions"
                            >
                            <span>{{ region.label }}</span>
                        </label>
                    </div>
                    <p v-if="form.errors.regions" class="mt-1 text-xs text-red-600">{{ form.errors.regions }}</p>
                    <p v-if="form.errors.all_regions" class="mt-1 text-xs text-red-600">{{ form.errors.all_regions }}</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Description</h2>
                    <textarea v-model="form.description" rows="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Files</h2>
                    <div v-if="item.cover_image_url" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <img :src="item.cover_image_url" :alt="item.title" class="h-20 w-14 rounded object-cover object-top">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Current cover</p>
                            <p class="text-xs text-slate-500">Upload a new image below to replace it.</p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Replace cover image</label>
                        <input
                            type="file"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            @change="form.cover_image = $event.target.files?.[0] ?? null"
                        >
                        <p v-if="form.errors.cover_image" class="mt-1 text-xs text-red-600">{{ form.errors.cover_image }}</p>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">Current {{ selectedTypeLabel }} file</p>
                        <p>
                            {{ item.file_name || 'Stored file' }}
                            <span v-if="formatBytes(item.file_size)" class="text-slate-500">({{ formatBytes(item.file_size) }})</span>
                        </p>
                    </div>

                    <div v-if="form.type === 'ebook'" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Replace PDF file</label>
                            <input
                                type="file"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                :accept="acceptedPdfTypes"
                                @change="form.pdf_file = $event.target.files?.[0] ?? null"
                            >
                            <p class="mt-1 text-xs text-slate-500">Leave blank to keep the current PDF.</p>
                            <p v-if="form.errors.pdf_file" class="mt-1 text-xs text-red-600">{{ form.errors.pdf_file }}</p>
                        </div>
                        <div v-if="item.audio_file_name" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            <p class="font-semibold text-slate-900">Current companion audio</p>
                            <p>
                                {{ item.audio_file_name }}
                                <span v-if="formatBytes(item.audio_file_size)" class="text-slate-500">({{ formatBytes(item.audio_file_size) }})</span>
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Replace or add audio version</label>
                            <input
                                type="file"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                :accept="acceptedAudiobookTypes"
                                @change="form.audio_file = $event.target.files?.[0] ?? null"
                            >
                            <p class="mt-1 text-xs text-slate-500">Optional companion audio for this e-book.</p>
                            <p v-if="form.errors.audio_file" class="mt-1 text-xs text-red-600">{{ form.errors.audio_file }}</p>
                        </div>
                    </div>

                    <div v-else>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Replace audiobook file</label>
                        <input
                            type="file"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            :accept="acceptedAudiobookTypes"
                            @change="form.file = $event.target.files?.[0] ?? null"
                        >
                        <p class="mt-1 text-xs text-slate-500">Leave blank to keep the current audio file.</p>
                        <p v-if="form.errors.file" class="mt-1 text-xs text-red-600">{{ form.errors.file }}</p>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Pricing</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Price</label>
                            <input
                                v-model="form.price"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                required
                            >
                            <p class="mt-1 text-xs text-slate-500">Use 0 for free titles. Anything above 0 uses checkout.</p>
                            <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Currency</label>
                            <input
                                v-model="form.currency"
                                type="text"
                                maxlength="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase"
                                required
                            >
                            <p v-if="form.errors.currency" class="mt-1 text-xs text-red-600">{{ form.errors.currency }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-end">
                    <Link
                        :href="route('admin.library.index')"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
