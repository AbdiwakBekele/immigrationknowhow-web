<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    user: Object,
    isProvider: Boolean,
    serviceTypes: Array,
    countryOptions: Array,
    languageOptions: Array,
    existingData: Object,
});

const currentStep = ref(props.isProvider ? 4 : 2);
const totalSteps = computed(() => (props.isProvider ? 5 : 3));
const isBusinessStep = computed(() => props.isProvider && currentStep.value === 4);
const isUserStepTwo = computed(() => !props.isProvider && currentStep.value === 2);
const isUserStepThree = computed(() => !props.isProvider && currentStep.value === 3);

const onboardingHeading = computed(() => {
    if (isBusinessStep.value) {
        return 'Business information';
    }
    if (isUserStepThree.value) {
        return 'Congratulations';
    }
    return props.isProvider ? 'Pricing and service area' : 'Service preferences';
});

const formData = ref({
    services_needed: [],
    city: props.user?.city || '',
    country: props.user?.country || 'US',
    preferred_language: props.user?.preferred_language || 'en',
    business: {
        business_name: '',
        bio: '',
        tagline: '',
        business_email: props.user?.email || '',
        business_phone: '',
        website: '',
        years_experience: null,
    },
    services: {
        types: [],
        specializations: [],
    },
    pricing: {
        model: 'hourly',
        hourly_rate: null,
        consultation_fee: null,
        free_consultation: false,
        notes: '',
    },
    'service-area': {
        remote: false,
        in_person: true,
        radius: null,
        areas: [],
    },
});

const mergeExistingOnboarding = () => {
    const e = props.existingData || {};
    if (e.services?.services_needed?.length) {
        formData.value.services_needed = e.services.services_needed;
    }
    if (e.services?.types) {
        formData.value.services.types = e.services.types;
    }
    if (e.services?.specializations) {
        formData.value.services.specializations = e.services.specializations;
    }
    if (e.location?.city) {
        formData.value.city = e.location.city;
    }
    if (e.location?.country) {
        formData.value.country = e.location.country;
    }
    if (e.language?.preferred) {
        formData.value.preferred_language = e.language.preferred;
    }
    if (e.business && typeof e.business === 'object') {
        formData.value.business = { ...formData.value.business, ...e.business };
    }
    if (e.pricing && typeof e.pricing === 'object') {
        formData.value.pricing = { ...formData.value.pricing, ...e.pricing };
    }
    if (e['service-area'] && typeof e['service-area'] === 'object') {
        formData.value['service-area'] = { ...formData.value['service-area'], ...e['service-area'] };
    }
};

mergeExistingOnboarding();

onMounted(() => {
    const reg = props.existingData?.registration?.service_type;
    if (!reg) {
        return;
    }
    if (!props.isProvider && (!formData.value.services_needed || formData.value.services_needed.length === 0)) {
        formData.value.services_needed = [reg];
    }
    if (props.isProvider) {
        const types = formData.value.services?.types || [];
        if (!types.includes(reg)) {
            formData.value.services.types = [...types, reg];
        }
    }
});

const saving = ref(false);

const completeOnboarding = async () => {
    saving.value = true;
    const payload = JSON.parse(JSON.stringify(formData.value));
    if (!props.isProvider) {
        payload.languages = [payload.preferred_language || 'en'];
    }

    router.post(route('onboarding.complete'), payload, {
        onFinish: () => {
            saving.value = false;
        },
    });
};

const userServiceType = computed({
    get: () => formData.value.services_needed?.[0] || '',
    set: (value) => {
        formData.value.services_needed = value ? [value] : [];
    },
});

const pricingModels = [
    { value: 'hourly', label: 'Hourly rate' },
    { value: 'flat_rate', label: 'Flat rate' },
    { value: 'consultation', label: 'Consultation-based' },
    { value: 'custom', label: 'Custom' },
];

const goToStepFive = () => {
    currentStep.value = 5;
};

const goToStepThree = () => {
    currentStep.value = 3;
};

