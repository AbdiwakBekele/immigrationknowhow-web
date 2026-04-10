<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
});

const form = useForm({
    maintenance_mode: props.settings.maintenance_mode ?? false,
    reviews_auto_approve: props.settings.reviews_auto_approve ?? false,
    email_notifications: props.settings.email_notifications ?? true,
    new_provider_alerts: props.settings.new_provider_alerts ?? true,
});

const save = () => {
    form.patch('/admin/settings');
};
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                <p class="mt-1 text-gray-500">Control platform preferences and notification behavior.</p>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center gap-2">
                    <Cog6ToothIcon class="h-5 w-5 text-sky-600" />
                    <p class="font-semibold text-gray-900">Platform configuration</p>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">Maintenance mode</span>
                        <input v-model="form.maintenance_mode" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">Auto-approve reviews</span>
                        <input v-model="form.reviews_auto_approve" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">Email notifications</span>
                        <input v-model="form.email_notifications" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">New provider alerts</span>
                        <input v-model="form.new_provider_alerts" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-sky-700 disabled:opacity-60"
                        >
                            Save changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
