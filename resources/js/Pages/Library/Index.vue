<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import {
    Bars3Icon,
    BellIcon,
    BookOpenIcon,
    BookmarkIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    ListBulletIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
    MusicalNoteIcon,
    PhoneIcon,
    ShoppingCartIcon,
    Squares2X2Icon,
    UserCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    items: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    authors: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    groupedItems: { type: Array, default: () => [] },
    forcedType: { type: String, default: null },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const mobileMenuOpen = ref(false);
const viewMode = ref('grid');

const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || '');
const selectedRegion = ref(props.filters?.region || '');
const selectedAuthor = ref(props.filters?.author || '');
const selectedType = ref(props.filters?.type || '');
const selectedAccess = ref(props.filters?.access || '');
const selectedSort = ref(props.filters?.sort || 'newest');
const localeCodes = ['en', 'fr', 'es'];
const normalizeLocale = (value) => {
    const locale = typeof value === 'string' ? value.trim() : '';

    return localeCodes.includes(locale) ? locale : 'en';
};
const locale = ref(normalizeLocale(page.props.locale));

const user = computed(() => page.props.auth?.user ?? null);
const isAuthenticated = computed(() => Boolean(user.value));
const branding = computed(() => page.props.branding || {});
const companyName = computed(() => branding.value.company_name || 'Immigrant Knowhow');
const footerTagline = computed(() => (
    branding.value.footer_tagline
    || 'Immigrant Knowhow is where real support meets community. We help immigrants navigate life in a new country through trusted services, expert guidance, and meaningful human connection.'
));
const supportEmail = computed(() => branding.value.support_email || 'hello@immigrantknowhow.com');
const supportPhone = computed(() => branding.value.support_phone || '(646) 466-5105');
const supportAddress = computed(() => branding.value.support_address || '767 Broadway #627 Manhattan, NY 10003');

const storefrontRoute = computed(() => {
    if (props.forcedType === 'ebook') {
        return 'library.ebooks';
    }

    if (props.forcedType === 'audiobook') {
        return 'library.audiobooks';
    }

    return 'library.index';
});

const pageTitle = computed(() => (
    props.forcedType === 'audiobook'
        ? 'Explore Our Audio Collection'
        : 'Explore Our Book Collection'
));

const visibleItems = computed(() => props.items?.data ?? []);
const resultTotal = computed(() => Number(props.items?.total ?? visibleItems.value.length) || 0);
const resultLabel = computed(() => {
    const noun = props.forcedType === 'audiobook' ? 'audiobook' : 'eBook';
    const plural = resultTotal.value === 1 ? noun : `${noun}s`;

    return `${resultTotal.value} ${plural} found`;
});

const hasActiveFilters = computed(() => Boolean(
    search.value
    || selectedCategory.value
    || selectedRegion.value
    || selectedAuthor.value
    || selectedType.value
    || selectedAccess.value
    || (selectedSort.value && selectedSort.value !== 'newest')
));

const navLinks = computed(() => [
    { label: 'Community', href: route('home') },
    { label: 'Services', href: route('marketplace.index') },
    { label: 'Country', href: route('library.ebooks') },
    { label: 'Contact', href: `mailto:${supportEmail.value}` },
]);

watch(
    () => page.props.locale,
    (value) => {
        locale.value = normalizeLocale(value);
    },
);

const updateLocale = () => {
    router.post(route('locale.update'), { locale: locale.value }, {
        preserveScroll: true,
    });
};

