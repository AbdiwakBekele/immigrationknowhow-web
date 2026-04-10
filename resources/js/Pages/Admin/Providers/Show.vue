<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon, PencilSquareIcon, StarIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    provider: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
});

const initial = (provider) => {
    const letter = provider?.user?.first_name?.trim()?.charAt(0);
    return letter ? letter.toUpperCase() : '?';
};

const categoryLabel = (value) => {
    if (!value) return 'Other';
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
};
</script>

<template>
    <Head :title="`Provider · ${provider.business_name}`" />

    <AdminLayout>
        <div class="mx-auto max-w-5xl space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex items-center gap-2">
                    <Link
                        href="/admin/providers"
                        class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">Provider Profile</h1>
                        <p class="text-xs text-slate-500">Detailed information and account status</p>
                    </div>
                </div>
                <Link
                    :href="`/admin/providers/${provider.slug}/edit`"
                    class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700"
                >
                    <PencilSquareIcon class="h-4 w-4" />
                    Edit Provider
                </Link>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-700">
                        {{ initial(provider) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-base font-semibold text-slate-900">{{ provider.business_name }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ provider.user?.first_name }} {{ provider.user?.last_name }} · {{ provider.user?.email }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                        {{ categoryLabel(provider.primary_service_type || provider.service_types?.[0]) }}
                    </span>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="provider.is_verified ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ provider.is_verified ? 'Verified' : 'Pending verification' }}
                    </span>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="provider.is_active ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-700'"
                    >
                        {{ provider.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Total Leads</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ stats.leads_count || 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Converted Leads</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ stats.leads_converted || 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Average Rating</p>
                    <p class="mt-1 inline-flex items-center gap-1 text-xl font-semibold text-slate-900">
                        <StarIcon class="h-5 w-5 text-yellow-500" />
                        {{ stats.average_rating || 0 }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-900">Provider Details</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Business Name</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-900">{{ provider.business_name || '-' }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Email</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-900">{{ provider.user?.email || '-' }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Phone</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-900">{{ provider.user?.phone || '-' }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Joined</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-900">
                            {{ provider.user?.created_at ? new Date(provider.user.created_at).toLocaleDateString() : '-' }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Bio</p>
                    <p class="mt-0.5 text-sm text-slate-800">{{ provider.bio || 'No bio provided.' }}</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
