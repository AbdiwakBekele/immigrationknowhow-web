<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    BookOpenIcon,
    DocumentTextIcon,
    MagnifyingGlassIcon,
    MusicalNoteIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
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

const search = ref(props.filters?.search || '');
const hasSearch = computed(() => String(props.filters?.search || '').trim() !== '');
let searchTimer = null;

const applySearch = () => {
    const term = String(search.value || '').trim();

    router.get(route('admin.library.index'), {
        type: props.filters?.type || undefined,
        category: props.filters?.category || undefined,
        search: term || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const queueSearch = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        searchTimer = null;
        applySearch();
    }, 350);
};

watch(search, queueSearch);

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

const clearSearch = () => {
    search.value = '';
};

const itemCategory = (item) => item?.category?.name || 'General';
const itemCreator = (item) => item?.author || item?.library_author?.name || 'Unknown Author';

const itemFormat = (item) => {
    if (item?.has_audio_companion) return 'PDF + Audio';
    if (item?.type === 'audiobook') return 'Audio';
    return 'PDF';
};

const itemYear = (item) => {
    if (item?.published_at) {
        const y = new Date(item.published_at).getFullYear();
        if (!Number.isNaN(y)) return y;
    }
    if (item?.publication_year) return item.publication_year;
    if (item?.created_at) {
        const y = new Date(item.created_at).getFullYear();
        if (!Number.isNaN(y)) return y;
    }
    return 'Now';
};

const requiresPayment = (item) => Number(item?.price ?? 0) > 0;

const formatPrice = (item) => {
    const amount = Number(item?.price ?? 0);
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

                <form class="mb-5" @submit.prevent="applySearch">
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                            aria-hidden="true"
                        />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by title, author, publisher, or ISBN..."
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50 pl-10 pr-10 text-sm text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-primary-500 focus:bg-white focus:ring-2 focus:ring-primary-500/20"
                        >
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600"
                            aria-label="Clear search"
                            @click="clearSearch"
                        >
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>
                </form>

                <div v-if="items?.data?.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
                    <article
                        v-for="item in items.data"
                        :key="item.id"
                        class="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <Link
                            :href="route('admin.library.show', item.slug)"
                            class="relative block aspect-square overflow-hidden bg-neutral-100"
                        >
                            <img
                                v-if="item.cover_image_url"
                                :src="item.cover_image_url"
                                :alt="item.title"
                                class="h-full w-full object-cover object-top transition duration-300 group-hover:scale-105"
                            >
                            <div
                                v-else
                                class="absolute inset-0 flex h-full w-full items-center justify-center bg-gradient-to-br from-indigo-500 to-indigo-700"
                            >
                                <BookOpenIcon v-if="item.type === 'ebook'" class="h-14 w-14 text-white/90" />
                                <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-14 w-14 text-white/90" />
                                <DocumentTextIcon v-else class="h-14 w-14 text-white/90" />
                            </div>
                            <span class="absolute left-2 top-2 rounded bg-blue-600 px-2 py-0.5 text-[11px] font-semibold text-white">
                                {{ itemCategory(item) }}
                            </span>
                            <span
                                v-if="!item.is_active"
                                class="absolute right-2 top-2 rounded bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white"
                            >
                                Inactive
                            </span>
                            <span
                                v-else-if="item.is_featured"
                                class="absolute right-2 top-2 rounded bg-amber-500 px-2 py-0.5 text-[11px] font-semibold text-white"
                            >
                                Featured
                            </span>
                        </Link>
                        <div class="flex flex-1 flex-col p-3">
                            <Link :href="route('admin.library.show', item.slug)" class="block">
                                <h3 class="line-clamp-2 text-sm font-semibold leading-5 text-neutral-950 transition group-hover:text-blue-700">
                                    {{ item.title }}
                                </h3>
                            </Link>
                            <p class="mt-1 text-xs text-neutral-500">{{ itemCreator(item) }}</p>
                            <div class="mt-1.5 flex items-center gap-3 text-[11px] text-neutral-400">
                                <span>{{ itemFormat(item) }}</span>
                                <span>{{ itemYear(item) }}</span>
                            </div>
                            <div class="mt-auto flex items-center justify-between gap-2 border-t border-neutral-100 pt-3">
                                <span
                                    class="text-sm font-bold"
                                    :class="requiresPayment(item) ? 'text-neutral-900' : 'text-emerald-600'"
                                >
                                    {{ formatPrice(item) }}
                                </span>
                                <div class="flex items-center gap-1">
                                    <Link
                                        :href="route('admin.library.show', item.slug)"
                                        class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                                        title="View"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        :href="route('admin.library.edit', item.slug)"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition hover:bg-neutral-50"
                                        title="Edit"
                                    >
                                        <PencilSquareIcon class="h-3.5 w-3.5" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50"
                                        title="Delete"
                                        @click="destroyItem(item)"
                                    >
                                        <TrashIcon class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <p v-else class="text-sm text-gray-500">
                    {{ hasSearch ? 'No library items match your search.' : 'No content in the library yet.' }}
                </p>
                <p v-if="!items?.data?.length && hasSearch" class="mt-2 text-sm text-gray-500">
                    Try another search term or clear the search.
                </p>

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
