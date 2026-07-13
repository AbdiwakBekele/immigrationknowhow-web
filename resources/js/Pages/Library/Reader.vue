<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeftIcon,
    BookOpenIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    MusicalNoteIcon,
    PauseIcon,
    PlayIcon,
} from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as pdfjsLib from 'pdfjs-dist/build/pdf.mjs';
import PdfJsWorker from 'pdfjs-dist/build/pdf.worker.mjs?worker&inline';
import { renderSafeMarkdown } from '@/utils/markdown';

if (!pdfjsLib.GlobalWorkerOptions.workerPort) {
    pdfjsLib.GlobalWorkerOptions.workerPort = new PdfJsWorker();
}

const props = defineProps({
    item: { type: Object, required: true },
    userAccess: { type: Object, default: null },
    mediaUrl: { type: String, required: true },
    mediaUrls: { type: Object, default: () => ({}) },
    progressUrl: { type: String, required: true },
    summary: { type: String, default: null },
    summaryUrl: { type: String, default: null },
});

const pdfMediaUrl = computed(() => props.mediaUrls?.pdf || (props.item.type === 'ebook' ? props.mediaUrl : null));
const audioMediaUrl = computed(() => props.mediaUrls?.audio || (props.item.type === 'audiobook' ? props.mediaUrl : null));
const hasPdf = computed(() => Boolean(pdfMediaUrl.value));
const hasAudio = computed(() => Boolean(audioMediaUrl.value));
const activeMode = ref(hasPdf.value ? 'reading' : 'audio');
const isPdf = computed(() => activeMode.value === 'reading' && hasPdf.value);
const isAudio = computed(() => activeMode.value === 'audio' && hasAudio.value);

const savedProgress = computed(() => props.userAccess?.progress ?? {});
const readingProgress = computed(() => savedProgress.value?.reading ?? savedProgress.value ?? {});
const audioProgress = computed(() => savedProgress.value?.audio ?? savedProgress.value ?? {});

const canvasRef = ref(null);
const canvasWrapRef = ref(null);
const audioRef = ref(null);
const isLoading = ref(false);
const isRendering = ref(false);
const errorMessage = ref('');
const currentPage = ref(Math.max(1, Number(readingProgress.value?.page) || 1));
const totalPages = ref(Number(readingProgress.value?.total_pages) || 0);
const pageInput = ref(currentPage.value);
const zoom = ref(1);
const audioCurrentTime = ref(Number(audioProgress.value?.position) || 0);
const audioDuration = ref(Number(audioProgress.value?.duration) || props.item.duration_seconds || 0);
const isAudioPlaying = ref(false);
const aiSummary = ref(props.summary || '');
const summaryLoading = ref(false);
const summaryError = ref('');
const renderedSummaryHtml = computed(() => renderSafeMarkdown(aiSummary.value));

let pdfDocument = null;
let renderTask = null;
let renderRun = 0;
let resizeTimer = null;
let saveTimer = null;
let lastQueuedProgress = null;
let lastAudioSaveAt = 0;
let audioWasRestored = false;

const contentLabel = computed(() => {
    if (hasPdf.value && hasAudio.value) return 'Read or listen to';
    return hasPdf.value ? 'Read' : 'Listen';
});

const readingPercent = computed(() => {
    if (!totalPages.value) return 0;
    return Math.min(100, Math.max(0, Math.round((currentPage.value / totalPages.value) * 100)));
});

const audioPercent = computed(() => {
    if (!audioDuration.value) return 0;
    return Math.min(100, Math.max(0, Math.round((audioCurrentTime.value / audioDuration.value) * 100)));
});

const displayPercent = computed(() => (activeMode.value === 'reading' ? readingPercent.value : audioPercent.value));

const csrfToken = () => {
    const rawToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return rawToken ? decodeURIComponent(rawToken) : '';
};

const persistProgress = async (mode, progress, keepalive = false) => {
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
            body: JSON.stringify({ mode, progress }),
        });
    } catch {
        // Progress autosave should never interrupt reading or listening.
    }
};

