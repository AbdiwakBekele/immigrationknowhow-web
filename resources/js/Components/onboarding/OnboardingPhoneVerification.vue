<script setup>
import { ref, computed, nextTick, onMounted, watch, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/vue';
import { ArrowLeftIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    phone: {
        type: String,
        default: '',
    },
    phoneDialOptions: {
        type: Array,
        required: true,
    },
    isProvider: {
        type: Boolean,
        default: false,
    },
    useExternalActions: {
        type: Boolean,
        default: false,
    },
    sendRouteName: {
        type: String,
        default: 'address-detail.send',
    },
    verifyRouteName: {
        type: String,
        default: 'address-detail.verify',
    },
});

const emit = defineEmits(['verified']);

const otpForm = useForm({
    code: '',
});

const OTP_LENGTH = 6;

const resendForm = useForm({
    phone: '',
});

const countryIso = ref('US');
const phoneLocal = ref('');
const hasSentOtp = ref(false);
const otpInputs = ref([]);
const isSendingOtp = ref(false);
const isVerifyingOtp = ref(false);
const resendCooldownSeconds = ref(0);
let resendCooldownInterval = null;

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
        resendCooldownInterval = null;
    }
});

const flagClass = (iso2) => `fi fi-${String(iso2 || '').toLowerCase()}`;

const normalizeLocalDigits = (value) => String(value || '').replace(/\D/g, '').slice(0, 15);

const formatUsPhone = (digits) => {
    const clean = normalizeLocalDigits(digits);
    if (clean.length <= 3) return clean;
    if (clean.length <= 6) return `${clean.slice(0, 3)}-${clean.slice(3)}`;
    return `${clean.slice(0, 3)}-${clean.slice(3, 6)}-${clean.slice(6)}`;
};

function initFromStored(digits) {
    countryIso.value = 'US';
    if (!digits) {
        phoneLocal.value = '';
        return;
    }
    phoneLocal.value = normalizeLocalDigits(digits.startsWith('1') ? digits.slice(1) : digits);
}

onMounted(() => {
    const digits = String(props.phone || '').replace(/\D/g, '');
    initFromStored(digits);
    hasSentOtp.value = digits.length >= 10;
});

watch(
    () => props.phone,
    (p) => {
        const digits = String(p || '').replace(/\D/g, '');
        initFromStored(digits);
        if (digits.length >= 10) {
            hasSentOtp.value = true;
        }
    },
);

watch(hasSentOtp, (sent) => {
    if (sent) {
        nextTick(() => {
            otpInputs.value[0]?.focus();
        });
    }
});

const selectedDial = computed(() => {
    const o = props.phoneDialOptions.find((x) => x.value === countryIso.value);
    return o ? String(o.dial) : '1';
});

const fullE164 = computed(() => {
    const local = normalizeLocalDigits(phoneLocal.value);
    const dial = String(selectedDial.value || '').replace(/\D/g, '');
    const localNormalized = countryIso.value === 'US'
        ? local
        : local.replace(/^0+/, ''); // common international trunk prefix

    return `+${dial}${localNormalized}`;
});

const phoneLocalDisplay = computed(() => formatUsPhone(phoneLocal.value));

const maskedPhone = computed(() => {
    const typed = fullE164.value.replace(/\D/g, '');
    const digits = typed || String(props.phone || '').replace(/\D/g, '');
    if (digits.length < 4) {
        return 'your phone number';
    }
    return `••••••${digits.slice(-4)}`;
});

const otpDigits = computed(() => {
    const digits = String(otpForm.code || '').replace(/\D/g, '').slice(0, OTP_LENGTH);
    return Array.from({ length: OTP_LENGTH }, (_, index) => digits[index] || '');
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

        return;
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        focusOtpInput(index - 1);
    }

    if (event.key === 'ArrowRight' && index < OTP_LENGTH - 1) {
        event.preventDefault();
        focusOtpInput(index + 1);
    }
};

const handleOtpPaste = (event) => {
    const pastedDigits = String(event.clipboardData?.getData('text') || '')
        .replace(/\D/g, '')
        .slice(0, OTP_LENGTH);

    if (!pastedDigits) {
        return;
    }

    event.preventDefault();
    otpForm.code = pastedDigits;
    otpForm.clearErrors('code');
    focusOtpInput(Math.min(pastedDigits.length, OTP_LENGTH) - 1);
};

