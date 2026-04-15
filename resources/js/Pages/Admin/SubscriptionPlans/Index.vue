<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    plans: { type: Array, default: () => [] },
});

const destroyPlan = (uuid) => {
    if (!window.confirm('Delete this plan?')) return;
    router.delete(route('admin.subscription-plans.destroy', uuid));
};
</script>

<template>
    <Head title="Subscription Plans" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Subscription management
                        </p>
                        <h1 class="mt-2 admin-title">
                            Subscription Plans
                        </h1>
                        <p class="admin-subtitle">
                            Create and maintain provider plans with clear pricing, billing cycles, and status controls.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link :href="route('admin.subscriptions.reports')" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Reports
                    </Link>
                    <Link :href="route('admin.subscription-plans.create')" class="inline-flex items-center rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                        New Plan
                    </Link>
                    </div>
                </div>
            </section>

            <section class="admin-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="admin-table-th">Plan</th>
                            <th class="admin-table-th">Price</th>
                            <th class="admin-table-th">Cycle</th>
                            <th class="admin-table-th">Status</th>
                            <th class="admin-table-th">Subscribers</th>
                            <th class="admin-table-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="plan in plans" :key="plan.uuid">
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ plan.name }}</p>
                                <p class="text-xs text-slate-500">{{ plan.slug }}</p>
                            </td>
                            <td class="px-6 py-4">{{ (plan.price_cents / 100).toFixed(2) }} {{ plan.currency }}</td>
                            <td class="px-6 py-4 capitalize">{{ plan.billing_cycle }}</td>
                            <td class="px-6 py-4 capitalize">{{ plan.status }}</td>
                            <td class="px-6 py-4">{{ plan.subscribers_count ?? 0 }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <Link :href="route('admin.subscription-plans.edit', plan.uuid)" class="text-sky-600 hover:text-sky-700">Edit</Link>
                                    <button type="button" class="text-rose-600 hover:text-rose-700" @click="destroyPlan(plan.uuid)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="plans.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">No plans yet.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AdminLayout>
</template>
