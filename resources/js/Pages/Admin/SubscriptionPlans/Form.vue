<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';

const props = defineProps({
    plan: { type: Object, default: null },
    serviceTypeOptions: { type: Array, default: () => [] },
});

const currencyOptions = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];

const form = useForm({
    name: props.plan?.name ?? '',
    slug: props.plan?.slug ?? '',
    description: props.plan?.description ?? '',
    price: props.plan ? Number((props.plan.price_cents / 100).toFixed(2)) : 0,
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
    service_type_option_id: props.plan?.service_type_option_id != null ? String(props.plan.service_type_option_id) : '',
    stripe_product_id: props.plan?.stripe_product_id ?? '',
    stripe_price_id: props.plan?.stripe_price_id ?? '',
});

const isEdit = computed(() => !!props.plan);

const submit = () => {
    const raw = form.price;
    if (raw !== '' && raw !== null && raw !== undefined) {
        const n = Number(String(raw).replace(',', '.'));
        if (Number.isFinite(n) && n >= 0) {
            form.price = n;
        }
    }

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
                    <Input
                        v-model="form.price"
                        type="number"
                        min="0"
                        step="any"
                        label="Price"
                        helper="Enter 0 for a free plan. Use any positive amount for paid plans."
                        :error="form.errors.price"
                        required
                    />
                    <div class="space-y-1">
                        <label class="admin-label">Currency</label>
                        <select v-model="form.currency" class="admin-select">
                            <option v-for="code in currencyOptions" :key="code" :value="code">{{ code }}</option>
                        </select>
                        <p v-if="form.errors.currency" class="text-xs text-rose-600">{{ form.errors.currency }}</p>
                    </div>
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
                    <label class="admin-label">Service type scope</label>
                    <p class="text-xs text-slate-500">
                        “All” shows this plan to every provider. Otherwise only providers who offer the selected type will see it during onboarding and billing.
                    </p>
                    <select v-model="form.service_type_option_id" class="admin-select">
                        <option value="">All provider service types</option>
                        <option v-for="o in serviceTypeOptions" :key="o.id" :value="String(o.id)">
                            {{ o.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.service_type_option_id" class="text-xs text-rose-600">{{ form.errors.service_type_option_id }}</p>
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

                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Stripe configuration (optional)</p>
                        <p class="mt-1 text-xs text-slate-600">
                            Paid plan switching requires a persistent Stripe Price ID (<span class="font-mono">price_…</span>).
                            If blank, the system may still create an inline price for checkout, but <span class="font-semibold">Switch Plan</span> won’t work.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Input
                            v-model="form.stripe_product_id"
                            label="Stripe Product ID"
                            placeholder="prod_..."
                            :error="form.errors.stripe_product_id"
                        />
                        <Input
                            v-model="form.stripe_price_id"
                            label="Stripe Price ID"
                            placeholder="price_..."
                            :error="form.errors.stripe_price_id"
                        />
                    </div>
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
