<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import { UserIcon, BriefcaseIcon } from '@heroicons/vue/24/outline';
import { ArrowLeftIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    roles: Array,
    initialRole: String,
    serviceTypes: Array,
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

const authImage = ref('https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=1400&q=80');
const fallbackImage = ref(false);

const handleImageError = () => {
    fallbackImage.value = true;
};

const submit = () => {
    const selectedRole = form.role;

    form.post(route('register'), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedRole === 'provider') {
                router.visit(route('address-detail'));
            }
        },
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

const roleOptions = [
    { value: 'user', label: 'I need services', icon: UserIcon },
    { value: 'provider', label: 'I provide services', icon: BriefcaseIcon },
];

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

const totalSteps = computed(() => (form.role === 'user' ? 3 : 5));

watch(
    () => form.role,
    () => {
        form.service_type = '';
    }
);
</script>

<template>
    <Head title="Create account" />

    <GuestLayout>
        <template #title>Create your account</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="1" :total-steps="totalSteps" />
        </template>
        <template #side-image>
            <div class="relative h-full w-full overflow-hidden">
                <img
                    v-if="!fallbackImage"
                    :src="authImage"
                    alt="Customer support illustration"
                    class="h-full w-full object-cover"
                    @error="handleImageError"
                />
                <div
                    v-if="!fallbackImage"
                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"
                />
                <div v-if="!fallbackImage" class="pointer-events-none absolute bottom-0 left-0 right-0 p-6 text-white">
                    <p class="mb-2 inline-flex rounded-full bg-emerald-500/70 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide">
                        Immigration Support
                    </p>
                    <h3 class="text-2xl font-bold leading-tight">Your journey. Our guidance.</h3>
                    <p class="mt-2 text-sm text-white/90">
                        Create your account and connect with trusted experts.
                    </p>
                </div>
                <div
                    v-else
                    class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-100 via-white to-primary-200 px-6 text-center text-sm font-medium text-primary-700"
                >
                    Side image unavailable right now.
                </div>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-2.5">
            <div class="space-y-1.5">
                <p class="text-sm font-medium text-neutral-700 sm:max-w-md sm:mx-auto">I am joining as</p>
                <div class="grid grid-cols-2 gap-2 sm:max-w-md sm:mx-auto">
                    <button
                        v-for="option in roleOptions"
                        :key="option.value"
                        type="button"
                        @click="form.role = option.value"
                        :class="[
                            'flex min-h-[42px] flex-col items-center justify-center rounded-md border px-1 py-1 text-center transition-colors duration-200',
                            form.role === option.value
                                ? 'border-primary-600 bg-primary-600 shadow-md'
                                : 'border-neutral-200 bg-white',
                        ]"
                    >
                        <component
                            :is="option.icon"
                            :class="[
                                'mb-0.5 h-3 w-3 shrink-0 sm:h-3.5 sm:w-3.5',
                                form.role === option.value ? 'text-white' : 'text-primary-500/80',
                            ]"
                        />
                        <span
                            :class="[
                                'text-center text-[10px] font-semibold leading-tight',
                                form.role === option.value ? 'text-white' : 'text-neutral-800',
                            ]"
                        >
                            {{ option.label }}
                        </span>
                    </button>
                </div>
                <p v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</p>
            </div>

            <Select
                v-if="form.role === 'provider'"
                v-model="form.service_type"
                :options="filteredServiceTypes"
                :label="serviceTypeLabel"
                :placeholder="serviceTypePlaceholder"
                :error="form.errors.service_type"
                size="compact"
                required
            />

            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <Input
                    v-model="form.first_name"
                    label="First name"
                    placeholder="Jane"
                    :error="form.errors.first_name"
                    size="compact"
                    required
                />
                <Input
                    v-model="form.last_name"
                    label="Last name"
                    placeholder="Doe"
                    :error="form.errors.last_name"
                    size="compact"
                    required
                />
            </div>

            <Input
                v-model="form.email"
                type="email"
                label="Email"
                placeholder="you@example.com"
                :error="form.errors.email"
                size="compact"
                required
            />

            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <Input
                    v-model="form.password"
                    type="password"
                    label="Password"
                    placeholder="••••••••"
                    helper="At least 8 characters"
                    :error="form.errors.password"
                    size="compact"
                    required
                />
                <Input
                    v-model="form.password_confirmation"
                    type="password"
                    label="Confirm password"
                    placeholder="••••••••"
                    :error="form.errors.password_confirmation"
                    size="compact"
                    required
                />
            </div>

            <div class="flex items-center justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="!rounded-md border border-neutral-200 bg-white !px-2.5 !py-1.5 !text-xs text-neutral-700 hover:bg-neutral-50"
                    @click="goBack"
                >
                    <ArrowLeftIcon class="h-3.5 w-3.5" />
                    Back
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    class="min-w-[10rem] !py-1.5 !text-xs !rounded-md"
                >
                    Continue
                </Button>
            </div>
        </form>

        <template #footer>
            <span class="inline-block pt-1">Already have an account?</span>
            <Link :href="route('login')" class="ml-1 font-semibold text-primary-600 hover:text-primary-500">
                Sign in
            </Link>
        </template>
    </GuestLayout>
</template>
