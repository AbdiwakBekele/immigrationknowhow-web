<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue';
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
});

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

const flagClass = (iso2) => `fi fi-${String(iso2 || '').toLowerCase()}`;

function initFromStored(digits) {
    countryIso.value = 'US';
    if (!digits) {
        phoneLocal.value = '';
        return;
    }
    phoneLocal.value = digits.startsWith('1') ? digits.slice(1) : digits;
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

const fullDigits = computed(() => {
    const local = phoneLocal.value.replace(/\D/g, '');
    return `${selectedDial.value}${local}`;
});

const maskedPhone = computed(() => {
    const typed = fullDigits.value.replace(/\D/g, '');
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
    console.log('[FLOW_DEBUG] Step 3 verify attempt -> expecting Step 4 on success', {
        codeLength: String(otpForm.code || '').length,
    });

    otpForm.post(route('address-detail.verify'), {
        preserveScroll: true,
        onSuccess: (page) => {
            console.log('[FLOW_DEBUG] Step 3 verify success', {
                nextUrl: page?.url || window.location.href,
                expectedStep: 4,
            });
            router.visit(route('onboarding.index', { step: 4 }));
        },
        onError: (errors) => {
            console.log('[FLOW_DEBUG] Step 3 verify validation/errors', errors);
        },
    });
};

const resendOtp = () => {
    resendForm.phone = fullDigits.value;
    console.log('[FLOW_DEBUG] Step 3 send/resend OTP', {
        phoneLast4: fullDigits.value.slice(-4),
        hasSentOtp: hasSentOtp.value,
    });
    resendForm.post(route('address-detail.send'), {
        preserveScroll: true,
        onSuccess: () => {
            hasSentOtp.value = true;
            console.log('[FLOW_DEBUG] Step 3 send/resend OTP success');
        },
        onError: (errors) => {
            console.log('[FLOW_DEBUG] Step 3 send/resend OTP errors', errors);
        },
    });
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
</script>

<template>
    <div class="space-y-5">
        <p class="text-sm text-neutral-600">
            We use your number for account security and important updates. You will receive a text message with a verification code.
        </p>

        <form v-if="!hasSentOtp" class="space-y-4" @submit.prevent="resendOtp">
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium text-neutral-700">Cell number</span>
                    <span class="text-sm text-red-500">*</span>
                </div>
                <div
                    class="flex rounded-xl border transition-all duration-200 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/20"
                    :class="resendForm.errors.phone ? 'border-red-300 ring-2 ring-red-500/20' : 'border-neutral-300'"
                >
                    <Listbox v-model="countryIso" as="div" class="relative shrink-0 border-r border-neutral-200 bg-white">
                        <ListboxButton
                            type="button"
                            class="flex h-[46px] min-w-[7.5rem] items-center gap-2 py-2 pl-3 pr-8 text-left text-sm text-neutral-900 focus:outline-none"
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
                        v-model="phoneLocal"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel-national"
                        placeholder="6636453463"
                        required
                        class="min-w-0 flex-1 border-0 bg-white px-4 py-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                        :aria-invalid="!!resendForm.errors.phone"
                    />
                </div>
                <p v-if="resendForm.errors.phone" class="text-xs text-red-600">{{ resendForm.errors.phone }}</p>
                <p class="text-xs text-neutral-500">Use a mobile number that can receive SMS.</p>
            </div>
            <div class="flex items-center justify-between gap-3 pt-2">
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
                <Button type="submit" variant="primary" size="sm" class="min-w-[10rem] !py-1.5 !text-xs !rounded-md" :loading="resendForm.processing">
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
                    :disabled="resendForm.processing"
                    @click="resendOtp"
                >
                    Resend code
                </button>
            </div>
            <div class="flex items-center justify-between gap-3 pt-2">
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
                <Button type="submit" variant="primary" size="sm" class="min-w-[10rem] !py-1.5 !text-xs !rounded-md" :loading="otpForm.processing">
                    Verify & Continue
                </Button>
            </div>
        </form>
    </div>
</template>
