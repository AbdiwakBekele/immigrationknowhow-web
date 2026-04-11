<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AffiliateLayout from '@/Layouts/AffiliateLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ affiliate: Object });

const form = useForm({
    first_name: props.affiliate.user?.first_name || '',
    last_name: props.affiliate.user?.last_name || '',
    phone: props.affiliate.phone || '',
    company_name: props.affiliate.company_name || '',
    website_url: props.affiliate.website_url || '',
    social_profile_url: props.affiliate.social_profile_url || '',
    payout_method: props.affiliate.payout_method || '',
    paypal_email: props.affiliate.payout_details?.paypal_email || '',
    bank_account_name: props.affiliate.payout_details?.bank_account_name || '',
});

const submit = () => form.patch(route('affiliate.profile.update'));
</script>

<template>
    <Head title="Affiliate Profile" />

    <AffiliateLayout>
        <div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-semibold text-slate-900">Affiliate profile</h1>
            <form class="mt-5 space-y-3" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2">
                    <Input v-model="form.first_name" label="First name" :error="form.errors.first_name" required />
                    <Input v-model="form.last_name" label="Last name" :error="form.errors.last_name" required />
                </div>
                <Input v-model="form.phone" label="Phone" :error="form.errors.phone" />
                <Input v-model="form.company_name" label="Company / brand" :error="form.errors.company_name" />
                <Input v-model="form.website_url" label="Website" :error="form.errors.website_url" />
                <Input v-model="form.social_profile_url" label="Social profile" :error="form.errors.social_profile_url" />
                <Input v-model="form.payout_method" label="Payout method" :error="form.errors.payout_method" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Input v-model="form.paypal_email" label="PayPal email" :error="form.errors.paypal_email" />
                    <Input v-model="form.bank_account_name" label="Bank account name" :error="form.errors.bank_account_name" />
                </div>
                <Button type="submit" :loading="form.processing">Save profile</Button>
            </form>
        </div>
    </AffiliateLayout>
</template>
