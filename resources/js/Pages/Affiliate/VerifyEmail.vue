<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Button from '@/Components/ui/Button.vue';

defineProps({
    status: String,
});

const form = useForm({});
const resend = () => form.post(route('affiliate.verification.send'));
</script>

<template>
    <Head title="Verify your email" />

    <GuestLayout>
        <template #title>Verify your affiliate email</template>
        <template #subtitle>Check your inbox and confirm your address to unlock the affiliate dashboard.</template>

        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
            <p>
                We sent a verification email to your account. Once verified, your affiliate account will become active and your referral dashboard will be available.
            </p>
            <p v-if="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-emerald-700">
                {{ status }}
            </p>
            <Button :loading="form.processing" @click="resend">Resend verification email</Button>
        </div>
    </GuestLayout>
</template>
