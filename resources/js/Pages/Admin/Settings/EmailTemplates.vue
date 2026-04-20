<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash ?? {});
</script>

<template>
    <Head title="Email Templates" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Email templates</h1>
                <p class="mt-1 text-gray-500">Update transactional emails for invites, welcomes, and account activations by role.</p>
            </div>

            <div v-if="flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flash.success }}
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Email title</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Event</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="template in templates" :key="template.id">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ template.title }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ template.event_label }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ template.role_label }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="template.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ template.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link
                                    :href="route('admin.email-templates.show', template.id)"
                                    class="rounded-lg bg-sky-600 px-3 py-1.5 font-medium text-white hover:bg-sky-700"
                                >
                                    Open
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!templates.length">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">No email templates found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
