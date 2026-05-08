<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import ProviderPhoneVerification from '@/Components/onboarding/ProviderPhoneVerification.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import { SIGNUP_FLOW_STEPS_PROVIDER } from '@/constants/authFlowProgress';

const props = defineProps({
    user: { type: Object, required: true },
    initialStep: { type: Number, default: 2 },
    requiresPhoneVerification: { type: Boolean, default: false },
    phoneVerification: { type: Object, default: null },
    isProvider: { type: Boolean, default: true },
    stateOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, default: () => [] },
    existingData: { type: Object, default: () => ({}) },
    subscriptionPlans: { type: Array, default: () => [] },
    stripeBillingReady: { type: Boolean, default: false },
    serviceTypes: { type: Array, default: () => [] },
    countryOptions: { type: Array, default: () => [] },
});

const draftStorageKey = computed(() => `ikh_onboarding_provider_draft:${props.user?.id ?? 'guest'}`);

const normalizeStep = (value, fallback) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : fallback;
};

const currentStep = ref(normalizeStep(props.initialStep, props.requiresPhoneVerification ? 3 : 4));
const totalSteps = computed(() => SIGNUP_FLOW_STEPS_PROVIDER);
const pageTitle = computed(() => (
    props.requiresPhoneVerification && currentStep.value === 3
        ? 'Phone verification'
        : 'Provider onboarding'
));

const isCoverageStep = computed(() => currentStep.value === 2);
const isPhoneStep = computed(() => props.requiresPhoneVerification && currentStep.value === 3);
const isProviderLocationStep = computed(() => currentStep.value === 4);
const isProviderBusinessStep = computed(() => currentStep.value === 5);
const isProviderPricingStep = computed(() => currentStep.value === 6);
const isProviderSubscriptionStep = computed(() => currentStep.value === 7);

const providerServiceLocationOptions = [
    { value: 'usa', label: 'USA' },
    { value: 'uk', label: 'UK' },
    { value: 'europe', label: 'Europe' },
    { value: 'canada', label: 'Canada' },
    { value: 'other', label: 'Other' },
];
const serviceLocationWithStates = ['usa'];
const coverageCountryToIso = {
    usa: 'US',
    canada: 'CA',
    uk: 'GB',
};
const coverageStateOptions = ref([...(props.stateOptions || [])]);

const loadCoverageStateOptions = async (coverageCountry) => {
    const iso = coverageCountryToIso[String(coverageCountry || '').toLowerCase()] || null;
    if (!iso) {
        coverageStateOptions.value = [];
        return;
    }

    try {
        const response = await fetch(route('locations.states', { country: iso }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) {
            throw new Error(`State lookup failed (${response.status})`);
        }
        const payload = await response.json();
        coverageStateOptions.value = Array.isArray(payload.states) ? payload.states : [];
    } catch {
        coverageStateOptions.value = [];
    }
};

const coverageForm = ref({
    serve_client_in_location: props.existingData?.['service-area']?.serve_client_in_location ?? false,
    coverage_country: props.existingData?.coverage_area?.country ?? 'usa',
    coverage_state: props.existingData?.coverage_area?.state ?? props.user?.state ?? '',
    coverage_postal_code: props.existingData?.coverage_area?.postal_code ?? '',
    service_area: {
        remote: props.existingData?.['service-area']?.remote ?? false,
        in_person: props.existingData?.['service-area']?.in_person ?? true,
        radius: props.existingData?.['service-area']?.radius ?? null,
        areas: props.existingData?.['service-area']?.areas ?? [],
    },
    preferred_language: props.user?.preferred_language ?? 'en',
});

const loadDraft = () => {
    try {
        const raw = sessionStorage.getItem(draftStorageKey.value);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object' && parsed.coverageForm) {
            coverageForm.value = { ...coverageForm.value, ...parsed.coverageForm };
        }
    } catch {
        // ignore corrupt draft
    }
};

const saveDraft = () => {
    try {
        sessionStorage.setItem(
            draftStorageKey.value,
            JSON.stringify({
                coverageForm: coverageForm.value,
            }),
        );
    } catch {
        // ignore quota / private mode
    }
};

