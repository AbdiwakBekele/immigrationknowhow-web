<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import LocationCountryStatePick from '@/Components/address/LocationCountryStatePick.vue';
import OnboardingPhoneVerification from '@/Components/onboarding/OnboardingPhoneVerification.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import { SIGNUP_FLOW_STEPS_USER } from '@/constants/authFlowProgress';

const props = defineProps({
    user: { type: Object, required: true },
    initialStep: { type: Number, default: 2 },
    requiresPhoneVerification: { type: Boolean, default: false },
    phoneVerification: { type: Object, default: null },
    isProvider: { type: Boolean, default: false },
    serviceTypes: { type: Array, default: () => [] },
    countryOptions: { type: Array, default: () => [] },
    stateOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, default: () => [] },
    existingData: { type: Object, default: () => ({}) },
});

const draftStorageKey = computed(() => `ikh_onboarding_user_draft:${props.user?.id ?? 'guest'}`);

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const normalizeStep = (value, fallback) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : fallback;
};

const currentStep = ref(normalizeStep(props.initialStep, props.requiresPhoneVerification ? 3 : 2));
const totalSteps = computed(() => SIGNUP_FLOW_STEPS_USER);
const pageTitle = computed(() => {
    if (currentStep.value === 3) {
        return 'Phone verification';
    }
    if (currentStep.value === 4) {
        return 'Congratulations';
    }
    return 'Onboarding';
});
const isUserAddressStep = computed(() => currentStep.value === 2);
const isPhoneStep = computed(() => props.requiresPhoneVerification && currentStep.value === 3);
const isCongratsStep = computed(() => currentStep.value === 4);

const MAX_USER_SERVICE_SELECTIONS = 8;

const userServiceTypeOptions = computed(() => props.serviceTypes || []);

const formData = ref({
    services_needed: [],
    city: props.user?.city || '',
    state: props.user?.state || '',
    postal_code: props.user?.postal_code || '',
    county: props.existingData?.location?.county || '',
    location_label: props.existingData?.location?.label || '',
    country: props.user?.country || 'US',
    preferred_language: props.user?.preferred_language || 'en',
    profile: {
        number_of_children: props.existingData?.profile?.number_of_children ?? null,
        children_ages_text: props.existingData?.profile?.children_ages_text ?? '',
        dogs_count: props.existingData?.profile?.dogs_count ?? null,
    },
});

const loadDraft = () => {
    try {
        const raw = sessionStorage.getItem(draftStorageKey.value);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object' && parsed.formData) {
            formData.value = { ...formData.value, ...parsed.formData };
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
                formData: formData.value,
            }),
        );
    } catch {
        // ignore quota / private mode
    }
};

onMounted(() => {
    loadDraft();
});

watch(formData, () => saveDraft(), { deep: true });

// IMPORTANT:
// OTP verification posts use preserveState, so Inertia can keep this component instance alive
// while props change (e.g. server redirects to step=4 and requiresPhoneVerification=false).
// Keep the UI step in sync with the latest server step.
watch(
    () => [props.initialStep, props.requiresPhoneVerification],
    ([nextInitialStep, nextRequiresPhone]) => {
        const next = normalizeStep(nextInitialStep, nextRequiresPhone ? 3 : 2);
        currentStep.value = next;
    },
    { immediate: true },
);

const userServicesLimitError = ref('');
const userAddressError = ref('');
const submittingUserAddress = ref(false);
const saving = ref(false);

const userServiceTypes = computed({
    get: () =>
        Array.isArray(formData.value.services_needed)
            ? [...new Set(formData.value.services_needed.filter((value) => value))]
            : [],
    set: (values) => {
        const selected = Array.isArray(values) ? [...new Set(values.filter((value) => value))] : [];
        if (selected.length > MAX_USER_SERVICE_SELECTIONS) {
            formData.value.services_needed = selected.slice(0, MAX_USER_SERVICE_SELECTIONS);
            userServicesLimitError.value = `You can select up to ${MAX_USER_SERVICE_SELECTIONS} services.`;
            return;
        }
        formData.value.services_needed = selected;
        userServicesLimitError.value = '';
    },
});

const phoneVerificationRef = ref(null);
const phoneStepContinuing = computed(() => phoneVerificationRef.value?.isContinuing ?? false);
const phoneStepHasSentOtp = computed(() => phoneVerificationRef.value?.hasSentOtp ?? false);

const continuePhoneVerificationStep = () => {
    phoneVerificationRef.value?.continueFlow?.();
};
const backPhoneVerificationStep = () => {
    if (phoneStepHasSentOtp.value) {
        phoneVerificationRef.value?.backFlow?.();
        return;
    }
    currentStep.value = 2;
};

