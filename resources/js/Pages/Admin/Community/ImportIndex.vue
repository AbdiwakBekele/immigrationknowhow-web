<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    batches: { type: Object, required: true },
    ownerOptions: { type: Array, default: () => [] },
    defaultOwnerId: { type: Number, default: null },
    headerSamples: { type: Object, default: () => ({}) },
});

const form = useForm({
    selected_owner_id: props.defaultOwnerId ?? '',
    users_file: null,
    posts_file: null,
    comments_file: null,
    reactions_file: null,
});

const historyRows = computed(() => props.batches?.data ?? []);

const fileFields = [
    {
        key: 'users_file',
        label: 'users_import.csv',
        help: 'Clean user rows with old_wp_user_id, email, role, languages, and country.',
    },
    {
        key: 'posts_file',
        label: 'community_posts_import.csv',
        help: 'Clean post rows with old_wp_post_id, contributor mapping, category, slug, and media URLs.',
    },
    {
        key: 'comments_file',
        label: 'community_comments_import.csv',
        help: 'Clean comment rows with post/user WP IDs and optional parent comment mapping.',
    },
    {
        key: 'reactions_file',
        label: 'community_post_reactions_import.csv',
        help: 'Clean reaction rows with old_wp_reaction_id, type, dedupe_key, and source user mapping.',
    },
];

const submit = () => {
    form.post(route('admin.community.import.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Community Import" />

    <AdminLayout>
        <div class="admin-page-container space-y-6">
            <section class="admin-hero-card">
                <Link
                    :href="route('admin.community.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back to community
                </Link>
                <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">WordPress community import</p>
                        <h1 class="mt-2 admin-title">Community Import</h1>
                        <p class="admin-subtitle">
                            Upload the four cleaned CSV files, validate headers and references, review the preview, then run the final import.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.2fr,0.8fr]">
                <form class="admin-panel space-y-5" @submit.prevent="submit">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Upload and validate</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Validation is a dry run. No database rows are written until you run the final import from the preview page.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Admin owner for imported posts</label>
                        <select v-model="form.selected_owner_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option v-for="owner in ownerOptions" :key="owner.id" :value="owner.id">
                                {{ owner.name }} ({{ owner.email }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">
                            All imported posts will use this admin as `author_id`. Original WordPress contributor mapping is stored separately.
                        </p>
                        <p v-if="form.errors.selected_owner_id" class="mt-1 text-xs text-red-600">{{ form.errors.selected_owner_id }}</p>
                    </div>

                    <div class="grid gap-4">
                        <div v-for="field in fileFields" :key="field.key" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <label class="mb-1 block text-sm font-semibold text-slate-900">{{ field.label }}</label>
                            <input
                                type="file"
                                accept=".csv,.txt,text/csv"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                @change="form[field.key] = $event.target.files?.[0] ?? null"
                            >
                            <p class="mt-2 text-xs text-slate-500">{{ field.help }}</p>
                            <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Validating...' : 'Upload and Validate' }}
                        </button>
                    </div>
                </form>

                <aside class="space-y-4">
                    <section class="admin-panel">
                        <h2 class="text-lg font-semibold text-slate-900">Expected CSV headers</h2>
                        <div class="mt-4 space-y-4">
                            <div v-for="(headers, key) in headerSamples" :key="key" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold capitalize text-slate-900">{{ key.replace('_', ' ') }}</p>
                                <code class="mt-2 block overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-slate-900 px-3 py-2 text-xs text-slate-100">
                                    {{ headers.join(', ') }}
                                </code>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>

            <section class="admin-panel">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Import history</h2>
                        <p class="mt-1 text-sm text-slate-500">Previous validation and import batches.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[880px] text-left text-sm">
                        <thead class="border-b border-slate-200 text-slate-500">
                            <tr>
                                <th class="pb-2 pr-3 font-medium">Batch</th>
                                <th class="pb-2 pr-3 font-medium">Status</th>
                                <th class="pb-2 pr-3 font-medium">Uploaded by</th>
                                <th class="pb-2 pr-3 font-medium">Owner</th>
                                <th class="pb-2 pr-3 font-medium">Totals</th>
                                <th class="pb-2 pr-3 font-medium">Issues</th>
                                <th class="pb-2 pr-3 font-medium">Created</th>
                                <th class="pb-2 text-right font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="batch in historyRows" :key="batch.id" class="text-slate-800">
                                <td class="py-3 pr-3 font-medium text-slate-900">#{{ batch.id }}</td>
                                <td class="py-3 pr-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="batch.status === 'completed'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : batch.status === 'completed_with_errors'
                                                ? 'bg-amber-100 text-amber-800'
                                                : batch.status === 'failed'
                                                    ? 'bg-rose-100 text-rose-800'
                                                    : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ batch.status }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3">
                                    {{ batch.uploaded_by?.name || 'Unknown' }}
                                </td>
                                <td class="py-3 pr-3">{{ batch.selected_owner_name || '—' }}</td>
                                <td class="py-3 pr-3 text-xs text-slate-600">
                                    U {{ batch.totals.users }} / P {{ batch.totals.posts }} / C {{ batch.totals.comments }} / R {{ batch.totals.reactions }}
                                </td>
                                <td class="py-3 pr-3 text-xs text-slate-600">
                                    {{ batch.errors_count }} errors, {{ batch.warnings_count }} warnings
                                </td>
                                <td class="py-3 pr-3 text-xs text-slate-600">{{ batch.created_at ? new Date(batch.created_at).toLocaleString() : '—' }}</td>
                                <td class="py-3 text-right">
                                    <Link
                                        :href="route('admin.community.import.show', batch.id)"
                                        class="inline-flex rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        View batch
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="!historyRows.length" class="py-8 text-center text-sm text-slate-500">No import batches yet.</p>

                <nav
                    v-if="batches.links?.length > 3"
                    class="mt-4 flex flex-wrap justify-center gap-2 border-t border-slate-100 pt-4"
                >
                    <template v-for="(link, index) in batches.links" :key="`${link.label}-${index}`">
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
    </AdminLayout>
</template>
