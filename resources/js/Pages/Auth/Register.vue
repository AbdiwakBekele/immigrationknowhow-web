<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import { UserIcon, BriefcaseIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    roles: Array,
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const roleOptions = [
    {
        value: 'user',
        label: 'I need services',
        description: 'Find and connect with immigration service providers',
        icon: UserIcon,
    },
    {
        value: 'provider',
        label: 'I provide services',
        description: 'Offer your professional services to immigrants',
        icon: BriefcaseIcon,
    },
];
</script>

<template>
    <Head title="Create Account" />

    <GuestLayout>
        <template #title>Create your account</template>
        <template #subtitle>Join our community and get started</template>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Role selection -->
            <div class="space-y-3">
                <label class="block text-sm font-medium text-neutral-700">I want to...</label>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        v-for="option in roleOptions"
                        :key="option.value"
                        type="button"
                        @click="form.role = option.value"
                        :class="[
                            'relative p-4 rounded-xl border-2 text-left transition-all duration-200',
                            form.role === option.value
                                ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500/20'
                                : 'border-neutral-200 hover:border-neutral-300'
                        ]"
                    >
                        <component
                            :is="option.icon"
                            :class="[
                                'w-6 h-6 mb-2',
                                form.role === option.value ? 'text-primary-600' : 'text-neutral-400'
                            ]"
                        />
                        <div class="font-semibold text-sm text-neutral-900">{{ option.label }}</div>
                        <div class="text-xs text-neutral-500 mt-0.5">{{ option.description }}</div>
                    </button>
                </div>
                <p v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <Input
                    v-model="form.first_name"
                    label="First name"
                    placeholder="John"
                    :error="form.errors.first_name"
                    required
                />
                <Input
                    v-model="form.last_name"
                    label="Last name"
                    placeholder="Doe"
                    :error="form.errors.last_name"
                    required
                />
            </div>

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
                helper="Must be at least 8 characters"
                :error="form.errors.password"
                required
            />

            <Input
                v-model="form.password_confirmation"
                type="password"
                label="Confirm password"
                placeholder="••••••••"
                :error="form.errors.password_confirmation"
                required
            />

            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        v-model="form.terms"
                        type="checkbox"
                        class="w-4 h-4 mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="text-sm text-neutral-600">
                        I agree to the
                        <a href="#" class="text-primary-600 hover:text-primary-500 font-medium">Terms of Service</a>
                        and
                        <a href="#" class="text-primary-600 hover:text-primary-500 font-medium">Privacy Policy</a>
                    </span>
                </label>
                <p v-if="form.errors.terms" class="text-xs text-red-600">{{ form.errors.terms }}</p>
            </div>

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Create account
            </Button>
        </form>

        <template #footer>
            Already have an account?
            <Link :href="route('login')" class="font-semibold text-primary-600 hover:text-primary-500 ml-1">
                Sign in
            </Link>
        </template>
    </GuestLayout>
</template>
