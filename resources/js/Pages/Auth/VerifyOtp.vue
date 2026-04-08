<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/vue';
import { ArrowLeftIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    phone: {
        type: String,
        default: '',
    },
    isProvider: {
        type: Boolean,
        default: false,
    },
    phoneDialOptions: {
        type: Array,
        required: true,
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
    // Keep US as default and avoid auto-switching country code from saved digits.
    phoneLocal.value = digits.startsWith('1') ? digits.slice(1) : digits;
}

onMounted(() => {
    initFromStored(String(props.phone || '').replace(/\D/g, ''));
    hasSentOtp.value = Boolean(String(props.phone || '').replace(/\D/g, ''));
});

watch(
    () => props.phone,
    (p) => initFromStored(String(p || '').replace(/\D/g, '')),
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
    otpForm.post(route('address-detail.verify'), {
        preserveScroll: true,
        onSuccess: () => {
            router.visit(
                props.isProvider
                    ? route('onboarding.index', { step: 4 })
                    : route('onboarding.index', { step: 2 }),
            );
        },
    });
};

const resendOtp = () => {
    resendForm.phone = fullDigits.value;
    resendForm.post(route('address-detail.send'), {
        preserveScroll: true,
        onSuccess: () => {
            hasSentOtp.value = true;
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

    router.visit(route('address-detail'));
};
</script>

<template>
    <Head title="Phone number" />

    <GuestLayout>
        <template #title>{{ hasSentOtp ? 'Enter verification code' : 'Add your phone number' }}</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="3" :total-steps="5" />
        </template>
        <template #side-image>
            <img
                src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1400&q=80"
                alt="Phone verification"
                class="h-full w-full object-cover"
            />
        </template>

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
                        id="verify-otp-phone-local"
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
            <p v-if="hasSentOtp" class="text-sm text-neutral-600">We sent a 6-digit code to {{ maskedPhone }}.</p>
            <div class="flex items-center justify-between gap-3">
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
            <div class="flex items-center justify-between gap-3">
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

        <template #footer />
    </GuestLayout>
</template>
