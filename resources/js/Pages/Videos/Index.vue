<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { debounce } from 'lodash-es';
import { MagnifyingGlassIcon, PlayCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    videos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    platformOptions: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search ?? '');
const platform = ref(props.filters?.platform ?? '');
const category = ref(props.filters?.category ?? '');

const applyFilters = debounce(() => {
    router.get(route('videos.index'), {
        search: search.value || undefined,
        platform: platform.value || undefined,
        category: category.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch([search, platform, category], applyFilters);
</script>

<template>
    <Head title="Videos" />

    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-5 px-4 pb-10 pt-2 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <h1 class="text-xl font-bold text-slate-900">Videos</h1>
                <p class="mt-1 text-sm text-slate-500">Watch all published videos in one place.</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row">
                    <div class="relative min-w-0 flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search videos..."
                            class="w-full rounded-lg border border-slate-200 py-2.5 pl-10 pr-3 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        >
                    </div>
                    <select
                        v-model="platform"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    >
                        <option value="">All platforms</option>
                        <option v-for="opt in platformOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                    <select
                        v-model="category"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    >
                        <option value="">All categories</option>
                        <option v-for="opt in categoryOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                </div>
            </div>

            <div v-if="videos.data?.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="video in videos.data"
                    :key="video.id"
                    :href="route('videos.show', video.slug)"
                    class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                >
                    <div class="relative h-44 w-full bg-slate-100">
                        <img
                            v-if="video.thumbnail_url"
                            :src="video.thumbnail_url"
                            :alt="video.title"
                            class="h-full w-full object-cover"
                        >
                        <div v-else class="flex h-full items-center justify-center">
                            <PlayCircleIcon class="h-12 w-12 text-slate-400" />
                        </div>
                    </div>
                    <div class="space-y-1 p-4">
                        <h2 class="line-clamp-2 text-sm font-semibold text-slate-900 group-hover:text-sky-700">{{ video.title }}</h2>
                        <p class="text-xs capitalize text-slate-500">{{ video.platform }}<span v-if="video.category"> • {{ video.category }}</span></p>
                    </div>
                </Link>
            </div>

            <div v-else class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                No videos found.
            </div>
        </div>
    </AppLayout>
</template>
