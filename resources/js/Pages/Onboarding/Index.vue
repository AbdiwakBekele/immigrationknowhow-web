<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import { CheckIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    user: Object,
    initialStep: Number,
    isProvider: Boolean,
    serviceTypes: Array,
    countryOptions: Array,
    languageOptions: Array,
    existingData: Object,
    subscriptionPlans: Array,
});

const currentStep = ref(props.initialStep ?? (props.isProvider ? 4 : 2));
const totalSteps = computed(() => (props.isProvider ? 6 : 3));
const isProviderBusinessStep = computed(() => props.isProvider && currentStep.value === 4);
const isProviderPricingStep = computed(() => props.isProvider && currentStep.value === 5);
const isProviderSubscriptionStep = computed(() => props.isProvider && currentStep.value === 6);
const isUserStepTwo = computed(() => !props.isProvider && currentStep.value === 2);
const isUserStepThree = computed(() => !props.isProvider && currentStep.value === 3);
const hasSubscriptionPlans = computed(() => (props.subscriptionPlans || []).length > 0);
const selectedBillingCycle = ref('monthly');
const hasCheckoutReadyPlans = computed(() => (props.subscriptionPlans || []).some((plan) => !!plan.stripe_price_id || Number(plan.price_cents || 0) <= 0));

const onboardingHeading = computed(() => {
    if (isProviderBusinessStep.value) {
        return 'Business information';
    }
    if (isProviderPricingStep.value) {
        return 'Pricing';
    }
    if (isProviderSubscriptionStep.value) {
        return 'Choose your subscription';
    }
    if (isUserStepThree.value) {
        return 'Congratulations';
    }
    return props.isProvider ? 'Business information' : 'Service preferences';
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
        license_number: '',
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
    subscription: {
        plan_uuid: '',
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
    if (e.subscription && typeof e.subscription === 'object') {
        formData.value.subscription = { ...formData.value.subscription, ...e.subscription };
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

const goToProviderPricingStep = () => {
    currentStep.value = 5;
};

const goToProviderSubscriptionStep = () => {
    currentStep.value = 6;
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
    if (props.isProvider && currentStep.value === 6) {
        currentStep.value = 5;
        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(route('address-detail.otp'));
};

const selectedPlan = computed(() => (props.subscriptionPlans || []).find((plan) => plan.uuid === formData.value.subscription.plan_uuid) || null);
const selectedPlanRequiresCheckout = computed(() => {
    if (!selectedPlan.value) return false;
    return Number(selectedPlan.value.price_cents || 0) > 0 && !!selectedPlan.value.stripe_price_id;
});
const finishButtonLabel = computed(() => {
    if (selectedPlanRequiresCheckout.value) {
        return 'Subscribe & continue';
    }
    return 'Finish setup';
});
const canFinishProviderOnboarding = computed(() => {
    if (!props.isProvider || !isProviderSubscriptionStep.value) return true;
    if (!hasCheckoutReadyPlans.value) return true;
    return !!formData.value.subscription.plan_uuid;
});

const planCycleLabel = (cycle) => {
    if (cycle === 'monthly') return 'Month';
    if (cycle === 'yearly') return 'Year';
    if (cycle === 'quarterly') return 'Quarter';
    return cycle;
};

const availableCycles = computed(() => {
    const cycles = [...new Set((props.subscriptionPlans || []).map((plan) => plan.billing_cycle))];
    const preferredOrder = ['monthly', 'yearly', 'quarterly'];
    return preferredOrder.filter((cycle) => cycles.includes(cycle));
});

const filteredPlans = computed(() => {
    if (!hasSubscriptionPlans.value) return [];
    const plansInCycle = (props.subscriptionPlans || []).filter((plan) => plan.billing_cycle === selectedBillingCycle.value);
    if (plansInCycle.length > 0) return plansInCycle;
    return props.subscriptionPlans || [];
});

const planIsSelectable = (plan) => !!plan?.stripe_price_id || Number(plan?.price_cents || 0) <= 0;

onMounted(() => {
    if (!props.isProvider) return;
    if (availableCycles.value.length > 0 && !availableCycles.value.includes(selectedBillingCycle.value)) {
        selectedBillingCycle.value = availableCycles.value[0];
    }
});
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

            <div v-else-if="isProviderBusinessStep" class="space-y-2.5">
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
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <Input
                        v-model="formData.business.license_number"
                        label="License number"
                        placeholder="Enter license number"
                        size="compact"
                    />
                    <Input
                        v-model="formData.business.years_experience"
                        type="number"
                        label="Years of experience"
                        placeholder="e.g. 5"
                        size="compact"
                    />
                </div>
            </div>

            <div v-else-if="isProviderPricingStep" class="space-y-3.5">
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
            <div v-else-if="isProviderSubscriptionStep" class="space-y-4">
                <div class="p-2 sm:p-3">
                    <div class="text-center">
                        <h2 class="text-2xl font-semibold text-slate-900 sm:text-3xl">Choose Your Plan</h2>
                        <p class="mt-1 text-sm text-slate-500">Choose the right program for your business</p>
                    </div>

                    <div v-if="availableCycles.length > 0" class="mt-5 flex justify-center">
                        <div class="inline-flex rounded-full border border-slate-200 bg-white p-1 shadow-sm">
                            <button
                                v-for="cycle in availableCycles"
                                :key="cycle"
                                type="button"
                                :class="[
                                    'rounded-full px-5 py-1.5 text-sm font-medium transition',
                                    selectedBillingCycle === cycle
                                        ? 'bg-gradient-to-r from-sky-600 to-blue-500 text-white shadow'
                                        : 'text-slate-600 hover:text-slate-900',
                                ]"
                                @click="selectedBillingCycle = cycle"
                            >
                                {{ cycle.charAt(0).toUpperCase() + cycle.slice(1) }}
                            </button>
                        </div>
                    </div>

                    <div v-if="hasSubscriptionPlans" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <button
                            v-for="plan in filteredPlans"
                            :key="plan.uuid"
                            type="button"
                            :disabled="!planIsSelectable(plan)"
                            :class="[
                                'relative overflow-hidden rounded-2xl border bg-white p-5 text-left shadow-sm transition-all',
                                !planIsSelectable(plan)
                                    ? 'cursor-not-allowed opacity-60'
                                    : '',
                                formData.subscription.plan_uuid === plan.uuid
                                    ? 'border-sky-400 ring-2 ring-sky-200 shadow-md'
                                    : 'border-slate-200 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md',
                            ]"
                            @click="planIsSelectable(plan) ? (formData.subscription.plan_uuid = plan.uuid) : null"
                        >
                            <div
                                v-if="plan.is_featured"
                                class="absolute left-0 right-0 top-0 h-1.5 bg-gradient-to-r from-sky-500 to-blue-500"
                            ></div>
                            <div class="pt-2">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-xl font-semibold text-slate-900">{{ plan.name }}</h3>
                                    <span
                                        v-if="plan.is_featured"
                                        class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700"
                                    >
                                        Most Popular
                                    </span>
                                </div>
                                <div class="mt-3 flex items-end gap-1">
                                    <span class="text-4xl font-bold text-sky-600">${{ (plan.price_cents / 100).toFixed(0) }}</span>
                                    <span class="pb-1 text-sm text-slate-400">/ {{ planCycleLabel(plan.billing_cycle) }}</span>
                                </div>
                                <p v-if="plan.description" class="mt-2 text-sm text-slate-500">{{ plan.description }}</p>
                                <p v-if="!planIsSelectable(plan)" class="mt-2 text-xs font-medium text-amber-700">
                                    Checkout setup pending for this plan.
                                </p>
                                <p v-else-if="Number(plan.price_cents || 0) <= 0" class="mt-2 text-xs font-medium text-emerald-700">
                                    Starts on free tier.
                                </p>
                                <ul class="mt-4 space-y-2">
                                    <li
                                        v-for="(feature, index) in (plan.features || [])"
                                        :key="`${plan.uuid}-${index}`"
                                        class="flex items-start gap-2 text-sm text-slate-600"
                                    >
                                        <CheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-sky-500" />
                                        <span>{{ feature }}</span>
                                    </li>
                                </ul>
                            </div>
                        </button>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        Plans are not available yet. You can finish onboarding and subscribe later from your dashboard.
                    </div>
                </div>

                <p v-if="hasCheckoutReadyPlans && !formData.subscription.plan_uuid" class="text-xs text-rose-600">
                    Please select a plan to continue.
                </p>
                <div v-if="selectedPlan" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                    Selected: <span class="font-semibold">{{ selectedPlan.name }}</span>
                </div>
            </div>
            <div v-else class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
                <h2 class="text-base font-semibold text-emerald-800">Congratulations!</h2>
                <p class="text-sm text-emerald-700">
                    Your preferences are saved. Click the button below to complete setup.
                </p>
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
                <Button v-if="isProviderBusinessStep" variant="primary" size="sm" class="!py-1.5 !text-xs !rounded-md" @click="goToProviderPricingStep">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else-if="isProviderPricingStep" variant="primary" size="sm" class="!py-1.5 !text-xs !rounded-md" @click="goToProviderSubscriptionStep">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else-if="isUserStepTwo" variant="primary" size="sm" class="!py-1.5 !text-xs !rounded-md" @click="goToStepThree">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else variant="primary" size="sm" class="!py-1.5 !text-xs" :loading="saving" :disabled="!canFinishProviderOnboarding" @click="completeOnboarding">
                    {{ finishButtonLabel }}
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </GuestLayout>
    </template>