onMounted(() => {
    loadDraft();
    void loadCoverageStateOptions(coverageForm.value.coverage_country);
});

watch(coverageForm, () => saveDraft(), { deep: true });

watch(
    () => coverageForm.value.coverage_country,
    async (next, prev) => {
        if (next === prev) return;
        // Reset state/city input when switching regions so we don't keep mismatched values.
        coverageForm.value.coverage_state = '';
        coverageForm.value.coverage_postal_code = '';
        await loadCoverageStateOptions(next);
    },
);

// Provider OTP verification posts use preserveState; keep step synced with server redirects.
watch(
    () => [props.initialStep, props.requiresPhoneVerification],
    ([nextInitialStep, nextRequiresPhone]) => {
        const next = normalizeStep(nextInitialStep, nextRequiresPhone ? 3 : 4);
        currentStep.value = next;
    },
    { immediate: true },
);

const submittingCoverage = ref(false);
const coverageError = ref('');

const submitCoverageStep = () => {
    coverageError.value = '';
    submittingCoverage.value = true;

    router.post(route('address-detail.send'), coverageForm.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            try {
                sessionStorage.removeItem(draftStorageKey.value);
            } catch {
                // ignore
            }
        },
        onError: () => {
            coverageError.value = 'Please review your coverage area and try again.';
        },
        onFinish: () => {
            submittingCoverage.value = false;
        },
    });
};

const providerLocationForm = ref({
    address_line_1: props.existingData?.location?.street ?? props.user?.address ?? '',
    address_line_2: props.existingData?.location?.address_line_2 ?? '',
    city: props.existingData?.location?.city ?? props.user?.city ?? '',
    state: props.existingData?.location?.state ?? props.user?.state ?? '',
    postal_code: props.existingData?.location?.postal_code ?? props.user?.postal_code ?? '',
    country: props.existingData?.location?.country ?? props.user?.country ?? 'US',
    county: props.existingData?.location?.county ?? '',
    location_label: props.existingData?.location?.label ?? '',
});

const submittingProviderLocation = ref(false);
const providerLocationError = ref('');

const submitProviderLocationStep = () => {
    providerLocationError.value = '';

    if (!providerLocationForm.value.address_line_1) {
        providerLocationError.value = 'Please enter your business street address to continue.';
        return;
    }
    if (!providerLocationForm.value.city || !providerLocationForm.value.state || !providerLocationForm.value.country) {
        providerLocationError.value = 'Please complete city, state, and country to continue.';
        return;
    }
    if (String(providerLocationForm.value.country).toUpperCase() === 'US' && !providerLocationForm.value.postal_code) {
        providerLocationError.value = 'Please add a ZIP code to continue.';
        return;
    }

    submittingProviderLocation.value = true;

    router.post(
        route('onboarding.progress'),
        {
            step: 'location',
            data: {
                street: providerLocationForm.value.address_line_1,
                address_line_2: providerLocationForm.value.address_line_2,
                city: providerLocationForm.value.city,
                state: providerLocationForm.value.state,
                postal_code: providerLocationForm.value.postal_code,
                country: providerLocationForm.value.country,
                county: providerLocationForm.value.county,
                label: providerLocationForm.value.location_label,
            },
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Keep URL + server-driven initialStep in sync.
                router.visit(route('onboarding.provider', { step: 5 }), {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                });
            },
            onError: () => {
                providerLocationError.value = 'Please review your address details and try again.';
            },
            onFinish: () => {
                submittingProviderLocation.value = false;
            },
        },
    );
};

const providerBusinessForm = ref({
    business_name: props.existingData?.business?.business_name ?? '',
    tagline: props.existingData?.business?.tagline ?? '',
    bio: props.existingData?.business?.bio ?? '',
    license_number: props.existingData?.business?.license_number ?? '',
    years_experience: props.existingData?.business?.years_experience ?? null,
});

const submittingProviderBusiness = ref(false);
const providerBusinessError = ref('');

