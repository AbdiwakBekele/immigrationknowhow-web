<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    maskedPhone: {
        type: String,
        default: null,
    },
    needsPhone: {
        type: Boolean,
        default: false,
    },
    codeSent: {
        type: Boolean,
        default: false,
    },
    attemptsRemaining: {
        type: Number,
        default: 5,
    },
    lockedOut: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
    usesFakeSms: {
        type: Boolean,
        default: false,
    },
    fakeOtpCode: {
        type: String,
        default: '123456',
    },
});

const OTP_LENGTH = 6;

const otpForm = useForm({
    code: '',
});

const sendForm = useForm({});

const otpInputs = ref([]);
const resendCooldownSeconds = ref(0);
const isSending = ref(false);
let resendCooldownInterval = null;

const otpDigits = computed(() => {
    const digits = String(otpForm.code || '').replace(/\D/g, '').slice(0, OTP_LENGTH);
    return Array.from({ length: OTP_LENGTH }, (_, index) => digits[index] || '');
});

const canSubmit = computed(() =>
    !props.lockedOut &&
    !props.needsPhone &&
    props.codeSent &&
    otpForm.code.replace(/\D/g, '').length === OTP_LENGTH,
);

const canResend = computed(() =>
    !props.lockedOut &&
    !props.needsPhone &&
    props.codeSent &&
    !sendForm.processing &&
    !isSending.value &&
    resendCooldownSeconds.value <= 0,
);

const startResendCooldown = (seconds = 30) => {
    if (resendCooldownInterval) {
        clearInterval(resendCooldownInterval);
        resendCooldownInterval = null;
    }

    resendCooldownSeconds.value = Math.max(0, Number(seconds) || 0);
    if (resendCooldownSeconds.value <= 0) return;

    resendCooldownInterval = setInterval(() => {
        resendCooldownSeconds.value = Math.max(0, resendCooldownSeconds.value - 1);
        if (resendCooldownSeconds.value <= 0 && resendCooldownInterval) {
            clearInterval(resendCooldownInterval);
            resendCooldownInterval = null;
        }
    }, 1000);
};

onBeforeUnmount(() => {
    if (resendCooldownInterval) {
        clearInterval(resendCooldownInterval);
    }
});

const setOtpCode = (digits) => {
    otpForm.code = digits.join('').replace(/\D/g, '').slice(0, OTP_LENGTH);
    otpForm.clearErrors('code');
};

const setOtpInputRef = (element, index) => {
    otpInputs.value[index] = element;
};

const focusOtpInput = (index) => {
    nextTick(() => {
        otpInputs.value[index]?.focus();
        otpInputs.value[index]?.select?.();
    });
};

const handleOtpInput = (index, event) => {
    const typedDigits = String(event.target.value || '').replace(/\D/g, '');
    const digits = [...otpDigits.value];

    if (!typedDigits) {
        digits[index] = '';
        setOtpCode(digits);
        return;
    }

    typedDigits
        .slice(0, OTP_LENGTH - index)
        .split('')
        .forEach((digit, offset) => {
            digits[index + offset] = digit;
        });

    setOtpCode(digits);

    const nextIndex = Math.min(index + typedDigits.length, OTP_LENGTH - 1);
    focusOtpInput(nextIndex);
};

const handleOtpKeydown = (index, event) => {
    if (event.key === 'Backspace') {
        event.preventDefault();
        const digits = [...otpDigits.value];

        if (digits[index]) {
            digits[index] = '';
            setOtpCode(digits);
            return;
        }

        if (index > 0) {
            digits[index - 1] = '';
            setOtpCode(digits);
            focusOtpInput(index - 1);
        }
    }
};

const handleOtpPaste = (event) => {
    const pastedDigits = String(event.clipboardData?.getData('text') || '')
        .replace(/\D/g, '')
        .slice(0, OTP_LENGTH);

    if (!pastedDigits) return;

    event.preventDefault();
    otpForm.code = pastedDigits;
    otpForm.clearErrors('code');
    focusOtpInput(Math.min(pastedDigits.length, OTP_LENGTH) - 1);
};

const submitOtp = () => {
    if (!canSubmit.value || otpForm.processing) return;

    otpForm.post(route('admin.2fa.verify'), {
        preserveScroll: true,
    });
};

