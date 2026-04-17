<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({ affiliate: Object, statuses: Array, commissionTypes: Array });

const form = useForm({
    status: props.affiliate.status,
    phone: props.affiliate.phone || '',
    company_name: props.affiliate.company_name || '',
    website_url: props.affiliate.website_url || '',
    social_profile_url: props.affiliate.social_profile_url || '',
    notes: props.affiliate.notes || '',
    commission_type_override: props.affiliate.commission_type_override?.value || props.affiliate.commission_type_override || '',
    commission_value_override: props.affiliate.commission_value_override || '',
});

const submit = () => form.put(route('admin.affiliates.update', props.affiliate.id));
</script>

<template>
    <Head title="Edit Affiliate" />

    <AdminLayout>
        <div class="mx-auto max-w-3xl space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('admin.affiliates.show', affiliate.id)"
                        class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        title="Back to affiliate"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">Edit affiliate</h1>
                        <p class="mt-0.5 text-xs text-slate-500">Update status, contact details, and commission overrides.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <form class="space-y-3" @submit.prevent="submit">
                <Select v-model="form.status" :options="statuses" label="Status" :error="form.errors.status" required />
                <Input v-model="form.phone" label="Phone" :error="form.errors.phone" />
                <Input v-model="form.company_name" label="Company" :error="form.errors.company_name" />
                <Input v-model="form.website_url" label="Website" :error="form.errors.website_url" />
                <Input v-model="form.social_profile_url" label="Social profile" :error="form.errors.social_profile_url" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Select v-model="form.commission_type_override" :options="commissionTypes" label="Commission override type" :error="form.errors.commission_type_override" />
                    <Input v-model="form.commission_value_override" label="Commission override value" :error="form.errors.commission_value_override" />
                </div>
                <Input v-model="form.notes" label="Notes" :error="form.errors.notes" />
                <Button type="submit" :loading="form.processing">Save affiliate</Button>
            </form>
            </div>
        </div>
    </AdminLayout>
</template>
