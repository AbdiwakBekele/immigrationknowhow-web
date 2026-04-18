<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import { CheckIcon } from '@heroicons/vue/24/solid';
import { SparklesIcon, CreditCardIcon } from '@heroicons/vue/24/outline';
import LocationCountryStatePick from '@/Components/address/LocationCountryStatePick.vue';
import OnboardingPhoneVerification from '@/Components/onboarding/OnboardingPhoneVerification.vue';
import { parsePlaceToAddressFields } from '@/utils/googlePlaceAddress';
import { SIGNUP_FLOW_STEPS_PROVIDER, SIGNUP_FLOW_STEPS_USER } from '@/constants/authFlowProgress';

const props = defineProps({
    user: Object,
    initialStep: Number,
    requiresPhoneVerification: { type: Boolean, default: false },
    phoneVerification: { type: Object, default: null },
    isProvider: Boolean,
    serviceTypes: Array,
    countryOptions: Array,
    stateOptions: { type: Array, default: () => [] },
    languageOptions: Array,
    existingData: Object,
    subscriptionPlans: Array,
    /** True when STRIPE_SECRET is set — paid plans can use Checkout (dashboard Price or dynamic price_data). */
    stripeBillingReady: { type: Boolean, default: false },
});

const SELECT_SERVICE_LATER_VALUE = '__select_service_later__';

const currentStep = ref(props.requiresPhoneVerification ? 3 : (props.initialStep ?? 4));

const pageTitle = computed(() => (props.requiresPhoneVerification ? 'Phone verification' : 'Onboarding'));
const totalSteps = computed(() => (props.isProvider ? SIGNUP_FLOW_STEPS_PROVIDER : SIGNUP_FLOW_STEPS_USER));
const isProviderLocationStep = computed(() => props.isProvider && currentStep.value === 4);
const isProviderBusinessStep = computed(() => props.isProvider && currentStep.value === 5);
const isProviderPricingStep = computed(() => props.isProvider && currentStep.value === 6);
const isProviderSubscriptionStep = computed(() => props.isProvider && currentStep.value === 7);
const isUserPreferencesStep = computed(() => !props.isProvider && currentStep.value === 4);
const isUserCongratulationsStep = computed(() => !props.isProvider && currentStep.value === 5);
const hasSubscriptionPlans = computed(() => (props.subscriptionPlans || []).length > 0);
const selectedBillingCycle = ref('monthly');
const userServiceTypeOptions = computed(() => [
    { value: SELECT_SERVICE_LATER_VALUE, label: 'I will select one later on' },
    ...(props.serviceTypes || []),
]);
const hasCheckoutReadyPlans = computed(() =>
    (props.subscriptionPlans || []).some(
        (plan) => Number(plan.price_cents || 0) <= 0 || props.stripeBillingReady,
    ),
);

const onboardingHeading = computed(() => {
    if (props.requiresPhoneVerification) {
        return 'Phone verification';
    }
    if (isProviderLocationStep.value) {
        return 'Address details';
    }
    if (isProviderBusinessStep.value) {
        return 'Business information';
    }
    if (isProviderPricingStep.value) {
        return 'Pricing';
    }
    if (isProviderSubscriptionStep.value) {
        return 'Choose your subscription';
    }
    if (isUserCongratulationsStep.value) {
        return 'Congratulations';
    }
    return props.isProvider ? 'Business information' : 'Service preferences';
});

