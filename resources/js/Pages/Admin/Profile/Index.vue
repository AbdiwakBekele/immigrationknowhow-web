<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    user: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Admin Profile" />

    <AdminLayout>
        <div class="mx-auto max-w-3xl">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h1 class="text-xl font-semibold text-gray-900">Admin Profile</h1>
                <p class="mt-1 text-sm text-gray-500">Your administrator account details.</p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Full Name</p>
                        <p class="mt-1 font-medium text-gray-900">{{ user?.full_name || '-' }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Email</p>
                        <p class="mt-1 font-medium text-gray-900">{{ user?.email || '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
