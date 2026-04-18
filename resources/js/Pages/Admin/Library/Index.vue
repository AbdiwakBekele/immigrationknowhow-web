<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { BookOpenIcon, DocumentTextIcon, MusicalNoteIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    items: Object,
    categories: Array,
    authors: {
        type: Array,
        default: () => [],
    },
    types: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const regionOptions = [
    { value: 'usa', label: 'USA' },
    { value: 'canada', label: 'Canada' },
    { value: 'great_britain', label: 'Great Britain' },
    { value: 'europe', label: 'Europe' },
];

const uploadForm = useForm({
    title: '',
    type: props.types?.[0]?.value ?? '',
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
    () => uploadForm.type,
    () => {
        uploadForm.file = null;
        uploadForm.pdf_file = null;
        uploadForm.audio_file = null;
        uploadForm.cover_image = null;
    },
);

watch(
    () => uploadForm.all_regions,
    (all) => {
        if (all) {
            uploadForm.regions = [];
        } else if (!uploadForm.regions?.length) {
            uploadForm.regions = ['usa'];
        }
    },
);

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

const acceptedPdfTypes = '.pdf,application/pdf';

const acceptedAudiobookTypes = '.mp3,.m4a,.aac,.wav,.ogg,audio/*';

const submitUpload = () => {
    if (uploadForm.new_author_name?.trim()) {
        uploadForm.author_id = '';
    }

    uploadForm
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
            onSuccess: () => {
                uploadForm.reset(
                    'title',
                    'description',
                    'publisher',
                    'published_at',
                    'isbn',
                    'page_count',
                    'language',
                    'estimated_reading_minutes',
                    'difficulty_level',
                    'recommended_age_group',
                    'file',
                    'pdf_file',
                    'audio_file',
                    'cover_image',
                    'price',
                    'new_author_name',
                );
                uploadForm.price = '0';
                uploadForm.type = props.types?.[0]?.value ?? '';
                uploadForm.all_regions = false;
                uploadForm.regions = ['usa'];
                uploadForm.category_id = '';
                uploadForm.author_id = '';
                uploadForm.is_active = true;
                uploadForm.is_featured = false;
                uploadForm.currency = 'USD';
            },
        });
};

