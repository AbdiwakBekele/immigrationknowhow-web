<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
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
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';
import { renderSafeMarkdown } from '@/utils/markdown';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const props = defineProps({
    item: { type: Object, required: true },
    userAccess: { type: Object, default: null },
    mediaUrl: { type: String, required: true },
    mediaUrls: { type: Object, default: () => ({}) },
    progressUrl: { type: String, required: true },
    summary: { type: String, default: null },
    summaryUrl: { type: String, default: null },
    summaryStatus: { type: String, default: null },
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
    if (token) headers['X-XSRF-TOKEN'] = token;

    try {
        await fetch(props.progressUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive,
            headers,
            body: JSON.stringify({ mode, progress }),
        });
    } catch {
        // ignore
    }
};

const queueSave = (mode, progress, delay = 1000) => {
    clearTimeout(saveTimer);
    lastQueuedProgress = { mode, progress };
    saveTimer = setTimeout(() => {
        if (!lastQueuedProgress) return;
        persistProgress(lastQueuedProgress.mode, lastQueuedProgress.progress);
        lastQueuedProgress = null;
    }, delay);
};

const flushProgress = () => {
    clearTimeout(saveTimer);
    if (lastQueuedProgress) {
        persistProgress(lastQueuedProgress.mode, lastQueuedProgress.progress, true);
        lastQueuedProgress = null;
    }
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

const handleVisibilityChange = () => {
    if (document.visibilityState === 'hidden') {
        flushProgress();
    }
};

const handleResize = () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        if (!isPdf.value) return;
        renderPage(currentPage.value);
    }, 250);
};

const loadPdf = async () => {
    if (!pdfMediaUrl.value) return;
    isLoading.value = true;
    errorMessage.value = '';

    try {
        pdfDocument = await pdfjsLib.getDocument({ url: pdfMediaUrl.value, withCredentials: true }).promise;
        totalPages.value = pdfDocument.numPages || 0;
        currentPage.value = Math.min(Math.max(1, currentPage.value), totalPages.value || 1);
        pageInput.value = currentPage.value;
        // The canvas is only mounted when `isLoading` is false (template uses v-if/v-else).
        // Ensure the first render happens after the canvas exists.
        isLoading.value = false;
        await nextTick();
        await renderPage(currentPage.value);
    } catch (e) {
        errorMessage.value = 'Could not load this PDF right now.';
        isLoading.value = false;
    } finally {
        if (isLoading.value) {
            isLoading.value = false;
        }
    }
};

const renderPage = async (pageNumber) => {
    if (!pdfDocument || !canvasRef.value || isRendering.value) return;
    isRendering.value = true;
    errorMessage.value = '';
    renderRun += 1;
    const thisRun = renderRun;

    try {
        if (renderTask) renderTask.cancel();
        const page = await pdfDocument.getPage(pageNumber);

        const baseViewport = page.getViewport({ scale: 1 });
        const availableWidth = Math.max(320, (canvasWrapRef.value?.clientWidth ?? 900) - 32);
        // Important: never downscale below the PDF's native size (scale=1).
        // If the container is narrower, we scroll instead of rendering a tiny "square".
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

        if (thisRun !== renderRun) return;

        queueSave('reading', readingPayload());
    } catch (e) {
        if (String(e?.name || '') === 'RenderingCancelledException') return;
        errorMessage.value = 'Could not render this page.';
    } finally {
        isRendering.value = false;
    }
};

const gotoPage = (pageNumber) => {
    const n = Math.min(Math.max(1, Number(pageNumber) || 1), totalPages.value || 1);
    currentPage.value = n;
    pageInput.value = n;
    renderPage(n);
};

const prevPage = () => gotoPage(currentPage.value - 1);
const nextPage = () => gotoPage(currentPage.value + 1);

const zoomIn = () => {
    zoom.value = Math.min(2.5, Math.round((zoom.value + 0.1) * 10) / 10);
    renderPage(currentPage.value);
};
const zoomOut = () => {
    zoom.value = Math.max(0.6, Math.round((zoom.value - 0.1) * 10) / 10);
    renderPage(currentPage.value);
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
    queueSave('audio', audioPayload(), 350);
};

const handleAudioTimeUpdate = () => {
    audioCurrentTime.value = audioRef.value?.currentTime || 0;
    audioDuration.value = audioRef.value?.duration || audioDuration.value || 0;
    const now = Date.now();
    if (now - lastAudioSaveAt > 5000) {
        lastAudioSaveAt = now;
        queueSave('audio', audioPayload());
    }
};

const toggleAudioPlayback = () => {
    if (!audioRef.value) return;
    if (audioRef.value.paused) {
        audioRef.value.play();
        isAudioPlaying.value = true;
    } else {
        audioRef.value.pause();
        isAudioPlaying.value = false;
    }
};

const formatPlaybackTime = (seconds) => {
    const safeSeconds = Math.max(0, Number(seconds) || 0);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainingSeconds = Math.floor(safeSeconds % 60);
    if (hours > 0) return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
};

const loadSummary = async () => {
    if (!props.summaryUrl || summaryLoading.value) return;

    summaryLoading.value = true;
    summaryError.value = '';

    const token = csrfToken();
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (token) headers['X-XSRF-TOKEN'] = token;

    try {
        const response = await fetch(props.summaryUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({}),
        });
        if (!response.ok) throw new Error('Summary request failed');
        const payload = await response.json();
        aiSummary.value = payload.summary || '';
        if (!aiSummary.value) {
            summaryError.value = payload.message || 'Summary not available yet.';
        }
    } catch {
        summaryError.value = 'Could not load summary right now. Please try again.';
    } finally {
        summaryLoading.value = false;
    }
};

