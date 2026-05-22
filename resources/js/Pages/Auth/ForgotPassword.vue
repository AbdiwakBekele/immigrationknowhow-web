<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot Password" />

    <GuestLayout
        panel-badge="Account recovery"
        panel-title="Reset your password"
        panel-description="Enter the email on your account and we will send you a secure link to choose a new password."
    >
        <template #title>Forgot password?</template>
        <template #subtitle>
            We will email you a reset link if an active account uses that address.
        </template>

        <div
            v-if="status"
            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <Input
                v-model="form.email"
                type="email"
                label="Email address"
                placeholder="you@example.com"
                autocomplete="username"
                :error="form.errors.email"
                required
            />

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Email reset link
            </Button>
        </form>

        <template #footer>
            Remember your password?
            <Link
                :href="route('login')"
                class="ml-1 font-semibold text-primary-700 transition hover:text-primary-800"
            >
                Back to sign in
            </Link>
        </template>
    </GuestLayout>
</template>