const submitOtp = () => {
    if (otpForm.processing || isVerifyingOtp.value) return;
    isVerifyingOtp.value = true;
    console.log('[FLOW_DEBUG] Step 3 verify attempt -> expecting Step 4 on success', {
        codeLength: String(otpForm.code || '').length,
    });

    otpForm.post(route(props.verifyRouteName), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            console.log('[FLOW_DEBUG] Step 3 verify success', {
                expectedStep: 4,
            });
            emit('verified');
        },
        onError: (errors) => {
            console.log('[FLOW_DEBUG] Step 3 verify validation/errors', errors);
        },
        onFinish: () => {
            isVerifyingOtp.value = false;
        },
    });
};

const resendOtp = () => {
    if (resendForm.processing || isSendingOtp.value) return;
    if (hasSentOtp.value && resendCooldownSeconds.value > 0) return;
    const localDigits = normalizeLocalDigits(phoneLocal.value);
    if (countryIso.value === 'US') {
        if (localDigits.length !== 10) {
            resendForm.setError('phone', 'Enter a valid 10-digit phone number (123-456-7890).');
            return;
        }
    } else {
        // General international guardrails; Twilio Verify expects E.164 (+ + 8..15 digits total).
        const e164Digits = fullE164.value.replace(/\D/g, '');
        if (e164Digits.length < 8 || e164Digits.length > 15) {
            resendForm.setError('phone', 'Enter a valid phone number for the selected country.');
            return;
        }
    }
    resendForm.clearErrors('phone');

    resendForm.phone = fullE164.value;
    isSendingOtp.value = true;
    console.log('[FLOW_DEBUG] Step 3 send/resend OTP', {
        phoneLast4: fullE164.value.slice(-4),
        hasSentOtp: hasSentOtp.value,
    });
    resendForm.post(route(props.sendRouteName), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            hasSentOtp.value = true;
            if (hasSentOtp.value) {
                startResendCooldown(30);
            }
            console.log('[FLOW_DEBUG] Step 3 send/resend OTP success');
        },
        onError: (errors) => {
            console.log('[FLOW_DEBUG] Step 3 send/resend OTP errors', errors);
        },
        onFinish: () => {
            isSendingOtp.value = false;
        },
    });
};

const handlePhoneInput = (event) => {
    phoneLocal.value = normalizeLocalDigits(event?.target?.value);
    resendForm.clearErrors('phone');
};

const editNumber = () => {
    hasSentOtp.value = false;
    otpForm.code = '';
    otpForm.clearErrors();
    otpInputs.value = [];
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    if (props.isProvider) {
        router.visit(route('address-detail'));
        return;
    }

    router.visit(route('onboarding.index', { step: 2 }));
};

const continueFlow = () => {
    if (isContinuing.value) return;
    if (hasSentOtp.value) {
        submitOtp();
        return;
    }

    resendOtp();
};

const backFlow = () => {
    if (hasSentOtp.value) {
        editNumber();
        return;
    }

    goBack();
};

const isContinuing = computed(() =>
    hasSentOtp.value
        ? (otpForm.processing || isVerifyingOtp.value)
        : (resendForm.processing || isSendingOtp.value),
);

const canResend = computed(() => !(resendForm.processing || isSendingOtp.value || resendCooldownSeconds.value > 0));

defineExpose({
    continueFlow,
    backFlow,
    isContinuing,
    hasSentOtp,
});
</script>

