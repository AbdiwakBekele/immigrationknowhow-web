<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { VideoCameraIcon, PlayCircleIcon, FireIcon, PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    videos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    platformOptions: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    maxUploadMb: { type: Number, default: 100 },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const rows = computed(() => props.videos?.data ?? []);

const searchModel = computed({
    get: () => props.filters?.search ?? '',
    set: (value) => {
        router.get(
            route('admin.videos.index'),
            { ...props.filters, search: value || undefined },
            { preserveState: true, replace: true },
        );
    },
});

const platformModel = computed({
    get: () => props.filters?.platform ?? '',
    set: (value) => {
        router.get(
            route('admin.videos.index'),
            { ...props.filters, platform: value || undefined },
            { preserveState: true, replace: true },
        );
    },
});

const destroyVideo = (slug) => {
    if (!confirm('Remove this video from the site?')) {
        return;
    }
    router.delete(route('admin.videos.destroy', slug));
};
</script>

<template>
    <Head title="Videos" />

    <AdminLayout>
        <div class="space-y-6">
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </div>

            <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Videos</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Embed from YouTube / Vimeo / TikTok, or upload MP4, WebM, or MOV (stored privately; max ~{{ maxUploadMb }} MB per file).
                    </p>
                </div>
                <Link
                    :href="route('admin.videos.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-sky-700"
                >
                    <PlusIcon class="h-5 w-5" />
                    Add video
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-rose-100 p-2">
                        <VideoCameraIcon class="h-5 w-5 text-rose-600" />
                    </div>
                    <p class="text-3xl font-semibold text-slate-900">{{ stats.total ?? 0 }}</p>
                    <p class="text-sm text-slate-500">Total videos</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <PlayCircleIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-3xl font-semibold text-slate-900">{{ stats.active ?? 0 }}</p>
                    <p class="text-sm text-slate-500">Active</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-amber-100 p-2">
                        <FireIcon class="h-5 w-5 text-amber-600" />
                    </div>
                    <p class="text-3xl font-semibold text-slate-900">{{ stats.featured ?? 0 }}</p>
                    <p class="text-sm text-slate-500">Featured</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Search</label>
                        <input
                            v-model="searchModel"
                            type="search"
                            placeholder="Title, category, platform…"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                    </div>
                    <div class="w-full md:w-48">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Platform</label>
                        <select
                            v-model="platformModel"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                            <option value="">All</option>
                            <option v-for="opt in platformOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-slate-200 text-slate-500">
                            <tr>
                                <th class="pb-2 pr-3 font-medium">Title</th>
                                <th class="pb-2 pr-3 font-medium">Platform</th>
                                <th class="pb-2 pr-3 font-medium">Category</th>
                                <th class="pb-2 pr-3 font-medium">Price</th>
                                <th class="pb-2 pr-3 font-medium">Status</th>
                                <th class="pb-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="video in rows" :key="video.id" class="text-slate-800">
                                <td class="py-3 pr-3">
                                    <p class="font-medium text-slate-900">{{ video.title }}</p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ video.platform === 'upload' ? (video.file_name || 'Uploaded file') : video.video_url }}
                                    </p>
                                </td>
                                <td class="py-3 pr-3 capitalize">{{ video.platform }}</td>
                                <td class="py-3 pr-3">{{ video.category || '—' }}</td>
                                <td class="py-3 pr-3">
                                    <template v-if="video.price != null && video.price !== ''">
                                        {{ Number(video.price).toFixed(2) }} {{ (video.currency || 'USD').toUpperCase() }}
                                    </template>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="py-3 pr-3">
                                    <span
                                        class="mr-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="video.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ video.is_active ? 'Active' : 'Hidden' }}
                                    </span>
                                    <span
                                        v-if="video.is_featured"
                                        class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                    >
                                        Featured
                                    </span>
                                </td>
                                <td class="py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="route('admin.videos.edit', video.slug)"
                                            class="inline-flex rounded-lg border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-50"
                                            title="Edit"
                                        >
                                            <PencilSquareIcon class="h-5 w-5" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex rounded-lg border border-slate-200 p-1.5 text-red-600 hover:bg-red-50"
                                            title="Delete"
                                            @click="destroyVideo(video.slug)"
                                        >
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="!rows.length" class="py-8 text-center text-sm text-slate-500">No videos yet. Add one with a public video link.</p>

                <nav v-if="videos.links?.length > 3" class="mt-4 flex flex-wrap justify-center gap-1 border-t border-slate-100 pt-4">
                    <Link
                        v-for="link in videos.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url"
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AdminLayout>
</template>
