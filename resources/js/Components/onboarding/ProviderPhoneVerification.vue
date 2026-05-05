<script setup>
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import OnboardingPhoneVerification from '@/Components/onboarding/OnboardingPhoneVerification.vue';

const props = defineProps({
    phone: {
        type: String,
        default: '',
    },
    phoneDialOptions: {
        type: Array,
        required: true,
    },
});

const phoneVerificationRef = ref(null);
const isContinuing = computed(() => phoneVerificationRef.value?.isContinuing ?? false);
const hasSentOtp = computed(() => phoneVerificationRef.value?.hasSentOtp ?? false);

const goToProviderStep4 = () => {
    router.visit(route('onboarding.provider', { step: 4 }), {
        preserveScroll: true,
        replace: true,
    });
};

// Allow Provider.vue to control actions like the old setup.
const continueFlow = () => phoneVerificationRef.value?.continueFlow?.();
const backFlow = () => phoneVerificationRef.value?.backFlow?.();

defineExpose({
    continueFlow,
    backFlow,
    isContinuing,
    hasSentOtp,
});
</script>

<template>
    <OnboardingPhoneVerification
        ref="phoneVerificationRef"
        :phone="props.phone"
        :phone-dial-options="props.phoneDialOptions"
        :is-provider="true"
        :use-external-actions="true"
        send-route-name="onboarding.provider.phone.send"
        verify-route-name="onboarding.provider.phone.verify"
        @verified="goToProviderStep4"
    />
</template>

