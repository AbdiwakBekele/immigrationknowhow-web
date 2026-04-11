<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    rules: Object,
    scopeOptions: Array,
    triggerOptions: Array,
    typeOptions: Array,
    affiliates: Array,
    users: Array,
});

const form = useForm({
    scope: 'global',
    trigger_event: 'signup',
    affiliate_id: '',
    referred_user_id: '',
    commission_type: 'fixed',
    commission_value: '',
    currency: 'USD',
    priority: 0,
    starts_at: '',
    ends_at: '',
    notes: '',
    is_active: true,
});

const affiliateOptions = props.affiliates.map((item) => ({ value: item.id, label: item.label }));
const userOptions = props.users.map((item) => ({ value: item.id, label: item.label }));

const submit = () => form.post(route('admin.affiliates.commissions.store'));
</script>

<template>
    <Head title="Affiliate Commissions" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-xl font-semibold text-slate-900">Commission rules</h1>
                <form class="mt-5 grid gap-3 md:grid-cols-2" @submit.prevent="submit">
                    <Select v-model="form.scope" :options="scopeOptions" label="Scope" :error="form.errors.scope" required />
                    <Select v-model="form.trigger_event" :options="triggerOptions" label="Trigger event" :error="form.errors.trigger_event" required />
                    <Select v-model="form.affiliate_id" :options="affiliateOptions" label="Affiliate" :error="form.errors.affiliate_id" />
                    <Select v-model="form.referred_user_id" :options="userOptions" label="Referred user" :error="form.errors.referred_user_id" />
                    <Select v-model="form.commission_type" :options="typeOptions" label="Commission type" :error="form.errors.commission_type" required />
                    <Input v-model="form.commission_value" label="Commission value" :error="form.errors.commission_value" required />
                    <Input v-model="form.currency" label="Currency" :error="form.errors.currency" required />
                    <Input v-model="form.priority" label="Priority" :error="form.errors.priority" />
                    <Input v-model="form.starts_at" type="date" label="Starts at" :error="form.errors.starts_at" />
                    <Input v-model="form.ends_at" type="date" label="Ends at" :error="form.errors.ends_at" />
                    <Input v-model="form.notes" label="Notes" :error="form.errors.notes" />
                    <div class="md:col-span-2">
                        <Button type="submit" :loading="form.processing">Save commission rule</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500">
                            <th class="pb-3">Scope</th>
                            <th class="pb-3">Trigger</th>
                            <th class="pb-3">Commission</th>
                            <th class="pb-3">Affiliate</th>
                            <th class="pb-3">User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rule in rules.data" :key="rule.id" class="border-b border-slate-100">
                            <td class="py-3 capitalize text-slate-600">{{ rule.scope }}</td>
                            <td class="py-3 capitalize text-slate-600">{{ rule.trigger_event }}</td>
                            <td class="py-3 font-medium text-slate-900">{{ rule.commission_type }} {{ rule.commission_value }}</td>
                            <td class="py-3 text-slate-600">{{ rule.affiliate?.user?.email || '—' }}</td>
                            <td class="py-3 text-slate-600">{{ rule.referred_user?.email || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