const submitUserAddressStep = () => {
    userAddressError.value = '';
    if (!formData.value.city || !formData.value.state || !formData.value.country) {
        userAddressError.value = 'Please complete city, state, and country to continue.';
        return;
    }

    submittingUserAddress.value = true;

    router.post(route('address-detail.send'), {
        address: null,
        city: formData.value.city,
        state: formData.value.state,
        country: formData.value.country,
        postal_code: formData.value.postal_code,
        county: formData.value.county,
        location_label: formData.value.location_label,
        preferred_language: formData.value.preferred_language,
        number_of_children: formData.value.profile.number_of_children,
        children_ages_text: formData.value.profile.children_ages_text,
        dogs_count: formData.value.profile.dogs_count,
        services_needed: formData.value.services_needed,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            currentStep.value = 3;
        },
        onError: () => {
            userAddressError.value = 'Please review your details and try again.';
        },
        onFinish: () => {
            submittingUserAddress.value = false;
        },
    });
};

const completeOnboarding = async () => {
    saving.value = true;
    router.post(route('onboarding.complete'), {
        ...formData.value,
        languages: [formData.value.preferred_language || 'en'],
    }, {
        onSuccess: () => {
            try {
                sessionStorage.removeItem(draftStorageKey.value);
            } catch {
                // ignore
            }
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};

const goBack = () => {
    if (isPhoneStep.value) {
        backPhoneVerificationStep();
        return;
    }
    if (currentStep.value === 4) {
        currentStep.value = props.requiresPhoneVerification ? 3 : 2;
        return;
    }
    if (currentStep.value > 2) {
        currentStep.value = Math.max(2, currentStep.value - 1);
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
                src="https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=1400&q=80"
                alt="Onboarding"
                class="h-full w-full object-cover"
            />
        </template>

        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-7">
            <div v-if="props.requiresPhoneVerification && props.phoneVerification" v-show="currentStep === 3">
                <OnboardingPhoneVerification
                    ref="phoneVerificationRef"
                    :phone="props.phoneVerification?.phone ?? ''"
                    :phone-dial-options="props.phoneVerification?.phoneDialOptions ?? []"
                    :is-provider="false"
                    :use-external-actions="true"
                    @verified="currentStep = 4"
                />
            </div>

            <div v-show="!(props.requiresPhoneVerification && props.phoneVerification && currentStep === 3)">
                <div v-if="isUserAddressStep" class="space-y-5">
                    <Select
                        v-model="userServiceTypes"
                        :options="userServiceTypeOptions"
                        label="Select services needed (up to 8)"
                        placeholder="I will select one later on"
                        :multiple="true"
                        size="auth"
                    />
                    <p v-if="userServicesLimitError" class="text-sm font-medium text-red-600">
                        {{ userServicesLimitError }}
                    </p>

                    <LocationCountryStatePick
                        v-model:country="formData.country"
                        v-model:state="formData.state"
                        v-model:city="formData.city"
                        v-model:postal-code="formData.postal_code"
                        v-model:county="formData.county"
                        v-model:location-label="formData.location_label"
                        :country-options="countryOptions"
                        :initial-state-options="stateOptions"
                        location-mode="google"
                    />

                    <Select
                        v-model="formData.preferred_language"
                        :options="languageOptions"
                        label="Language preference"
                        placeholder="Select language"
                        size="auth"
                        required
                    />

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="formData.profile.number_of_children"
                            type="number"
                            min="0"
                            max="30"
                            label="Number of children"
                            placeholder="e.g. 2"
                            size="compact"
                        />
                        <Input
                            v-model="formData.profile.children_ages_text"
                            label="Children's ages"
                            placeholder="e.g. 4, 7 or newborn"
                            size="compact"
                        />
                    </div>
                    <Input
                        v-model="formData.profile.dogs_count"
                        type="number"
                        min="0"
                        max="50"
                        label="Dogs (pets)"
                        placeholder="How many dogs in the household?"
                        size="compact"
                    />
                    <p v-if="userAddressError" class="text-sm font-medium text-red-600">{{ userAddressError }}</p>
                </div>

                <div v-else-if="isCongratsStep" class="space-y-4 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center">
                    <h2 class="text-lg font-semibold text-emerald-900">Congratulations!</h2>
                    <p v-if="flashSuccess" class="text-sm text-emerald-800">
                        {{ flashSuccess }}
                    </p>
                    <p class="text-sm text-emerald-700">
                        You're all set to finish onboarding. Use the button below when you're ready.
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
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>

                <Button
                    v-else-if="isUserAddressStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="submittingUserAddress"
                    :disabled="submittingUserAddress"
                    @click="submitUserAddressStep"
                >
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>

                <Button
                    v-else
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="saving"
                    @click="completeOnboarding"
                >
                    Finish setup
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </GuestLayout>
</template>

