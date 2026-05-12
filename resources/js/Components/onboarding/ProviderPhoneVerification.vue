<script setup>
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

const emit = defineEmits(['verified']);

const phoneVerificationRef = ref(null);
const isContinuing = computed(() => phoneVerificationRef.value?.isContinuing ?? false);
const hasSentOtp = computed(() => phoneVerificationRef.value?.hasSentOtp ?? false);

const handleVerified = () => {
    emit('verified');
};

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
        @verified="handleVerified"
    />
</template>

