<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import LocationCountryStatePick from '@/Components/address/LocationCountryStatePick.vue';
import OnboardingPhoneVerification from '@/Components/onboarding/OnboardingPhoneVerification.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import { SIGNUP_FLOW_STEPS_USER } from '@/constants/authFlowProgress';

const props = defineProps({
    initialStep: Number,
    requiresPhoneVerification: { type: Boolean, default: false },
    phoneVerification: { type: Object, default: null },
    serviceTypes: { type: Array, default: () => [] },
    countryOptions: { type: Array, default: () => [] },
    stateOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, default: () => [] },
    existingData: { type: Object, default: () => ({}) },
    user: Object,
});

const currentStep = ref(props.initialStep ?? (props.requiresPhoneVerification ? 3 : 4));
const saving = ref(false);
const submittingAddress = ref(false);
const errorMessage = ref('');

const formData = ref({
    services_needed: props.existingData?.services?.services_needed || [],
    address: props.user?.address || '',
    city: props.existingData?.location?.city || props.user?.city || '',
    state: props.existingData?.location?.state || props.user?.state || '',
    country: props.existingData?.location?.country || props.user?.country || 'US',
    postal_code: props.existingData?.location?.postal_code || props.user?.postal_code || '',
    county: props.existingData?.location?.county || '',
    location_label: props.existingData?.location?.label || '',
    preferred_language: props.existingData?.language?.preferred || props.user?.preferred_language || 'en',
});

const heading = computed(() => {
    if (currentStep.value === 2) return 'Address details';
    if (currentStep.value === 3) return 'Phone verification';
    return 'Congratulations';
});

const submitAddress = () => {
    errorMessage.value = '';
    if (!formData.value.city || !formData.value.state || !formData.value.country) {
        errorMessage.value = 'Please complete city, state, and country.';
        return;
    }
    if (String(formData.value.country).toUpperCase() === 'US' && !formData.value.postal_code) {
        errorMessage.value = 'Please add your ZIP code.';
        return;
    }

    router.post(route('address-detail.send'), formData.value, {
        onStart: () => {
            submittingAddress.value = true;
        },
        onSuccess: () => {
            currentStep.value = 3;
        },
        onFinish: () => {
            submittingAddress.value = false;
        },
        onError: () => {
            errorMessage.value = 'Could not save address details. Please try again.';
        },
    });
};

const goBack = () => {
    if (currentStep.value === 4) {
        currentStep.value = props.requiresPhoneVerification ? 3 : 2;
        return;
    }
    if (currentStep.value === 3) {
        currentStep.value = 2;
        return;
    }
    router.visit(route('register'));
};

const finish = () => {
    saving.value = true;
    router.post(route('onboarding.complete'), {
        ...formData.value,
        languages: [formData.value.preferred_language || 'en'],
    }, {
        onFinish: () => {
            saving.value = false;
        },
    });
};
</script>

<template>
    <Head title="Service Needer Onboarding" />
    <GuestLayout>
        <template #title>{{ heading }}</template>
        <template #progress>
            <AuthFlowProgress :current-step="currentStep" :total-steps="SIGNUP_FLOW_STEPS_USER" />
        </template>

        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-7">
            <OnboardingPhoneVerification
                v-if="requiresPhoneVerification && currentStep === 3 && phoneVerification"
                :phone="phoneVerification.phone"
                :phone-dial-options="phoneVerification.phoneDialOptions"
                :is-provider="false"
            />

            <template v-else-if="currentStep === 2">
                <div class="space-y-5">
                    <Select
                        v-model="formData.services_needed"
                        :options="serviceTypes"
                        label="Services needed"
                        placeholder="Choose services"
                        :multiple="true"
                    />

                    <LocationCountryStatePick
                        v-model:country="formData.country"
                        v-model:state="formData.state"
                        v-model:city="formData.city"
                        v-model:postal-code="formData.postal_code"
                        v-model:county="formData.county"
                        v-model:location-label="formData.location_label"
                        :country-options="countryOptions"
                        :initial-state-options="stateOptions"
                    />

                    <Select
                        v-model="formData.preferred_language"
                        :options="languageOptions"
                        label="Preferred language"
                        placeholder="Select language"
                    />
                    <p v-if="errorMessage" class="text-sm font-medium text-red-600">{{ errorMessage }}</p>
                </div>
            </template>

            <div v-else class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                Your account is ready. Click Finish setup to continue.
            </div>

            <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <Button type="button" variant="ghost" @click="goBack">
                    <ArrowLeftIcon class="h-4 w-4" /> Back
                </Button>

                <Button
                    v-if="currentStep === 2"
                    type="button"
                    variant="primary"
                    :loading="submittingAddress"
                    :disabled="submittingAddress"
                    @click="submitAddress"
                >
                    Continue <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else-if="currentStep === 3" type="button" variant="primary" @click="currentStep = 4">
                    Continue <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else type="button" variant="primary" :loading="saving" @click="finish">
                    Finish setup <ArrowRightIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </GuestLayout>
</template>
