<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { LinkIcon, CursorArrowRaysIcon, SparklesIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <Head title="Affiliates" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Affiliates</h1>
                <p class="mt-1 text-gray-500">Track partner links, placements, and click performance.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-violet-100 p-2">
                        <LinkIcon class="h-5 w-5 text-violet-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Affiliate links</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <CursorArrowRaysIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Total clicks</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-amber-100 p-2">
                        <SparklesIcon class="h-5 w-5 text-amber-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Top performer</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-gray-800">Affiliate dashboard is available</p>
                <p class="mt-2 text-gray-500">Use this page to continue building campaigns and tracking controls.</p>
            </div>
        </div>
    </AdminLayout>
</template>