const applyFilters = () => {
    router.get(route(storefrontRoute.value), {
        search: search.value || undefined,
        category: selectedCategory.value || undefined,
        region: selectedRegion.value || undefined,
        author: selectedAuthor.value || undefined,
        type: props.forcedType ? undefined : (selectedType.value || undefined),
        access: selectedAccess.value || undefined,
        sort: selectedSort.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    selectedRegion.value = '';
    selectedAuthor.value = '';
    selectedType.value = '';
    selectedAccess.value = '';
    selectedSort.value = 'newest';

    router.get(route(storefrontRoute.value), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const requiresPayment = (item) => Boolean(item?.is_premium) || Number(item?.price || 0) > 0;

const formatPrice = (item) => {
    const amount = Number(item?.price || 0);
    if (!amount) {
        return 'Free';
    }

    return `${item.currency ?? 'USD'} ${amount.toFixed(2)}`;
};

const stripHtml = (value) => String(value || '').replace(/<[^>]*>/g, '').trim();

const itemDescription = (item) => stripHtml(item?.description) || 'A practical guide for your immigration journey.';

const itemCategory = (item) => item?.category?.name || 'General';

const itemCreator = (item) => item?.author || item?.library_author?.name || 'Unknown Author';

const itemFormat = (item) => {
    if (item?.has_audio_companion) {
        return 'PDF + Audio';
    }

    if (item?.type === 'audiobook') {
        return 'Audio';
    }

    return 'PDF';
};

const itemMeta = (item) => {
    if (item?.duration_formatted) {
        return item.duration_formatted;
    }

    if (item?.file_size_formatted && item.file_size_formatted !== 'N/A') {
        return item.file_size_formatted;
    }

    return itemFormat(item);
};

const itemYear = (item) => {
    if (item?.published_at) {
        const publishedYear = new Date(item.published_at).getFullYear();
        if (!Number.isNaN(publishedYear)) {
            return publishedYear;
        }
    }

    if (item?.publication_year) {
        return item.publication_year;
    }

    if (item?.created_at) {
        const year = new Date(item.created_at).getFullYear();
        if (!Number.isNaN(year)) {
            return year;
        }
    }

    return 'Now';
};

const checkoutUrl = (item) => route('library.pay', { item: item.slug });
const readUrl = (item) => route('library.read', { item: item.slug });
const showUrl = (item) => route('library.show', { item: item.slug });
const freePurchaseUrl = (item) => route('library.purchase', { item: item.slug });

const actionLabel = (item) => {
    if (item?.has_access) {
        return item?.type === 'audiobook' ? 'Continue Listening' : 'Continue Reading';
    }

    if (requiresPayment(item)) {
        return isAuthenticated.value ? 'Add to cart' : 'Buy Now';
    }

    return 'Read Now';
};

const actionHref = (item) => {
    if (item?.has_access) {
        return readUrl(item);
    }

    if (requiresPayment(item)) {
        return checkoutUrl(item);
    }

    return showUrl(item);
};

const activePath = computed(() => (page.url || '').split('?')[0] || '/');
const isActiveHref = (href) => {
    if (!href || href.startsWith('mailto:')) {
        return false;
    }

    try {
        const url = new URL(href, window.location.origin);
        return activePath.value === url.pathname;
    } catch {
        return activePath.value === href;
    }
};
</script>

<template>
    <Head :title="props.forcedType === 'audiobook' ? 'Audiobooks' : 'eBooks'" />

    <div class="min-h-screen bg-white text-neutral-950">
        <header class="border-b border-neutral-200 bg-white">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link :href="route('home')" class="flex items-center">
                    <BrandLogo
                        context="site"
                        :show-name="false"
                        mark-class="flex h-8 w-32 items-center justify-start overflow-hidden rounded-none bg-transparent"
                        image-class="h-full w-auto object-contain object-left"
                    />
                </Link>

                <nav class="hidden items-center gap-7 md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.label"
                        :href="link.href"
                        :class="[
                            'text-sm font-medium transition hover:text-blue-700',
                            isActiveHref(link.href) ? 'text-blue-700' : 'text-neutral-700',
                        ]"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    <template v-if="isAuthenticated">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700"
                        >
                            <UserCircleIcon class="h-5 w-5" />
                            {{ user?.first_name || user?.name || 'Account' }}
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                            aria-label="Sign out"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-medium text-neutral-700 hover:text-blue-700">
                            Sign in
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        >
                            Sign up
                        </Link>
                    </template>
                    <BellIcon class="h-5 w-5 text-neutral-400" aria-hidden="true" />
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 md:hidden"
                    aria-label="Open menu"
                    @click="mobileMenuOpen = true"
                >
                    <Bars3Icon class="h-5 w-5" />
                </button>
            </div>

            <div v-if="mobileMenuOpen" class="border-t border-neutral-200 bg-white md:hidden">
                <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-sm font-semibold text-neutral-900">Menu</span>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-neutral-600 hover:bg-neutral-100"
                            aria-label="Close menu"
                            @click="mobileMenuOpen = false"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="grid gap-2">
                        <a
                            v-for="link in navLinks"
                            :key="link.label"
                            :href="link.href"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100"
                            @click="mobileMenuOpen = false"
                        >
                            {{ link.label }}
                        </a>
                        <Link
                            v-if="!isAuthenticated"
                            :href="route('login')"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50"
                            @click="mobileMenuOpen = false"
                        >
                            Sign in or create account
                        </Link>
                        <Link
                            v-else
                            :href="route('dashboard')"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50"
                            @click="mobileMenuOpen = false"
                        >
                            My account
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden border-b border-neutral-200 bg-neutral-100">
                <div
                    class="absolute inset-0 bg-cover bg-center opacity-20"
                    style="background-image: url('/images/airportcrowd.jpg')"
                    aria-hidden="true"
                ></div>
                <div class="absolute inset-0 bg-white/80" aria-hidden="true"></div>

                <div class="relative mx-auto max-w-6xl px-4 py-14 text-center sm:px-6 lg:px-8">
                    <h1 class="font-display text-3xl font-bold text-neutral-950 sm:text-4xl">
                        {{ pageTitle }}
                    </h1>

                    <form class="mx-auto mt-6 max-w-5xl" @submit.prevent="applyFilters">
                        <div class="mx-auto max-w-md">
                            <label for="library-search" class="sr-only">Search books</label>
                            <div class="relative">
                                <MagnifyingGlassIcon
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                                    aria-hidden="true"
                                />
                                <input
                                    id="library-search"
                                    v-model="search"
                                    type="search"
                                    placeholder="Search by title, author, or keyword..."
                                    class="h-10 w-full rounded-lg border border-neutral-200 bg-white pl-10 pr-3 text-sm text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                                />
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto_auto]">
                            <label class="sr-only" for="category-filter">Category</label>
                            <select
                                id="category-filter"
                                v-model="selectedCategory"
                                class="h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                            >
                                <option value="">All Categories</option>
                                <option v-for="category in categories" :key="category.slug" :value="category.slug">
                                    {{ category.name }}
                                </option>
                            </select>

                            <label class="sr-only" for="region-filter">Country</label>
                            <select
                                id="region-filter"
                                v-model="selectedRegion"
                                class="h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                            >
                                <option value="">All Countries</option>
                                <option v-for="region in regions" :key="region.value" :value="region.value">
                                    {{ region.label }}
                                </option>
                            </select>

                            <label class="sr-only" for="author-filter">Author</label>
                            <select
                                id="author-filter"
                                v-model="selectedAuthor"
                                class="h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                            >
                                <option value="">All Authors</option>
                                <option v-for="author in authors" :key="author.slug" :value="author.slug">
                                    {{ author.name }}
                                </option>
                            </select>

                            <template v-if="!props.forcedType">
                                <label class="sr-only" for="type-filter">Format</label>
                                <select
                                    id="type-filter"
                                    v-model="selectedType"
                                    class="h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                                >
                                    <option value="">All Formats</option>
                                    <option v-for="typeOption in types" :key="typeOption.value" :value="typeOption.value">
                                        {{ typeOption.label }}
                                    </option>
                                </select>
                            </template>
                            <template v-else>
                                <label class="sr-only" for="sort-filter">Sort</label>
                                <select
                                    id="sort-filter"
                                    v-model="selectedSort"
                                    class="h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                                >
                                    <option value="newest">Newest First</option>
                                    <option value="featured">Featured</option>
                                    <option value="popular">Popular</option>
                                    <option value="best_sellers">Best Sellers</option>
                                    <option value="title">Title</option>
                                    <option value="price_low">Price: Low to High</option>
                                    <option value="price_high">Price: High to Low</option>
                                </select>
                            </template>

                            <select
                                v-if="!props.forcedType"
                                v-model="selectedSort"
                                class="h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                            >
                                <option value="newest">Newest First</option>
                                <option value="featured">Featured</option>
                                <option value="popular">Popular</option>
                                <option value="best_sellers">Best Sellers</option>
                                <option value="title">Title</option>
                                <option value="price_low">Price: Low to High</option>
                                <option value="price_high">Price: High to Low</option>
                            </select>

                            <button
                                type="submit"
                                class="h-10 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Apply Filters
                            </button>

                            <button
                                type="button"
                                class="h-10 rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>

                        <div class="mt-4 flex justify-center">
                            <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm">
                                <span class="text-base">{{ locale.toUpperCase() }}</span>
                                <span class="h-4 w-px bg-neutral-200" aria-hidden="true"></span>
                                <span class="sr-only">Language</span>
                                <select
                                    v-model="locale"
                                    class="border-0 bg-transparent p-0 text-sm font-semibold text-neutral-700 outline-none focus:ring-0"
                                    @change="updateLocale"
                                >
                                    <option value="en">EN</option>
                                    <option value="fr">FR</option>
                                    <option value="es">ES</option>
                                </select>
                            </label>
                        </div>
                    </form>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success || page.props.flash?.info || page.props.flash?.error"
                    class="mb-6 rounded-lg border px-4 py-3 text-sm"
                    :class="page.props.flash?.error ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-800'"
                >
                    {{ page.props.flash?.error || page.props.flash?.success || page.props.flash?.info }}
                </div>

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-neutral-700">
                        {{ resultLabel }}
                    </p>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :class="[
                                'inline-flex h-9 w-9 items-center justify-center rounded-lg border transition',
                                viewMode === 'grid' ? 'border-blue-600 bg-blue-600 text-white' : 'border-neutral-200 text-neutral-500 hover:bg-neutral-50',
                            ]"
                            aria-label="Grid view"
                            @click="viewMode = 'grid'"
                        >
                            <Squares2X2Icon class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            :class="[
                                'inline-flex h-9 w-9 items-center justify-center rounded-lg border transition',
                                viewMode === 'list' ? 'border-blue-600 bg-blue-600 text-white' : 'border-neutral-200 text-neutral-500 hover:bg-neutral-50',
                            ]"
                            aria-label="List view"
                            @click="viewMode = 'list'"
                        >
                            <ListBulletIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div
                    v-if="visibleItems.length"
                    :class="[
                        viewMode === 'grid'
                            ? 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3'
                            : 'grid gap-4',
                    ]"
                >
                    <article
                        v-for="item in visibleItems"
                        :key="item.uuid"
                        :class="[
                            'group overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                            viewMode === 'list' ? 'grid gap-4 p-4 sm:grid-cols-[12rem_1fr]' : '',
                        ]"
                    >
                        <Link
                            :href="item.has_access ? readUrl(item) : showUrl(item)"
                            :class="[
                                'relative block overflow-hidden bg-neutral-100',
                                viewMode === 'list' ? 'h-44 rounded-lg sm:h-full' : 'h-44',
                            ]"
                        >
                            <img
                                v-if="item.cover_image_url"
                                :src="item.cover_image_url"
                                :alt="item.title"
                                class="h-full w-full object-cover object-top transition duration-300 group-hover:scale-105"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center bg-neutral-100">
                                <DocumentTextIcon class="h-16 w-16 text-neutral-300" />
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

                        <div :class="viewMode === 'list' ? 'flex min-w-0 flex-col' : 'p-4'">
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

                            <Link :href="item.has_access ? readUrl(item) : showUrl(item)" class="block">
                                <h2 class="line-clamp-2 text-base font-semibold leading-6 text-neutral-950 transition group-hover:text-blue-700">
                                    {{ item.title }}
                                </h2>
                            </Link>

                            <p class="mt-1 text-sm text-neutral-500">
                                by {{ itemCreator(item) }}
                            </p>

                            <p class="mt-3 line-clamp-2 text-sm leading-6 text-neutral-600">
                                {{ itemDescription(item) }}
                            </p>

                            <div class="mt-5 flex items-center gap-5 text-xs text-neutral-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <DocumentTextIcon class="h-4 w-4 text-neutral-400" />
                                    {{ itemMeta(item) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <CalendarDaysIcon class="h-4 w-4 text-neutral-400" />
                                    {{ itemYear(item) }}
                                </span>
                            </div>

                            <div class="mt-5 flex items-center gap-3">
                                <Link
                                    v-if="isAuthenticated && !item.has_access && !requiresPayment(item)"
                                    :href="freePurchaseUrl(item)"
                                    method="post"
                                    as="button"
                                    type="button"
                                    class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <BookOpenIcon class="h-4 w-4" />
                                    {{ actionLabel(item) }}
                                </Link>
                                <a
                                    v-else
                                    :href="actionHref(item)"
                                    class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <ShoppingCartIcon v-if="requiresPayment(item) && !item.has_access" class="h-4 w-4" />
                                    <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-4 w-4" />
                                    <BookOpenIcon v-else class="h-4 w-4" />
                                    {{ actionLabel(item) }}
                                </a>

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

                <div v-else class="rounded-lg border border-neutral-200 bg-white px-6 py-14 text-center shadow-sm">
                    <BookOpenIcon class="mx-auto h-12 w-12 text-neutral-300" />
                    <h2 class="mt-4 text-lg font-semibold text-neutral-950">No books found</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-600">
                        {{
                            hasActiveFilters
                                ? 'Try another search or clear the filters.'
                                : 'New titles are coming soon.'
                        }}
                    </p>
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="mt-5 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                        @click="resetFilters"
                    >
                        Clear Filters
                    </button>
                </div>

                <div v-if="items.links && items.last_page > 1" class="mt-10 flex justify-center">
                    <nav class="flex flex-wrap items-center justify-center gap-1">
                        <Link
                            v-for="link in items.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'min-w-9 rounded-lg px-3 py-2 text-sm font-semibold transition',
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : link.url
                                      ? 'text-neutral-600 hover:bg-neutral-100'
                                      : 'cursor-not-allowed text-neutral-300',
                            ]"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </section>
        </main>

        <footer class="border-t border-neutral-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid gap-10 md:grid-cols-[1.2fr_0.7fr_1fr_1fr]">
                    <div>
                        <BrandLogo
                            context="site"
                            :show-name="false"
                            mark-class="flex h-10 w-36 items-center justify-start overflow-hidden rounded-none bg-transparent"
                            image-class="h-full w-auto object-contain object-left"
                        />
                        <p class="mt-4 max-w-sm text-sm leading-6 text-neutral-600">
                            {{ footerTagline }}
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-neutral-950">Links</h3>
                        <ul class="mt-4 space-y-3 text-sm text-neutral-600">
                            <li><Link :href="route('home')" class="hover:text-blue-700">Home</Link></li>
                            <li><Link :href="route('marketplace.index')" class="hover:text-blue-700">Community</Link></li>
                            <li><Link :href="route('marketplace.index')" class="hover:text-blue-700">Services</Link></li>
                            <li><a :href="`mailto:${supportEmail}`" class="hover:text-blue-700">Contact</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-neutral-950">Promise</h3>
                        <p class="mt-4 text-sm leading-6 text-neutral-600">
                            We're here to make immigration feel less isolating and more empowering, connecting practical help with real support.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-neutral-950">Contact</h3>
                        <ul class="mt-4 space-y-3 text-sm text-neutral-600">
                            <li class="flex gap-2">
                                <MapPinIcon class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                                <span>{{ supportAddress }}</span>
                            </li>
                            <li class="flex gap-2">
                                <PhoneIcon class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                                <a :href="`tel:${supportPhone}`" class="hover:text-blue-700">{{ supportPhone }}</a>
                            </li>
                            <li class="flex gap-2">
                                <EnvelopeIcon class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                                <a :href="`mailto:${supportEmail}`" class="hover:text-blue-700">{{ supportEmail }}</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-10 flex flex-col gap-4 border-t border-neutral-200 pt-6 text-sm text-neutral-500 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ new Date().getFullYear() }} {{ companyName }}</p>
                    <div class="flex items-center gap-5">
                        <a href="#" class="hover:text-blue-700">Terms</a>
                        <a href="#" class="hover:text-blue-700">Privacy</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
