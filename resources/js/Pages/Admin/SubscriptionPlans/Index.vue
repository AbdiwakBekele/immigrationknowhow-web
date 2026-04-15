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
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Subscription Plans</h1>
                    <p class="text-sm text-slate-500">Create dynamic provider subscription plans.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.subscriptions.reports')" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Reports
                    </Link>
                    <Link :href="route('admin.subscription-plans.create')" class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-700">
                        New Plan
                    </Link>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Plan</th>
                            <th class="px-4 py-3 font-medium">Price</th>
                            <th class="px-4 py-3 font-medium">Cycle</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Subscribers</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="plan in plans" :key="plan.uuid">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ plan.name }}</p>
                                <p class="text-xs text-slate-500">{{ plan.slug }}</p>
                            </td>
                            <td class="px-4 py-3">{{ (plan.price_cents / 100).toFixed(2) }} {{ plan.currency }}</td>
                            <td class="px-4 py-3 capitalize">{{ plan.billing_cycle }}</td>
                            <td class="px-4 py-3 capitalize">{{ plan.status }}</td>
                            <td class="px-4 py-3">{{ plan.subscribers_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <Link :href="route('admin.subscription-plans.edit', plan.uuid)" class="text-sky-600 hover:text-sky-700">Edit</Link>
                                    <button type="button" class="text-rose-600 hover:text-rose-700" @click="destroyPlan(plan.uuid)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="plans.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No plans yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
