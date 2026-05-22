<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    token: String,
    email: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password" />

    <GuestLayout
        panel-badge="Account recovery"
        panel-title="Choose a new password"
        panel-description="Use a strong password you have not used on other sites. After saving, sign in with your new credentials."
    >
        <template #title>Set a new password</template>
        <template #subtitle>
            This link expires after a short time for your security.
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <Input
                v-model="form.email"
                type="email"
                label="Email address"
                autocomplete="username"
                :error="form.errors.email"
                required
            />

            <Input
                v-model="form.password"
                type="password"
                label="New password"
                autocomplete="new-password"
                :error="form.errors.password"
                required
            />

            <Input
                v-model="form.password_confirmation"
                type="password"
                label="Confirm new password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                required
            />

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Reset password
            </Button>
        </form>

        <template #footer>
            <Link
                :href="route('login')"
                class="font-semibold text-primary-700 transition hover:text-primary-800"
            >
                Back to sign in
            </Link>
        </template>
    </GuestLayout>
</template>