const destroyItem = (item) => {
    if (!confirm(`Delete "${item.title}" from the library? This removes the stored PDF/audio files too.`)) {
        return;
    }

    router.delete(route('admin.library.destroy', item.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Library" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Content library
                </p>
                <h1 class="mt-2 admin-title">Library</h1>
                <p class="admin-subtitle">Upload e-books and audiobooks, organized by category.</p>
            </section>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-[1.1rem] border border-gray-100 bg-white p-3 shadow-sm">
                    <div class="mb-2 inline-flex rounded-lg bg-indigo-100 p-1.5">
                        <BookOpenIcon class="h-5 w-5 text-indigo-600" />
                    </div>
                    <p class="text-xl font-semibold text-gray-900">{{ stats.total }}</p>
                    <p class="text-sm text-gray-500">Visible items</p>
                </div>
                <div
                    v-for="typeOption in types.slice(0, 2)"
                    :key="typeOption.value"
                    class="rounded-[1.1rem] border border-gray-100 bg-white p-3 shadow-sm"
                >
                    <div class="mb-2 inline-flex rounded-lg bg-sky-100 p-1.5">
                        <DocumentTextIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-xl font-semibold text-gray-900">{{ stats.byType[typeOption.value] ?? 0 }}</p>
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
	                    <div class="lg:col-span-2">
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Author</label>
	                        <p class="mb-2 text-xs text-gray-500">
	                            Choose an existing author or add a new name once; the same author can be linked to many titles.
	                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <select
                                    v-model="uploadForm.author_id"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2"
                                    :disabled="!!uploadForm.new_author_name?.trim()"
                                >
                                    <option value="">No author</option>
                                    <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                                <p v-if="uploadForm.errors.author_id" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.author_id }}</p>
                            </div>
                            <div>
                                <input
                                    v-model="uploadForm.new_author_name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2"
                                    placeholder="Or enter a new author name"
                                >
                                <p v-if="uploadForm.errors.new_author_name" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.new_author_name }}</p>
	                            </div>
	                        </div>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Publisher</label>
	                        <input v-model="uploadForm.publisher" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2">
	                        <p v-if="uploadForm.errors.publisher" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.publisher }}</p>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Published date</label>
	                        <input v-model="uploadForm.published_at" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2">
	                        <p v-if="uploadForm.errors.published_at" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.published_at }}</p>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">ISBN</label>
	                        <input v-model="uploadForm.isbn" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2">
	                        <p v-if="uploadForm.errors.isbn" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.isbn }}</p>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Pages count</label>
	                        <input v-model="uploadForm.page_count" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2">
	                        <p v-if="uploadForm.errors.page_count" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.page_count }}</p>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Language</label>
	                        <input v-model="uploadForm.language" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="English">
	                        <p v-if="uploadForm.errors.language" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.language }}</p>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Estimated reading time (minutes)</label>
	                        <input v-model="uploadForm.estimated_reading_minutes" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2">
	                        <p v-if="uploadForm.errors.estimated_reading_minutes" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.estimated_reading_minutes }}</p>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Difficulty level</label>
	                        <input v-model="uploadForm.difficulty_level" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Beginner">
	                        <p v-if="uploadForm.errors.difficulty_level" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.difficulty_level }}</p>
	                    </div>
	                    <div>
	                        <label class="mb-1 block text-sm font-medium text-gray-700">Recommended age group</label>
	                        <input v-model="uploadForm.recommended_age_group" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="All">
	                        <p v-if="uploadForm.errors.recommended_age_group" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.recommended_age_group }}</p>
	                    </div>
	                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Regions</label>
                    <p class="mb-2 text-xs text-gray-500">
                        Defaults to <span class="font-medium text-gray-700">USA only</span> unless you change regions or mark worldwide.
                    </p>
                    <label class="mb-3 flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="uploadForm.all_regions" type="checkbox" class="rounded border-gray-300">
                        Available worldwide (all regions)
                    </label>
                    <div class="flex flex-wrap gap-3" :class="uploadForm.all_regions ? 'pointer-events-none opacity-50' : ''">
                        <label
                            v-for="region in regionOptions"
                            :key="region.value"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700"
                        >
                            <input
                                v-model="uploadForm.regions"
                                :value="region.value"
                                type="checkbox"
                                class="rounded border-gray-300"
                                :disabled="uploadForm.all_regions"
                            >
                            <span>{{ region.label }}</span>
                        </label>
                    </div>
                    <p v-if="uploadForm.errors.regions" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.regions }}</p>
                    <p v-if="uploadForm.errors['regions.0']" class="mt-1 text-xs text-red-600">{{ uploadForm.errors['regions.0'] }}</p>
                    <p v-if="uploadForm.errors.all_regions" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.all_regions }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                    <textarea v-model="uploadForm.description" rows="8" class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm"></textarea>
                    <p v-if="uploadForm.errors.description" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.description }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cover image <span class="font-normal text-gray-500">(optional)</span></label>
                    <input
                        type="file"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        @change="uploadForm.cover_image = $event.target.files?.[0] ?? null"
                    >
                    <p class="mt-1 text-xs text-gray-500">Shown on the public library. Max 2MB. JPG, PNG, WebP, or GIF.</p>
                    <p v-if="uploadForm.errors.cover_image" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.cover_image }}</p>
                </div>

                <div v-if="uploadForm.type === 'ebook'" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">PDF file</label>
                        <input
                            type="file"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                            :accept="acceptedPdfTypes"
                            required
                            @change="uploadForm.pdf_file = $event.target.files?.[0] ?? null"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Max 1000MB. E-books must be PDF.
                        </p>
                        <p v-if="uploadForm.errors.pdf_file" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.pdf_file }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Audio version <span class="font-normal text-gray-500">(optional)</span></label>
                        <input
                            type="file"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                            :accept="acceptedAudiobookTypes"
                            @change="uploadForm.audio_file = $event.target.files?.[0] ?? null"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Optional companion audiobook for this title. MP3, M4A, AAC, WAV, or OGG — max 1000MB.
                        </p>
                        <p v-if="uploadForm.errors.audio_file" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.audio_file }}</p>
                    </div>
                </div>

                <div v-else>
                    <label class="mb-1 block text-sm font-medium text-gray-700">File</label>
                    <input
                        type="file"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        :accept="acceptedAudiobookTypes"
                        required
                        @change="uploadForm.file = $event.target.files?.[0] ?? null"
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Max 1000MB. Audiobooks support MP3, M4A, AAC, WAV, OGG.
                    </p>
                    <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.file }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Price <span class="text-red-500">*</span></label>
                        <input
                            v-model="uploadForm.price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                            placeholder="0 for free"
                            required
                        >
                        <p class="mt-1 text-xs text-gray-500">Required. Use 0 for free titles. Any price above 0 is treated as a one-time purchase.</p>
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
	                                <div class="flex shrink-0 items-center gap-2">
	                                    <Link :href="route('library.show', item.slug)" class="text-indigo-600 hover:text-indigo-500">View</Link>
	                                    <Link
	                                        :href="route('admin.library.edit', item.slug)"
	                                        class="inline-flex rounded-lg border border-gray-200 p-1.5 text-gray-600 hover:bg-white"
	                                        title="Edit"
	                                    >
	                                        <PencilSquareIcon class="h-4 w-4" />
	                                    </Link>
	                                    <button
	                                        type="button"
	                                        class="inline-flex rounded-lg border border-gray-200 p-1.5 text-red-600 hover:bg-red-50"
	                                        title="Delete"
	                                        @click="destroyItem(item)"
	                                    >
	                                        <TrashIcon class="h-4 w-4" />
	                                    </button>
	                                </div>
	                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500">No content in the library yet.</p>
            </div>
        </div>
    </AdminLayout>
</template>