watch(activeMode, async (mode) => {
    if (mode === 'reading' && hasPdf.value && !pdfDocument) {
        await loadPdf();
    }
});

onMounted(async () => {
    if (isPdf.value) {
        await loadPdf();
    }
    window.addEventListener('resize', handleResize);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('pagehide', flushProgress);
});

onBeforeUnmount(() => {
    flushProgress();
    clearTimeout(resizeTimer);
    clearTimeout(saveTimer);
    window.removeEventListener('resize', handleResize);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('pagehide', flushProgress);

    if (renderTask) renderTask.cancel();
    if (pdfDocument) pdfDocument.destroy();
});
</script>

<template>
    <Head :title="`${contentLabel} ${item.title}`" />

    <ProviderLayout>
        <div class="min-h-[calc(100vh-4rem)] bg-slate-950 text-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 border-b border-white/10 pb-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <Link
                            :href="route('provider.library.show', item.slug)"
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
                            <div class="h-full rounded-full bg-sky-400 transition-all duration-300" :style="{ width: `${displayPercent}%` }" />
                        </div>
                    </div>
                </div>

                <div v-if="errorMessage" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    {{ errorMessage }}
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="hasPdf"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        :class="activeMode === 'reading' ? 'border-sky-400 bg-sky-500/20 text-white' : 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'"
                        @click="activeMode = 'reading'"
                    >
                        Read
                    </button>
                    <button
                        v-if="hasAudio"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        :class="activeMode === 'audio' ? 'border-emerald-400 bg-emerald-500/20 text-white' : 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'"
                        @click="activeMode = 'audio'"
                    >
                        Listen
                    </button>
                </div>

                <div v-if="isPdf" class="rounded-2xl border border-white/10 bg-white/5 p-3 sm:p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10 disabled:opacity-40"
                                :disabled="currentPage <= 1 || isLoading"
                                @click="prevPage"
                            >
                                <ChevronLeftIcon class="h-4 w-4" />
                                Prev
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10 disabled:opacity-40"
                                :disabled="totalPages && currentPage >= totalPages || isLoading"
                                @click="nextPage"
                            >
                                Next
                                <ChevronRightIcon class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-slate-300">Page</label>
                            <input
                                v-model="pageInput"
                                type="number"
                                min="1"
                                :max="totalPages || 999"
                                class="w-20 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white outline-none focus:border-sky-400"
                                @keydown.enter.prevent="gotoPage(pageInput)"
                                @blur="gotoPage(pageInput)"
                            />
                            <span class="text-xs font-semibold text-slate-400">/ {{ totalPages || '...' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                @click="zoomOut"
                            >
                                -
                            </button>
                            <span class="text-xs font-semibold text-slate-300">{{ Math.round(zoom * 100) }}%</span>
                            <button
                                type="button"
                                class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                @click="zoomIn"
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <div ref="canvasWrapRef" class="overflow-auto rounded-xl bg-slate-900 p-3">
                        <div v-if="isLoading" class="py-10 text-center text-sm text-slate-300">Loading...</div>
                        <canvas v-else ref="canvasRef" class="mx-auto block rounded-md bg-white" />
                    </div>
                </div>

                <div v-else-if="isAudio" class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <audio
                        ref="audioRef"
                        class="w-full"
                        controls
                        controlsList="nodownload"
                        preload="metadata"
                        :src="audioMediaUrl"
                        @contextmenu.prevent
                        @loadedmetadata="handleAudioLoaded"
                        @timeupdate="handleAudioTimeUpdate"
                        @pause="queueSave('audio', audioPayload(), 0)"
                        @seeked="queueSave('audio', audioPayload(), 0)"
                        @ended="queueSave('audio', audioPayload(), 0)"
                    />

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-500/20 px-4 py-2 text-sm font-semibold text-white ring-1 ring-emerald-400/30 transition hover:bg-emerald-500/30"
                            @click="toggleAudioPlayback"
                        >
                            <PlayIcon v-if="!isAudioPlaying" class="h-5 w-5" />
                            <PauseIcon v-else class="h-5 w-5" />
                            {{ isAudioPlaying ? 'Pause' : 'Play' }}
                        </button>
                        <div class="text-xs font-semibold text-slate-300">
                            <span class="tabular-nums">{{ formatPlaybackTime(audioCurrentTime) }}</span>
                            <span class="text-slate-500"> / </span>
                            <span class="tabular-nums">{{ formatPlaybackTime(audioDuration) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="item.type === 'ebook' && (summaryUrl || aiSummary)" class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-bold text-white">AI Summary</h2>
                        <button
                            v-if="summaryUrl && !aiSummary"
                            type="button"
                            class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:bg-white/10"
                            :disabled="summaryLoading"
                            @click="loadSummary"
                        >
                            {{ summaryLoading ? 'Loading...' : 'Load summary' }}
                        </button>
                    </div>
                    <p v-if="summaryStatus === 'failed'" class="text-sm text-amber-200">
                        Summary generation failed previously for this book.
                    </p>
                    <p v-if="summaryError" class="text-sm text-red-200">{{ summaryError }}</p>
                    <div v-else-if="aiSummary" class="prose prose-invert prose-sm max-w-none" v-html="renderedSummaryHtml" />
                    <p v-else class="text-sm text-slate-300">Summary will appear here when ready.</p>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>