const resendOtp = () => {
    if (!canResend.value || isSending.value || sendForm.processing) return;

    isSending.value = true;

    sendForm.post(route('admin.2fa.send'), {
        preserveScroll: true,
        onSuccess: () => {
            otpForm.code = '';
            otpForm.clearErrors('code');
            startResendCooldown(30);
        },
        onFinish: () => {
            isSending.value = false;
        },
    });
};
</script>

<template>
    <Head title="Admin verification" />

    <GuestLayout
        panel-badge="Admin security"
        panel-title="Verify it’s really you"
        panel-description="For your protection, admin access requires a one-time code sent to your mobile number."
    >
        <template #title>Two-factor authentication</template>
        <template #subtitle>
            Enter the verification code sent to your phone to continue to the admin panel.
        </template>

        <div
            v-if="usesFakeSms"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950"
        >
            <p class="font-semibold">SMS not configured</p>
            <p class="mt-2 leading-6 text-amber-900">
                Twilio Verify credentials are missing, so no text message was sent.
                For local testing, enter code
                <span class="font-mono font-bold">{{ fakeOtpCode }}</span>.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
        >
            {{ status }}
        </div>

        <div
            v-if="sendForm.errors.code && !lockedOut && !needsPhone"
            class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
            {{ sendForm.errors.code }}
        </div>

        <div
            v-if="needsPhone"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950"
        >
            <p class="font-semibold">Phone number required</p>
            <p class="mt-2 leading-6 text-amber-900">
                Your admin account must have a mobile number on file before two-factor authentication can be enabled.
            </p>
            <Link
                :href="route('admin.profile.index')"
                class="mt-4 inline-flex items-center rounded-xl bg-amber-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-800"
            >
                Add phone in profile
            </Link>
        </div>

        <div
            v-else-if="lockedOut"
            class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900"
        >
            <p class="font-semibold">Too many failed attempts</p>
            <p class="mt-2 leading-6">
                For security, verification is temporarily locked. Sign out and sign in again to retry.
            </p>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="mt-4 inline-flex items-center rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-800 transition hover:bg-red-100"
            >
                Sign out
            </Link>
        </div>

        <form v-else class="space-y-6" @submit.prevent="submitOtp">
            <p class="text-sm text-stone-600">
                <template v-if="codeSent">
                    We sent a 6-digit code by SMS to
                    <span class="font-semibold text-stone-800">{{ maskedPhone || 'your phone' }}</span>.
                </template>
                <template v-else>
                    Preparing your verification code…
                </template>
            </p>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-stone-700">
                    Verification code
                    <span class="ml-0.5 text-red-500">*</span>
                </label>
                <div class="flex justify-center gap-2" @paste="handleOtpPaste">
                    <input
                        v-for="(_, index) in otpDigits"
                        :key="index"
                        :ref="(element) => setOtpInputRef(element, index)"
                        :value="otpDigits[index]"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="1"
                        class="h-12 w-10 rounded-lg border border-stone-300 bg-white text-center text-base font-semibold text-stone-900 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 sm:h-12 sm:w-11"
                        :class="otpForm.errors.code ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20' : ''"
                        @input="handleOtpInput(index, $event)"
                        @keydown="handleOtpKeydown(index, $event)"
                    />
                </div>
                <p v-if="otpForm.errors.code" class="text-center text-xs text-red-600">{{ otpForm.errors.code }}</p>
                <p v-else class="text-center text-xs text-stone-500">{{ attemptsRemaining }} attempt(s) remaining</p>
            </div>

            <div class="flex justify-center">
                <button
                    type="button"
                    class="text-sm font-semibold text-primary-700 transition hover:text-primary-800 disabled:opacity-50"
                    :disabled="!canResend"
                    @click="resendOtp"
                >
                    <span v-if="resendCooldownSeconds > 0">Resend in {{ resendCooldownSeconds }}s</span>
                    <span v-else-if="sendForm.processing || isSending">Sending…</span>
                    <span v-else>Resend code</span>
                </button>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-flex items-center justify-center rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-50"
                >
                    Sign out
                </Link>

                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    class="min-w-[10rem]"
                    :loading="otpForm.processing"
                    :disabled="!canSubmit || otpForm.processing"
                >
                    Verify & continue
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