const submitProviderBusinessStep = () => {
    providerBusinessError.value = '';

    // Keep it light: allow empty values, but don't allow an all-empty submission.
    const hasAny =
        String(providerBusinessForm.value.business_name || '').trim() ||
        String(providerBusinessForm.value.tagline || '').trim() ||
        String(providerBusinessForm.value.bio || '').trim() ||
        String(providerBusinessForm.value.license_number || '').trim() ||
        String(providerBusinessForm.value.years_experience ?? '').trim();

    if (!hasAny) {
        providerBusinessError.value = 'Please add at least one business detail to continue.';
        return;
    }

    submittingProviderBusiness.value = true;

    router.post(
        route('onboarding.progress'),
        {
            step: 'business',
            data: {
                business_name: providerBusinessForm.value.business_name,
                tagline: providerBusinessForm.value.tagline,
                bio: providerBusinessForm.value.bio,
                license_number: providerBusinessForm.value.license_number,
                years_experience: providerBusinessForm.value.years_experience,
            },
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.visit(route('onboarding.provider', { step: 6 }), {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                });
            },
            onError: () => {
                providerBusinessError.value = 'Please review your business details and try again.';
            },
            onFinish: () => {
                submittingProviderBusiness.value = false;
            },
        },
    );
};

const pricingModels = [
    { value: 'hourly', label: 'Hourly rate' },
    { value: 'flat_rate', label: 'Flat rate' },
    { value: 'consultation', label: 'Consultation-based' },
    { value: 'custom', label: 'Custom' },
];

const providerPricingForm = ref({
    model: props.existingData?.pricing?.model ?? 'hourly',
    hourly_rate: props.existingData?.pricing?.hourly_rate ?? null,
    consultation_fee: props.existingData?.pricing?.consultation_fee ?? null,
    free_consultation: Boolean(props.existingData?.pricing?.free_consultation ?? false),
    notes: props.existingData?.pricing?.notes ?? '',
});

watch(
    () => providerPricingForm.value.free_consultation,
    (next) => {
        if (next) {
            providerPricingForm.value.consultation_fee = null;
        }
    },
);

const pricingFeeValue = computed({
    get: () => {
        if (providerPricingForm.value.model === 'hourly' || providerPricingForm.value.model === 'flat_rate') {
            return providerPricingForm.value.hourly_rate;
        }
        return null;
    },
    set: (value) => {
        if (providerPricingForm.value.model === 'hourly' || providerPricingForm.value.model === 'flat_rate') {
            providerPricingForm.value.hourly_rate = value;
        }
    },
});

const pricingFeeLabel = computed(() => {
    switch (providerPricingForm.value.model) {
        case 'hourly':
            return 'Hourly rate (USD)';
        case 'flat_rate':
            return 'Flat fee (USD)';
        case 'consultation':
            return 'Consultation fee (USD)';
        default:
            return 'Fee (USD)';
    }
});

const pricingFeePlaceholder = computed(() => {
    switch (providerPricingForm.value.model) {
        case 'hourly':
            return '150';
        case 'flat_rate':
            return 'e.g. 500';
        case 'consultation':
            return 'Optional';
        default:
            return '';
    }
});

const submittingProviderPricing = ref(false);
const providerPricingError = ref('');

const submitProviderPricingStep = () => {
    providerPricingError.value = '';

    const feeText = String(pricingFeeValue.value ?? '').trim();
    const feeIsMissing = feeText === '';

    if (providerPricingForm.value.model === 'hourly' && feeIsMissing) {
        providerPricingError.value = 'Please enter your hourly rate to continue.';
        return;
    }
    if (providerPricingForm.value.model === 'flat_rate' && feeIsMissing) {
        providerPricingError.value = 'Please enter your flat fee to continue.';
        return;
    }
    if (providerPricingForm.value.model === 'custom') {
        providerPricingForm.value.hourly_rate = null;
    }

    if (providerPricingForm.value.free_consultation) {
        providerPricingForm.value.consultation_fee = null;
    }

    submittingProviderPricing.value = true;

    router.post(
        route('onboarding.progress'),
        {
            step: 'pricing',
            data: {
                model: providerPricingForm.value.model,
                hourly_rate: providerPricingForm.value.hourly_rate,
                consultation_fee: providerPricingForm.value.consultation_fee,
                free_consultation: Boolean(providerPricingForm.value.free_consultation),
                notes: providerPricingForm.value.notes,
            },
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.visit(route('onboarding.provider', { step: 7 }), {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                });
            },
            onError: () => {
                providerPricingError.value = 'Please review your pricing details and try again.';
            },
            onFinish: () => {
                submittingProviderPricing.value = false;
            },
        },
    );
};

