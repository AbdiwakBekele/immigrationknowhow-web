<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    BookOpenIcon,
    DocumentTextIcon,
    MusicalNoteIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

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

const stats = computed(() => {
    const typeCounts = Object.fromEntries((props.types ?? []).map((type) => [type.value, Number(type.count ?? 0)]));

    return {
        total: Object.values(typeCounts).reduce((sum, count) => sum + count, 0),
        byType: typeCounts,
    };
});

const formatType = (type) => props.types.find((option) => option.value === type)?.label ?? type;

const formatPrice = (item) => {
    const amount = Number(item.price ?? 0);
    if (!Number.isFinite(amount) || amount <= 0) {
        return 'Free';
    }

    return `${item.currency ?? 'USD'} ${amount.toFixed(2)}`;
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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Content library
                        </p>
                        <h1 class="mt-2 admin-title">Library</h1>
                        <p class="admin-subtitle">Manage e-books and audiobooks in card view.</p>
                    </div>
                    <Link
                        :href="route('admin.library.create')"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Add Library
                    </Link>
                </div>
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

            <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Library Items</h2>
                    <span class="text-sm text-gray-500">{{ items?.total ?? 0 }} total</span>
                </div>

                <div v-if="items?.data?.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="item in items.data"
                        :key="item.id"
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
                    >
                        <div class="relative h-44 bg-gray-100">
                            <img
                                v-if="item.cover_image_url"
                                :src="item.cover_image_url"
                                :alt="item.title"
                                class="h-full w-full object-cover object-top"
                            >
                            <div
                                v-else
                                class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-indigo-500 to-indigo-700"
                            >
                                <BookOpenIcon v-if="item.type === 'ebook'" class="h-9 w-9 text-white/90" />
                                <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-9 w-9 text-white/90" />
                                <DocumentTextIcon v-else class="h-9 w-9 text-white/90" />
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-gray-900">{{ item.title }}</h3>
                                <p class="truncate text-sm text-gray-500">
                                    {{ item.author || 'Unknown author' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ formatType(item.type) }}</span>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-700">{{ formatPrice(item) }}</span>
                                <span
                                    class="rounded-full px-2.5 py-1"
                                    :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                >
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="item.is_featured" class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-700">Featured</span>
                            </div>

                            <p class="text-xs text-gray-500">
                                Category: {{ item.category?.name || 'Uncategorized' }}
                            </p>

                            <div class="flex items-center justify-between gap-2">
                                <Link :href="route('admin.library.show', item.slug)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                    View
                                </Link>
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="route('admin.library.edit', item.slug)"
                                        class="inline-flex rounded-lg border border-gray-200 p-1.5 text-gray-600 hover:bg-gray-50"
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
                    </article>
                </div>

                <p v-else class="text-sm text-gray-500">No content in the library yet.</p>

                <div v-if="items?.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
                    <Link
                        v-for="link in items.links"
                        :key="link.label"
                        :href="link.url ?? ''"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm',
                            link.active
                                ? 'bg-primary-600 text-white'
                                : (link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400'),
                        ]"
                        :preserve-scroll="true"
                        v-html="link.label"
                    />
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
