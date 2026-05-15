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

const isPaginatorLikeBag = (bag) => {
    if (!bag || typeof bag !== 'object' || Array.isArray(bag)) {
        return false;
    }

    return Array.isArray(bag.data) && (Array.isArray(bag.links) || bag.links !== undefined);
};

const isValidationErrorBag = (bag) => {
    if (!bag || typeof bag !== 'object' || Array.isArray(bag) || isPaginatorLikeBag(bag)) {
        return false;
    }

    return Object.values(bag).some((value) => {
        if (typeof value === 'string') {
            return value.length > 0;
        }

        if (Array.isArray(value)) {
            return value.some((entry) => typeof entry === 'string' && entry.length > 0);
        }

        return false;
    });
};

const logClientFeedback = (event, payload) => {
    console.info(`[AppFeedback] ${event}`, payload);
};

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

    if (queue.length > 1) {
        logClientFeedback('multiple_flash_messages', {
            types: queue.map(([type]) => type),
            messages: Object.fromEntries(queue),
            page: page.url,
        });
    }

    for (const [type, message] of queue) {
        toast[type](message, {
            timeout: type === 'error' ? 5500 : 4000,
        });
    }
};

const notifyValidationErrors = (bag) => {
    if (!isValidationErrorBag(bag)) {
        if (isPaginatorLikeBag(bag)) {
            logClientFeedback('skipped_paginator_errors_prop', {
                reason: 'Inertia page prop named "errors" is a paginator, not a validation bag.',
                keys: Object.keys(bag),
                page: page.url,
            });
        }

        return;
    }

    const values = Object.values(bag)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter((value) => typeof value === 'string' && value.length > 0);

    if (!values.length) {
        return;
    }

    const [firstError] = values;
    const extraCount = values.length - 1;

    logClientFeedback('validation_errors_toast', {
        count: values.length,
        page: page.url,
    });

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