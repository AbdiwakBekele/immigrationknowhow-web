<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { VideoCameraIcon, PlayCircleIcon, FireIcon } from '@heroicons/vue/24/outline';

defineProps({
    stats: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Videos" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Videos</h1>
                <p class="mt-1 text-gray-500">Manage embedded educational videos and featured media.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-rose-100 p-2">
                        <VideoCameraIcon class="h-5 w-5 text-rose-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.total ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Total videos</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <PlayCircleIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.active ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Active videos</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-amber-100 p-2">
                        <FireIcon class="h-5 w-5 text-amber-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.featured ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Featured videos</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-gray-800">Video manager page is connected</p>
                <p class="mt-2 text-gray-500">This route is now available directly from the admin sidebar.</p>
            </div>
        </div>
    </AdminLayout>
</template>
