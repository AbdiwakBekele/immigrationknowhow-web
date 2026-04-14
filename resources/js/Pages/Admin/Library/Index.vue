<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { BookOpenIcon, DocumentTextIcon, MusicalNoteIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    items: Object,
    categories: Array,
    types: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const uploadForm = useForm({
    title: '',
    type: props.types?.[0]?.value ?? '',
    category_id: '',
    author: '',
    description: '',
    file: null,
    price: '0',
    currency: 'USD',
    is_active: true,
    is_featured: false,
    is_premium: false,
});

const groupedItems = computed(() => {
    const allItems = props.items?.data ?? [];
    const groups = {};

    allItems.forEach((item) => {
        const key = item.category?.slug ?? 'uncategorized';
        if (!groups[key]) {
            groups[key] = {
                name: item.category?.name ?? 'Uncategorized',
                items: [],
            };
        }
        groups[key].items.push(item);
    });

    return Object.entries(groups).map(([slug, group]) => ({
        slug,
        ...group,
    }));
});

const stats = computed(() => {
    const typeCounts = Object.fromEntries((props.types ?? []).map((type) => [type.value, Number(type.count ?? 0)]));

    return {
        total: Object.values(typeCounts).reduce((sum, count) => sum + count, 0),
        byType: typeCounts,
    };
});

const selectedTypeOption = computed(() => {
    return (props.types ?? []).find((type) => type.value === uploadForm.type) ?? null;
});

const acceptedFileTypes = computed(() => {
    if (uploadForm.type === 'ebook') {
        return '.pdf,application/pdf';
    }

    if (uploadForm.type === 'audiobook') {
        return '.mp3,.m4a,.aac,.wav,.ogg,audio/*';
    }

    return '*/*';
});

const submitUpload = () => {
    uploadForm.post(route('admin.library.store'), {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset('title', 'author', 'description', 'file', 'price');
            uploadForm.price = '0';
            uploadForm.type = props.types?.[0]?.value ?? '';
            uploadForm.category_id = '';
            uploadForm.is_active = true;
            uploadForm.is_featured = false;
            uploadForm.is_premium = false;
            uploadForm.currency = 'USD';
        },
    });
};
</script>

<template>
    <Head title="Library" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Library</h1>
                <p class="mt-1 text-gray-500">Upload e-books and audiobooks, organized by category.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-indigo-100 p-2">
                        <BookOpenIcon class="h-5 w-5 text-indigo-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.total }}</p>
                    <p class="text-sm text-gray-500">Visible items</p>
                </div>
                <div
                    v-for="typeOption in types.slice(0, 2)"
                    :key="typeOption.value"
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"
                >
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <DocumentTextIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.byType[typeOption.value] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">{{ typeOption.label }}</p>
                </div>
            </div>

            <form class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-4" @submit.prevent="submitUpload">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                        <input v-model="uploadForm.title" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
                        <p v-if="uploadForm.errors.title" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Type</label>
                        <select v-model="uploadForm.type" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
                            <option v-for="typeOption in types" :key="typeOption.value" :value="typeOption.value">
                                {{ typeOption.label }}
                            </option>
                        </select>
                        <p v-if="uploadForm.errors.type" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.type }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Category (optional)</label>
                        <select v-model="uploadForm.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                            <option value="">Uncategorized</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <p v-if="uploadForm.errors.category_id" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.category_id }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Author</label>
                        <input v-model="uploadForm.author" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <p v-if="uploadForm.errors.author" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.author }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                    <textarea v-model="uploadForm.description" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                    <p v-if="uploadForm.errors.description" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.description }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">File</label>
                    <input
                        type="file"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        :accept="acceptedFileTypes"
                        @change="uploadForm.file = $event.target.files?.[0] ?? null"
                        required
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Max 1000MB.
                        <span v-if="selectedTypeOption?.value === 'ebook'">E-books must be PDF.</span>
                        <span v-else-if="selectedTypeOption?.value === 'audiobook'">Audiobooks support MP3, M4A, AAC, WAV, OGG.</span>
                    </p>
                    <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.file }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Price <span class="text-red-500">*</span></label>
                        <input
                            v-model="uploadForm.price"
                            type="number"
                            :min="uploadForm.is_premium ? '0.01' : '0'"
                            step="0.01"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                            placeholder="0 for free"
                            required
                        >
                        <p class="mt-1 text-xs text-gray-500">Required. Use 0 for free titles. If “One-time purchase” is checked, price must be at least 0.01.</p>
                        <p v-if="uploadForm.errors.price" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.price }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Currency <span class="text-red-500">*</span></label>
                        <input
                            v-model="uploadForm.currency"
                            type="text"
                            maxlength="3"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 uppercase"
                            placeholder="USD"
                            required
                        >
                        <p v-if="uploadForm.errors.currency" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.currency }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="uploadForm.is_premium" type="checkbox" class="rounded border-gray-300">
                        One-time purchase required
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="uploadForm.is_active" type="checkbox" class="rounded border-gray-300">
                        Active
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="uploadForm.is_featured" type="checkbox" class="rounded border-gray-300">
                        Featured
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500">Excludes editing, compression, conversion, and metadata cleanup.</p>
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                        :disabled="uploadForm.processing"
                    >
                        {{ uploadForm.processing ? 'Uploading...' : 'Upload to library' }}
                    </button>
                </div>
            </form>

            <div class="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Content by Category</h2>
                <div v-if="groupedItems.length" class="space-y-4">
                    <div v-for="group in groupedItems" :key="group.slug" class="rounded-lg border border-gray-200 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">{{ group.name }}</h3>
                            <span class="text-xs text-gray-500">{{ group.items.length }} item(s)</span>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="item in group.items"
                                :key="item.id"
                                class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div
                                        class="relative h-11 w-8 shrink-0 overflow-hidden rounded-md bg-gray-200 ring-1 ring-gray-200/80"
                                    >
                                        <img
                                            v-if="item.cover_image_url"
                                            :src="item.cover_image_url"
                                            :alt="item.title"
                                            class="absolute inset-0 h-full w-full object-cover object-top"
                                        />
                                        <div
                                            v-else
                                            class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-indigo-500 to-indigo-700"
                                        >
                                            <BookOpenIcon
                                                v-if="item.type === 'ebook'"
                                                class="h-5 w-5 text-white/90"
                                            />
                                            <MusicalNoteIcon
                                                v-else-if="item.type === 'audiobook'"
                                                class="h-5 w-5 text-white/90"
                                            />
                                            <DocumentTextIcon v-else class="h-5 w-5 text-white/90" />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900">{{ item.title }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ types.find((typeOption) => typeOption.value === item.type)?.label ?? item.type }}
                                        </p>
                                    </div>
                                </div>
                                <Link :href="route('library.show', item.slug)" class="shrink-0 text-indigo-600 hover:text-indigo-500">View</Link>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500">No content in the library yet.</p>
            </div>
        </div>
    </AdminLayout>
</template>
