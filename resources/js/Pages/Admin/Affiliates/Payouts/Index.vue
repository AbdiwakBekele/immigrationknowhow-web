<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ payouts: Object, affiliates: Array });
const affiliateOptions = props.affiliates.map((item) => ({ value: item.id, label: item.label }));

const form = useForm({
    affiliate_id: '',
    earning_ids: [],
    amount: '',
    currency: 'USD',
    payout_date: '',
    payment_method: '',
    payment_reference: '',
    notes: '',
});

const submit = () => form.post(route('admin.affiliates.payouts.store'));
</script>

<template>
    <Head title="Affiliate Payouts" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-xl font-semibold text-slate-900">Record payout</h1>
                <p class="mt-1 text-sm text-slate-500">Enter earning IDs as a comma-separated list until the richer line-item picker lands.</p>
                <form class="mt-5 grid gap-3 md:grid-cols-2" @submit.prevent="submit">
                    <Select v-model="form.affiliate_id" :options="affiliateOptions" label="Affiliate" :error="form.errors.affiliate_id" required />
                    <Input v-model="form.amount" label="Amount" :error="form.errors.amount" required />
                    <Input v-model="form.currency" label="Currency" :error="form.errors.currency" required />
                    <Input v-model="form.payout_date" type="date" label="Payout date" :error="form.errors.payout_date" required />
                    <Input v-model="form.payment_method" label="Payment method" :error="form.errors.payment_method" required />
                    <Input v-model="form.payment_reference" label="Payment reference" :error="form.errors.payment_reference" />
                    <Input v-model="form.notes" label="Notes" :error="form.errors.notes" />
                    <Input
                        :model-value="Array.isArray(form.earning_ids) ? form.earning_ids.join(',') : ''"
                        label="Earning IDs"
                        :error="form.errors.earning_ids"
                        @update:model-value="form.earning_ids = $event.split(',').map((value) => Number(value.trim())).filter(Boolean)"
                    />
                    <div class="md:col-span-2">
                        <Button type="submit" :loading="form.processing">Record payout</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500">
                            <th class="pb-3">Affiliate</th>
                            <th class="pb-3">Date</th>
                            <th class="pb-3">Method</th>
                            <th class="pb-3">Reference</th>
                            <th class="pb-3">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payout in payouts.data" :key="payout.id" class="border-b border-slate-100">
                            <td class="py-3 text-slate-600">{{ payout.affiliate?.user?.email }}</td>
                            <td class="py-3 text-slate-600">{{ payout.payout_date }}</td>
                            <td class="py-3 text-slate-600">{{ payout.payment_method }}</td>
                            <td class="py-3 text-slate-600">{{ payout.payment_reference || '—' }}</td>
                            <td class="py-3 font-medium text-slate-900">{{ payout.currency }} {{ payout.amount }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
