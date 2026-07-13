<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeftIcon,
    BookOpenIcon,
    MusicalNoteIcon,
    ClockIcon,
    DocumentTextIcon,
    HeartIcon,
    ShareIcon,
    CheckCircleIcon,
    PlayIcon,
    ChevronRightIcon,
    LockClosedIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolid } from '@heroicons/vue/24/solid';
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { renderSafeMarkdown } from '@/utils/markdown';

const props = defineProps({
    item: { type: Object, required: true },
    relatedItems: { type: Array, default: () => [] },
    userAccess: { type: Object, default: null },
    hasAccess: { type: Boolean, default: false },
    requiresPaidAccess: { type: Boolean, default: false },
    stripeSetupNote: { type: String, default: null },
    libraryPaymentMode: { type: String, default: 'stripe' },
    manualPaymentPending: { type: Boolean, default: false },
    mediaUrls: { type: Object, default: () => ({}) },
    progressUrl: { type: String, default: null },
    summary: { type: String, default: null },
    summaryUrl: { type: String, default: null },
    summaryStatus: { type: String, default: null },
});

const page = usePage();
const isOpening = ref(false);
const isFavorited = ref(props.userAccess?.is_favorite || false);
const activeDetailTab = ref('more');
const landingAudioRef = ref(null);
const summaryText = ref(props.summary || '');
const summaryLoading = ref(false);
const summaryError = ref('');

console.group('[Library Show] Summary initial state');
console.info('Item slug:', props.item?.slug);
console.info('User has access:', props.hasAccess);
console.info('Summary status:', props.summaryStatus || 'not_generated');
console.info('Summary prop chars:', (props.summary || '').length);
console.info('Summary URL available:', Boolean(props.summaryUrl));
console.groupEnd();

const savedAudioProgress = computed(() => {
    const progress = props.userAccess?.progress ?? {};

    return progress.audio ?? (progress.position !== undefined ? progress : {});
});
const landingAudioCurrentTime = ref(Number(savedAudioProgress.value?.position) || 0);
const landingAudioDuration = ref(Number(savedAudioProgress.value?.duration) || props.item.duration_seconds || 0);
let landingAudioSaveTimer = null;
let landingAudioLastSaveAt = 0;
let landingAudioWasRestored = false;

/** Full URL for Stripe / manual pay step — use a real `<a href>` so navigation works even if an Inertia visit stalls (e.g. slow Stripe API). */
const libraryPayUrl = computed(() => route('library.pay', { item: props.item.slug }));
const sharePurchaseUrl = computed(() => {
    const path = route('library.show', { item: props.item.slug });
    const isAbsolute = /^https?:\/\//i.test(String(path));
    if (typeof window !== 'undefined' && !isAbsolute) {
        return `${window.location.origin}${path}`;
    }
    return path;
});

const libraryCartAddUrl = computed(() => route('library.cart.add', { item: props.item.slug }));

const libraryListUrl = computed(() => {
    if (props.item.type === 'audiobook') {
        return route('library.audiobooks');
    }
    if (props.item.type === 'ebook') {
        return route('library.ebooks');
    }
    return route('library.index');
});

const libraryListLabel = computed(() => {
    if (props.item.type === 'audiobook') {
        return 'Audiobooks';
    }
    if (props.item.type === 'ebook') {
        return 'E-Books';
    }
    return 'Library';
});

const formatFileSize = (bytes) => {
    if (!bytes) return 'N/A';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
};

