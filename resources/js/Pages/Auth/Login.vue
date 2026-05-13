<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
    authenticatedUser: {
        type: Object,
        default: null,
    },
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

    <GuestLayout
        panel-badge="Trusted Support"
        panel-title="Starting fresh shouldn’t mean starting alone!"
        panel-description="Access a network of verified service providers who share your language and cultural understanding—built to support you every step of the way."
    >
        <template #title>Welcome back</template>
        <template #subtitle>
            Sign in to access your account, forms, dashboard, and next steps.
        </template>

        <div
            v-if="status"
            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
        >
            {{ status }}
        </div>

        <div
            v-if="authenticatedUser"
            class="mb-6 rounded-2xl border border-primary-200 bg-primary-50 px-5 py-4 text-sm text-primary-950"
        >
            <p class="leading-6">
                <span class="font-semibold">You are already signed in</span>
                <span class="text-primary-800"> as {{ authenticatedUser.email }}</span>
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <Link
                    :href="authenticatedUser.continueUrl || route('dashboard')"
                    class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700"
                >
                    Continue to your account
                </Link>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    type="button"
                    class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50"
                >
                    Sign out
                </Link>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <Input
                v-model="form.email"
                type="email"
                label="Email address"
                placeholder="you@example.com"
                autocomplete="email"
                :error="form.errors.email"
                required
            />

            <Input
                v-model="form.password"
                type="password"
                label="Password"
                placeholder="Enter your password"
                autocomplete="current-password"
                :error="form.errors.password"
                required
            />

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="inline-flex cursor-pointer items-center gap-3">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-stone-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="text-sm font-medium text-stone-600">Remember me</span>
                </label>

                <p
                    v-if="canResetPassword"
                    class="text-sm text-stone-500"
                >
                    Need help signing in? Contact support.
                </p>
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
            Don’t have an account?
            <Link
                :href="route('register')"
                class="ml-1 font-semibold text-primary-700 transition hover:text-primary-800"
            >
                Create one now
            </Link>
        </template>
    </GuestLayout>
</template>