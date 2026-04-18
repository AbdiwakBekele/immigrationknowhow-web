<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import { UserIcon, BriefcaseIcon } from '@heroicons/vue/24/outline';
import { ArrowLeftIcon } from '@heroicons/vue/20/solid';
import { SIGNUP_FLOW_STEPS_PROVIDER, SIGNUP_FLOW_STEPS_USER } from '@/constants/authFlowProgress';

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    initialRole: {
        type: String,
        default: 'user',
    },
    serviceTypes: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.initialRole || 'user',
    service_type: '',
});

const roleOptions = [
    {
        value: 'user',
        label: 'I need services',
        description: 'Create an account to request help and continue onboarding.',
        icon: UserIcon,
    },
    {
        value: 'provider',
        label: 'I provide services',
        description: 'Create an account to offer services and continue setup.',
        icon: BriefcaseIcon,
    },
];

const totalSteps = computed(() => (form.role === 'user' ? SIGNUP_FLOW_STEPS_USER : SIGNUP_FLOW_STEPS_PROVIDER));

const serviceTypeLabel = computed(() =>
    form.role === 'provider' ? 'Service you provide' : 'Service you need'
);

const serviceTypePlaceholder = computed(() =>
    form.role === 'provider'
        ? 'Choose the service you provide'
        : 'Choose the service you need help with'
);

const filteredServiceTypes = computed(() =>
    (props.serviceTypes || []).filter((type) => {
        if (form.role === 'provider') {
            return type.for_provider ?? true;
        }

        return type.for_user ?? true;
    })
);

watch(
    () => form.role,
    () => {
        form.service_type = '';
    }
);

const submit = () => {
    form.post(route('register'), {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    window.location.href = route('home');
};
</script>

<template>
    <Head title="Create account" />

    <GuestLayout
        panel-badge="Account setup"
        panel-title="Create your account with a cleaner, more comfortable experience."
        panel-description="This redesign keeps the current registration flow working while making it easier to read, easier to scan, and easier to complete."
        :panel-points="[
            'Larger forms with better spacing',
            'Softer colors and less visual noise',
            'The same role-based backend flow you already use',
        ]"
    >
        <template #title>Create your account</template>
        <template #subtitle>
            Start with your basic details below. Your next steps will continue based on the role you choose.
        </template>

        <template #progress>
            <AuthFlowProgress :current-step="1" :total-steps="totalSteps" />
        </template>

        <form @submit.prevent="submit" class="space-y-7">
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">
                        Join as
                    </p>
                    <p class="mt-1 text-sm leading-6 text-stone-600">
                        Choose the option that best matches how you will use the platform.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <button
                        v-for="option in roleOptions"
                        :key="option.value"
                        type="button"
                        @click="form.role = option.value"
                        :class="[
                            'group rounded-2xl border p-5 text-left transition-all duration-200',
                            form.role === option.value
                                ? 'border-primary-500 bg-primary-50 shadow-sm ring-2 ring-primary-200'
                                : 'border-stone-200 bg-white hover:border-stone-300 hover:bg-stone-50',
                        ]"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                :class="[
                                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border transition-colors',
                                    form.role === option.value
                                        ? 'border-primary-200 bg-white text-primary-700'
                                        : 'border-stone-200 bg-stone-50 text-stone-600 group-hover:bg-white',
                                ]"
                            >
                                <component :is="option.icon" class="h-5 w-5" />
                            </div>

                            <div class="min-w-0">
                                <p
                                    :class="[
                                        'text-base font-semibold',
                                        form.role === option.value ? 'text-primary-900' : 'text-stone-900',
                                    ]"
                                >
                                    {{ option.label }}
                                </p>
                                <p class="mt-1 text-sm leading-6 text-stone-600">
                                    {{ option.description }}
                                </p>
                            </div>
                        </div>
                    </button>
                </div>

                <p v-if="form.errors.role" class="text-sm text-red-600">
                    {{ form.errors.role }}
                </p>
            </div>

            <div
                v-if="form.role === 'provider'"
                class="rounded-2xl border border-stone-200 bg-stone-50 p-4 sm:p-5"
            >
                <Select
                    v-model="form.service_type"
                    :options="filteredServiceTypes"
                    :label="serviceTypeLabel"
                    :placeholder="serviceTypePlaceholder"
                    :error="form.errors.service_type"
                    required
                />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <Input
                    v-model="form.first_name"
                    label="First name"
                    placeholder="Jane"
                    autocomplete="given-name"
                    :error="form.errors.first_name"
                    required
                />

                <Input
                    v-model="form.last_name"
                    label="Last name"
                    placeholder="Doe"
                    autocomplete="family-name"
                    :error="form.errors.last_name"
                    required
                />
            </div>

            <Input
                v-model="form.email"
                type="email"
                label="Email address"
                placeholder="you@example.com"
                autocomplete="email"
                :error="form.errors.email"
                required
            />

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <Input
                    v-model="form.password"
                    type="password"
                    label="Password"
                    placeholder="Create a password"
                    autocomplete="new-password"
                    helper="Use at least 8 characters."
                    :error="form.errors.password"
                    required
                />

                <Input
                    v-model="form.password_confirmation"
                    type="password"
                    label="Confirm password"
                    placeholder="Re-enter your password"
                    autocomplete="new-password"
                    :error="form.errors.password_confirmation"
                    required
                />
            </div>

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    size="md"
                    class="justify-center border border-stone-300 bg-white text-stone-700 hover:bg-stone-50 sm:justify-start"
                    @click="goBack"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back
                </Button>

                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    :loading="form.processing"
                    class="w-full sm:w-auto sm:min-w-[13rem]"
                >
                    Continue
                </Button>
            </div>
        </form>

        <template #footer>
            Already have an account?
            <Link
                :href="route('login')"
                class="ml-1 font-semibold text-primary-700 transition hover:text-primary-800"
            >
                Sign in
            </Link>
        </template>
    </GuestLayout>
</template>