<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { StarIcon, ChatBubbleLeftRightIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <Head title="Reviews" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reviews</h1>
                <p class="mt-1 text-gray-500">Moderate feedback quality and monitor trust signals.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-yellow-100 p-2">
                        <StarIcon class="h-5 w-5 text-yellow-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Average rating</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <ChatBubbleLeftRightIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">New reviews</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-rose-100 p-2">
                        <ExclamationTriangleIcon class="h-5 w-5 text-rose-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Flagged items</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-gray-800">Review moderation page is live</p>
                <p class="mt-2 text-gray-500">You can now open this route from the sidebar and build moderation flows here.</p>
            </div>
        </div>
    </AdminLayout>
</template>