const providerSubscriptionForm = ref({
    plan_uuid: props.existingData?.subscription?.plan_uuid ?? '',
});
const submittingProviderSubscription = ref(false);
const providerSubscriptionError = ref('');

const selectablePlans = computed(() => {
    const plans = Array.isArray(props.subscriptionPlans) ? props.subscriptionPlans : [];
    return plans.filter((p) => (p?.price_cents ?? 0) <= 0 || props.stripeBillingReady);
});

const submitProviderSubscriptionStep = () => {
    providerSubscriptionError.value = '';

    if (!providerSubscriptionForm.value.plan_uuid) {
        providerSubscriptionError.value = 'Please choose a subscription plan to continue.';
        return;
    }

    submittingProviderSubscription.value = true;

    // Final step: use server-side complete() which may redirect to Stripe if needed.
    router.post(
        route('onboarding.complete'),
        {
            address_line_1: providerLocationForm.value.address_line_1,
            address_line_2: providerLocationForm.value.address_line_2,
            city: providerLocationForm.value.city,
            state: providerLocationForm.value.state,
            country: providerLocationForm.value.country,
            postal_code: providerLocationForm.value.postal_code,
            county: providerLocationForm.value.county,
            location_label: providerLocationForm.value.location_label,
            business: providerBusinessForm.value,
            pricing: providerPricingForm.value,
            subscription: providerSubscriptionForm.value,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                if (errors?.['subscription.plan_uuid']) {
                    providerSubscriptionError.value = errors['subscription.plan_uuid'];
                    return;
                }
                providerSubscriptionError.value = 'Please review your subscription selection and try again.';
            },
            onFinish: () => {
                submittingProviderSubscription.value = false;
            },
        },
    );
};

const phoneVerificationRef = ref(null);
const phoneStepContinuing = computed(() => phoneVerificationRef.value?.isContinuing ?? false);
const phoneStepHasSentOtp = computed(() => phoneVerificationRef.value?.hasSentOtp ?? false);

const continuePhoneVerificationStep = () => {
    phoneVerificationRef.value?.continueFlow?.();
};