const loadSummary = async () => {
    if (!props.summaryUrl || summaryLoading.value || aiSummary.value) return;

    summaryLoading.value = true;
    summaryError.value = '';

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
        aiSummary.value = payload.summary || '';
        if (!aiSummary.value) {
            summaryError.value = payload.message || 'Summary not available yet for this ebook.';
        }
    } catch {
        summaryError.value = 'Could not load summary right now. Please try again.';
    } finally {
        summaryLoading.value = false;
    }
};

const queueProgressSave = (mode, progress, delay = 900) => {
    lastQueuedProgress = { mode, progress };
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        const queued = lastQueuedProgress;
        lastQueuedProgress = null;
        if (queued) {
            persistProgress(queued.mode, queued.progress);
        }
    }, delay);
};

const flushProgress = () => {
    if (!lastQueuedProgress) return;

    const queued = lastQueuedProgress;
    lastQueuedProgress = null;
    clearTimeout(saveTimer);
    persistProgress(queued.mode, queued.progress, true);
};

const formatTime = (seconds) => {
    const safeSeconds = Math.max(0, Number(seconds) || 0);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainingSeconds = Math.floor(safeSeconds % 60);

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    }

    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
};

const readingPayload = () => ({
    page: currentPage.value,
    total_pages: totalPages.value,
    percentage: readingPercent.value,
});

const audioPayload = () => ({
    position: Math.floor(audioCurrentTime.value),
    duration: Math.floor(audioDuration.value),
    percentage: audioPercent.value,
});

