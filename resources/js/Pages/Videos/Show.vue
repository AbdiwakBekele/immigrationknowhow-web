<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    video: { type: Object, required: true },
    streamUrl: { type: String, default: null },
    relatedVideos: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="video.title" />

    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-5 px-4 pb-10 pt-2 sm:px-6 lg:px-8">
            <Link :href="route('videos.index')" class="inline-flex items-center text-sm font-medium text-sky-600 hover:text-sky-700">
                Back to videos
            </Link>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-2xl font-bold text-slate-900">{{ video.title }}</h1>
                <p v-if="video.category || video.platform" class="mt-1 text-sm capitalize text-slate-500">
                    {{ video.platform }}<span v-if="video.category"> • {{ video.category }}</span>
                </p>

                <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-black">
                    <video
                        v-if="streamUrl"
                        class="h-auto w-full"
                        controls
                        playsinline
                        :src="streamUrl"
                    />
                    <div
                        v-else-if="video.embed_html"
                        class="aspect-video w-full [&>iframe]:h-full [&>iframe]:w-full"
                        v-html="video.embed_html"
                    />
                    <a
                        v-else-if="video.video_url"
                        :href="video.video_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block p-6 text-center text-sm font-medium text-sky-600 hover:text-sky-700"
                    >
                        Open video source
                    </a>
                </div>

                <p v-if="video.description" class="mt-4 whitespace-pre-line text-sm text-slate-700">
                    {{ video.description }}
                </p>
            </div>

            <div v-if="relatedVideos.length" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Related videos</h2>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="item in relatedVideos"
                        :key="item.id"
                        :href="route('videos.show', item.slug)"
                        class="rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50"
                    >
                        <p class="line-clamp-2 font-medium text-slate-900">{{ item.title }}</p>
                        <p class="mt-1 text-xs capitalize text-slate-500">{{ item.platform }}</p>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
