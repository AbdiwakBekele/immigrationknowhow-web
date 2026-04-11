<script setup>
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const page = usePage();
const toast = useToast();

const flash = computed(() => page?.props?.flash || {});
const errors = computed(() => page?.props?.errors || {});

let lastFlashSignature = '';
let lastErrorSignature = '';

const notifyFlashMessages = (messages) => {
    const queue = [
        ['success', messages.success],
        ['error', messages.error],
        ['warning', messages.warning],
        ['info', messages.info],
    ].filter(([, value]) => Boolean(value));

    if (messages.otp_sent) {
        queue.push(['info', 'Verification code sent. Please check your phone and enter the OTP.']);
    }

    for (const [type, message] of queue) {
        toast[type](message, {
            timeout: type === 'error' ? 5500 : 4000,
        });
    }
};

const notifyValidationErrors = (bag) => {
    const values = Object.values(bag).flat().filter(Boolean);

    if (!values.length) {
        return;
    }

    const [firstError] = values;
    const extraCount = values.length - 1;

    toast.error(extraCount > 0 ? `${firstError} (+${extraCount} more issue${extraCount > 1 ? 's' : ''})` : firstError, {
        timeout: 6000,
    });
};

watch(
    flash,
    (value) => {
        const signature = JSON.stringify(value || {});

        if (!signature || signature === '{}' || signature === lastFlashSignature) {
            return;
        }

        lastFlashSignature = signature;
        notifyFlashMessages(value || {});
    },
    { immediate: true, deep: true },
);

watch(
    errors,
    (value) => {
        const signature = JSON.stringify(value || {});

        if (!signature || signature === '{}' || signature === lastErrorSignature) {
            return;
        }

        lastErrorSignature = signature;
        notifyValidationErrors(value || {});
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <span class="hidden" aria-hidden="true"></span>
</template>