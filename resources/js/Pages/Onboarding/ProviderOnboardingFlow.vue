<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import OnboardingPhoneVerification from '@/Components/onboarding/OnboardingPhoneVerification.vue';
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import { SIGNUP_FLOW_STEPS_PROVIDER } from '@/constants/authFlowProgress';

const props = defineProps({
    initialStep: Number,
    requiresPhoneVerification: { type: Boolean, default: false },
    phoneVerification: { type: Object, default: null },
    user: Object,
    serviceTypes: { type: Array, default: () => [] },
    existingData: { type: Object, default: () => ({}) },
    subscriptionPlans: { type: Array, default: () => [] },
});

const currentStep = ref(props.initialStep ?? (props.requiresPhoneVerification ? 3 : 4));
const saving = ref(false);
const pricingModels = [
    { value: 'hourly', label: 'Hourly rate' },
    { value: 'flat_rate', label: 'Flat rate' },
    { value: 'consultation', label: 'Consultation-based' },
    { value: 'custom', label: 'Custom' },
];

const formData = ref({
    business: {
        business_name: props.existingData?.business?.business_name || '',
        bio: props.existingData?.business?.bio || '',
        tagline: props.existingData?.business?.tagline || '',
        business_email: props.existingData?.business?.business_email || props.user?.email || '',
        business_phone: props.existingData?.business?.business_phone || '',
        website: props.existingData?.business?.website || '',
        license_number: props.existingData?.business?.license_number || '',
        years_experience: props.existingData?.business?.years_experience || null,
    },
    services: {
        types: props.existingData?.services?.types || [],
        specializations: props.existingData?.services?.specializations || [],
    },
    pricing: {
        model: props.existingData?.pricing?.model || 'hourly',
        hourly_rate: props.existingData?.pricing?.hourly_rate || null,
        consultation_fee: props.existingData?.pricing?.consultation_fee || null,
        free_consultation: Boolean(props.existingData?.pricing?.free_consultation),
        notes: props.existingData?.pricing?.notes || '',
    },
    subscription: {
        plan_uuid: props.existingData?.subscription?.plan_uuid || '',
    },
});

const heading = computed(() => {
    if (currentStep.value === 3) return 'Phone verification';
    if (currentStep.value === 4) return 'Business information';
    if (currentStep.value === 5) return 'Pricing';
    if (currentStep.value === 6) return 'Subscription';
    return 'Review';
});

const continueStep = () => {
    if (currentStep.value < 7) {
        currentStep.value += 1;
    }
};

const goBack = () => {
    if (props.requiresPhoneVerification && currentStep.value === 3) {
        router.visit(route('address-detail'));
        return;
    }
    if (currentStep.value > 4) {
        currentStep.value -= 1;
        return;
    }
    router.visit(route('address-detail'));
};

const finish = () => {
    saving.value = true;
    router.post(route('onboarding.complete'), formData.value, {
        onFinish: () => {
            saving.value = false;
        },
    });
};
</script>

<template>
    <Head title="Provider Onboarding" />
    <GuestLayout>
        <template #title>{{ heading }}</template>
        <template #progress>
            <AuthFlowProgress :current-step="currentStep" :total-steps="SIGNUP_FLOW_STEPS_PROVIDER" />
        </template>

        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-7">
            <OnboardingPhoneVerification
                v-if="requiresPhoneVerification && currentStep === 3 && phoneVerification"
                :phone="phoneVerification.phone"
                :phone-dial-options="phoneVerification.phoneDialOptions"
                :is-provider="true"
            />

            <template v-else>
                <div v-if="currentStep === 4" class="space-y-4">
                    <Input v-model="formData.business.business_name" label="Business name" />
                    <Input v-model="formData.business.tagline" label="Tagline" />
                    <label class="label">Bio</label>
                    <textarea v-model="formData.business.bio" rows="4" class="input" />
                    <Select v-model="formData.services.types" :options="serviceTypes" label="Service types" :multiple="true" />
                </div>

                <div v-else-if="currentStep === 5" class="space-y-4">
                    <select v-model="formData.pricing.model" class="input w-full">
                        <option v-for="m in pricingModels" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <Input v-model="formData.pricing.hourly_rate" type="number" label="Hourly rate" />
                    <Input v-model="formData.pricing.consultation_fee" type="number" label="Consultation fee" />
                </div>

                <div v-else-if="currentStep === 6" class="space-y-4">
                    <Select
                        v-model="formData.subscription.plan_uuid"
                        :options="subscriptionPlans.map((p) => ({ value: p.uuid, label: p.name }))"
                        label="Choose plan"
                        placeholder="Select plan"
                    />
                </div>

                <div v-else class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    Your provider profile is ready. Click Finish setup to continue.
                </div>
            </template>

            <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <Button type="button" variant="ghost" @click="goBack">
                    <ArrowLeftIcon class="h-4 w-4" /> Back
                </Button>
                <Button
                    v-if="currentStep < 7"
                    type="button"
                    variant="primary"
                    @click="continueStep"
                >
                    Continue <ArrowRightIcon class="h-4 w-4" />
                </Button>
                <Button v-else type="button" variant="primary" :loading="saving" @click="finish">
                    Finish setup <ArrowRightIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </GuestLayout>
</template>
