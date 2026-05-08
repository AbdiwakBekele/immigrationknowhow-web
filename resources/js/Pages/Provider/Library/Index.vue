<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import {
    BookOpenIcon,
    BookmarkIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    MusicalNoteIcon,
    HeartIcon,
    ShoppingCartIcon,
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/vue/24/solid';
import { computed } from 'vue';

defineProps({
    purchasedItems: { type: Object, required: true },
    availableItems: { type: Object, required: true },
});

const page = usePage();
const cartCount = computed(() => Number(page.props.library_cart_count ?? 0) || 0);
const cartBadge = computed(() => {
    const n = cartCount.value;
    if (n < 1) return '';
    if (n > 99) return '99+';
    return String(n);
});

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

const readUrl = (item) => route('provider.library.read', { item: item.slug });
const showUrl = (item) => route('provider.library.show', { item: item.slug });
const freePurchaseUrl = (item) => route('library.purchase', { item: item.slug });
</script>

<template>
    <Head title="Provider Library" />

    <ProviderLayout>
        <div class="mx-auto max-w-7xl space-y-8 pb-10 text-neutral-950">
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-neutral-950">My Library</h1>
                        <p class="mt-1 text-sm text-neutral-600">
                            Purchased titles and the catalog — same cart and checkout as the member library.
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

            <div
                v-if="page.props.flash?.success || page.props.flash?.info || page.props.flash?.error"
                class="rounded-lg border px-4 py-3 text-sm"
                :class="page.props.flash?.error ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-800'"
            >
                {{ page.props.flash?.error || page.props.flash?.success || page.props.flash?.info }}
            </div>

            <section>
                <div class="mb-4 flex items-center gap-2">
                    <BookOpenIcon class="h-5 w-5 text-emerald-600" />
                    <h2 class="text-lg font-semibold text-neutral-900">Purchased and available now</h2>
                </div>
                <div
                    v-if="purchasedItems?.data?.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <article
                        v-for="item in purchasedItems.data"
                        :key="item.uuid"
                        class="group flex flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <Link :href="readUrl(item)" class="relative block h-40 overflow-hidden bg-neutral-100">
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
                                <BookOpenIcon v-if="item.type === 'ebook'" class="h-12 w-12 text-white/90" />
                                <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-12 w-12 text-white/90" />
                                <DocumentTextIcon v-else class="h-12 w-12 text-white/90" />
                            </div>
                            <span class="absolute left-2 top-2 rounded bg-blue-600 px-2 py-1 text-xs font-semibold text-white">
                                {{ itemCategory(item) }}
                            </span>
                        </Link>
                        <div class="flex flex-1 flex-col p-4">
                            <div class="mb-2 flex items-center justify-between gap-3 text-xs font-medium text-neutral-500">
                                <span>{{ itemFormat(item) }}</span>
                                <button
                                    type="button"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-neutral-200 text-neutral-400 transition hover:border-blue-200 hover:text-blue-700"
                                    aria-label="Save for later"
                                >
                                    <BookmarkIcon class="h-4 w-4" />
                                </button>
                            </div>
                            <Link :href="readUrl(item)" class="block">
                                <h3 class="line-clamp-2 text-base font-semibold leading-6 text-neutral-950 transition group-hover:text-blue-700">
                                    {{ item.title }}
                                </h3>
                            </Link>
                            <p class="mt-1 text-sm text-neutral-500">by {{ itemCreator(item) }}</p>
                            <p class="mt-3 line-clamp-2 text-sm leading-6 text-neutral-600">
                                {{ itemDescription(item) }}
                            </p>
                            <div class="mt-4 flex items-center gap-5 text-xs text-neutral-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <DocumentTextIcon class="h-4 w-4 text-neutral-400" />
                                    {{ itemMeta(item) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <CalendarDaysIcon class="h-4 w-4 text-neutral-400" />
                                    {{ itemYear(item) }}
                                </span>
                            </div>
                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <Link
                                    :href="readUrl(item)"
                                    class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <BookOpenIcon class="h-4 w-4" />
                                    {{ item.type === 'audiobook' ? 'Continue listening' : 'Continue reading' }}
                                </Link>
                                <Link
                                    :href="route('library.favorite', item.slug)"
                                    method="post"
                                    as="button"
                                    preserve-scroll
                                    :title="isFavorited(item) ? 'Favorited' : 'Mark as favorite'"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-rose-200 text-rose-700 transition hover:bg-rose-50"
                                >
                                    <HeartSolidIcon v-if="isFavorited(item)" class="h-4 w-4" />
                                    <HeartIcon v-else class="h-4 w-4" />
                                </Link>
                                <Link
                                    :href="showUrl(item)"
                                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-neutral-200 px-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50"
                                >
                                    Details
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-lg border border-dashed border-neutral-300 bg-white p-6 text-sm text-neutral-600">
                    No purchased titles yet.
                </div>
                <div v-if="purchasedItems.links && purchasedItems.last_page > 1" class="mt-8 flex justify-center">
                    <nav class="flex flex-wrap items-center justify-center gap-1">
                        <template v-for="link in purchasedItems.links" :key="`purchased-${link.label}`">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'min-w-9 rounded-lg px-3 py-2 text-sm font-semibold transition',
                                    link.active ? 'bg-blue-600 text-white' : 'text-neutral-600 hover:bg-neutral-100',
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="min-w-9 cursor-not-allowed rounded-lg px-3 py-2 text-sm font-semibold text-neutral-300"
                                v-html="link.label"
                            />
                        </template>
                    </nav>
                </div>
            </section>

            <section>
                <div class="mb-4 flex items-center gap-2">
                    <ShoppingCartIcon class="h-5 w-5 text-blue-600" />
                    <h2 class="text-lg font-semibold text-neutral-900">Available to purchase</h2>
                </div>
                <div
                    v-if="availableItems?.data?.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <article
                        v-for="item in availableItems.data"
                        :key="item.uuid"
                        class="group flex flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <Link :href="showUrl(item)" class="relative block h-40 overflow-hidden bg-neutral-100">
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
                                <BookOpenIcon v-if="item.type === 'ebook'" class="h-12 w-12 text-white/90" />
                                <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-12 w-12 text-white/90" />
                                <DocumentTextIcon v-else class="h-12 w-12 text-white/90" />
                            </div>
                            <span class="absolute left-2 top-2 rounded bg-blue-600 px-2 py-1 text-xs font-semibold text-white">
                                {{ itemCategory(item) }}
                            </span>
                            <span
                                v-if="requiresPayment(item)"
                                class="absolute bottom-2 left-2 rounded bg-white px-2 py-1 text-xs font-semibold text-neutral-800 shadow-sm"
                            >
                                {{ formatPrice(item) }}
                            </span>
                        </Link>
                        <div class="flex flex-1 flex-col p-4">
                            <div class="mb-2 flex items-center justify-between gap-3 text-xs font-medium text-neutral-500">
                                <span>{{ itemFormat(item) }}</span>
                                <button
                                    type="button"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-neutral-200 text-neutral-400 transition hover:border-blue-200 hover:text-blue-700"
                                    aria-label="Save for later"
                                >
                                    <BookmarkIcon class="h-4 w-4" />
                                </button>
                            </div>
                            <Link :href="showUrl(item)" class="block">
                                <h3 class="line-clamp-2 text-base font-semibold leading-6 text-neutral-950 transition group-hover:text-blue-700">
                                    {{ item.title }}
                                </h3>
                            </Link>
                            <p class="mt-1 text-sm text-neutral-500">by {{ itemCreator(item) }}</p>
                            <p class="mt-3 line-clamp-2 text-sm leading-6 text-neutral-600">
                                {{ itemDescription(item) }}
                            </p>
                            <div class="mt-4 flex items-center gap-5 text-xs text-neutral-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <DocumentTextIcon class="h-4 w-4 text-neutral-400" />
                                    {{ itemMeta(item) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <CalendarDaysIcon class="h-4 w-4 text-neutral-400" />
                                    {{ itemYear(item) }}
                                </span>
                            </div>
                            <div class="mt-5 flex items-center gap-2">
                                <Link
                                    v-if="!requiresPayment(item)"
                                    :href="freePurchaseUrl(item)"
                                    method="post"
                                    as="button"
                                    type="button"
                                    class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <BookOpenIcon class="h-4 w-4" />
                                    Read now
                                </Link>
                                <Link
                                    v-else
                                    :href="route('library.cart.add', { item: item.slug })"
                                    method="post"
                                    as="button"
                                    type="button"
                                    :data="cartPortal"
                                    class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <ShoppingCartIcon class="h-4 w-4" />
                                    Add to cart
                                </Link>
                                <Link
                                    :href="showUrl(item)"
                                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-neutral-200 px-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50"
                                >
                                    Details
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-lg border border-dashed border-neutral-300 bg-white p-6 text-sm text-neutral-600">
                    No additional titles available right now.
                </div>
                <div v-if="availableItems.links && availableItems.last_page > 1" class="mt-8 flex justify-center">
                    <nav class="flex flex-wrap items-center justify-center gap-1">
                        <template v-for="link in availableItems.links" :key="`available-${link.label}`">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'min-w-9 rounded-lg px-3 py-2 text-sm font-semibold transition',
                                    link.active ? 'bg-blue-600 text-white' : 'text-neutral-600 hover:bg-neutral-100',
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="min-w-9 cursor-not-allowed rounded-lg px-3 py-2 text-sm font-semibold text-neutral-300"
                                v-html="link.label"
                            />
                        </template>
                    </nav>
                </div>
            </section>
        </div>
    </ProviderLayout>
</template>
