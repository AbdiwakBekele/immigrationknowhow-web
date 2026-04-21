<script setup>
import { watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
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

const form = useForm({
    title: '',
    type: props.types?.[0]?.value ?? 'ebook',
    all_regions: false,
    regions: ['usa'],
    category_id: '',
    author_id: '',
    new_author_name: '',
    description: '',
    publisher: '',
    published_at: '',
    isbn: '',
    page_count: '',
    language: '',
    estimated_reading_minutes: '',
    difficulty_level: '',
    recommended_age_group: '',
    file: null,
    pdf_file: null,
    audio_file: null,
    cover_image: null,
    price: '0',
    currency: 'USD',
    is_active: true,
    is_featured: false,
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

const acceptedPdfTypes = '.pdf,application/pdf';
const acceptedAudiobookTypes = '.mp3,.m4a,.aac,.wav,.ogg,audio/*';

const submit = () => {
    console.group('[Admin Library] Upload started');
    console.info('Preparing library upload request');
    console.info('Title:', form.title);
    console.info('Type:', form.type);
    console.info('Price/Currency:', form.price, form.currency);
    console.info('Has PDF file:', Boolean(form.pdf_file));
    console.info('Has audio file:', Boolean(form.file || form.audio_file));
    console.info('Regions:', form.all_regions ? 'all_regions=true' : form.regions);
    console.groupEnd();

    if (form.new_author_name?.trim()) {
        form.author_id = '';
    }

    form
        .transform((data) => {
            const next = { ...data };
            if (next.author_id === '' || next.author_id === null || next.author_id === undefined) {
                next.author_id = null;
            } else {
                next.author_id = Number(next.author_id);
            }
            return next;
        })
        .post(route('admin.library.store'), {
            forceFormData: true,
            onStart: () => {
                console.info('[Admin Library] POST /admin/library request dispatched');
            },
            onSuccess: () => {
                console.info('[Admin Library] Upload success');
                console.info('[Admin Library] Server should have queued summary generation for ebooks');
            },
            onError: (errors) => {
                console.error('[Admin Library] Upload failed with validation/response errors', errors);
            },
            onFinish: () => {
                console.info('[Admin Library] Upload request finished');
            },
        });
};
</script>

<template>
    <Head title="Add Library Item" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <Link
                    :href="route('admin.library.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back to library
                </Link>
                <h1 class="mt-3 admin-title">Add Library Item</h1>
                <p class="admin-subtitle">Upload a new ebook or audiobook.</p>
            </section>

            <form class="admin-panel space-y-6" @submit.prevent="submit">
                <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
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
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
                        <select v-model="form.category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Uncategorized</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Author</label>
                        <select v-model="form.author_id" class="w-full rounded-lg border border-slate-300 px-3 py-2" :disabled="!!form.new_author_name?.trim()">
                            <option value="">No author</option>
                            <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">New author name (optional)</label>
                        <input v-model="form.new_author_name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </section>

                <section>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                    <textarea v-model="form.description" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                </section>

                <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Cover image (optional)</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full rounded-lg border border-slate-300 px-3 py-2" @change="form.cover_image = $event.target.files?.[0] ?? null">
                    </div>
                    <div v-if="form.type === 'ebook'">
                        <label class="mb-1 block text-sm font-medium text-slate-700">PDF file</label>
                        <input type="file" :accept="acceptedPdfTypes" class="w-full rounded-lg border border-slate-300 px-3 py-2" required @change="form.pdf_file = $event.target.files?.[0] ?? null">
                        <p v-if="form.errors.pdf_file" class="mt-1 text-xs text-red-600">{{ form.errors.pdf_file }}</p>
                    </div>
                    <div v-else>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Audio file</label>
                        <input type="file" :accept="acceptedAudiobookTypes" class="w-full rounded-lg border border-slate-300 px-3 py-2" required @change="form.file = $event.target.files?.[0] ?? null">
                        <p v-if="form.errors.file" class="mt-1 text-xs text-red-600">{{ form.errors.file }}</p>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Price</label>
                        <input v-model="form.price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Currency</label>
                        <input v-model="form.currency" type="text" maxlength="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase" required>
                    </div>
                </section>

                <section>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Regions</label>
                    <label class="mb-3 flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.all_regions" type="checkbox" class="rounded border-slate-300">
                        Available worldwide
                    </label>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="region in regionOptions"
                            :key="region.value"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                        >
                            <input v-model="form.regions" :value="region.value" :disabled="form.all_regions" type="checkbox" class="rounded border-slate-300">
                            <span>{{ region.label }}</span>
                        </label>
                    </div>
                </section>

                <div class="flex items-center justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Uploading...' : 'Create library item' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

