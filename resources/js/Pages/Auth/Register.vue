<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import { UserIcon, BriefcaseIcon } from '@heroicons/vue/24/outline';

defineProps({
    roles: Array,
    serviceTypes: Array,
    languageOptions: Array,
    countryOptions: Array,
    captchaCode: {
        type: String,
        required: true,
    },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user',
    service_type: '',
    address: '',
    city: '',
    country: 'US',
    postal_code: '',
    preferred_language: 'en',
    terms: false,
    captcha: '',
});

const submit = () => {
    form.post(route('register'), {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
        onError: () => {
            form.captcha = '';
        },
    });
};

const roleOptions = [
    { value: 'user', label: 'I need services', icon: UserIcon },
    { value: 'provider', label: 'I provide services', icon: BriefcaseIcon },
];
</script>

<template>
    <Head title="Create account" />

    <GuestLayout>
        <template #title>Create your account</template>
        <template #subtitle>One short form — then we will confirm your phone.</template>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-2">
                <div class="grid grid-cols-2 gap-2 sm:max-w-md sm:mx-auto">
                    <button
                        v-for="option in roleOptions"
                        :key="option.value"
                        type="button"
                        @click="form.role = option.value"
                        :class="[
                            'flex flex-col items-center justify-center rounded-xl border px-2 py-3 text-center transition-all duration-200',
                            form.role === 'provider' && option.value === 'provider'
                                ? 'border-primary-600 bg-primary-600 shadow-md ring-1 ring-primary-700/20'
                                : option.value === 'provider'
                                  ? 'border-primary-300 bg-primary-100/90 shadow-sm hover:border-primary-400 hover:bg-primary-100'
                                  : form.role === option.value
                                    ? 'border-primary-600 bg-primary-50/90 shadow-sm ring-1 ring-primary-500/25'
                                    : 'border-neutral-200 bg-white shadow-sm hover:border-primary-300',
                        ]"
                    >
                        <component
                            :is="option.icon"
                            :class="[
                                'mb-1.5 h-6 w-6 shrink-0 sm:h-7 sm:w-7',
                                form.role === 'provider' && option.value === 'provider'
                                    ? 'text-white'
                                    : option.value === 'provider'
                                      ? 'text-primary-700'
                                      : form.role === option.value
                                        ? 'text-primary-600'
                                        : 'text-primary-500/80',
                            ]"
                        />
                        <span
                            :class="[
                                'text-center text-xs font-semibold leading-tight sm:text-[13px]',
                                form.role === 'provider' && option.value === 'provider'
                                    ? 'text-white'
                                    : option.value === 'provider'
                                      ? 'text-primary-900'
                                      : form.role === option.value
                                        ? 'text-primary-900'
                                        : 'text-neutral-800',
                            ]"
                        >
                            {{ option.label }}
                        </span>
                    </button>
                </div>
                <p v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input
                    v-model="form.first_name"
                    label="First name"
                    placeholder="Jane"
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
                label="Email"
                placeholder="you@example.com"
                :error="form.errors.email"
                required
            />

            <Select
                v-model="form.service_type"
                :options="serviceTypes"
                label="Service type"
                placeholder="Choose the type that fits you best"
                :error="form.errors.service_type"
                required
            />

            <div class="space-y-2">
                <p class="text-xs font-medium text-neutral-600">Location</p>
                <Input
                    v-model="form.address"
                    label="Street address"
                    placeholder="Street, apt / unit"
                    :error="form.errors.address"
                    size="compact"
                    required
                    autocomplete="street-address"
                />
                <div class="grid grid-cols-2 gap-2">
                    <Input
                        v-model="form.city"
                        label="City"
                        placeholder="City"
                        :error="form.errors.city"
                        size="compact"
                        required
                        autocomplete="address-level2"
                    />
                    <Input
                        v-model="form.postal_code"
                        label="Postal code"
                        placeholder="ZIP / postal"
                        :error="form.errors.postal_code"
                        size="compact"
                        required
                        autocomplete="postal-code"
                    />
                </div>
                <Select
                    v-model="form.country"
                    :options="countryOptions"
                    label="Country"
                    placeholder="Country"
                    :error="form.errors.country"
                    size="compact"
                    required
                />
            </div>

            <Select
                v-model="form.preferred_language"
                :options="languageOptions"
                label="Language"
                placeholder="Select language"
                :error="form.errors.preferred_language"
                required
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input
                    v-model="form.password"
                    type="password"
                    label="Password"
                    placeholder="••••••••"
                    helper="At least 8 characters"
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
            </div>

            <div class="space-y-1.5">
                <label for="register-captcha" class="block text-sm font-medium text-neutral-700">
                    Captcha
                    <span class="ml-0.5 text-red-500">*</span>
                </label>
                <div
                    class="flex overflow-hidden rounded-xl border transition-all duration-200"
                    :class="
                        form.errors.captcha
                            ? 'border-red-300 ring-2 ring-red-500/20'
                            : 'border-neutral-300 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/20'
                    "
                >
                    <input
                        id="register-captcha"
                        v-model="form.captcha"
                        type="text"
                        inputmode="numeric"
                        maxlength="4"
                        autocomplete="off"
                        placeholder="Enter the 4-digit code"
                        required
                        class="min-w-0 flex-1 border-0 bg-white px-4 py-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                    />
                    <div
                        class="flex min-w-[5.5rem] shrink-0 select-none items-center justify-center bg-emerald-900 px-4 py-3 text-lg font-semibold tracking-widest text-white"
                        aria-hidden="true"
                    >
                        {{ captchaCode }}
                    </div>
                </div>
                <p v-if="form.errors.captcha" class="text-xs text-red-600">{{ form.errors.captcha }}</p>
            </div>

            <div class="space-y-2">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        v-model="form.terms"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="text-sm text-neutral-600">
                        I agree to the
                        <a href="#" class="font-medium text-primary-600 hover:text-primary-500">Terms</a>
                        and
                        <a href="#" class="font-medium text-primary-600 hover:text-primary-500">Privacy Policy</a>
                    </span>
                </label>
                <p v-if="form.errors.terms" class="text-xs text-red-600">{{ form.errors.terms }}</p>
            </div>

            <Button type="submit" variant="primary" size="lg" :loading="form.processing" class="w-full">
                Continue
            </Button>
        </form>

        <template #footer>
            Already have an account?
            <Link :href="route('login')" class="ml-1 font-semibold text-primary-600 hover:text-primary-500">
                Sign in
            </Link>
        </template>
    </GuestLayout>
</template>
