<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign In" />

    <GuestLayout>
        <template #title>Welcome back</template>
        <template #subtitle>Sign in to your account to continue</template>

        <div v-if="status" class="mb-4 p-4 rounded-xl bg-emerald-50 text-sm font-medium text-emerald-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <Input
                v-model="form.email"
                type="email"
                label="Email address"
                placeholder="you@example.com"
                :error="form.errors.email"
                required
            />

            <Input
                v-model="form.password"
                type="password"
                label="Password"
                placeholder="••••••••"
                :error="form.errors.password"
                required
            />

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="w-4 h-4 rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="text-sm text-neutral-600">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    href="#"
                    class="text-sm font-medium text-primary-600 hover:text-primary-500"
                >
                    Forgot password?
                </Link>
            </div>

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Sign in
            </Button>
        </form>

        <template #footer>
            Don't have an account?
            <Link :href="route('register')" class="font-semibold text-primary-600 hover:text-primary-500 ml-1">
                Create one now
            </Link>
        </template>
    </GuestLayout>
</template>