const formData = ref({
    services_needed: [],
    address_line_1: props.existingData?.location?.street || props.user?.address || '',
    address_line_2: props.existingData?.location?.address_line_2 || '',
    city: props.user?.city || '',
    state: props.user?.state || '',
    postal_code: props.user?.postal_code || '',
    county: props.existingData?.location?.county || '',
    location_label: props.existingData?.location?.label || '',
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
    profile: {
        has_children: false,
        children_ages: [],
        has_pets: false,
        pet_types: [],
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
    if (e.location?.state) {
        formData.value.state = e.location.state;
    }
    if (e.location?.postal_code) {
        formData.value.postal_code = e.location.postal_code;
    }
    if (e.location?.county) {
        formData.value.county = e.location.county;
    }
    if (e.location?.label) {
        formData.value.location_label = e.location.label;
    }
    if (e.location?.country) {
        formData.value.country = e.location.country;
    }
    if (e.location?.street) {
        formData.value.address_line_1 = e.location.street;
    }
    if (e.location?.address_line_2) {
        formData.value.address_line_2 = e.location.address_line_2;
    }
    if (props.isProvider && e.coverage_area && typeof e.coverage_area === 'object') {
        const cov = e.coverage_area;
        if (cov.country && !formData.value.country) {
            formData.value.country = cov.country;
        }
        if (cov.state && !formData.value.state) {
            formData.value.state = cov.state;
        }
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
    if (e.profile && typeof e.profile === 'object') {
        formData.value.profile = {
            ...formData.value.profile,
            has_children: Boolean(e.profile.has_children),
            children_ages: Array.isArray(e.profile.children_ages) ? e.profile.children_ages : [],
            has_pets: Boolean(e.profile.has_pets),
            pet_types: Array.isArray(e.profile.pet_types) ? e.profile.pet_types : [],
        };
    }
};

mergeExistingOnboarding();

const providerAddressMode = ref('autocomplete');
const providerAutocompleteStatus = ref('');
let providerAddressDebounce = null;
const providerAddressStateOptions = ref([...(props.stateOptions || [])]);

async function loadProviderAddressStates(countryCode) {
    const code = countryCode || 'US';
    try {
        const response = await fetch(route('locations.states', { country: code }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) {
            throw new Error(`State lookup failed (${response.status})`);
        }
        const payload = await response.json();
        providerAddressStateOptions.value = Array.isArray(payload.states) ? payload.states : [];
    } catch {
        providerAddressStateOptions.value = [];
    }
}

function onProviderAddressCountryChange() {
    formData.value.state = '';
    void loadProviderAddressStates(formData.value.country);
}

async function applyParsedPlaceToProviderAddress(parsed) {
    formData.value.address_line_1 = parsed.address_line_1;
    if (parsed.address_line_2) {
        formData.value.address_line_2 = parsed.address_line_2;
    }
    formData.value.city = parsed.city;
    formData.value.postal_code = parsed.postal_code;
    formData.value.country = parsed.country;
    await loadProviderAddressStates(parsed.country);
    formData.value.state = parsed.state;
}

async function runProviderAddressAutocomplete() {
    const query = String(formData.value.address_line_1 || '').trim();
    if (query.length < 3) {
        providerAutocompleteStatus.value = '';
        return;
    }
    try {
        const response = await fetch(route('address-detail.autocomplete', { query }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) {
            throw new Error(`Autocomplete failed (${response.status})`);
        }
        const payload = await response.json();
        if (!payload?.ok || !payload?.data) {
            providerAutocompleteStatus.value = '';
            return;
        }
        const parsed = parsePlaceToAddressFields(payload.data);
        await applyParsedPlaceToProviderAddress(parsed);
        providerAutocompleteStatus.value = '';
    } catch {
        providerAutocompleteStatus.value = 'Unable to autocomplete this address right now.';
    }
}

function onProviderAddressLineInput() {
    if (providerAddressMode.value !== 'autocomplete') {
        return;
    }
    if (providerAddressDebounce) {
        clearTimeout(providerAddressDebounce);
    }
    providerAddressDebounce = setTimeout(() => {
        void runProviderAddressAutocomplete();
    }, 300);
}

watch(
    currentStep,
    async (step) => {
        if (props.requiresPhoneVerification) {
            return;
        }
        if (props.isProvider && step === 4) {
            await loadProviderAddressStates(formData.value.country || 'US');
        }
    },
    { immediate: true },
);

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
const childAgeInput = ref('');
const petTypeInput = ref('');

const completeOnboarding = async () => {
    saving.value = true;
    const payload = JSON.parse(JSON.stringify(formData.value));
    if (!payload.profile.has_children) {
        payload.profile.children_ages = [];
    }
    if (!payload.profile.has_pets) {
        payload.profile.pet_types = [];
    }
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
    get: () => formData.value.services_needed?.[0] || SELECT_SERVICE_LATER_VALUE,
    set: (value) => {
        formData.value.services_needed = value && value !== SELECT_SERVICE_LATER_VALUE ? [value] : [];
    },
});

const pricingModels = [
    { value: 'hourly', label: 'Hourly rate' },
    { value: 'flat_rate', label: 'Flat rate' },
    { value: 'consultation', label: 'Consultation-based' },
    { value: 'custom', label: 'Custom' },
];

const goToProviderPricingStep = () => {
    currentStep.value = 6;
};

const goToProviderSubscriptionStep = () => {
    currentStep.value = 7;
};

const goToProviderBusinessStep = () => {
    currentStep.value = 5;
};

const goToUserCongratulationsStep = () => {
    currentStep.value = 5;
};

const addChildAge = () => {
    const age = Number(childAgeInput.value);
    if (!Number.isInteger(age) || age < 0 || age > 25) {
        return;
    }

    if (!formData.value.profile.children_ages.includes(age)) {
        formData.value.profile.children_ages.push(age);
    }

    childAgeInput.value = '';
};

const removeChildAge = (age) => {
    formData.value.profile.children_ages = formData.value.profile.children_ages.filter((item) => item !== age);
};

const addPetType = () => {
    const pet = petTypeInput.value.trim();
    if (!pet || formData.value.profile.pet_types.includes(pet)) {
        petTypeInput.value = '';
        return;
    }

    formData.value.profile.pet_types.push(pet);
    petTypeInput.value = '';
};

const removePetType = (pet) => {
    formData.value.profile.pet_types = formData.value.profile.pet_types.filter((item) => item !== pet);
};

const goBack = () => {
    if (props.requiresPhoneVerification) {
        router.visit(route('address-detail'));
        return;
    }

    if (!props.isProvider && currentStep.value === 5) {
        currentStep.value = 4;
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
    if (props.isProvider && currentStep.value === 7) {
        currentStep.value = 6;
        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(route('address-detail'));
};

const selectedPlan = computed(() => (props.subscriptionPlans || []).find((plan) => plan.uuid === formData.value.subscription.plan_uuid) || null);
const selectedPlanRequiresCheckout = computed(() => {
    if (!selectedPlan.value) return false;
    return Number(selectedPlan.value.price_cents || 0) > 0 && props.stripeBillingReady;
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

const planIsSelectable = (plan) => {
    if (Number(plan?.price_cents || 0) <= 0) return true;
    return props.stripeBillingReady;
};

const selectPlanAndContinue = async (plan) => {
    if (!planIsSelectable(plan) || saving.value) return;
    formData.value.subscription.plan_uuid = plan.uuid;
    await nextTick();
    completeOnboarding();
};

onMounted(() => {
    if (!props.isProvider) return;
    if (availableCycles.value.length > 0 && !availableCycles.value.includes(selectedBillingCycle.value)) {
        selectedBillingCycle.value = availableCycles.value[0];
    }
});
</script>

<template>
    <Head :title="pageTitle" />

    <GuestLayout>
        <template #title>{{ onboardingHeading }}</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="currentStep" :total-steps="totalSteps" />
        </template>
        <template #side-image>
            <div v-if="requiresPhoneVerification" class="relative h-full w-full overflow-hidden">
                <img
                    src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1400&q=80"
                    alt="Phone verification"
                    class="h-full w-full object-cover"
                />
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent" />
                <div class="pointer-events-none absolute bottom-0 left-0 right-0 p-6 text-white">
                    <p class="mb-2 inline-flex rounded-full bg-sky-500/70 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide">
                        Secure sign-up
                    </p>
                    <h3 class="text-2xl font-bold leading-tight">Verify your mobile number</h3>
                    <p class="mt-2 text-sm text-white/90">We will text you a code to protect your account.</p>
                </div>
            </div>
            <div v-else class="relative h-full w-full overflow-hidden">
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

        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-7">
            <OnboardingPhoneVerification
                v-if="requiresPhoneVerification && phoneVerification"
                :phone="phoneVerification.phone"
                :phone-dial-options="phoneVerification.phoneDialOptions"
            />
            <template v-else>
            <div v-if="isUserPreferencesStep || isProviderLocationStep" class="space-y-5">
                <Select
                    v-if="isUserPreferencesStep"
                    v-model="userServiceType"
                    :options="userServiceTypeOptions"
                    label="Select service type"
                    placeholder="Choose a service now or later"
                    size="auth"
                />

                <div v-if="isProviderLocationStep" class="space-y-5">
                    <p class="text-sm text-neutral-600">
                        Enter your business street address. Search with Google Places or fill the fields manually.
                    </p>
                    <fieldset class="space-y-2">
                        <legend class="mb-2 text-sm font-medium text-slate-700">How would you like to enter your street address?</legend>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-800">
                                <input
                                    v-model="providerAddressMode"
                                    type="radio"
                                    value="autocomplete"
                                    class="h-4 w-4 border-neutral-300 text-primary-600"
                                />
                                Search
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-800">
                                <input
                                    v-model="providerAddressMode"
                                    type="radio"
                                    value="manual"
                                    class="h-4 w-4 border-neutral-300 text-primary-600"
                                />
                                Manual entry
                            </label>
                        </div>
                    </fieldset>

                    <div v-if="providerAddressMode === 'autocomplete'">
                        <label for="provider-street-autocomplete" class="mb-3 block text-sm font-medium text-slate-700">
                            Street address search
                        </label>
                        <input
                            id="provider-street-autocomplete"
                            v-model="formData.address_line_1"
                            type="text"
                            autocomplete="street-address"
                            placeholder="Start typing your address"
                            class="w-full rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-base text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            @input="onProviderAddressLineInput"
                            @change="onProviderAddressLineInput"
                        >
                        <p v-if="providerAutocompleteStatus" class="mt-2 text-sm text-slate-500">{{ providerAutocompleteStatus }}</p>
                        <p class="mt-2 text-xs text-slate-500">
                            We will fill city, state, ZIP, and country from the best matching place. You can edit them below.
                        </p>
                    </div>

                    <template v-else>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Input
                                v-model="formData.address_line_1"
                                label="Address line 1"
                                placeholder="Street address, P.O. box"
                                size="compact"
                                required
                            />
                            <Input
                                v-model="formData.address_line_2"
                                label="Address line 2 (optional)"
                                placeholder="Apt, suite, unit, building"
                                size="compact"
                            />
                        </div>
                    </template>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="formData.city"
                            label="City"
                            placeholder="City"
                            size="compact"
                            required
                        />
                        <Select
                            v-if="providerAddressStateOptions.length > 0"
                            v-model="formData.state"
                            :options="providerAddressStateOptions"
                            label="State"
                            placeholder="Select state"
                            size="auth"
                            required
                        />
                        <Input
                            v-else
                            v-model="formData.state"
                            label="State / region"
                            placeholder="State or region"
                            size="compact"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="formData.postal_code"
                            label="ZIP / postal code"
                            placeholder="Postal code"
                            size="compact"
                            required
                        />
                        <Select
                            v-model="formData.country"
                            :options="countryOptions"
                            label="Country"
                            placeholder="Select country"
                            size="auth"
                            required
                            @update:model-value="onProviderAddressCountryChange"
                        />
                    </div>
                </div>

                <LocationCountryStatePick
                    v-if="isUserPreferencesStep"
                    v-model:country="formData.country"
                    v-model:state="formData.state"
                    v-model:city="formData.city"
                    v-model:postal-code="formData.postal_code"
                    v-model:county="formData.county"
                    v-model:location-label="formData.location_label"
                    :country-options="countryOptions"
                    :initial-state-options="stateOptions"
                />

                <template v-if="isUserPreferencesStep">
                <Select
                    v-model="formData.preferred_language"
                    :options="languageOptions"
                    label="Language preference"
                    placeholder="Select language"
                    size="auth"
                    required
                />

                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-white bg-white p-4 shadow-sm">
                            <input
                                v-model="formData.profile.has_children"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">I have children</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Ages help us match family support like tutoring or child care.</span>
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-white bg-white p-4 shadow-sm">
                            <input
                                v-model="formData.profile.has_pets"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">I have pets</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Pets help us recommend providers like pet sitters and pet care.</span>
                            </span>
                        </label>
                    </div>

                    <div v-if="formData.profile.has_children" class="mt-4">
                        <label class="mb-2 block text-sm font-medium text-slate-700">Children ages</label>
                        <div class="flex gap-2">
                            <input
                                v-model="childAgeInput"
                                type="number"
                                min="0"
                                max="25"
                                class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                placeholder="Add age"
                                @keydown.enter.prevent="addChildAge"
                            >
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                @click="addChildAge"
                            >
                                Add
                            </button>
                        </div>
                        <div v-if="formData.profile.children_ages.length" class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="age in formData.profile.children_ages"
                                :key="`child-age-${age}`"
                                type="button"
                                class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700"
                                @click="removeChildAge(age)"
                            >
                                Age {{ age }} x
                            </button>
                        </div>
                    </div>

                    <div v-if="formData.profile.has_pets" class="mt-4">
                        <label class="mb-2 block text-sm font-medium text-slate-700">Pet types</label>
                        <div class="flex gap-2">
                            <input
                                v-model="petTypeInput"
                                type="text"
                                class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                placeholder="Dog, cat, bird..."
                                @keydown.enter.prevent="addPetType"
                            >
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                @click="addPetType"
                            >
                                Add
                            </button>
                        </div>
                        <div v-if="formData.profile.pet_types.length" class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="pet in formData.profile.pet_types"
                                :key="`pet-${pet}`"
                                type="button"
                                class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700"
                                @click="removePetType(pet)"
                            >
                                {{ pet }} x
                            </button>
                        </div>
                    </div>
                </div>
                </template>
            </div>
            <div v-else-if="isUserCongratulationsStep" class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
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
            </div>
            <div v-else-if="isProviderSubscriptionStep" class="space-y-6">
                <div
                    class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-sky-50/40 px-4 py-8 sm:px-8"
                >
                    <div
                        class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-sky-400/15 blur-3xl"
                    />
                    <div
                        class="pointer-events-none absolute -bottom-20 -left-12 h-40 w-40 rounded-full bg-violet-400/10 blur-3xl"
                    />
                    <div class="relative text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600/90">Billing</p>
                        <h2 class="mt-2 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            Choose your plan
                        </h2>
                        <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">
                            Pick the program that fits your practice. Paid plans open a secure Stripe checkout; free plans
                            activate instantly.
                        </p>
                    </div>

                    <div v-if="availableCycles.length > 0" class="relative mt-8 flex justify-center">
                        <div
                            class="inline-flex rounded-full border border-slate-200/90 bg-white/90 p-1 shadow-sm backdrop-blur-sm"
                            role="tablist"
                            aria-label="Billing cycle"
                        >
                            <button
                                v-for="cycle in availableCycles"
                                :key="cycle"
                                type="button"
                                role="tab"
                                :aria-selected="selectedBillingCycle === cycle"
                                :class="[
                                    'rounded-full px-5 py-2 text-sm font-semibold transition-all duration-200',
                                    selectedBillingCycle === cycle
                                        ? 'bg-gradient-to-r from-sky-600 to-indigo-600 text-white shadow-md shadow-sky-500/25'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                                ]"
                                @click="selectedBillingCycle = cycle"
                            >
                                {{ cycle.charAt(0).toUpperCase() + cycle.slice(1) }}
                            </button>
                        </div>
                    </div>

                    <div v-if="hasSubscriptionPlans" class="relative mt-8 grid gap-5 sm:grid-cols-2">
                        <div
                            v-for="plan in filteredPlans"
                            :key="plan.uuid"
                            role="group"
                            :aria-label="plan.name"
                            :class="[
                                'relative flex flex-col rounded-2xl border bg-white/90 p-6 shadow-sm backdrop-blur-sm transition-all duration-300',
                                formData.subscription.plan_uuid === plan.uuid
                                    ? 'border-sky-400 ring-2 ring-sky-300/60 shadow-lg shadow-sky-500/10'
                                    : 'border-slate-200/90 hover:border-sky-200 hover:shadow-md',
                                !planIsSelectable(plan) ? 'opacity-75' : '',
                            ]"
                        >
                            <div
                                v-if="plan.is_featured"
                                class="absolute inset-x-0 top-0 h-1 rounded-t-2xl bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500"
                            />
                            <div
                                class="flex items-start justify-between gap-3"
                                :class="plan.is_featured ? 'pt-2' : ''"
                            >
                                <div>
                                    <h3 class="font-display text-lg font-bold text-slate-900">{{ plan.name }}</h3>
                                    <p v-if="plan.description" class="mt-1 text-sm leading-relaxed text-slate-600">
                                        {{ plan.description }}
                                    </p>
                                </div>
                                <span
                                    v-if="plan.is_featured"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-sky-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-sky-800"
                                >
                                    <SparklesIcon class="h-3.5 w-3.5" />
                                    Popular
                                </span>
                            </div>

                            <div class="mt-5 flex items-baseline gap-1 border-b border-slate-100 pb-5">
                                <template v-if="Number(plan.price_cents || 0) <= 0">
                                    <span class="font-display text-4xl font-bold text-emerald-600">Free</span>
                                    <span class="text-sm font-medium text-slate-500">forever</span>
                                </template>
                                <template v-else>
                                    <span class="font-display text-4xl font-bold text-sky-600">
                                        ${{ (plan.price_cents / 100).toFixed(0) }}
                                    </span>
                                    <span class="text-sm text-slate-500">
                                        / {{ planCycleLabel(plan.billing_cycle) }}
                                    </span>
                                </template>
                            </div>

                            <ul class="mt-4 flex-1 space-y-2.5">
                                <li
                                    v-for="(feature, index) in plan.features || []"
                                    :key="`${plan.uuid}-${index}`"
                                    class="flex items-start gap-2.5 text-sm text-slate-700"
                                >
                                    <span
                                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700"
                                    >
                                        <CheckIcon class="h-3 w-3" />
                                    </span>
                                    <span>{{ feature }}</span>
                                </li>
                                <li
                                    v-if="!(plan.features && plan.features.length)"
                                    class="text-sm italic text-slate-400"
                                >
                                    All core provider features included.
                                </li>
                            </ul>

                            <p
                                v-if="Number(plan.price_cents || 0) > 0 && !props.stripeBillingReady"
                                class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 ring-1 ring-amber-200/80"
                            >
                                Add your Stripe secret key in <code class="rounded bg-amber-100/80 px-1">.env</code> to
                                enable card checkout for paid plans.
                            </p>
                            <p
                                v-else-if="Number(plan.price_cents || 0) > 0 && props.stripeBillingReady"
                                class="mt-4 flex items-center gap-2 text-xs font-medium text-slate-500"
                            >
                                <CreditCardIcon class="h-4 w-4 text-sky-600" />
                                Secure checkout powered by Stripe
                            </p>

                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    :disabled="!planIsSelectable(plan) || saving"
                                    :class="[
                                        'inline-flex flex-1 min-w-[8rem] items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2',
                                        planIsSelectable(plan) && !saving
                                            ? 'bg-gradient-to-r from-sky-600 to-indigo-600 text-white shadow-md shadow-sky-500/25 hover:from-sky-500 hover:to-indigo-500'
                                            : 'cursor-not-allowed bg-slate-200 text-slate-500',
                                    ]"
                                    @click="selectPlanAndContinue(plan)"
                                >
                                    <template v-if="Number(plan.price_cents || 0) <= 0">
                                        Start free
                                    </template>
                                    <template v-else>
                                        Subscribe with Stripe
                                    </template>
                                    <ArrowRightIcon class="h-4 w-4 opacity-90" />
                                </button>
                                <button
                                    type="button"
                                    :disabled="!planIsSelectable(plan)"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="planIsSelectable(plan) ? (formData.subscription.plan_uuid = plan.uuid) : null"
                                >
                                    Select
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="relative mt-8 rounded-2xl border border-amber-200/90 bg-amber-50/90 px-4 py-4 text-center text-sm text-amber-900"
                    >
                        Plans are not available yet. Finish setup and subscribe later from your provider dashboard.
                    </div>
                </div>

                <p v-if="hasCheckoutReadyPlans && !formData.subscription.plan_uuid" class="text-center text-xs text-rose-600">
                    Choose a plan above, or use <span class="font-semibold">Select</span> then <span class="font-semibold">Finish setup</span>.
                </p>
                <div
                    v-if="selectedPlan"
                    class="flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900"
                >
                    <CheckIcon class="h-5 w-5 shrink-0 text-emerald-600" />
                    <span>
                        Selected:
                        <span class="font-semibold">{{ selectedPlan.name }}</span>
                        — use <span class="font-semibold">Finish setup</span> below if you prefer not to pay yet.
                    </span>
                </div>
            </div>
            <div v-else class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
                <h2 class="text-base font-semibold text-emerald-800">Congratulations!</h2>
                <p class="text-sm text-emerald-700">
                    Your preferences are saved. Click the button below to complete setup.
                </p>
            </div>

            <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
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
                <Button v-if="isProviderLocationStep" variant="primary" size="lg" class="min-w-[11rem]" @click="goToProviderBusinessStep">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else-if="isProviderBusinessStep" variant="primary" size="lg" class="min-w-[11rem]" @click="goToProviderPricingStep">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else-if="isProviderPricingStep" variant="primary" size="lg" class="min-w-[11rem]" @click="goToProviderSubscriptionStep">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else-if="isUserPreferencesStep" variant="primary" size="lg" class="min-w-[11rem]" @click="goToUserCongratulationsStep">
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else variant="primary" size="lg" class="min-w-[11rem]" :loading="saving" :disabled="!canFinishProviderOnboarding" @click="completeOnboarding">
                    {{ finishButtonLabel }}
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
            </div>
            </template>
        </div>
    </GuestLayout>
    </template>
