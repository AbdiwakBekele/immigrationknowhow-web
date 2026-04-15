<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';

const props = defineProps({
    plan: { type: Object, default: null },
});

const form = useForm({
    name: props.plan?.name ?? '',
    slug: props.plan?.slug ?? '',
    description: props.plan?.description ?? '',
    price_cents: props.plan?.price_cents ?? 9900,
    currency: props.plan?.currency ?? 'USD',
    billing_cycle: props.plan?.billing_cycle ?? 'monthly',
    features: props.plan?.features?.join('\n') ?? '',
    status: props.plan?.status ?? 'draft',
    is_featured: props.plan?.is_featured ?? false,
    sort_order: props.plan?.sort_order ?? 0,
    commission_type: props.plan?.commission_type ?? 'percentage',
    commission_value: props.plan?.commission_value ?? 10,
    recurring_commission_enabled: props.plan?.recurring_commission_enabled ?? true,
    max_recurring_commission_cycles: props.plan?.max_recurring_commission_cycles ?? null,
});

const isEdit = computed(() => !!props.plan);

const submit = () => {
    if (isEdit.value) {
        form.patch(route('admin.subscription-plans.update', props.plan.uuid));
        return;
    }

    form.post(route('admin.subscription-plans.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Subscription Plan' : 'New Subscription Plan'" />

    <AdminLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">{{ isEdit ? 'Edit Plan' : 'Create Plan' }}</h1>
                <Link :href="route('admin.subscription-plans.index')" class="text-sm font-medium text-slate-600 hover:text-slate-800">
                    Back to plans
                </Link>
            </div>

            <form class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.name" label="Plan name" :error="form.errors.name" required />
                    <Input v-model="form.slug" label="Slug (optional)" :error="form.errors.slug" placeholder="auto-generated-if-empty" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <Input v-model="form.price_cents" type="number" min="0" label="Price (cents)" :error="form.errors.price_cents" required />
                    <Input v-model="form.currency" label="Currency" :error="form.errors.currency" required />
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-700">Billing cycle</label>
                        <select v-model="form.billing_cycle" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                        <p v-if="form.errors.billing_cycle" class="text-xs text-rose-600">{{ form.errors.billing_cycle }}</p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-700">Description</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="form.errors.description" class="text-xs text-rose-600">{{ form.errors.description }}</p>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-700">Features (one per line)</label>
                    <textarea v-model="form.features" rows="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Featured listing&#10;Priority lead routing&#10;Analytics dashboard" />
                    <p v-if="form.errors.features" class="text-xs text-rose-600">{{ form.errors.features }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-700">Status</label>
                        <select v-model="form.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <Input v-model="form.sort_order" type="number" min="0" label="Sort order" :error="form.errors.sort_order" />
                    <label class="mt-6 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_featured" type="checkbox" class="rounded border-slate-300 text-sky-600" />
                        Featured plan
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-4">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-700">Commission type</label>
                        <select v-model="form.commission_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed</option>
                        </select>
                    </div>
                    <Input v-model="form.commission_value" type="number" min="0" step="0.01" label="Commission value" :error="form.errors.commission_value" />
                    <Input v-model="form.max_recurring_commission_cycles" type="number" min="1" label="Max recurring cycles" :error="form.errors.max_recurring_commission_cycles" />
                    <label class="mt-6 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.recurring_commission_enabled" type="checkbox" class="rounded border-slate-300 text-sky-600" />
                        Recurring enabled
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Link :href="route('admin.subscription-plans.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-60">
                        {{ isEdit ? 'Update Plan' : 'Create Plan' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
