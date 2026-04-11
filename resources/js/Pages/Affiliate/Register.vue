<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    company_name: '',
    website_url: '',
    social_profile_url: '',
    payout_method: '',
    paypal_email: '',
    bank_account_name: '',
});

const submit = () => {
    form.post(route('affiliate.register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Affiliate registration" />

    <GuestLayout>
        <template #title>Join the affiliate program</template>
        <template #subtitle>Register as a referral partner and track your clicks, signups, conversions, and payouts.</template>

        <form class="space-y-3" @submit.prevent="submit">
            <div class="grid gap-3 sm:grid-cols-2">
                <Input v-model="form.first_name" label="First name" :error="form.errors.first_name" required />
                <Input v-model="form.last_name" label="Last name" :error="form.errors.last_name" required />
            </div>
            <Input v-model="form.email" type="email" label="Email" :error="form.errors.email" required />
            <div class="grid gap-3 sm:grid-cols-2">
                <Input v-model="form.password" type="password" label="Password" :error="form.errors.password" required />
                <Input v-model="form.password_confirmation" type="password" label="Confirm password" :error="form.errors.password_confirmation" required />
            </div>
            <Input v-model="form.phone" label="Phone" :error="form.errors.phone" />
            <Input v-model="form.company_name" label="Company / brand" :error="form.errors.company_name" />
            <Input v-model="form.website_url" label="Website" :error="form.errors.website_url" />
            <Input v-model="form.social_profile_url" label="Social profile" :error="form.errors.social_profile_url" />
            <Input v-model="form.payout_method" label="Preferred payout method" :error="form.errors.payout_method" placeholder="PayPal, bank transfer, etc." />
            <div class="grid gap-3 sm:grid-cols-2">
                <Input v-model="form.paypal_email" label="PayPal email" :error="form.errors.paypal_email" />
                <Input v-model="form.bank_account_name" label="Bank account name" :error="form.errors.bank_account_name" />
            </div>
            <Button type="submit" :loading="form.processing" class="w-full">Create affiliate account</Button>
        </form>

        <template #footer>
            Already invited?
            <span class="text-slate-500">Use the invitation link from your email.</span>
            <br>
            Already have an account?
            <Link :href="route('login')" class="font-semibold text-primary-600 hover:text-primary-500">Sign in</Link>
        </template>
    </GuestLayout>
</template>
