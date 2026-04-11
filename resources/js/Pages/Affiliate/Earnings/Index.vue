<script setup>
import { Head } from '@inertiajs/vue3';
import AffiliateLayout from '@/Layouts/AffiliateLayout.vue';

defineProps({ earnings: Object });
</script>

<template>
    <Head title="Affiliate Earnings" />

    <AffiliateLayout>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-xl font-semibold text-slate-900">Earnings ledger</h1>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500">
                            <th class="pb-3">Event</th>
                            <th class="pb-3">Referred user</th>
                            <th class="pb-3">Amount</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="earning in earnings.data" :key="earning.id" class="border-b border-slate-100">
                            <td class="py-3 font-medium text-slate-900">{{ earning.event_type }}</td>
                            <td class="py-3 text-slate-600">{{ earning.referred_user?.email || '—' }}</td>
                            <td class="py-3 text-slate-600">{{ earning.currency }} {{ earning.commission_amount }}</td>
                            <td class="py-3 capitalize text-slate-600">{{ earning.status }}</td>
                            <td class="py-3 text-slate-600">{{ earning.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!earnings.data.length" class="py-6 text-sm text-slate-500">No earnings yet.</p>
            </div>
        </div>
    </AffiliateLayout>
</template>
