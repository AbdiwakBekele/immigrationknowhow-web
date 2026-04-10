<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Squares2X2Icon, CheckCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    serviceTypes: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Service Types" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Service Types</h1>
                <p class="mt-1 text-gray-500">Configure category options available to users and providers.</p>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Squares2X2Icon class="h-5 w-5 text-sky-600" />
                    <p class="font-semibold text-gray-900">Configured service types ({{ serviceTypes.length }})</p>
                </div>
                <div v-if="serviceTypes.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="type in serviceTypes"
                        :key="type.id"
                        class="rounded-lg border border-gray-200 p-3"
                    >
                        <div class="flex items-center justify-between">
                            <p class="font-medium text-gray-900">{{ type.label }}</p>
                            <CheckCircleIcon v-if="type.is_active" class="h-4 w-4 text-emerald-600" />
                        </div>
                        <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">{{ type.value }}</p>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">
                    No service types found yet.
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