<template>
    <div class="space-y-5">
        <p class="text-sm text-neutral-600">
            We use your number for account security and important updates. You will receive a text message with a verification code.
        </p>

        <form v-if="!hasSentOtp" class="space-y-4" @submit.prevent="resendOtp">
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium text-neutral-700">Phone number</span>
                    <span class="text-sm text-red-500">*</span>
                </div>
                <div
                    class="flex rounded-2xl border bg-white/95 shadow-sm transition-all duration-200 focus-within:border-blue-400 focus-within:ring-4 focus-within:ring-blue-100"
                    :class="resendForm.errors.phone ? 'border-red-300 focus-within:border-red-400 focus-within:ring-4 focus-within:ring-red-100' : 'border-slate-200'"
                >
                    <Listbox v-model="countryIso" as="div" class="relative shrink-0 border-r border-slate-200 bg-transparent">
                        <ListboxButton
                            type="button"
                            class="flex h-[58px] min-w-[7.5rem] items-center gap-2 bg-transparent py-4 pl-5 pr-10 text-left text-base text-slate-900 focus:outline-none"
                        >
                            <span :class="[flagClass(countryIso), 'h-4 w-5 rounded-sm']" aria-hidden="true" />
                            <span class="font-medium tabular-nums">+{{ selectedDial }}</span>
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                <ChevronDownIcon class="h-4 w-4 text-neutral-400" aria-hidden="true" />
                            </span>
                        </ListboxButton>
                        <ListboxOptions
                            class="absolute z-20 mt-1 max-h-64 min-w-[16rem] overflow-auto rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
                        >
                            <ListboxOption
                                v-for="opt in phoneDialOptions"
                                v-slot="{ active }"
                                :key="opt.value"
                                :value="opt.value"
                                as="template"
                            >
                                <li
                                    :class="[
                                        'cursor-pointer select-none px-3 py-2.5',
                                        active ? 'bg-primary-50 text-primary-900' : 'text-neutral-900',
                                    ]"
                                >
                                    <span class="flex items-center gap-2">
                                        <span :class="[flagClass(opt.value), 'h-4 w-5 rounded-sm']" aria-hidden="true" />
                                        <span class="tabular-nums font-medium">+{{ opt.dial }}</span>
                                        <span class="truncate text-neutral-500">{{ opt.label }}</span>
                                    </span>
                                </li>
                            </ListboxOption>
                        </ListboxOptions>
                    </Listbox>
                    <input
                        id="onboarding-verify-phone-local"
                        :value="phoneLocalDisplay"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel-national"
                        placeholder="123-456-7890"
                        required
                        maxlength="12"
                        class="min-w-0 flex-1 rounded-r-2xl border-0 bg-transparent px-5 py-4 text-base text-slate-900 placeholder:text-slate-400 focus:outline-none"
                        :aria-invalid="!!resendForm.errors.phone"
                        @input="handlePhoneInput"
                    />
                </div>
                <p v-if="resendForm.errors.phone" class="text-xs text-red-600">{{ resendForm.errors.phone }}</p>
                <p class="text-xs text-neutral-500">Use a mobile number that can receive SMS.</p>
            </div>
            <div v-if="!useExternalActions" class="flex items-center justify-between gap-3 pt-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="!rounded-md border border-neutral-200 bg-white !px-2.5 !py-1.5 !text-xs text-neutral-700 hover:bg-neutral-50"
                    @click="goBack"
                >
                    <ArrowLeftIcon class="h-3.5 w-3.5" />
                    Back
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    size="sm"
                    class="min-w-[10rem] !py-1.5 !text-xs !rounded-md"
                    :loading="resendForm.processing || isSendingOtp"
                    :disabled="resendForm.processing || isSendingOtp"
                >
                    Continue
                </Button>
            </div>
        </form>

        <form v-else class="space-y-4" @submit.prevent="submitOtp">
            <p class="text-sm text-neutral-600">We sent a 6-digit code to {{ maskedPhone }}.</p>
            <div class="space-y-2">
                <label class="block text-sm font-medium text-neutral-700">
                    Code
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
                        class="h-12 w-10 rounded-lg border border-neutral-300 bg-white text-center text-base font-semibold text-neutral-900 transition-all duration-200 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 sm:h-12 sm:w-11"
                        :class="otpForm.errors.code ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20' : ''"
                        @input="handleOtpInput(index, $event)"
                        @keydown="handleOtpKeydown(index, $event)"
                    />
                </div>
                <p v-if="otpForm.errors.code" class="text-xs text-red-600">{{ otpForm.errors.code }}</p>
            </div>
            <div class="flex justify-center">
                <button
                    type="button"
                    class="text-sm font-medium text-primary-600 hover:text-primary-500 disabled:opacity-50"
                    :disabled="!canResend"
                    @click="resendOtp"
                >
                    <span v-if="resendCooldownSeconds > 0">
                        Resend in {{ resendCooldownSeconds }}s
                    </span>
                    <span v-else>
                        Resend code
                    </span>
                </button>
            </div>
            <div v-if="!useExternalActions" class="flex items-center justify-between gap-3 pt-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="!rounded-md border border-neutral-200 bg-white !px-2.5 !py-1.5 !text-xs text-neutral-700 hover:bg-neutral-50"
                    @click="editNumber"
                >
                    <ArrowLeftIcon class="h-3.5 w-3.5" />
                    Back
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    size="sm"
                    class="min-w-[10rem] !py-1.5 !text-xs !rounded-md"
                    :loading="otpForm.processing || isVerifyingOtp"
                    :disabled="otpForm.processing || isVerifyingOtp"
                >
                    Verify & Continue
                </Button>
            </div>
        </form>
    </div>
</template>