const formatDuration = (seconds) => {
    if (!seconds) return 'N/A';
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins} min`;
};

const openItem = () => {
    isOpening.value = true;
    router.visit(route('library.read', props.item.slug), {
        onFinish: () => {
            isOpening.value = false;
        },
    });
    setTimeout(() => {
        isOpening.value = false;
    }, 3000);
};

const toggleFavorite = () => {
    router.post(`/library/${props.item.slug}/favorite`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            isFavorited.value = !isFavorited.value;
        },
    });
};

const purchaseItem = () => {
    router.post(route('library.purchase', { item: props.item.slug }), {}, {
        preserveScroll: true,
    });
};

const sharePurchaseLink = async () => {
    const title = `"${props.item.title}" on IKH Library`;
    const url = sharePurchaseUrl.value;

    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
        try {
            await navigator.share({ title, url });
            return;
        } catch {
            // Fall back to clipboard below.
        }
    }

    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(url);
        window.alert('Book link copied.');
        return;
    }

    window.prompt('Copy this book link:', url);
};

const progressValue = (progress) => {
    if (!progress) return 0;
    if (progress.percentage) return Math.round(progress.percentage);
    if (progress.page && progress.total_pages) {
        return Math.round((progress.page / progress.total_pages) * 100);
    }
    if (progress.position && progress.duration) {
        return Math.round((progress.position / progress.duration) * 100);
    }
    return 0;
};

const progressPercentage = computed(() => {
    if (!props.userAccess?.progress) return 0;

    const progress = props.userAccess.progress;
    if (props.item.type === 'ebook') {
        return Math.max(progressValue(progress.reading ?? progress), progressValue(progress.audio));
    }

    if (props.item.type === 'audiobook') {
        return progressValue(progress.audio ?? progress);
    }

    return 0;
});

const hasAudioCompanion = computed(() => props.item.type === 'ebook' && props.item.has_audio_companion);
const landingAudioUrl = computed(() => props.mediaUrls?.audio || null);
const canPlayLandingAudio = computed(() => props.hasAccess && Boolean(landingAudioUrl.value));
const landingAudioPercent = computed(() => {
    if (!landingAudioDuration.value) return 0;

    return Math.min(100, Math.max(0, Math.round((landingAudioCurrentTime.value / landingAudioDuration.value) * 100)));
});
const accessActionLabel = computed(() => {
    if (hasAudioCompanion.value) return 'Read or listen now';
    return props.item.type === 'ebook' ? 'Read now' : 'Listen now';
});
const addAccessLabel = computed(() => {
    if (hasAudioCompanion.value) return 'Add to library';
    return props.item.type === 'ebook' ? 'Add to library & read' : 'Add to library & listen';
});
const canShowSummaryTab = computed(() => props.item.type === 'ebook');
const canRequestSummary = computed(() => Boolean(props.summaryUrl) && !summaryText.value);
const renderedSummaryHtml = computed(() => renderSafeMarkdown(summaryText.value));

const formatDate = (value) => {
    if (!value) return null;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const publicationDisplay = computed(() => {
    if (props.item.published_at) {
        return formatDate(props.item.published_at);
    }

    return props.item.publication_year ? String(props.item.publication_year) : null;
});

const formatReadingTime = (minutes) => {
    const value = Number(minutes);
    if (!Number.isFinite(value) || value <= 0) return null;

    if (value < 60) {
        return `${Math.round(value)} min`;
    }

    const hours = value / 60;

    return `${Number.isInteger(hours) ? hours : hours.toFixed(1)} hr`;
};

const formatPlaybackTime = (seconds) => {
    const safeSeconds = Math.max(0, Number(seconds) || 0);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainingSeconds = Math.floor(safeSeconds % 60);

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    }

    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
};

const csrfToken = () => {
    const rawToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return rawToken ? decodeURIComponent(rawToken) : '';
};

const loadSummary = async () => {
    if (!props.summaryUrl || summaryLoading.value) {
        console.warn('[Library Show] Summary request skipped', {
            hasSummaryUrl: Boolean(props.summaryUrl),
            summaryLoading: summaryLoading.value,
        });
        return;
    }

    summaryLoading.value = true;
    summaryError.value = '';
    console.info('[Library Show] Summary request started', {
        itemSlug: props.item?.slug,
        summaryStatus: props.summaryStatus,
    });

    const token = csrfToken();
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    try {
        const response = await fetch(props.summaryUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({}),
        });
        if (!response.ok) {
            throw new Error('Summary request failed');
        }
        const payload = await response.json();
        console.info('[Library Show] Summary response received', {
            status: payload?.status,
            generatedAt: payload?.generated_at,
            summaryChars: (payload?.summary || '').length,
            hasMessage: Boolean(payload?.message),
        });
        summaryText.value = payload.summary || '';
        if (!summaryText.value) {
            summaryError.value = payload.message || 'Summary not available yet for this ebook.';
            console.warn('[Library Show] Summary empty', {
                message: summaryError.value,
            });
        }
    } catch (error) {
        console.error('[Library Show] Summary request failed', error);
        summaryError.value = 'Could not load summary right now. Please try again.';
    } finally {
        summaryLoading.value = false;
        console.info('[Library Show] Summary request finished');
    }
};

watch(activeDetailTab, (tab) => {
    if (tab !== 'summary') return;

    console.info('[Library Show] Summary tab opened', {
        hasSummaryText: Boolean(summaryText.value),
        summaryTextChars: summaryText.value.length,
        summaryStatus: props.summaryStatus,
        canRequestSummary: canRequestSummary.value,
    });
});

const landingAudioPayload = () => ({
    position: Math.floor(landingAudioCurrentTime.value),
    duration: Math.floor(landingAudioDuration.value),
    percentage: landingAudioPercent.value,
});

const persistLandingAudioProgress = async (progress, keepalive = false) => {
    if (!props.progressUrl) return;

    const token = csrfToken();
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    try {
        await fetch(props.progressUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive,
            headers,
            body: JSON.stringify({
                mode: 'audio',
                progress,
            }),
        });
    } catch {
        // Listening progress should never interrupt the book page.
    }
};

const queueLandingAudioSave = (delay = 900) => {
    if (!canPlayLandingAudio.value) return;

    clearTimeout(landingAudioSaveTimer);
    landingAudioSaveTimer = setTimeout(() => {
        persistLandingAudioProgress(landingAudioPayload());
    }, delay);
};

const flushLandingAudioProgress = () => {
    clearTimeout(landingAudioSaveTimer);

    if (canPlayLandingAudio.value) {
        persistLandingAudioProgress(landingAudioPayload(), true);
    }
};

const restoreLandingAudioPosition = () => {
    if (landingAudioWasRestored || !landingAudioRef.value) return;

    const savedPosition = Number(savedAudioProgress.value?.position) || 0;
    const duration = landingAudioRef.value.duration || landingAudioDuration.value || 0;

    if (savedPosition > 1 && (!duration || savedPosition < duration - 2)) {
        landingAudioRef.value.currentTime = savedPosition;
        landingAudioCurrentTime.value = savedPosition;
    }

    landingAudioWasRestored = true;
};

const handleLandingAudioLoaded = () => {
    landingAudioDuration.value = landingAudioRef.value?.duration || landingAudioDuration.value || 0;
    restoreLandingAudioPosition();
    queueLandingAudioSave(300);
};

const handleLandingAudioTimeUpdate = () => {
    landingAudioCurrentTime.value = landingAudioRef.value?.currentTime || 0;
    landingAudioDuration.value = landingAudioRef.value?.duration || landingAudioDuration.value || 0;

    const now = Date.now();
    if (now - landingAudioLastSaveAt > 5000) {
        landingAudioLastSaveAt = now;
        queueLandingAudioSave();
    }
};

const handleLandingAudioEnded = () => {
    landingAudioCurrentTime.value = landingAudioDuration.value;
    queueLandingAudioSave(0);
};

const handleLandingAudioVisibility = () => {
    if (document.visibilityState === 'hidden') {
        flushLandingAudioProgress();
    }
};

const languageLabelsByCode = {
    en: 'English',
    fr: 'French',
    es: 'Spanish',
    de: 'German',
    it: 'Italian',
    pt: 'Portuguese',
    ar: 'Arabic',
    hi: 'Hindi',
    zh: 'Chinese',
    ja: 'Japanese',
    ko: 'Korean',
    ru: 'Russian',
};

const formatLanguageDisplay = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';

    const compactTokens = raw
        .split(/[\s,;|/]+/)
        .map((token) => token.trim().toLowerCase())
        .filter((token) => /^[a-z]{2,5}$/.test(token));

    const uniqueTokens = [...new Set(compactTokens)];
    if (uniqueTokens.length) {
        const labels = uniqueTokens.map((code) => {
            const codeLabel = languageLabelsByCode[code];
            return codeLabel ? `${code.toUpperCase()}: ${codeLabel}` : code.toUpperCase();
        });
        return labels.join(', ');
    }

    return raw;
};

const metadataFacts = computed(() => ([
    { label: 'Author', value: props.item.author },
    { label: 'Publisher', value: props.item.publisher },
    { label: 'Published', value: publicationDisplay.value },
    { label: 'Pages Count', value: props.item.page_count ? `${props.item.page_count}` : null },
    { label: 'ISBN', value: props.item.isbn },
    { label: 'Language', value: formatLanguageDisplay(props.item.language) },
]).filter((fact) => fact.value !== null && fact.value !== undefined && String(fact.value).trim() !== ''));

const moreInfoFacts = computed(() => ([
    { label: 'Estimated Reading Time', value: formatReadingTime(props.item.estimated_reading_minutes) },
    { label: 'Difficulty Level', value: props.item.difficulty_level },
    { label: 'Recommended Age Group', value: props.item.recommended_age_group },
]).filter((fact) => fact.value !== null && fact.value !== undefined && String(fact.value).trim() !== ''));

onMounted(() => {
    if (canShowSummaryTab.value) {
        activeDetailTab.value = 'summary';
    }
    document.addEventListener('visibilitychange', handleLandingAudioVisibility);
    window.addEventListener('pagehide', flushLandingAudioProgress);
});

onBeforeUnmount(() => {
    flushLandingAudioProgress();
    document.removeEventListener('visibilitychange', handleLandingAudioVisibility);
    window.removeEventListener('pagehide', flushLandingAudioProgress);
});
</script>

<template>
    <Head :title="item.title" />

    <AppLayout>
        <div
            class="relative min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-100/90 via-slate-50 to-white pb-12 pt-6 sm:pb-16 sm:pt-8"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,rgba(59,149,243,0.18),transparent)]"
                aria-hidden="true"
            />
            <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <!-- Back + path -->
                <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-center sm:gap-4">
                    <Link
                        :href="libraryListUrl"
                        class="group inline-flex w-fit shrink-0 items-center gap-2 rounded-2xl border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/90 px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-200/50 transition hover:-translate-y-px hover:border-primary-200/90 hover:from-primary-50/30 hover:to-white hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30"
                    >
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100/90 text-slate-600 ring-1 ring-slate-200/60 transition group-hover:bg-primary-50 group-hover:text-primary-700 group-hover:ring-primary-200/60">
                            <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <span>Back to {{ libraryListLabel }}</span>
                    </Link>
                    <nav
                        class="min-w-0 flex-1"
                        aria-label="Breadcrumb"
                    >
                        <ol
                            class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1 rounded-2xl border border-slate-200/60 bg-white/80 px-3 py-2.5 shadow-sm ring-1 ring-slate-200/40 backdrop-blur-sm sm:px-4 sm:py-2.5"
                        >
                            <li class="inline-flex min-w-0 items-center">
                                <Link
                                    :href="libraryListUrl"
                                    class="truncate text-xs font-semibold text-slate-500 transition hover:text-primary-600 sm:text-sm"
                                >
                                    {{ libraryListLabel }}
                                </Link>
                            </li>
                            <li class="inline-flex" aria-hidden="true">
                                <ChevronRightIcon class="h-3.5 w-3.5 text-slate-300 sm:h-4 sm:w-4" />
                            </li>
                            <li
                                v-if="item.category"
                                class="inline-flex min-w-0 items-center"
                            >
                                <Link
                                    :href="`/library?category=${item.category.slug}`"
                                    class="truncate text-xs font-semibold text-slate-500 transition hover:text-primary-600 sm:text-sm"
                                >
                                    {{ item.category.name }}
                                </Link>
                            </li>
                            <li
                                v-if="item.category"
                                class="inline-flex"
                                aria-hidden="true"
                            >
                                <ChevronRightIcon class="h-3.5 w-3.5 text-slate-300 sm:h-4 sm:w-4" />
                            </li>
                            <li
                                class="min-w-0 text-xs font-bold text-slate-900 sm:text-sm"
                                aria-current="page"
                            >
                                <span class="line-clamp-2 sm:line-clamp-1">{{ item.title }}</span>
                            </li>
                        </ol>
                    </nav>
                </div>

                <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                    <!-- Main -->
                    <div class="lg:col-span-8">
                        <article
                            class="overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-soft-lg ring-1 ring-slate-200/60 backdrop-blur-sm"
                        >
                            <div class="relative p-5 sm:p-8">
                                <div
                                    class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-primary-400/10 blur-3xl"
                                    aria-hidden="true"
                                />
                                <div class="relative flex flex-col gap-6 sm:flex-row sm:gap-8">
                                    <!-- Cover -->
                                    <div class="mx-auto shrink-0 sm:mx-0">
                                        <div
                                            class="relative overflow-hidden rounded-2xl shadow-soft ring-2 ring-white sm:w-[11.5rem]"
                                        >
                                            <div class="relative aspect-[3/4] w-36 bg-slate-100 sm:w-full">
                                                <img
                                                    v-if="item.cover_image_url"
                                                    :src="item.cover_image_url"
                                                    :alt="item.title"
                                                    class="absolute inset-0 h-full w-full object-contain"
                                                />
                                                <div
                                                    v-else
                                                    class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary-500 via-primary-600 to-primary-800"
                                                >
                                                    <BookOpenIcon
                                                        v-if="item.type === 'ebook'"
                                                        class="h-12 w-12 text-white/90 drop-shadow-md"
                                                    />
                                                    <MusicalNoteIcon v-else class="h-12 w-12 text-white/90 drop-shadow-md" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 space-y-2">
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full border border-primary-100 bg-primary-50/90 px-3 py-1 text-xs font-semibold text-primary-700 shadow-sm"
                                                >
                                                    <BookOpenIcon v-if="item.type === 'ebook'" class="h-3.5 w-3.5" />
                                                    <MusicalNoteIcon v-else class="h-3.5 w-3.5" />
                                                    {{ item.type === 'ebook' ? 'E-Book' : 'Audiobook' }}
                                                </span>
                                                <h1
                                                    class="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl xl:text-4xl"
                                                >
                                                    {{ item.title }}
                                                </h1>
	                                                <p v-if="item.author" class="text-base text-slate-600">
	                                                    <span class="text-slate-400">by</span>
	                                                    {{ item.author }}
	                                                </p>
	                                            </div>
                                            <button
                                                type="button"
                                                title="Favorite"
                                                class="group shrink-0 rounded-full border border-slate-200/80 bg-white/90 p-2.5 shadow-sm transition hover:border-primary-200 hover:bg-primary-50/80 hover:shadow-md"
                                                @click="toggleFavorite"
                                            >
                                                <HeartSolid
                                                    v-if="isFavorited"
                                                    class="h-6 w-6 text-rose-500 transition group-hover:scale-105"
                                                />
                                                <HeartIcon
                                                    v-else
                                                    class="h-6 w-6 text-slate-400 transition group-hover:text-rose-400"
                                                />
	                                            </button>
	                                        </div>

		                                        <div class="mt-5 flex flex-wrap gap-2">
                                            <div
                                                v-if="item.page_count"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <DocumentTextIcon class="h-4 w-4 text-slate-400" />
                                                {{ item.page_count }} pages
                                            </div>
                                            <div
                                                v-if="item.duration_seconds"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <ClockIcon class="h-4 w-4 text-slate-400" />
                                                {{ formatDuration(item.duration_seconds) }}
                                            </div>
                                            <div
                                                v-if="hasAudioCompanion"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-sky-100 bg-sky-50/90 px-3 py-1.5 text-sm text-sky-800"
                                            >
                                                <MusicalNoteIcon class="h-4 w-4 text-sky-500" />
                                                Audio included
                                            </div>
                                            <div
                                                v-if="item.file_size"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <DocumentTextIcon class="h-4 w-4 text-slate-400" />
                                                {{ formatFileSize(item.file_size) }}
                                            </div>
                                            <div
                                                v-if="item.view_count"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <CheckCircleIcon class="h-4 w-4 text-emerald-500/80" />
                                                {{ item.view_count.toLocaleString() }} views
                                            </div>
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <Link
                                                v-if="item.category"
                                                :href="`/library?category=${item.category.slug}`"
                                                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-primary-200 hover:text-primary-700"
                                            >
                                                {{ item.category.name }}
                                            </Link>
                                            <span
                                                v-if="item.language"
                                                class="inline-flex items-center rounded-full border border-sky-100 bg-sky-50/90 px-3 py-1 text-xs font-semibold text-sky-800"
                                            >
                                                {{ formatLanguageDisplay(item.language) }}
                                            </span>
                                        </div>

                                        <div v-if="userAccess && progressPercentage > 0" class="mt-6">
                                            <div class="mb-2 flex items-center justify-between text-sm font-medium text-slate-600">
                                                <span>Your progress</span>
                                                <span class="tabular-nums text-primary-700">{{ progressPercentage }}%</span>
                                            </div>
	                                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/60">
	                                                <div
	                                                    class="h-full rounded-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-500"
	                                                    :style="{ width: `${progressPercentage}%` }"
	                                                />
		                                        </div>
		                                    </div>
		                                </div>
	                                </div>

	                                <dl
	                                    v-if="metadataFacts.length"
	                                    class="relative mt-6 grid grid-cols-1 gap-3 border-t border-slate-100 pt-6 md:grid-cols-2"
	                                >
	                                    <div
	                                        v-for="fact in metadataFacts"
	                                        :key="fact.label"
	                                        class="grid gap-1 rounded-xl bg-slate-50 px-4 py-3 text-sm sm:grid-cols-[8.5rem_1fr] sm:items-start sm:gap-4"
	                                    >
	                                        <dt class="font-semibold text-slate-900">{{ fact.label }}:</dt>
	                                        <dd class="min-w-0 break-words text-slate-700">{{ fact.value }}</dd>
	                                    </div>
	                                </dl>
                            </div>

	                            <div class="border-t border-slate-100/90 bg-slate-50/40 px-5 py-6 sm:px-8 sm:py-8">
	                                <div
	                                    v-if="canShowSummaryTab || moreInfoFacts.length || item.description"
	                                    class="mb-5 inline-flex rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200"
	                                >
	                                    <button
	                                        v-if="canShowSummaryTab"
	                                        type="button"
	                                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
	                                        :class="activeDetailTab === 'summary' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
	                                        @click="activeDetailTab = 'summary'"
	                                    >
	                                        Summary
	                                    </button>
	                                    <button
	                                        type="button"
	                                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
	                                        :class="activeDetailTab === 'more' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
	                                        @click="activeDetailTab = 'more'"
	                                    >
	                                        More Info
	                                    </button>
	                                </div>

	                                <dl v-if="activeDetailTab === 'more'" class="space-y-3">
	                                    <div class="rounded-xl bg-white px-4 py-4 text-sm ring-1 ring-slate-100">
	                                        <h2 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
	                                            About this {{ item.type === 'ebook' ? 'book' : 'audiobook' }}
	                                        </h2>
	                                        <p class="mt-2 whitespace-pre-line leading-relaxed text-slate-600">
	                                            {{ item.description || 'No description available yet.' }}
	                                        </p>
	                                    </div>
	                                    <div
	                                        v-for="fact in moreInfoFacts"
	                                        :key="fact.label"
	                                        class="grid grid-cols-[minmax(0,14rem)_1fr] gap-4 rounded-xl bg-white px-4 py-3 text-sm ring-1 ring-slate-100"
	                                    >
	                                        <dt class="font-semibold text-slate-900">{{ fact.label }}:</dt>
	                                        <dd class="text-slate-600">{{ fact.value }}</dd>
	                                    </div>
	                                </dl>

	                                <div v-else class="space-y-3">
	                                    <div class="flex items-center justify-between gap-3">
	                                        <h2 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
	                                            AI Summary
	                                        </h2>
	                                        <button
	                                            v-if="canRequestSummary"
	                                            type="button"
	                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
	                                            :disabled="summaryLoading"
	                                            @click="loadSummary"
	                                        >
	                                            {{ summaryLoading ? 'Loading summary...' : 'Summarize this book' }}
	                                        </button>
	                                    </div>

	                                    <p v-if="summaryStatus === 'failed'" class="text-sm text-amber-700">
	                                        Summary generation failed previously for this book. Check logs before retrying via code/admin.
	                                    </p>
	                                    <p v-if="summaryError" class="text-sm text-red-600">{{ summaryError }}</p>
	                                    <div
	                                        v-else-if="summaryText"
	                                        class="prose prose-slate prose-sm max-w-none sm:prose-base"
	                                        v-html="renderedSummaryHtml"
	                                    >
	                                    </div>
	                                    <p v-else class="text-sm text-slate-600">
	                                        Summary will appear here when ready.
	                                    </p>
	                                </div>
	                            </div>

                            <div
                                v-if="item.table_of_contents?.length"
                                class="border-t border-slate-100/90 px-5 py-6 sm:px-8 sm:py-8"
                            >
                                <h2 class="font-display text-lg font-bold text-slate-900 sm:text-xl">Table of contents</h2>
                                <ul class="mt-4 space-y-2">
                                    <li
                                        v-for="(chapter, index) in item.table_of_contents"
                                        :key="index"
                                        class="flex items-baseline gap-3 rounded-xl px-3 py-2 text-slate-600 transition hover:bg-white/80"
                                    >
                                        <span
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-500"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span class="text-sm sm:text-base">{{ chapter.title || chapter }}</span>
                                    </li>
                                </ul>
                            </div>
                        </article>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-4">
                        <div
                            class="sticky top-24 overflow-hidden rounded-3xl border border-white/80 bg-white/85 shadow-soft-lg ring-1 ring-slate-200/50 backdrop-blur-md"
                        >
                            <div
                                class="border-b border-slate-100/90 bg-gradient-to-br from-primary-600/5 via-white to-sky-50/30 px-6 py-5"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Library</p>
                                <p class="mt-1 font-display text-lg font-bold text-slate-900">Get this title</p>
                            </div>

                            <div class="space-y-4 px-6 py-6">
                                <div
                                    v-if="hasAccess"
                                    class="flex items-center gap-2 rounded-2xl border border-emerald-100 bg-emerald-50/80 px-4 py-3 text-emerald-800"
                                >
                                    <CheckCircleIcon class="h-5 w-5 shrink-0 text-emerald-600" />
                                    <span class="text-sm font-semibold">You have full access</span>
                                </div>

                                <button
                                    v-if="hasAccess"
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:from-primary-500 hover:to-primary-600 disabled:opacity-60"
                                    :disabled="isOpening"
                                    @click="openItem"
                                >
                                    <BookOpenIcon v-if="!isOpening && item.type === 'ebook'" class="h-5 w-5" />
                                    <PlayIcon v-else-if="!isOpening" class="h-5 w-5" />
                                    <svg
                                        v-else
                                        class="h-5 w-5 animate-spin"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{ isOpening ? 'Opening...' : accessActionLabel }}
                                </button>

                                <p
                                    v-if="hasAccess && hasAudioCompanion"
                                    class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-2 text-sm leading-relaxed text-sky-900"
                                >
                                    Includes protected PDF reading and companion audio listening in the same player.
                                </p>

                                <div
                                    v-if="canPlayLandingAudio"
                                    class="space-y-3 rounded-xl border border-sky-100 bg-sky-50/80 p-4 text-sky-950"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-sky-700 shadow-sm ring-1 ring-sky-100">
                                                <MusicalNoteIcon class="h-5 w-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold">Listen to audio</p>
                                                <p class="mt-0.5 text-xs leading-relaxed text-sky-800">
                                                    Your listening position saves automatically.
                                                </p>
                                            </div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-sky-700 shadow-sm ring-1 ring-sky-100">
                                            {{ landingAudioPercent }}%
                                        </span>
                                    </div>

                                    <div class="h-2 overflow-hidden rounded-full bg-white ring-1 ring-sky-100">
                                        <div
                                            class="h-full rounded-full bg-sky-500 transition-all duration-300"
                                            :style="{ width: `${landingAudioPercent}%` }"
                                        />
                                    </div>

                                    <audio
                                        ref="landingAudioRef"
                                        class="w-full"
                                        controls
                                        controlsList="nodownload"
                                        preload="metadata"
                                        :src="landingAudioUrl"
                                        @contextmenu.prevent
                                        @loadedmetadata="handleLandingAudioLoaded"
                                        @timeupdate="handleLandingAudioTimeUpdate"
                                        @pause="queueLandingAudioSave(0)"
                                        @seeked="queueLandingAudioSave(0)"
                                        @ended="handleLandingAudioEnded"
                                    />

                                    <div class="flex items-center justify-between text-xs font-semibold text-sky-800">
                                        <span class="tabular-nums">{{ formatPlaybackTime(landingAudioCurrentTime) }}</span>
                                        <span class="tabular-nums">{{ formatPlaybackTime(landingAudioDuration) }}</span>
                                    </div>
                                </div>

                                <div v-if="!hasAccess && (item.is_premium || requiresPaidAccess)" class="space-y-4">
                                    <p v-if="libraryPaymentMode === 'stripe'" class="text-sm leading-relaxed text-slate-600">
                                        Unlock this {{ item.type === 'ebook' ? 'e-book' : (item.type === 'video' ? 'video' : 'audiobook') }} with Stripe's secure
                                        checkout (same hosted flow as provider subscriptions). You will return here when payment completes.
                                    </p>
                                    <p v-else-if="libraryPaymentMode === 'manual'" class="text-sm leading-relaxed text-slate-600">
                                        Unlock this {{ item.type === 'ebook' ? 'e-book' : (item.type === 'video' ? 'video' : 'audiobook') }} by completing payment
                                        (bank transfer, PayPal, or another method we support). You will submit a payment reference on the next step.
                                    </p>
                                    <p v-else class="text-sm leading-relaxed text-slate-600">
                                        Card checkout is being set up for this title. Please check back soon.
                                    </p>
                                    <div
                                        v-if="item.price"
                                        class="flex items-end justify-between rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3"
                                    >
                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                                            <p class="font-display text-2xl font-bold tracking-tight text-slate-900">
                                                {{ item.currency ?? 'USD' }}
                                                <span class="tabular-nums">{{ item.price }}</span>
                                            </p>
                                        </div>
                                        <LockClosedIcon class="h-8 w-8 text-slate-300" aria-hidden="true" />
                                    </div>
                                    <p
                                        v-if="page.props.flash?.error"
                                        class="rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700"
                                    >
                                        {{ page.props.flash.error }}
                                    </p>
                                    <p
                                        v-if="stripeSetupNote"
                                        class="rounded-xl border border-amber-100 bg-amber-50/90 px-3 py-2 text-sm text-amber-900"
                                    >
                                        {{ stripeSetupNote }}
                                    </p>
                                    <div
                                        v-if="manualPaymentPending"
                                        class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950"
                                    >
                                        <p class="font-semibold">Payment verification pending</p>
                                        <p class="mt-1 text-amber-900/90">
                                            We received your payment details. An administrator will confirm and unlock your access — check back soon.
                                        </p>
                                    </div>
                                    <a
                                        v-if="!manualPaymentPending && libraryPaymentMode !== 'unavailable'"
                                        :href="libraryPayUrl"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/30 transition hover:from-primary-500 hover:to-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                                    >
                                        <template v-if="libraryPaymentMode === 'stripe'">
                                            Continue to payment
                                            <span v-if="item.price" class="opacity-95">· {{ item.currency ?? 'USD' }} {{ item.price }}</span>
                                        </template>
                                        <template v-else>
                                            Continue to pay (manual)
                                            <span v-if="item.price" class="opacity-95">· {{ item.currency ?? 'USD' }} {{ item.price }}</span>
                                        </template>
                                    </a>
                                    <Link
                                        v-if="!manualPaymentPending && libraryPaymentMode === 'stripe'"
                                        :href="libraryCartAddUrl"
                                        method="post"
                                        as="button"
                                        type="button"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-slate-50"
                                    >
                                        Add to cart
                                    </Link>
                                    <a
                                        v-else-if="manualPaymentPending && libraryPaymentMode !== 'unavailable'"
                                        :href="libraryPayUrl"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-slate-50"
                                    >
                                        View payment instructions
                                    </a>
                                    <p
                                        v-if="libraryPaymentMode === 'stripe'"
                                        class="flex items-start gap-2 text-xs leading-relaxed text-slate-500"
                                    >
                                        <ShieldCheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600/80" />
                                        Apple Pay, Google Pay, and cards show when your browser supports them.
                                    </p>
                                </div>

                                <div v-else-if="!hasAccess" class="space-y-4 text-center">
                                    <p class="text-sm leading-relaxed text-slate-600">
                                        This {{ item.type === 'ebook' ? 'e-book' : (item.type === 'video' ? 'video' : 'audiobook') }} is free — add it to your
                                        library to open it.
                                    </p>
                                    <button
                                        type="button"
                                        class="w-full rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:from-primary-500 hover:to-primary-600"
                                        @click="purchaseItem"
                                    >
                                        {{ addAccessLabel }}
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                    @click="sharePurchaseLink"
                                >
                                    <ShareIcon class="h-4 w-4" />
                                    Share
                                </button>

                                <dl class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-slate-500">Format</dt>
                                        <dd class="font-semibold text-slate-900">
                                            {{ item.file_format?.toUpperCase() || 'PDF' }}
                                        </dd>
                                    </div>
	                                    <div v-if="item.isbn" class="flex items-center justify-between gap-3">
	                                        <dt class="text-slate-500">ISBN</dt>
	                                        <dd class="font-mono text-xs font-semibold text-slate-900">{{ item.isbn }}</dd>
	                                    </div>
	                                    <div v-if="publicationDisplay" class="flex items-center justify-between gap-3">
	                                        <dt class="text-slate-500">Published</dt>
	                                        <dd class="font-semibold text-slate-900">{{ publicationDisplay }}</dd>
	                                    </div>
	                                    <div v-if="item.publisher" class="flex items-center justify-between gap-3">
	                                        <dt class="text-slate-500">Publisher</dt>
	                                        <dd class="font-semibold text-slate-900">{{ item.publisher }}</dd>
	                                    </div>
	                                    <div v-if="item.language" class="flex items-center justify-between gap-3">
	                                        <dt class="text-slate-500">Language</dt>
	                                        <dd class="font-semibold text-slate-900">{{ formatLanguageDisplay(item.language) }}</dd>
	                                    </div>
	                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related -->
                <section v-if="relatedItems.length" class="mt-12 sm:mt-16">
                    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Discover more</p>
                            <h2 class="font-display text-xl font-bold text-slate-900 sm:text-2xl">You might also like</h2>
                        </div>
                        <Link
                            :href="`/library?category=${item.category?.slug}`"
                            class="text-sm font-semibold text-primary-600 transition hover:text-primary-700"
                        >
                            View all in category →
                        </Link>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5 xl:grid-cols-6">
                        <Link
                            v-for="related in relatedItems"
                            :key="related.id"
                            :href="`/library/${related.slug}`"
                            class="group overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 shadow-sm ring-1 ring-slate-100/80 transition hover:-translate-y-0.5 hover:border-primary-200/60 hover:shadow-soft-lg"
                        >
                            <div class="relative aspect-[4/5] w-full overflow-hidden bg-slate-100">
                                <img
                                    v-if="related.cover_image_url"
                                    :src="related.cover_image_url"
                                    :alt="related.title"
                                    class="absolute inset-0 h-full w-full object-contain transition duration-500 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary-500 to-primary-800"
                                >
                                    <BookOpenIcon
                                        v-if="related.type === 'ebook'"
                                        class="h-10 w-10 text-white/90"
                                    />
                                    <MusicalNoteIcon v-else class="h-10 w-10 text-white/90" />
                                </div>
                                <span
                                    class="absolute right-2 top-2 rounded-full bg-white/95 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 shadow-sm backdrop-blur-sm"
                                >
                                    {{ related.type === 'ebook' ? 'E-Book' : 'Audio' }}
                                </span>
                            </div>
                            <div class="p-3">
                                <h3
                                    class="line-clamp-2 text-sm font-semibold leading-snug text-slate-900 group-hover:text-primary-700"
                                >
                                    {{ related.title }}
                                </h3>
                                <p v-if="related.author" class="mt-1 truncate text-xs text-slate-500">
                                    {{ related.author }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
