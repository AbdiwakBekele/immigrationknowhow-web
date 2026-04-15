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
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Subscription management
                        </p>
                        <h1 class="mt-2 admin-title">
                            {{ isEdit ? 'Edit Plan' : 'Create Plan' }}
                        </h1>
                        <p class="admin-subtitle">
                            Configure plan pricing, billing cycle, feature list, and affiliate commission behavior.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link :href="route('admin.subscription-plans.index')" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Back to plans
                        </Link>
                    </div>
                </div>
            </section>

            <form class="space-y-5 admin-panel" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.name" label="Plan name" :error="form.errors.name" required />
                    <Input v-model="form.slug" label="Slug (optional)" :error="form.errors.slug" placeholder="auto-generated-if-empty" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <Input v-model="form.price_cents" type="number" min="0" label="Price (cents)" :error="form.errors.price_cents" required />
                    <Input v-model="form.currency" label="Currency" :error="form.errors.currency" required />
                    <div class="space-y-1">
                        <label class="admin-label">Billing cycle</label>
                        <select v-model="form.billing_cycle" class="admin-select">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                        <p v-if="form.errors.billing_cycle" class="text-xs text-rose-600">{{ form.errors.billing_cycle }}</p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="admin-label">Description</label>
                    <textarea v-model="form.description" rows="3" class="admin-textarea" />
                    <p v-if="form.errors.description" class="text-xs text-rose-600">{{ form.errors.description }}</p>
                </div>

                <div class="space-y-1">
                    <label class="admin-label">Features (one per line)</label>
                    <textarea v-model="form.features" rows="5" class="admin-textarea" placeholder="Featured listing&#10;Priority lead routing&#10;Analytics dashboard" />
                    <p v-if="form.errors.features" class="text-xs text-rose-600">{{ form.errors.features }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1">
                        <label class="admin-label">Status</label>
                        <select v-model="form.status" class="admin-select">
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
                        <label class="admin-label">Commission type</label>
                        <select v-model="form.commission_type" class="admin-select">
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

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="route('admin.subscription-plans.index')" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60">
                        {{ isEdit ? 'Update Plan' : 'Create Plan' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
