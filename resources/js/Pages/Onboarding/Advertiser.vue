<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import OnboardingPhoneVerification from '@/Components/onboarding/OnboardingPhoneVerification.vue';
import { SIGNUP_FLOW_STEPS_USER } from '@/constants/authFlowProgress';

const props = defineProps({
    user: Object,
    initialStep: Number,
    requiresPhoneVerification: { type: Boolean, default: false },
    phoneVerification: { type: Object, default: null },
    countryOptions: Array,
    stateOptions: { type: Array, default: () => [] },
    languageOptions: Array,
    existingData: Object,
});

const currentStep = ref(props.initialStep ?? (props.requiresPhoneVerification ? 3 : 4));
const totalSteps = computed(() => SIGNUP_FLOW_STEPS_USER);
const isAddressStep = computed(() => currentStep.value === 2);
const isPhoneStep = computed(() => props.requiresPhoneVerification && currentStep.value === 3);
const phoneVerificationRef = ref(null);

const formData = ref({
    address_line_1: props.existingData?.location?.street || props.user?.address || '',
    city: props.existingData?.location?.city || props.user?.city || '',
    state: props.existingData?.location?.state || props.user?.state || '',
    postal_code: props.existingData?.location?.postal_code || props.user?.postal_code || '',
    county: props.existingData?.location?.county || '',
    location_label: props.existingData?.location?.label || '',
    country: props.existingData?.location?.country || props.user?.country || 'US',
    preferred_language: props.existingData?.language?.preferred || props.user?.preferred_language || 'en',
    confirms_advertiser_intent: false,
});

const submittingAddress = ref(false);
const saving = ref(false);
const addressError = ref('');
const confirmationError = ref('');

const onboardingHeading = computed(() => {
    if (isAddressStep.value) {
        return 'Address details';
    }

    if (isPhoneStep.value) {
        return 'Phone verification';
    }

    return 'Congratulations';
});

const submitAddressStep = () => {
    addressError.value = '';

    if (!formData.value.address_line_1) {
        addressError.value = 'Please enter your street address to continue.';
        return;
    }

    if (!formData.value.city || !formData.value.state || !formData.value.country) {
        addressError.value = 'Please complete city, state, and country to continue.';
        return;
    }

    if (String(formData.value.country).toUpperCase() === 'US' && !formData.value.postal_code) {
        addressError.value = 'Please add a ZIP code to continue.';
        return;
    }

    router.post(route('address-detail.send'), {
        address: formData.value.address_line_1,
        city: formData.value.city,
        state: formData.value.state,
        country: formData.value.country,
        postal_code: formData.value.postal_code,
        county: formData.value.county,
        location_label: formData.value.location_label,
        preferred_language: formData.value.preferred_language,
    }, {
        preserveState: false,
        onStart: () => {
            submittingAddress.value = true;
        },
        onSuccess: () => {
            addressError.value = '';
            currentStep.value = 3;
        },
        onError: () => {
            addressError.value = 'Please review your address details and try again.';
        },
        onFinish: () => {
            submittingAddress.value = false;
        },
    });
};

const completeOnboarding = () => {
    confirmationError.value = '';

    if (!formData.value.confirms_advertiser_intent) {
        confirmationError.value = 'Please confirm you are posting job adverts to continue.';
        return;
    }

    saving.value = true;

    router.post(route('onboarding.complete'), {
        address_line_1: formData.value.address_line_1,
        city: formData.value.city,
        state: formData.value.state,
        country: formData.value.country,
        postal_code: formData.value.postal_code,
        county: formData.value.county,
        location_label: formData.value.location_label,
        preferred_language: formData.value.preferred_language,
        languages: [formData.value.preferred_language || 'en'],
        services_needed: [],
    }, {
        onFinish: () => {
            saving.value = false;
        },
    });
};

const goBack = () => {
    if (isPhoneStep.value && phoneVerificationRef.value?.backFlow) {
        phoneVerificationRef.value.backFlow();
        return;
    }

    if (isPhoneStep.value) {
        currentStep.value = 2;
        return;
    }

    if (currentStep.value === 4) {
        currentStep.value = props.requiresPhoneVerification ? 3 : 2;
        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(route('onboarding.advertiser', { step: 2 }));
};

const continuePhoneStep = () => {
    if (phoneVerificationRef.value?.continueFlow) {
        phoneVerificationRef.value.continueFlow();
    }
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

        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-7">
            <OnboardingPhoneVerification
                v-if="isPhoneStep && phoneVerification"
                ref="phoneVerificationRef"
                :phone="phoneVerification.phone"
                :phone-dial-options="phoneVerification.phoneDialOptions"
                :is-provider="false"
                :use-external-actions="true"
            />

            <div v-else-if="isAddressStep" class="space-y-5">
                <Input
                    v-model="formData.address_line_1"
                    label="Street address"
                    placeholder="123 Main St"
                    required
                />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <Input
                        v-model="formData.city"
                        label="City"
                        placeholder="City"
                        required
                    />
                    <Select
                        v-model="formData.state"
                        :options="stateOptions"
                        label="State"
                        placeholder="Select state"
                        size="auth"
                        required
                    />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <Input
                        v-model="formData.postal_code"
                        label="Postal / ZIP code"
                        placeholder="ZIP or postal code"
                    />
                    <Select
                        v-model="formData.country"
                        :options="countryOptions"
                        label="Country"
                        placeholder="Select country"
                        size="auth"
                        required
                    />
                </div>

                <Input
                    v-model="formData.county"
                    label="County (optional)"
                    placeholder="County"
                />

                <Select
                    v-model="formData.preferred_language"
                    :options="languageOptions"
                    label="Language preference"
                    placeholder="Select language"
                    size="auth"
                    required
                />

                <p v-if="addressError" class="text-sm font-medium text-red-600">
                    {{ addressError }}
                </p>
            </div>

            <div v-else class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-3">
                    <input
                        v-model="formData.confirms_advertiser_intent"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="text-sm text-slate-700">
                        I post job adverts and understand each ad requires a one-time publish payment.
                    </span>
                </label>
                <p v-if="confirmationError" class="text-sm font-medium text-red-600">
                    {{ confirmationError }}
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

                <Button
                    v-if="isPhoneStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="phoneVerificationRef?.isContinuing"
                    @click="continuePhoneStep"
                >
                    Continue
                    <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button
                    v-else-if="isAddressStep"
                    variant="primary"
                    size="lg"
                    class="min-w-[11rem]"
                    :loading="submittingAddress"
                    :disabled="submittingAddress"
                    @click="submitAddressStep"
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