const syncProviderStepInUrl = (step) => {
    // Keep UI step and URL query (?step=) in sync.
    // Without this, "Back" can change the UI step while the URL still points to the old step,
    // so "Continue" may appear to do nothing (it navigates to the same URL).
    router.visit(route('onboarding.provider', { step }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const goBack = () => {
    if (isPhoneStep.value) {
        if (phoneStepHasSentOtp.value) {
            phoneVerificationRef.value?.backFlow?.();
            return;
        }
        currentStep.value = 2;
        syncProviderStepInUrl(2);
        return;
    }

    if (currentStep.value === 4) {
        const next = props.requiresPhoneVerification ? 3 : 2;
        currentStep.value = next;
        syncProviderStepInUrl(next);
        return;
    }
    if (currentStep.value === 5) {
        currentStep.value = 4;
        syncProviderStepInUrl(4);
        return;
    }
    if (currentStep.value === 6) {
        currentStep.value = 5;
        syncProviderStepInUrl(5);
        return;
    }
    if (currentStep.value === 7) {
        currentStep.value = 6;
        syncProviderStepInUrl(6);
        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit(route('register'));
};
</script>

<template>
    <Head :title="pageTitle" />

    <GuestLayout>
        <template #title>{{ pageTitle }}</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="currentStep" :total-steps="totalSteps" />
        </template>
        <template #side-image>
            <img
                src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1400&q=80"
                alt="Provider onboarding"
                class="h-full w-full object-cover"
            />
        </template>

        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-7">
            <div v-if="props.requiresPhoneVerification && props.phoneVerification" v-show="currentStep === 3">
                <ProviderPhoneVerification
                    ref="phoneVerificationRef"
                    :phone="props.phoneVerification?.phone ?? ''"
                    :phone-dial-options="props.phoneVerification?.phoneDialOptions ?? []"
                />
            </div>

            <div v-show="!(props.requiresPhoneVerification && props.phoneVerification && currentStep === 3)">
                <div v-if="isCoverageStep" class="space-y-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Coverage area</p>
                    <p class="text-sm text-neutral-600">
                        Select your service location where you offer services. You will enter your full business address in a later step.
                    </p>

                    <label class="mt-1 flex cursor-pointer items-center gap-2">
                        <input
                            v-model="coverageForm.serve_client_in_location"
                            type="checkbox"
                            class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                        />
                        <span class="text-sm text-neutral-700">I serve the client in their location</span>
                    </label>

                    <Select
                        v-model="coverageForm.coverage_country"
                        :options="providerServiceLocationOptions"
                        label="Service Location"
                        placeholder="Select service location"
                        size="auth"
                        required
                    />

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Select
                            v-if="serviceLocationWithStates.includes(coverageForm.coverage_country) && coverageStateOptions.length > 0"
                            v-model="coverageForm.coverage_state"
                            :options="coverageStateOptions"
                            label="State"
                            placeholder="Select state"
                            size="auth"
                            required
                        />
                        <Input
                            v-else
                            v-model="coverageForm.coverage_state"
                            label="State / region"
                            placeholder="Enter state or region"
                            size="compact"
                            :required="true"
                        />

                        <Input
                            v-model="coverageForm.coverage_postal_code"
                            :label="coverageForm.coverage_country === 'usa' ? 'City / ZIP code' : 'City (optional)'"
                            :placeholder="coverageForm.coverage_country === 'usa' ? 'Enter city or ZIP code' : 'Enter city'"
                            size="compact"
                            :required="coverageForm.coverage_country === 'usa'"
                        />
                    </div>

                    <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Service area</h3>
                        <p class="text-xs text-neutral-500">How you meet clients in your coverage region.</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="coverageForm.service_area.in_person"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                                />
                                <span class="text-sm text-neutral-700">In-person services</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="coverageForm.service_area.remote"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                                />
                                <span class="text-sm text-neutral-700">Remote / virtual services</span>
                            </label>
                        </div>
                        <Input
                            v-if="coverageForm.service_area.in_person"
                            v-model="coverageForm.service_area.radius"
                            type="number"
                            label="Service radius (miles)"
                            placeholder="e.g. 25"
                            size="compact"
                        />
                    </div>

                    <Select
                        v-model="coverageForm.preferred_language"
                        :options="languageOptions"
                        label="Language"
                        placeholder="Select language"
                        size="auth"
                        required
                    />

                    <p v-if="coverageError" class="text-sm font-medium text-red-600">{{ coverageError }}</p>
                </div>

                <div v-else-if="isProviderLocationStep" class="space-y-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Business address</p>
                    <p class="text-sm text-neutral-600">
                        Enter your business address. This will be used on your profile and for matching clients.
                    </p>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="providerLocationForm.address_line_1"
                            label="Address line 1"
                            placeholder="Street address"
                            size="compact"
                            required
                        />
                        <Input
                            v-model="providerLocationForm.address_line_2"
                            label="Address line 2 (optional)"
                            placeholder="Apt, suite, unit, building"
                            size="compact"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="providerLocationForm.city"
                            label="City"
                            placeholder="City"
                            size="compact"
                            required
                        />
                        <Select
                            v-if="String(providerLocationForm.country).toUpperCase() === 'US' && stateOptions.length > 0"
                            v-model="providerLocationForm.state"
                            :options="stateOptions"
                            label="State"
                            placeholder="Select state"
                            size="auth"
                            required
                        />
                        <Input
                            v-else
                            v-model="providerLocationForm.state"
                            label="State / region"
                            placeholder="State or region"
                            size="compact"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="providerLocationForm.postal_code"
                            label="ZIP / postal code"
                            placeholder="Postal code"
                            size="compact"
                            :required="String(providerLocationForm.country).toUpperCase() === 'US'"
                        />
                        <Select
                            v-model="providerLocationForm.country"
                            :options="countryOptions"
                            label="Country"
                            placeholder="Select country"
                            size="auth"
                            required
                        />
                    </div>

                    <Input
                        v-model="providerLocationForm.county"
                        label="County (optional)"
                        placeholder="County"
                        size="compact"
                    />

                    <p v-if="providerLocationError" class="text-sm font-medium text-red-600">{{ providerLocationError }}</p>
                </div>

                <div v-else-if="isProviderBusinessStep" class="space-y-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Business</p>
                    <p class="text-sm text-neutral-600">
                        Tell clients about your practice. You can refine these details later.
                    </p>

                    <Input
                        v-model="providerBusinessForm.business_name"
                        label="Business name"
                        placeholder="Your practice or company name"
                        size="compact"
                    />

                    <Input
                        v-model="providerBusinessForm.tagline"
                        label="Tagline"
                        placeholder="Short line that appears in search"
                        size="compact"
                    />

                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-neutral-700">Bio</label>
                        <textarea
                            v-model="providerBusinessForm.bio"
                            rows="4"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            placeholder="Experience, credentials, and how you help clients..."
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="providerBusinessForm.license_number"
                            label="License number"
                            placeholder="Enter license number"
                            size="compact"
                        />
                        <Input
                            v-model="providerBusinessForm.years_experience"
                            type="number"
                            min="0"
                            max="80"
                            label="Years of experience"
                            placeholder="e.g. 5"
                            size="compact"
                        />
                    </div>

                    <p v-if="providerBusinessError" class="text-sm font-medium text-red-600">{{ providerBusinessError }}</p>
                </div>

                <div v-else-if="isProviderPricingStep" class="space-y-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Pricing</p>
                    <p class="text-sm text-neutral-600">
                        Add pricing details for your services. You can adjust later.
                    </p>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-neutral-700">Pricing model</label>
                        <select
                            v-model="providerPricingForm.model"
                            class="w-full rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-base text-slate-900 shadow-sm outline-none transition duration-200 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 disabled:shadow-none"
                        >
                            <option v-for="m in pricingModels" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-if="providerPricingForm.model === 'hourly' || providerPricingForm.model === 'flat_rate'"
                            v-model="pricingFeeValue"
                            type="number"
                            min="0"
                            :label="pricingFeeLabel"
                            :placeholder="pricingFeePlaceholder"
                            size="compact"
                        />
                        <Input
                            v-model="providerPricingForm.consultation_fee"
                            type="number"
                            min="0"
                            label="Consultation fee (USD)"
                            placeholder="Optional"
                            size="compact"
                            :disabled="providerPricingForm.free_consultation"
                        />
                    </div>

                    <div v-if="providerPricingForm.model === 'custom'" class="space-y-1.5">
                        <label class="text-sm font-medium text-neutral-700">
                            Custom pricing notes
                            <span class="ml-0.5 text-slate-400">(optional)</span>
                        </label>
                        <textarea
                            v-model="providerPricingForm.notes"
                            rows="4"
                            placeholder="Describe how you price your services (e.g. depends on case complexity, package pricing, etc.)"
                            class="w-full rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-base text-slate-900 shadow-sm outline-none transition duration-200 placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            v-model="providerPricingForm.free_consultation"
                            type="checkbox"
                            class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                        />
                        <span class="text-sm text-neutral-700">Offer free initial consultations</span>
                    </label>

                    <p v-if="providerPricingError" class="text-sm font-medium text-red-600">{{ providerPricingError }}</p>
                </div>

                <div v-else-if="isProviderSubscriptionStep" class="space-y-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Subscription</p>
                    <p class="text-sm text-neutral-600">
                        Choose a plan to publish your provider profile. Free plans activate instantly; paid plans use Stripe checkout.
                    </p>

                    <div v-if="selectablePlans.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        No plans are available right now. Please contact support.
                    </div>

                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label
                            v-for="plan in selectablePlans"
                            :key="plan.uuid"
                            class="group w-full cursor-pointer rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md"
                            :class="providerSubscriptionForm.plan_uuid === plan.uuid ? 'border-sky-400 ring-2 ring-sky-300/40' : 'border-slate-200 hover:border-sky-200'"
                        >
                            <div class="flex items-start gap-4">
                                <input
                                    v-model="providerSubscriptionForm.plan_uuid"
                                    type="radio"
                                    name="provider-plan"
                                    :value="plan.uuid"
                                    class="mt-1.5 h-4 w-4 border-slate-300 text-primary-600"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-base font-semibold text-slate-900">{{ plan.name }}</p>
                                            <p v-if="plan.description" class="mt-1 text-sm text-slate-600">
                                                {{ plan.description }}
                                            </p>
                                        </div>
                                        <div class="shrink-0">
                                            <span
                                                class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                                            >
                                                <span v-if="(plan.price_cents ?? 0) <= 0">Free</span>
                                                <span v-else>${{ ((plan.price_cents ?? 0) / 100).toFixed(0) }} / {{ plan.billing_cycle }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <ul v-if="Array.isArray(plan.features) && plan.features.length" class="mt-4 space-y-2 text-sm text-slate-700">
                                        <li
                                            v-for="(feature, index) in plan.features"
                                            :key="`${plan.uuid}-feature-${index}`"
                                            class="flex items-start gap-2"
                                        >
                                            <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-50 text-sky-700">
                                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.3a1 1 0 0 1-1.42.005L3.29 9.26a1 1 0 1 1 1.414-1.414l3.04 3.04 6.543-6.59a1 1 0 0 1 1.417-.006Z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                            <span class="flex-1">{{ feature }}</span>
                                        </li>
                                    </ul>
                                    <p v-else class="mt-4 text-sm text-slate-500">
                                        All core provider features included.
                                    </p>
                                </div>
                            </div>
                        </label>
                    </div>

                    <p v-if="providerSubscriptionError" class="text-sm font-medium text-red-600">{{ providerSubscriptionError }}</p>
                </div>

                <div v-else class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
                    <h2 class="text-base font-semibold text-emerald-800">Continue setup</h2>
                    <p class="text-sm text-emerald-700">
                        Provider steps 5–7 will appear here (Business, Pricing, Subscription, Review).
                    </p>
                </div>
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

                <Button
                    v-if="isPhoneStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="phoneStepContinuing"
                    :disabled="phoneStepContinuing"
                    @click="continuePhoneVerificationStep"
                >
                    {{ phoneStepHasSentOtp ? 'Verify & Continue' : 'Continue' }}
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>

                <Button
                    v-else-if="isCoverageStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="submittingCoverage"
                    :disabled="submittingCoverage"
                    @click="submitCoverageStep"
                >
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>

                <Button
                    v-else-if="isProviderLocationStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="submittingProviderLocation"
                    :disabled="submittingProviderLocation"
                    @click="submitProviderLocationStep"
                >
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>

                <Button
                    v-else-if="isProviderBusinessStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="submittingProviderBusiness"
                    :disabled="submittingProviderBusiness"
                    @click="submitProviderBusinessStep"
                >
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>

                <Button
                    v-else-if="isProviderPricingStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="submittingProviderPricing"
                    :disabled="submittingProviderPricing"
                    @click="submitProviderPricingStep"
                >
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>

                <Button
                    v-else-if="isProviderSubscriptionStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="submittingProviderSubscription"
                    :disabled="submittingProviderSubscription"
                    @click="submitProviderSubscriptionStep"
                >
                    Finish setup
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </GuestLayout>
</template>