const fetchProtectedPdf = async () => {
    const response = await fetch(pdfMediaUrl.value, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/pdf,*/*',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`PDF request failed with status ${response.status}`);
    }

    const contentType = response.headers.get('Content-Type') || '';
    if (contentType.includes('text/html')) {
        throw new Error('PDF request returned an HTML page.');
    }

    const bytes = await response.arrayBuffer();
    if (!bytes.byteLength) {
        throw new Error('PDF response was empty.');
    }

    return new Uint8Array(bytes);
};

const loadPdf = async () => {
    if (!pdfMediaUrl.value) return;

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const data = await fetchProtectedPdf();
        const task = pdfjsLib.getDocument({
            data,
        });

        pdfDocument = await task.promise;
        totalPages.value = pdfDocument.numPages;
        currentPage.value = Math.min(Math.max(currentPage.value, 1), totalPages.value);
        pageInput.value = currentPage.value;
        await nextTick();
        await renderPdfPage();
        queueProgressSave('reading', readingPayload(), 300);
    } catch {
        errorMessage.value = 'We could not open this PDF. Please refresh and try again.';
    } finally {
        isLoading.value = false;
    }
};

const renderPdfPage = async () => {
    if (!pdfDocument || !canvasRef.value) return;

    const run = ++renderRun;
    isRendering.value = true;
    errorMessage.value = '';

    if (renderTask) {
        renderTask.cancel();
    }

    try {
        const page = await pdfDocument.getPage(currentPage.value);
        if (run !== renderRun) return;

        const baseViewport = page.getViewport({ scale: 1 });
        const availableWidth = Math.max(320, (canvasWrapRef.value?.clientWidth ?? 900) - 32);
        // Never start smaller than the PDF's native size (scale=1).
        // If the container is narrower, we prefer scrolling over rendering a tiny canvas.
        const fitScale = Math.max(1, Math.min(1.45, availableWidth / baseViewport.width));
        const viewport = page.getViewport({ scale: fitScale * zoom.value });
        const canvas = canvasRef.value;
        const context = canvas.getContext('2d');
        const outputScale = window.devicePixelRatio || 1;

        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        context.setTransform(outputScale, 0, 0, outputScale, 0, 0);
        renderTask = page.render({ canvasContext: context, viewport });
        await renderTask.promise;

        if (run === renderRun) {
            queueProgressSave('reading', readingPayload());
        }
    } catch (error) {
        if (error?.name !== 'RenderingCancelledException') {
            errorMessage.value = 'This page could not render. Try another page or refresh.';
        }
    } finally {
        if (run === renderRun) {
            isRendering.value = false;
        }
    }
};

const goToPage = (page) => {
    if (!totalPages.value) return;

    const nextPage = Math.min(Math.max(Number(page) || 1, 1), totalPages.value);
    currentPage.value = nextPage;
    pageInput.value = nextPage;
};

const submitPageInput = () => {
    goToPage(pageInput.value);
};

const zoomIn = () => {
    zoom.value = Math.min(2, Number((zoom.value + 0.15).toFixed(2)));
};

const zoomOut = () => {
    zoom.value = Math.max(0.7, Number((zoom.value - 0.15).toFixed(2)));
};

const switchMode = (mode) => {
    if (mode === activeMode.value) return;

    if (mode === 'reading' && audioRef.value && !audioRef.value.paused) {
        audioRef.value.pause();
    }

    activeMode.value = mode;
};

const restoreAudioPosition = () => {
    if (audioWasRestored || !audioRef.value) return;

    const savedPosition = Number(audioProgress.value?.position) || 0;
    const duration = audioRef.value.duration || audioDuration.value || 0;

    if (savedPosition > 1 && (!duration || savedPosition < duration - 2)) {
        audioRef.value.currentTime = savedPosition;
        audioCurrentTime.value = savedPosition;
    }

    audioWasRestored = true;
};

const handleAudioLoaded = () => {
    audioDuration.value = audioRef.value?.duration || audioDuration.value || 0;
    restoreAudioPosition();
    queueProgressSave('audio', audioPayload(), 300);
};

const handleAudioTimeUpdate = () => {
    audioCurrentTime.value = audioRef.value?.currentTime || 0;
    audioDuration.value = audioRef.value?.duration || audioDuration.value || 0;

    const now = Date.now();
    if (now - lastAudioSaveAt > 5000) {
        lastAudioSaveAt = now;
        queueProgressSave('audio', audioPayload());
    }
};

const handleAudioEnded = () => {
    audioCurrentTime.value = audioDuration.value;
    isAudioPlaying.value = false;
    queueProgressSave('audio', audioPayload(), 0);
};

const toggleAudioPlayback = async () => {
    if (!audioRef.value) return;

    if (audioRef.value.paused) {
        await audioRef.value.play();
    } else {
        audioRef.value.pause();
    }
};

const handleResize = () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        if (isPdf.value) {
            renderPdfPage();
        }
    }, 160);
};

const handleVisibilityChange = () => {
    if (document.visibilityState === 'hidden') {
        if (hasAudio.value) {
            queueProgressSave('audio', audioPayload(), 0);
        }
        flushProgress();
    }
};

watch(currentPage, (page) => {
    pageInput.value = page;
    if (pdfDocument) {
        renderPdfPage();
    }
});

watch(zoom, () => {
    if (pdfDocument) {
        renderPdfPage();
    }
});

watch(activeMode, async (mode) => {
    if (mode === 'reading' && hasPdf.value && !pdfDocument) {
        await loadPdf();
    }

    if (mode === 'audio') {
        await nextTick();
        restoreAudioPosition();
    }
});

onMounted(() => {
    if (hasPdf.value && activeMode.value === 'reading') {
        loadPdf();
        window.addEventListener('resize', handleResize);
    } else if (!hasAudio.value) {
        errorMessage.value = 'This title does not have a readable or playable file yet.';
    }

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('pagehide', flushProgress);
});

onBeforeUnmount(() => {
    if (hasAudio.value) {
        queueProgressSave('audio', audioPayload(), 0);
    }

    flushProgress();
    clearTimeout(resizeTimer);
    clearTimeout(saveTimer);
    window.removeEventListener('resize', handleResize);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('pagehide', flushProgress);

    if (renderTask) {
        renderTask.cancel();
    }

    if (pdfDocument) {
        pdfDocument.destroy();
    }
});
</script>

<template>
    <Head :title="`${contentLabel} ${item.title}`" />

    <AppLayout>
        <div class="min-h-[calc(100vh-4rem)] bg-slate-950 text-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 border-b border-white/10 pb-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <Link
                            :href="route('library.show', item.slug)"
                            class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"
                        >
                            <ArrowLeftIcon class="h-4 w-4" />
                            Back to title
                        </Link>
                        <div class="mt-3 flex min-w-0 items-start gap-3">
                            <div class="mt-1 rounded-lg bg-white/10 p-2">
                                <BookOpenIcon v-if="isPdf" class="h-5 w-5 text-sky-300" />
                                <MusicalNoteIcon v-else class="h-5 w-5 text-emerald-300" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">
                                    {{ isPdf ? 'PDF reader' : 'Audio player' }}
                                </p>
                                <h1 class="truncate text-xl font-bold text-white sm:text-2xl">
                                    {{ item.title }}
                                </h1>
                                <p v-if="item.author" class="mt-1 truncate text-sm text-slate-400">
                                    {{ item.author }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 lg:w-72">
                        <div class="mb-1 flex items-center justify-between text-xs font-medium text-slate-300">
                            <span>Your Progress</span>
                            <span class="tabular-nums">{{ displayPercent }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-800">
                            <div
                                class="h-full rounded-full bg-sky-400 transition-all duration-300"
                                :style="{ width: `${displayPercent}%` }"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasPdf && hasAudio"
                    class="flex flex-wrap gap-2 rounded-lg border border-white/10 bg-white/5 p-2"
                >
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition',
                            activeMode === 'reading'
                                ? 'bg-white text-slate-950'
                                : 'text-slate-300 hover:bg-white/10 hover:text-white',
                        ]"
                        @click="switchMode('reading')"
                    >
                        <BookOpenIcon class="h-4 w-4" />
                        Read PDF
                    </button>
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition',
                            activeMode === 'audio'
                                ? 'bg-white text-slate-950'
                                : 'text-slate-300 hover:bg-white/10 hover:text-white',
                        ]"
                        @click="switchMode('audio')"
                    >
                        <MusicalNoteIcon class="h-4 w-4" />
                        Listen audio
                    </button>
                </div>

                <div
                    v-if="errorMessage"
                    class="rounded-lg border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-100"
                >
                    {{ errorMessage }}
                </div>

                <section v-if="hasPdf" v-show="isPdf" class="flex min-h-[72vh] flex-col overflow-hidden rounded-lg border border-white/10 bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-slate-950/80 px-3 py-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center gap-2 rounded-md border border-white/10 px-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="currentPage <= 1 || isLoading"
                                @click="goToPage(currentPage - 1)"
                            >
                                <ChevronLeftIcon class="h-4 w-4" />
                                Previous
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-10 items-center gap-2 rounded-md border border-white/10 px-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="currentPage >= totalPages || isLoading"
                                @click="goToPage(currentPage + 1)"
                            >
                                Next
                                <ChevronRightIcon class="h-4 w-4" />
                            </button>
                        </div>

                        <form class="flex items-center gap-2 text-sm text-slate-300" @submit.prevent="submitPageInput">
                            <span>Page</span>
                            <input
                                v-model="pageInput"
                                type="number"
                                min="1"
                                :max="totalPages || 1"
                                class="h-10 w-20 rounded-md border border-white/10 bg-slate-900 px-2 text-center text-sm font-semibold text-white focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/20"
                            >
                            <span class="tabular-nums">of {{ totalPages || '-' }}</span>
                        </form>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="h-10 rounded-md border border-white/10 px-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10"
                                @click="zoomOut"
                            >
                                Zoom -
                            </button>
                            <span class="w-14 text-center text-sm tabular-nums text-slate-300">
                                {{ Math.round(zoom * 100) }}%
                            </span>
                            <button
                                type="button"
                                class="h-10 rounded-md border border-white/10 px-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10"
                                @click="zoomIn"
                            >
                                Zoom +
                            </button>
                        </div>
                    </div>

                    <div
                        ref="canvasWrapRef"
                        class="relative flex flex-1 justify-center overflow-auto bg-slate-800 px-3 py-5 sm:px-5"
                    >
                        <div v-if="isLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-slate-900/80">
                            <div class="rounded-lg border border-white/10 bg-slate-950 px-4 py-3 text-sm font-semibold text-slate-200">
                                Opening PDF...
                            </div>
                        </div>
                        <div v-if="isRendering && !isLoading" class="absolute right-4 top-4 z-10 rounded-md bg-slate-950/90 px-3 py-1.5 text-xs font-semibold text-slate-300">
                            Rendering...
                        </div>
                        <canvas
                            ref="canvasRef"
                            class="select-none bg-white shadow-2xl"
                            @contextmenu.prevent
                        />
                    </div>
                </section>

                <section v-if="hasAudio" v-show="isAudio" class="grid min-h-[72vh] items-center gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <div class="flex justify-center">
                        <div class="w-full max-w-sm overflow-hidden rounded-lg border border-white/10 bg-white/5">
                            <div class="relative aspect-[3/4] bg-slate-900">
                                <img
                                    v-if="item.cover_image_url"
                                    :src="item.cover_image_url"
                                    :alt="item.title"
                                    class="absolute inset-0 h-full w-full object-contain object-top"
                                    draggable="false"
                                    @contextmenu.prevent
                                >
                                <div v-else class="absolute inset-0 flex items-center justify-center">
                                    <MusicalNoteIcon class="h-20 w-20 text-emerald-300" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-white/10 bg-white/5 p-5 sm:p-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.12em] text-emerald-300">Now listening</p>
                        <h2 class="mt-2 text-2xl font-bold text-white sm:text-3xl">{{ item.title }}</h2>
                        <p v-if="item.narrator" class="mt-2 text-sm text-slate-300">Narrated by {{ item.narrator }}</p>
                        <p v-else-if="item.author" class="mt-2 text-sm text-slate-300">By {{ item.author }}</p>

                        <div class="mt-8">
                            <div class="mb-2 flex items-center justify-between text-sm text-slate-300">
                                <span class="tabular-nums">{{ formatTime(audioCurrentTime) }}</span>
                                <span class="tabular-nums">{{ formatTime(audioDuration) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-800">
                                <div
                                    class="h-full rounded-full bg-emerald-400 transition-all duration-300"
                                    :style="{ width: `${audioPercent}%` }"
                                />
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-12 w-12 items-center justify-center rounded-md bg-emerald-400 text-slate-950 transition hover:bg-emerald-300"
                                @click="toggleAudioPlayback"
                            >
                                <PauseIcon v-if="isAudioPlaying" class="h-6 w-6" />
                                <PlayIcon v-else class="ml-0.5 h-6 w-6" />
                            </button>
                            <p class="text-sm text-slate-300">
                                Your listening position saves automatically.
                            </p>
                        </div>

                        <audio
                            ref="audioRef"
                            class="mt-6 w-full"
                            controls
                            controlsList="nodownload"
                            preload="metadata"
                            :src="audioMediaUrl"
                            @contextmenu.prevent
                            @loadedmetadata="handleAudioLoaded"
                            @play="isAudioPlaying = true"
                            @pause="isAudioPlaying = false; queueProgressSave('audio', audioPayload(), 0)"
                            @timeupdate="handleAudioTimeUpdate"
                            @ended="handleAudioEnded"
                        />

                        <p class="mt-4 text-xs leading-relaxed text-slate-400">
                            Access stays inside your account and this player keeps your place for next time.
                        </p>
                    </div>
                </section>

                <section
                    v-if="hasPdf"
                    class="rounded-lg border border-white/10 bg-slate-900/60 p-4 sm:p-5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-white sm:text-lg">AI Summary</h2>
                        <button
                            type="button"
                            class="rounded-md border border-white/20 px-3 py-1.5 text-xs font-semibold text-slate-100 hover:bg-white/10"
                            :disabled="summaryLoading"
                            @click="loadSummary"
                        >
                            {{ summaryLoading ? 'Loading summary...' : 'Summarize this book' }}
                        </button>
                    </div>

                    <p v-if="summaryError" class="mt-3 text-sm text-rose-300">{{ summaryError }}</p>
                    <div
                        v-else-if="aiSummary"
                        class="prose prose-invert mt-3 max-w-none text-sm leading-relaxed"
                        v-html="renderedSummaryHtml"
                    >
                    </div>
                    <p v-else class="mt-3 text-sm text-slate-300">
                        Summary will appear here when ready.
                    </p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
