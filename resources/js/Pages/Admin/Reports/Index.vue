<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ChartBarIcon, UsersIcon, BriefcaseIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    stats: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Reports" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
                <p class="mt-1 text-gray-500">Monitor growth trends and operational performance.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <ChartBarIcon class="mb-3 h-5 w-5 text-sky-600" />
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.users ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Users</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <BriefcaseIcon class="mb-3 h-5 w-5 text-violet-600" />
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.providers ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Providers</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <UsersIcon class="mb-3 h-5 w-5 text-emerald-600" />
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.leads ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Leads</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <ChatBubbleLeftRightIcon class="mb-3 h-5 w-5 text-amber-600" />
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.reviews ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Reviews</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-gray-800">Reporting center is now available</p>
                <p class="mt-2 text-gray-500">You can plug in charts and exports on top of this connected page.</p>
            </div>
        </div>
    </AdminLayout>
</template>
