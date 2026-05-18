<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon, PlayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    batch: { type: Object, required: true },
    importIssues: { type: Object, required: true },
    isPreview: { type: Boolean, default: false },
    canImport: { type: Boolean, default: false },
    isImporting: { type: Boolean, default: false },
    headerSamples: { type: Object, default: () => ({}) },
});

const errorRows = computed(() => props.importIssues?.data ?? []);
const headerRows = computed(() => Object.entries(props.batch?.summary?.headers ?? {}));
const postsFileMeta = computed(() => props.batch?.summary?.file_metadata?.posts ?? null);
const isSubmitting = ref(false);

const runImport = () => {
    if (!props.canImport || isSubmitting.value) {
        return;
    }

    if (!window.confirm('Run the final community import for this batch? This will update existing imported records by old WordPress ID. The page will wait until the import finishes.')) {
        return;
    }

    isSubmitting.value = true;

    router.post(route('admin.community.import.run', props.batch.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Community Import Batch #${batch.id}`" />

    <AdminLayout>
        <div class="admin-page-container space-y-6">
            <section class="admin-hero-card">
                <Link
                    :href="route('admin.community.import.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back to import history
                </Link>

                <div class="mt-4 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            {{ isPreview ? 'Validation preview' : 'Import batch details' }}
                        </p>
                        <h1 class="mt-2 admin-title">Batch #{{ batch.id }}</h1>
                        <p class="admin-subtitle">
                            Status: <span class="font-semibold text-slate-900">{{ batch.status }}</span>
                        </p>
                    </div>

                    <button
                        v-if="canImport"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isSubmitting"
                        @click="runImport"
                    >
                        <PlayIcon class="h-5 w-5" />
                        {{ isSubmitting ? 'Importing…' : 'Run Final Import' }}
                    </button>
                </div>
            </section>

            <section
                v-if="isSubmitting"
                class="rounded-[1.1rem] border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
            >
                <p class="font-semibold">Import in progress</p>
                <p class="mt-1 text-blue-800">
                    Please keep this tab open. Large imports can take several minutes.
                </p>
            </section>

            <section
                v-else-if="isImporting"
                class="rounded-[1.1rem] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
            >
                <p class="font-semibold">This batch was left in an importing state</p>
                <p class="mt-1 text-amber-800">
                    A previous import may have been interrupted. Re-validate the batch before running again.
                </p>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[1.1rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-semibold text-slate-900">{{ batch.totals.users }}</p>
                    <p class="text-sm text-slate-500">Users</p>
                </div>
                <div class="rounded-[1.1rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-semibold text-slate-900">{{ batch.totals.posts }}</p>
                    <p class="text-sm text-slate-500">Posts</p>
                </div>
                <div class="rounded-[1.1rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-semibold text-slate-900">{{ batch.totals.comments }}</p>
                    <p class="text-sm text-slate-500">Comments</p>
                </div>
                <div class="rounded-[1.1rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-semibold text-slate-900">{{ batch.totals.reactions }}</p>
                    <p class="text-sm text-slate-500">Reactions</p>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[0.8fr,1.2fr]">
                <aside class="space-y-6">
                    <section class="admin-panel">
                        <h2 class="text-lg font-semibold text-slate-900">Batch summary</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-500">Uploaded by</dt>
                                <dd class="text-right font-medium text-slate-900">{{ batch.uploaded_by?.name || 'Unknown' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-500">Selected owner</dt>
                                <dd class="text-right font-medium text-slate-900">{{ batch.selected_owner_name || 'Unknown' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-500">Warnings</dt>
                                <dd class="text-right font-medium text-slate-900">{{ batch.warnings_count }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-500">Errors</dt>
                                <dd class="text-right font-medium text-slate-900">{{ batch.errors_count }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-500">Created</dt>
                                <dd class="text-right font-medium text-slate-900">{{ batch.created_at ? new Date(batch.created_at).toLocaleString() : '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-500">Started</dt>
                                <dd class="text-right font-medium text-slate-900">{{ batch.started_at ? new Date(batch.started_at).toLocaleString() : '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-500">Finished</dt>
                                <dd class="text-right font-medium text-slate-900">{{ batch.finished_at ? new Date(batch.finished_at).toLocaleString() : '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="admin-panel">
                        <h2 class="text-lg font-semibold text-slate-900">Uploaded files</h2>
                        <ul class="mt-4 space-y-2 text-sm text-slate-700">
                            <li><span class="font-medium text-slate-900">Users:</span> {{ batch.files.users }}</li>
                            <li><span class="font-medium text-slate-900">Posts:</span> {{ batch.files.posts }}</li>
                            <li><span class="font-medium text-slate-900">Comments:</span> {{ batch.files.comments }}</li>
                            <li><span class="font-medium text-slate-900">Reactions:</span> {{ batch.files.reactions }}</li>
                        </ul>
                        <div v-if="postsFileMeta" class="mt-4 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                            <p class="font-semibold text-slate-900">Posts file on disk (batch #{{ batch.id }})</p>
                            <p><span class="font-medium">Path:</span> {{ postsFileMeta.absolute_path }}</p>
                            <p><span class="font-medium">Size:</span> {{ postsFileMeta.size_bytes }} bytes</p>
                            <p><span class="font-medium">MD5:</span> {{ postsFileMeta.md5 }}</p>
                            <p><span class="font-medium">Modified:</span> {{ postsFileMeta.modified_at }}</p>
                        </div>
                    </section>

                    <section class="admin-panel">
                        <h2 class="text-lg font-semibold text-slate-900">Reference checks</h2>
                        <div class="mt-4 grid gap-2 text-sm text-slate-700">
                            <p>Posts missing contributors: {{ batch.summary.reference_checks?.posts_missing_contributors ?? 0 }}</p>
                            <p>Comments missing posts: {{ batch.summary.reference_checks?.comments_missing_posts ?? 0 }}</p>
                            <p>Comments missing users: {{ batch.summary.reference_checks?.comments_missing_users ?? 0 }}</p>
                            <p>Comments missing parents: {{ batch.summary.reference_checks?.comments_missing_parents ?? 0 }}</p>
                            <p>Reactions missing posts: {{ batch.summary.reference_checks?.reactions_missing_posts ?? 0 }}</p>
                            <p>Reactions missing users: {{ batch.summary.reference_checks?.reactions_missing_users ?? 0 }}</p>
                        </div>
                    </section>
                </aside>

                <div class="space-y-6">
                    <section class="admin-panel">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Preview result</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ canImport
                                        ? 'Validation passed. You can run the final import.'
                                        : 'This batch still has blocking issues or has already been imported.' }}
                                </p>
                            </div>
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="canImport
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-amber-100 text-amber-800'"
                            >
                                {{ canImport ? 'Import allowed' : 'Import blocked or already finished' }}
                            </span>
                        </div>

                        <div v-if="batch.imported.users || batch.imported.posts || batch.imported.comments || batch.imported.reactions" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                <p class="font-semibold text-slate-900">{{ batch.imported.users }}</p>
                                <p class="text-slate-500">Imported users</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                <p class="font-semibold text-slate-900">{{ batch.imported.posts }}</p>
                                <p class="text-slate-500">Imported posts</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                <p class="font-semibold text-slate-900">{{ batch.imported.comments }}</p>
                                <p class="text-slate-500">Imported comments</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                <p class="font-semibold text-slate-900">{{ batch.imported.reactions }}</p>
                                <p class="text-slate-500">Imported reactions</p>
                            </div>
                        </div>
                    </section>

                    <section class="admin-panel">
                        <h2 class="text-lg font-semibold text-slate-900">Header validation</h2>
                        <div class="mt-4 space-y-4">
                            <div v-for="[fileType, headerInfo] in headerRows" :key="fileType" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold capitalize text-slate-900">{{ fileType }}</p>
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="headerInfo.missing?.length ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                    >
                                        {{ headerInfo.missing?.length ? 'Missing required headers' : 'Headers look good' }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Actual</p>
                                <code class="mt-1 block overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-slate-900 px-3 py-2 text-xs text-slate-100">
                                    {{ headerInfo.actual?.join(', ') }}
                                </code>
                                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Required</p>
                                <code class="mt-1 block overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-white px-3 py-2 text-xs text-slate-700">
                                    {{ (headerSamples[fileType] || []).join(', ') }}
                                </code>
                                <p v-if="headerInfo.missing?.length" class="mt-3 text-xs font-medium text-rose-700">
                                    Missing: {{ headerInfo.missing.join(', ') }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="admin-panel">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Errors and warnings</h2>
                                <p class="mt-1 text-sm text-slate-500">Latest 20 row-level issues for this batch.</p>
                            </div>
                        </div>

                        <div v-if="errorRows.length" class="mt-4 space-y-4">
                            <article
                                v-for="error in errorRows"
                                :key="error.id"
                                class="rounded-xl border p-4"
                                :class="error.severity === 'warning' ? 'border-amber-200 bg-amber-50' : 'border-rose-200 bg-rose-50'"
                            >
                                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide">
                                    <span :class="error.severity === 'warning' ? 'text-amber-800' : 'text-rose-800'">
                                        {{ error.severity }}
                                    </span>
                                    <span class="text-slate-500">{{ error.file_type }}</span>
                                    <span v-if="error.row_number" class="text-slate-500">Row {{ error.row_number }}</span>
                                    <span v-if="error.old_wp_id" class="text-slate-500">WP ID {{ error.old_wp_id }}</span>
                                </div>
                                <p class="mt-2 text-sm font-medium text-slate-900">{{ error.message }}</p>
                                <code
                                    v-if="error.row_data"
                                    class="mt-3 block overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-white px-3 py-2 text-xs text-slate-700"
                                >
                                    {{ JSON.stringify(error.row_data, null, 2) }}
                                </code>
                            </article>
                        </div>
                        <p v-else class="mt-4 text-sm text-slate-500">No validation or import issues were recorded for this batch.</p>

                        <nav
                            v-if="importIssues.links?.length > 3"
                            class="mt-4 flex flex-wrap justify-center gap-2 border-t border-slate-100 pt-4"
                        >
                            <template v-for="(link, index) in importIssues.links" :key="`${link.label}-${index}`">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="inline-flex min-w-[2.5rem] items-center justify-center rounded-2xl px-3 py-2 text-sm font-medium transition"
                                    :class="link.active
                                        ? 'bg-blue-600 text-white'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                    preserve-scroll
                                >
                                    <span v-html="link.label" />
                                </Link>
                                <span
                                    v-else
                                    class="inline-flex min-w-[2.5rem] cursor-not-allowed items-center justify-center rounded-2xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-400"
                                    v-html="link.label"
                                />
                            </template>
                        </nav>
                    </section>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

