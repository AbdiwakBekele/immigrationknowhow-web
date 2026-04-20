<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon, BookOpenIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { renderSafeMarkdown } from '@/utils/markdown';

const props = defineProps({
    item: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const activeTab = ref('summary');
const hasSummary = computed(() => Boolean((props.item?.ai_summary || '').trim()));
const hasCover = computed(() => Boolean(props.item?.cover_image_url));
const itemTypeLabel = computed(() => (props.item?.type === 'ebook' ? 'E-Book' : 'Audiobook'));
const renderedSummaryHtml = computed(() => renderSafeMarkdown(props.item?.ai_summary || ''));
const canGenerateSummary = computed(() => (
    props.item?.type === 'ebook'
    && !hasSummary.value
    && props.item?.ai_summary_status !== 'processing'
    && props.item?.ai_summary_status !== 'queued'
));

const generateSummary = () => {
    console.group('[Admin Library] Manual summary generation');
    console.info('Generate Summary clicked');
    console.info('Item slug:', props.item.slug);
    console.info('Current summary status:', props.item.ai_summary_status || 'not_generated');
    console.info('Has existing summary:', hasSummary.value);
    console.groupEnd();

    router.post(route('admin.library.generate-summary', props.item.slug), {}, {
        preserveScroll: true,
        onStart: () => {
            console.info('[Admin Library] Summary generation request dispatched');
        },
        onSuccess: () => {
            console.info('[Admin Library] Summary generation request accepted by server');
            console.info('[Admin Library] Check Laravel logs for queue/job progress');
        },
        onError: (errors) => {
            console.error('[Admin Library] Summary generation request failed', errors);
        },
        onFinish: () => {
            console.info('[Admin Library] Summary generation request finished');
        },
    });
};

const summaryStatusClass = computed(() => {
    const status = props.item?.ai_summary_status;
    if (status === 'success') return 'bg-emerald-100 text-emerald-700';
    if (status === 'processing') return 'bg-blue-100 text-blue-700';
    if (status === 'queued') return 'bg-amber-100 text-amber-700';
    if (status === 'failed') return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-700';
});

const progressActiveStatuses = ['queued', 'processing', 'validating_input', 'loading_pdf', 'extracting_text', 'sending_to_ai', 'ai_accepted'];
const isProgressActive = computed(() => progressActiveStatuses.includes(props.item?.ai_summary_status || ''));

const summaryProgressMeta = computed(() => {
    const status = props.item?.ai_summary_status || 'not_generated';

    if (status === 'success') {
        return {
            label: 'Completed',
            hint: 'Summary is generated and ready to review.',
            percent: 100,
            barClass: 'bg-emerald-500',
        };
    }
    if (status === 'processing') {
        return {
            label: 'Generating',
            hint: 'AI is currently summarizing the book.',
            percent: 70,
            barClass: 'bg-blue-500',
        };
    }
    if (status === 'queued') {
        return {
            label: 'Queued',
            hint: 'Waiting for queue worker to start the summary job.',
            percent: 35,
            barClass: 'bg-amber-500',
        };
    }
    if (status === 'validating_input') {
        return {
            label: 'Validating',
            hint: 'Preparing and validating the uploaded PDF input.',
            percent: 45,
            barClass: 'bg-indigo-500',
        };
    }
    if (status === 'loading_pdf') {
        return {
            label: 'Loading PDF',
            hint: 'Reading the private PDF file before extraction.',
            percent: 55,
            barClass: 'bg-indigo-500',
        };
    }
    if (status === 'extracting_text') {
        return {
            label: 'Extracting Text',
            hint: 'Converting PDF pages into plain text for AI.',
            percent: 65,
            barClass: 'bg-blue-500',
        };
    }
    if (status === 'sending_to_ai') {
        return {
            label: 'Sending to AI',
            hint: 'PDF text has been prepared and sent to OpenAI.',
            percent: 80,
            barClass: 'bg-blue-500',
        };
    }
    if (status === 'ai_accepted') {
        return {
            label: 'AI Accepted',
            hint: 'OpenAI accepted the request and returned output.',
            percent: 90,
            barClass: 'bg-emerald-500',
        };
    }
    if (status === 'failed') {
        return {
            label: 'Needs Attention',
            hint: 'Previous summary attempt failed. You can try again.',
            percent: 100,
            barClass: 'bg-rose-500',
        };
    }

    return {
        label: 'Not Started',
        hint: 'No summary has been generated yet.',
        percent: 0,
        barClass: 'bg-slate-400',
    };
});

let refreshTimer = null;
const startRefreshTimer = () => {
    if (refreshTimer) return;
    refreshTimer = window.setInterval(() => {
        router.reload({
            only: ['item', 'stats'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 4000);
};

const stopRefreshTimer = () => {
    if (!refreshTimer) return;
    clearInterval(refreshTimer);
    refreshTimer = null;
};

onMounted(() => {
    if (isProgressActive.value) {
        startRefreshTimer();
    }
});

watch(isProgressActive, (active) => {
    if (active) {
        startRefreshTimer();
        return;
    }

    stopRefreshTimer();
});

onBeforeUnmount(() => {
    stopRefreshTimer();
});
</script>

<template>
    <Head :title="`Admin · ${item.title}`" />

    <AdminLayout>
        <div class="admin-page-container space-y-4">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-indigo-50 via-white to-sky-50 px-5 py-4 sm:px-6">
                    <Link
                        :href="route('admin.library.index')"
                        class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        <ArrowLeftIcon class="h-4 w-4" />
                        Back to library
                    </Link>
                    <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h1 class="truncate text-xl font-bold text-slate-900 sm:text-2xl">{{ item.title }}</h1>
                            <p class="mt-0.5 text-sm text-slate-600">
                                {{ item.library_author?.name || item.author || 'Unknown author' }} · {{ item.type === 'ebook' ? 'E-Book' : 'Audiobook' }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="summaryStatusClass">
                                    Summary: {{ item.ai_summary_status || 'not_generated' }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="item.is_featured" class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    Featured
                                </span>
                            </div>
                        </div>
                        <Link
                            :href="route('admin.library.edit', item.slug)"
                            class="rounded-xl bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                        >
                            Edit Book
                        </Link>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xs text-slate-500">Purchases</p>
                    <p class="text-xl font-semibold text-slate-900">{{ stats.purchases ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xs text-slate-500">Favorites</p>
                    <p class="text-xl font-semibold text-slate-900">{{ stats.favorites ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xs text-slate-500">Views</p>
                    <p class="text-xl font-semibold text-slate-900">{{ stats.views ?? 0 }}</p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Book Card</p>
                    <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <img
                            v-if="hasCover"
                            :src="item.cover_image_url"
                            :alt="`${item.title} cover`"
                            class="h-56 w-full object-cover"
                        >
                        <div
                            v-else
                            class="flex h-56 w-full items-center justify-center bg-gradient-to-br from-indigo-50 via-slate-50 to-sky-50 text-slate-500"
                        >
                            <div class="text-center">
                                <BookOpenIcon class="mx-auto h-10 w-10 text-indigo-400" />
                                <p class="mt-2 text-sm font-medium">No cover image</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 space-y-1.5">
                        <h2 class="line-clamp-2 text-lg font-semibold text-slate-900">{{ item.title }}</h2>
                        <p class="text-sm text-slate-600">{{ item.library_author?.name || item.author || 'Unknown author' }}</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                {{ itemTypeLabel }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                {{ item.file_size_formatted || 'Unknown size' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
                    <div class="mb-3">
                        <h3 class="text-lg font-semibold text-slate-900">Library Details</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Essential details only.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">File name</p>
                            <p class="mt-1 text-sm font-medium text-slate-900 break-all">{{ item.file_name || 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">File type</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ item.file_type || 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Companion audio</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">
                                {{ item.audio_file_name ? 'Attached' : 'Not attached' }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div
                    v-if="page.props.flash?.success || page.props.flash?.warning || page.props.flash?.error || page.props.flash?.info"
                    class="mb-4 rounded-xl border px-3.5 py-2.5 text-sm"
                    :class="page.props.flash?.error
                        ? 'border-rose-200 bg-rose-50 text-rose-700'
                        : page.props.flash?.warning
                            ? 'border-amber-200 bg-amber-50 text-amber-800'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                >
                    {{ page.props.flash?.error || page.props.flash?.warning || page.props.flash?.success || page.props.flash?.info }}
                </div>

                <div class="mb-4 inline-flex rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === 'summary' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                        @click="activeTab = 'summary'"
                    >
                        Summary
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === 'more' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                        @click="activeTab = 'more'"
                    >
                        More Info
                    </button>
                </div>

                <div v-if="activeTab === 'summary'" class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="inline-flex items-center gap-2 text-lg font-semibold text-slate-900">
                            <SparklesIcon class="h-5 w-5 text-indigo-600" />
                            Summarized Content
                        </h3>
                        <button
                            v-if="canGenerateSummary"
                            type="button"
                            class="rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-700"
                            @click="generateSummary"
                        >
                            Generate Summary
                        </button>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Summary Progress</p>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="summaryStatusClass">
                                {{ summaryProgressMeta.label }}
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="summaryProgressMeta.barClass"
                                :style="{ width: `${summaryProgressMeta.percent}%` }"
                            />
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
                            <span>{{ summaryProgressMeta.hint }}</span>
                            <span class="font-semibold tabular-nums">{{ summaryProgressMeta.percent }}%</span>
                        </div>
                        <div v-if="isProgressActive" class="mt-2 text-xs text-slate-500">
                            Live updates every 4s while processing...
                        </div>
                    </div>

                    <div
                        v-if="hasSummary"
                        class="prose prose-slate max-w-none text-sm leading-relaxed"
                        v-html="renderedSummaryHtml"
                    ></div>
                    <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                        No summarized content stored for this book yet.
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">Description</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">
                            {{ item.description || 'No description available.' }}
                        </p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="text-xs text-slate-500">Type</p>
                            <p class="mt-1 font-medium text-slate-900">{{ item.type }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="text-xs text-slate-500">Category</p>
                            <p class="mt-1 font-medium text-slate-900">{{ item.category?.name || 'Uncategorized' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="text-xs text-slate-500">Price</p>
                            <p class="mt-1 font-medium text-slate-900">{{ item.currency || 'USD' }} {{ item.price ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="text-xs text-slate-500">Status</p>
                            <p class="mt-1 font-medium text-slate-900">{{ item.is_active ? 'Active' : 'Inactive' }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

