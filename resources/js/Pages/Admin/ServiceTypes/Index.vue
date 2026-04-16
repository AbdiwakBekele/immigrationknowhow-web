<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    Squares2X2Icon,
    PlusIcon,
    CheckCircleIcon,
    XCircleIcon,
    PencilIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    serviceTypes: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const deleteType = (type) => {
    if (!window.confirm(`Delete "${type.label}"?`)) return;
    router.delete(route('admin.service-types.destroy', type.id));
};

const toggleActive = (type) => {
    router.patch(route('admin.service-types.toggle-active', type.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Service Types" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                        Service configuration
                    </p>
                    <h1 class="mt-2 admin-title">Service types</h1>
                    <p class="admin-subtitle">
                        Options in <code class="rounded bg-slate-100 px-1 font-mono text-xs">service_type_options</code> — used for onboarding, provider profiles, and filters.
                    </p>
                </div>
                <Link
                    href="/admin/service-types/create"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                >
                    <PlusIcon class="h-5 w-5" />
                    Add service type
                </Link>
                </div>
            </section>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>

            <section class="admin-table-wrap">
                <div class="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
                    <Squares2X2Icon class="h-5 w-5 text-sky-600" />
                    <p class="font-semibold text-slate-900">All types ({{ serviceTypes.length }})</p>
                </div>

                <div v-if="serviceTypes.length" class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Sort</th>
                                <th class="px-4 py-2.5">Label</th>
                                <th class="px-4 py-2.5">Value</th>
                                <th class="px-4 py-2.5">Icon</th>
                                <th class="px-4 py-2.5">Users</th>
                                <th class="px-4 py-2.5">Providers</th>
                                <th class="px-4 py-2.5">Cert Upload</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="type in serviceTypes" :key="type.id" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ type.sort_order }}
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    {{ type.label }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                    {{ type.value }}
                                </td>
                                <td class="px-4 py-3 text-lg leading-none text-slate-700">
                                    <span v-if="type.icon">{{ type.icon }}</span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <CheckCircleIcon v-if="type.for_user" class="h-5 w-5 text-emerald-600" />
                                    <XCircleIcon v-else class="h-5 w-5 text-slate-300" />
                                </td>
                                <td class="px-4 py-3">
                                    <CheckCircleIcon v-if="type.for_provider" class="h-5 w-5 text-emerald-600" />
                                    <XCircleIcon v-else class="h-5 w-5 text-slate-300" />
                                </td>
                                <td class="px-4 py-3">
                                    <CheckCircleIcon v-if="type.include_certificate" class="h-5 w-5 text-emerald-600" />
                                    <XCircleIcon v-else class="h-5 w-5 text-slate-300" />
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="type.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ type.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                            @click="toggleActive(type)"
                                        >
                                            {{ type.is_active ? 'Set Inactive' : 'Set Active' }}
                                        </button>
                                        <Link
                                            :href="route('admin.service-types.edit', type.id)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-50"
                                        >
                                            <PencilIcon class="h-3.5 w-3.5" />
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                            @click="deleteType(type)"
                                        >
                                            <TrashIcon class="h-3.5 w-3.5" />
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-4 py-12 text-center text-sm text-slate-500">
                    No service types yet.
                    <Link href="/admin/service-types/create" class="font-semibold text-sky-600 hover:text-sky-700">
                        Add the first one
                    </Link>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