const goBack = () => {
    if (!props.isProvider && currentStep.value === 3) {
        currentStep.value = 2;
        return;
    }

    if (props.isProvider && currentStep.value === 5) {
        currentStep.value = 4;
        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(route('address-detail.otp'));
};
</script>

<template>
    <Head title="Onboarding" />

    <GuestLayout>
        <template #title>{{ onboardingHeading }}</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="currentStep" :total-steps="totalSteps" />
        </template>
        <template #side-image>
            <div class="relative h-full w-full overflow-hidden">
                <img
                    src="https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=1400&q=80"
                    alt="Onboarding support"
                    class="h-full w-full object-cover"
                />
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent" />
                <div class="pointer-events-none absolute bottom-0 left-0 right-0 p-6 text-white">
                    <p class="mb-2 inline-flex rounded-full bg-emerald-500/70 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide">
                        Immigration Support
                    </p>
                    <h3 class="text-2xl font-bold leading-tight">Finish your setup in minutes.</h3>
                    <p class="mt-2 text-sm text-white/90">
                        Add your details and continue to your dashboard.
                    </p>
                </div>
            </div>
        </template>

        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm sm:p-5">
            <div v-if="isUserStepTwo" class="space-y-2.5">
                <Select
                    v-model="userServiceType"
                    :options="serviceTypes"
                    label="Select service type"
                    placeholder="e.g. Attorney, Tutor, Accountant"
                    size="compact"
                    required
                />
                <Input
                    v-model="formData.city"
                    label="Location"
                    placeholder="Enter city/location"
                    size="compact"
                    required
                />
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <Select
                        v-model="formData.country"
                        :options="countryOptions"
                        label="Country"
                        placeholder="Select country"
                        size="compact"
                        required
                    />
                    <Select
                        v-model="formData.preferred_language"
                        :options="languageOptions"
                        label="Language preference"
                        placeholder="Select language"
                        size="compact"
                        required
                    />
                </div>
            </div>
            <div v-else-if="isUserStepThree" class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
                <h2 class="text-base font-semibold text-emerald-800">Congratulations!</h2>
                <p class="text-sm text-emerald-700">
                    Your preferences are saved. Click the button below to complete setup.
                </p>
            </div>

            <div v-else-if="isBusinessStep" class="space-y-2.5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Business</h2>
                <Input
                    v-model="formData.business.business_name"
                    label="Business name"
                    placeholder="Your practice or company name"
                    size="compact"
                />
                <Input
                    v-model="formData.business.tagline"
                    label="Tagline"
                    placeholder="Short line that appears in search"
                    size="compact"
                />
                <div>
                    <label class="label">Bio</label>
                    <textarea
                        v-model="formData.business.bio"
                        rows="3"
                        class="input py-2.5 text-sm"
                        placeholder="Experience, credentials, and how you help clients..."
                    />
                </div>
                <Input
                    v-model="formData.business.years_experience"
                    type="number"
                    label="Years of experience"
                    placeholder="e.g. 5"
                    size="compact"
                />
            </div>

            <div v-else class="space-y-3.5">
                <div>
                        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-neutral-500">Pricing</h2>
                        <label class="label">Pricing model</label>
                        <select v-model="formData.pricing.model" class="input w-full py-2.5 text-sm">
                            <option v-for="m in pricingModels" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>
                </div>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <Input
                        v-if="formData.pricing.model === 'hourly'"
                        v-model="formData.pricing.hourly_rate"
                        type="number"
                        label="Hourly rate (USD)"
                        placeholder="150"
                        size="compact"
                    />
                    <Input
                        v-model="formData.pricing.consultation_fee"
                        type="number"
                        label="Consultation fee (USD)"
                        placeholder="Optional"
                        size="compact"
                    />
                </div>
                <label class="flex cursor-pointer items-center gap-2">
                    <input
                        type="checkbox"
                        v-model="formData.pricing.free_consultation"
                        class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                    />
                    <span class="text-sm text-neutral-700">Offer free initial consultations</span>
                </label>
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Service Area</h2>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="formData['service-area'].in_person"
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">In-person services</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="formData['service-area'].remote"
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">Remote / virtual services</span>
                        </label>
                    </div>
                    <Input
                        v-if="formData['service-area'].in_person"
                        v-model="formData['service-area'].radius"
                        type="number"
                        label="Service radius (miles)"
                        placeholder="25"
                        size="compact"
                    />
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3 border-t border-neutral-100 pt-4">
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
                <Button v-if="isBusinessStep" variant="primary" size="sm" class="!py-1.5 !text-xs !rounded-md" @click="goToStepFive">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else-if="isUserStepTwo" variant="primary" size="sm" class="!py-1.5 !text-xs !rounded-md" @click="goToStepThree">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else variant="primary" size="sm" class="!py-1.5 !text-xs" :loading="saving" @click="completeOnboarding">
                    Finish setup
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </GuestLayout>
    </template>
