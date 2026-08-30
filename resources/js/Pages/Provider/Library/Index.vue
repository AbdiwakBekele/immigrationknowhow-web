<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import EbookShareCampaignBanner from '@/Components/Library/EbookShareCampaignBanner.vue';
import {
    BookOpenIcon,
    BookmarkIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    MusicalNoteIcon,
    HeartIcon,
    MagnifyingGlassIcon,
    ShoppingCartIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/vue/24/solid';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useInertiaInfiniteScroll } from '@/composables/useInertiaInfiniteScroll';

const props = defineProps({
    purchasedItems: { type: Object, required: true },
    availableItems: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const activeTab = ref(props.filters?.tab === 'purchased' ? 'purchased' : 'available');
const search = ref(props.filters?.search || '');

const page = usePage();
const cartCount = computed(() => Number(page.props.library_cart_count ?? 0) || 0);
const cartBadge = computed(() => {
    const n = cartCount.value;
    if (n < 1) return '';
    if (n > 99) return '99+';
    return String(n);
});
const appliedSearch = computed(() => String(props.filters?.search || '').trim());
let searchTimer = null;

const submitSearch = () => {
    const term = String(search.value || '').trim();

    router.get(route('provider.library.index'), {
        search: term || undefined,
        tab: activeTab.value,
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
        submitSearch();
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

const cartPortal = { cart_portal: 'provider' };

const stripHtml = (value) => String(value || '').replace(/<[^>]*>/g, '').trim();
const itemDescription = (item) => stripHtml(item?.description) || 'A practical guide for your immigration journey.';
const itemCategory = (item) => item?.category?.name || 'General';
const itemCreator = (item) => item?.author || item?.library_author?.name || 'Unknown Author';

const itemFormat = (item) => {
    if (item?.has_audio_companion) return 'PDF + Audio';
    if (item?.type === 'audiobook') return 'Audio';
    return 'PDF';
};

const itemMeta = (item) => {
    if (item?.duration_formatted) return item.duration_formatted;
    if (item?.file_size_formatted && item.file_size_formatted !== 'N/A') return item.file_size_formatted;
    return itemFormat(item);
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

const requiresPayment = (item) => Boolean(item?.is_premium) || Number(item?.price || 0) > 0;

const formatPrice = (item) => {
    const amount = Number(item?.price || 0);
    if (!amount) return 'Free';
    return `${item.currency ?? 'USD'} ${amount.toFixed(2)}`;
};

const userAccessRecord = (item) => {
    if (Array.isArray(item?.user_access)) return item.user_access[0] || null;
    return item?.user_access || null;
};

const isFavorited = (item) => Boolean(userAccessRecord(item)?.is_favorite);

const buildProviderLibraryQuery = (tab) => {
    const term = String(search.value || '').trim();

    return {
        search: term || undefined,
        tab,
    };
};

const availableScroll = useInertiaInfiniteScroll(
    () => props.availableItems,
    {
        getUrl: () => route('provider.library.index'),
        buildQuery: () => buildProviderLibraryQuery('available'),
        pageParam: 'available_page',
        only: 'availableItems',
    },
);

const purchasedScroll = useInertiaInfiniteScroll(
    () => props.purchasedItems,
    {
        getUrl: () => route('provider.library.index'),
        buildQuery: () => buildProviderLibraryQuery('purchased'),
        pageParam: 'purchased_page',
        only: 'purchasedItems',
    },
);

const {
    displayedItems: availableItemsList,
    total: availableTotal,
    loadingMore: availableLoadingMore,
    loadMoreSentinel: availableLoadMoreSentinel,
    hasMore: availableHasMore,
} = availableScroll;

const {
    displayedItems: purchasedItemsList,
    total: purchasedTotal,
    loadingMore: purchasedLoadingMore,
    loadMoreSentinel: purchasedLoadMoreSentinel,
    hasMore: purchasedHasMore,
} = purchasedScroll;

const readUrl = (item) => route('provider.library.read', { item: item.slug });
const showUrl = (item) => route('provider.library.show', { item: item.slug });
const freePurchaseUrl = (item) => route('library.purchase', { item: item.slug });
</script>

<template>
    <Head title="Provider Library" />

    <ProviderLayout>
        <div class="mx-auto max-w-7xl space-y-6 pb-10 text-neutral-950">
            <EbookShareCampaignBanner :campaign-href="route('provider.library.share')" />
            <!-- Header -->
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-neutral-950">My Library</h1>
                        <p class="mt-1 text-sm text-neutral-600">
                            Browse available titles and access your purchased content.
                        </p>
                    </div>
                    <Link
                        :href="route('provider.library.cart')"
                        class="relative inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-semibold text-neutral-800 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800"
                    >
                        <ShoppingCartIcon class="h-4 w-4" />
                        Cart
                        <span
                            v-if="cartCount > 0"
                            class="absolute -right-1.5 -top-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold text-white"
                        >
                            {{ cartBadge }}
                        </span>
                    </Link>
                </div>
            </section>

            <!-- Flash messages -->
            <div
                v-if="page.props.flash?.success || page.props.flash?.info || page.props.flash?.error"
                class="rounded-lg border px-4 py-3 text-sm"
                :class="page.props.flash?.error ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-800'"
            >
                {{ page.props.flash?.error || page.props.flash?.success || page.props.flash?.info }}
            </div>

            <form class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm" @submit.prevent="submitSearch">
                <div>
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                            aria-hidden="true"
                        />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by title, author, publisher, or ISBN..."
                            class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 pl-10 pr-10 text-sm text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 transition hover:text-neutral-600"
                            aria-label="Clear search"
                            @click="clearSearch"
                        >
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </form>

            <!-- Tabs -->
            <div class="border-b border-neutral-200">
                <nav class="-mb-px flex gap-6" aria-label="Library tabs">
                    <button
                        type="button"
                        class="relative whitespace-nowrap pb-3 text-sm font-semibold transition"
                        :class="activeTab === 'available'
                            ? 'text-blue-600'
                            : 'text-neutral-500 hover:text-neutral-700'"
                        @click="activeTab = 'available'"
                    >
                        <span class="inline-flex items-center gap-2">
                            <ShoppingCartIcon class="h-4 w-4" />
                            Available
                            <span
                                v-if="availableItemsList.length"
                                class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700"
                            >
                                {{ availableTotal || availableItemsList.length }}
                            </span>
                        </span>
                        <span
                            v-if="activeTab === 'available'"
                            class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-blue-600"
                        />
                    </button>
                    <button
                        type="button"
                        class="relative whitespace-nowrap pb-3 text-sm font-semibold transition"
                        :class="activeTab === 'purchased'
                            ? 'text-blue-600'
                            : 'text-neutral-500 hover:text-neutral-700'"
                        @click="activeTab = 'purchased'"
                    >
                        <span class="inline-flex items-center gap-2">
                            <BookOpenIcon class="h-4 w-4" />
                            Purchased
                            <span
                                v-if="purchasedItemsList.length"
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"
                            >
                                {{ purchasedTotal || purchasedItemsList.length }}
                            </span>
                        </span>
                        <span
                            v-if="activeTab === 'purchased'"
                            class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-blue-600"
                        />
                    </button>
                </nav>
            </div>

            <!-- Available tab -->
            <section v-if="activeTab === 'available'">
                <div
                    v-if="availableItemsList.length"
                    class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5"
                >
                    <article
                        v-for="item in availableItemsList"
                        :key="item.uuid"
                        class="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <Link :href="showUrl(item)" class="relative block aspect-square overflow-hidden bg-neutral-100">
                            <img
                                v-if="item.cover_image_url"
                                :src="item.cover_image_url"
                                :alt="item.title"
                                class="h-full w-full object-cover object-top transition duration-300 group-hover:scale-105"
                            />
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
                        </Link>
                        <div class="flex flex-1 flex-col p-3">
                            <Link :href="showUrl(item)" class="block">
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
                                <span class="text-sm font-bold" :class="requiresPayment(item) ? 'text-neutral-900' : 'text-emerald-600'">
                                    {{ formatPrice(item) }}
                                </span>
                                <Link
                                    v-if="!requiresPayment(item)"
                                    :href="freePurchaseUrl(item)"
                                    method="post"
                                    as="button"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <BookOpenIcon class="h-3.5 w-3.5" />
                                    Read
                                </Link>
                                <Link
                                    v-else
                                    :href="route('library.cart.add', { item: item.slug })"
                                    method="post"
                                    as="button"
                                    type="button"
                                    :data="cartPortal"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <ShoppingCartIcon class="h-3.5 w-3.5" />
                                    Add to cart
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-lg border border-dashed border-neutral-300 bg-white p-8 text-center">
                    <ShoppingCartIcon class="mx-auto h-10 w-10 text-neutral-300" />
                    <p class="mt-3 text-sm font-medium text-neutral-600">
                        {{ appliedSearch ? 'No available titles match your search.' : 'No additional titles available right now.' }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-400">
                        {{ appliedSearch ? 'Try another search term or clear the search.' : 'Check back soon for new content.' }}
                    </p>
                </div>
                <div v-if="availableHasMore || availableLoadingMore" class="mt-8 flex flex-col items-center gap-3">
                    <div ref="availableLoadMoreSentinel" class="h-1 w-full" aria-hidden="true" />
                    <p v-if="availableLoadingMore" class="text-sm text-neutral-500">
                        Loading more titles…
                    </p>
                    <p v-else class="text-sm text-neutral-500">
                        Showing {{ availableItemsList.length }} of {{ availableTotal }} titles
                    </p>
                </div>
            </section>

            <!-- Purchased tab -->
            <section v-if="activeTab === 'purchased'">
                <div
                    v-if="purchasedItemsList.length"
                    class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5"
                >
                    <article
                        v-for="item in purchasedItemsList"
                        :key="item.uuid"
                        class="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <Link :href="readUrl(item)" class="relative block aspect-square overflow-hidden bg-neutral-100">
                            <img
                                v-if="item.cover_image_url"
                                :src="item.cover_image_url"
                                :alt="item.title"
                                class="h-full w-full object-cover object-top transition duration-300 group-hover:scale-105"
                            />
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
                        </Link>
                        <div class="flex flex-1 flex-col p-3">
                            <Link :href="readUrl(item)" class="block">
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
                                <Link
                                    :href="readUrl(item)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <BookOpenIcon class="h-3.5 w-3.5" />
                                    {{ item.type === 'audiobook' ? 'Listen' : 'Read' }}
                                </Link>
                                <Link
                                    :href="route('library.favorite', item.slug)"
                                    method="post"
                                    as="button"
                                    preserve-scroll
                                    :title="isFavorited(item) ? 'Favorited' : 'Mark as favorite'"
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition hover:bg-rose-50"
                                >
                                    <HeartSolidIcon v-if="isFavorited(item)" class="h-3.5 w-3.5" />
                                    <HeartIcon v-else class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-lg border border-dashed border-neutral-300 bg-white p-8 text-center">
                    <BookOpenIcon class="mx-auto h-10 w-10 text-neutral-300" />
                    <p class="mt-3 text-sm font-medium text-neutral-600">
                        {{ appliedSearch ? 'No purchased titles match your search.' : 'No purchased titles yet.' }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-400">
                        {{ appliedSearch ? 'Try another search term or clear the search.' : 'Browse the Available tab to find titles to add to your library.' }}
                    </p>
                    <button
                        v-if="!appliedSearch"
                        type="button"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        @click="activeTab = 'available'"
                    >
                        <ShoppingCartIcon class="h-4 w-4" />
                        Browse available titles
                    </button>
                </div>
                <div v-if="purchasedHasMore || purchasedLoadingMore" class="mt-8 flex flex-col items-center gap-3">
                    <div ref="purchasedLoadMoreSentinel" class="h-1 w-full" aria-hidden="true" />
                    <p v-if="purchasedLoadingMore" class="text-sm text-neutral-500">
                        Loading more titles…
                    </p>
                    <p v-else class="text-sm text-neutral-500">
                        Showing {{ purchasedItemsList.length }} of {{ purchasedTotal }} titles
                    </p>
                </div>
            </section>
        </div>
    </ProviderLayout>
</template>
