<script setup>
import { Head } from '@inertiajs/vue3';
import AffiliateLayout from '@/Layouts/AffiliateLayout.vue';

defineProps({ payouts: Object });
</script>

<template>
    <Head title="Affiliate Payouts" />

    <AffiliateLayout>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-xl font-semibold text-slate-900">Payout history</h1>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500">
                            <th class="pb-3">Date</th>
                            <th class="pb-3">Method</th>
                            <th class="pb-3">Reference</th>
                            <th class="pb-3">Amount</th>
                            <th class="pb-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payout in payouts.data" :key="payout.id" class="border-b border-slate-100">
                            <td class="py-3 text-slate-600">{{ payout.payout_date }}</td>
                            <td class="py-3 font-medium text-slate-900">{{ payout.payment_method }}</td>
                            <td class="py-3 text-slate-600">{{ payout.payment_reference || '—' }}</td>
                            <td class="py-3 text-slate-600">{{ payout.currency }} {{ payout.amount }}</td>
                            <td class="py-3 text-slate-600">{{ payout.notes || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!payouts.data.length" class="py-6 text-sm text-slate-500">No payouts recorded yet.</p>
            </div>
        </div>
    </AffiliateLayout>
</template>
