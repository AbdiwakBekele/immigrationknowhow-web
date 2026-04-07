<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
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
});

const page = usePage();

const phoneForm = useForm({
    phone: '',
});

const countryIso = ref('US');
const phoneLocal = ref('');

function flagEmoji(iso2) {
    if (!iso2 || iso2.length !== 2) {
        return '';
    }
    const u = iso2.toUpperCase();
    const base = 0x1f1e6;
    return String.fromCodePoint(base + u.charCodeAt(0) - 65, base + u.charCodeAt(1) - 65);
}

function initFromStored(digits) {
    if (!digits) {
        countryIso.value = 'US';
        phoneLocal.value = '';
        return;
    }
    const opts = [...props.phoneDialOptions].sort((a, b) => {
        const len = String(b.dial).length - String(a.dial).length;
        if (len !== 0) {
            return len;
        }
        return String(a.value).localeCompare(String(b.value));
    });
    for (const opt of opts) {
        const d = String(opt.dial);
        if (digits.startsWith(d)) {
            countryIso.value = opt.value;
            phoneLocal.value = digits.slice(d.length);
            return;
        }
    }
    countryIso.value = 'US';
    phoneLocal.value = digits;
}

onMounted(() => {
    initFromStored(String(props.phone || '').replace(/\D/g, ''));
});

watch(
    () => props.phone,
    (p) => initFromStored(String(p || '').replace(/\D/g, '')),
);

const selectedDial = computed(() => {
    const o = props.phoneDialOptions.find((x) => x.value === countryIso.value);
    return o ? String(o.dial) : '1';
});

const selectedCountryLabel = computed(() => {
    const o = props.phoneDialOptions.find((x) => x.value === countryIso.value);
    return o?.label ?? '';
});

const fullDigits = computed(() => {
    const local = phoneLocal.value.replace(/\D/g, '');
    return `${selectedDial.value}${local}`;
});

function syncPhoneToForm() {
    phoneForm.phone = fullDigits.value;
}

const otpForm = useForm({
    code: '',
});

const showOtpModal = ref(!!page.props.flash?.otp_sent);

watch(
    () => page.props.flash?.otp_sent,
    (v) => {
        if (v) {
            showOtpModal.value = true;
        }
    },
);

const maskedPhone = computed(() => {
    const p = fullDigits.value;
    if (p.length < 4) {
        return phoneLocal.value || p || 'your number';
    }
    return `••••••${p.slice(-4)}`;
});

const submitPhone = () => {
    syncPhoneToForm();
    phoneForm.post(route('verify-phone.send'), {
        preserveScroll: true,
        onSuccess: () => {
            showOtpModal.value = true;
            otpForm.reset('code');
        },
    });
};

const submitOtp = () => {
    otpForm.post(route('verify-phone.verify'), {
        preserveScroll: true,
    });
};

const resendOtp = () => {
    otpForm.clearErrors();
    syncPhoneToForm();
    phoneForm.post(route('verify-phone.send'), {
        preserveScroll: true,
    });
};

const closeModal = () => {
    if (!phoneForm.processing && !otpForm.processing) {
        showOtpModal.value = false;
    }
};
</script>

<template>
    <Head title="Verify phone" />

    <GuestLayout>
        <template #title>Verify your phone</template>
        <template #subtitle>We will send a 6-digit code to confirm your number.</template>

        <form class="space-y-6" @submit.prevent="submitPhone">
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium text-neutral-700">Cell number</span>
                    <span class="text-sm text-red-500">*</span>
                </div>
                <div
                    class="flex overflow-hidden rounded-xl border transition-all duration-200 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/20"
                    :class="phoneForm.errors.phone ? 'border-red-300 ring-2 ring-red-500/20' : 'border-neutral-300'"
                >
                    <Listbox v-model="countryIso" as="div" class="relative shrink-0">
                        <ListboxButton
                            type="button"
                            class="flex h-[46px] items-center gap-1.5 border-r border-neutral-200 bg-white py-2 pl-3 pr-8 text-left text-sm text-neutral-900 focus:outline-none"
                        >
                            <span class="text-base leading-none" aria-hidden="true">{{ flagEmoji(countryIso) }}</span>
                            <span class="font-medium tabular-nums">+{{ selectedDial }}</span>
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                <ChevronDownIcon class="h-4 w-4 text-neutral-400" aria-hidden="true" />
                            </span>
                        </ListboxButton>
                        <transition
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <ListboxOptions
                                class="absolute z-20 mt-1 max-h-60 min-w-[16rem] overflow-auto rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
                            >
                                <ListboxOption
                                    v-for="opt in phoneDialOptions"
                                    v-slot="{ active, selected }"
                                    :key="opt.value"
                                    :value="opt.value"
                                    as="template"
                                >
                                    <li
                                        :class="[
                                            'relative cursor-pointer select-none py-2.5 pl-10 pr-4',
                                            active ? 'bg-primary-50 text-primary-900' : 'text-neutral-900',
                                        ]"
                                    >
                                        <span
                                            v-if="selected"
                                            class="absolute inset-y-0 left-0 flex items-center pl-3 text-primary-600"
                                        >
                                            <span class="h-2 w-2 rounded-full bg-current" />
                                        </span>
                                        <span class="flex items-center gap-2">
                                            <span class="text-base leading-none" aria-hidden="true">{{
                                                flagEmoji(opt.value)
                                            }}</span>
                                            <span class="truncate">{{ opt.label }}</span>
                                            <span class="ml-auto shrink-0 tabular-nums text-neutral-500">+{{ opt.dial }}</span>
                                        </span>
                                    </li>
                                </ListboxOption>
                            </ListboxOptions>
                        </transition>
                    </Listbox>
                    <input
                        id="verify-phone-local"
                        v-model="phoneLocal"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel-national"
                        placeholder="6636453463"
                        required
                        class="min-w-0 flex-1 border-0 bg-white px-4 py-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                        :aria-invalid="!!phoneForm.errors.phone"
                    />
                </div>
                <p v-if="phoneForm.errors.phone" class="text-xs text-red-600">{{ phoneForm.errors.phone }}</p>
                <p v-else class="text-xs text-neutral-500">
                    Choose your country, enter your number without the country code, then tap Send code.
                </p>
            </div>
            <Button type="submit" variant="primary" size="lg" class="w-full" :loading="phoneForm.processing">
                Send code
            </Button>
        </form>

        <template #footer>
            <button
                type="button"
                class="text-sm text-neutral-500 hover:text-neutral-700"
                @click="router.post(route('logout'))"
            >
                Sign out
            </button>
        </template>
    </GuestLayout>

    <TransitionRoot appear :show="showOtpModal" as="template">
        <Dialog as="div" class="relative z-50" @close="closeModal">
            <TransitionChild
                as="template"
                enter="duration-200 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-150 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6">
                <div class="flex min-h-full items-center justify-center">
                    <TransitionChild
                        as="template"
                        enter="duration-200 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-150 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel
                            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl ring-1 ring-neutral-900/5"
                        >
                            <DialogTitle class="text-lg font-semibold text-neutral-900">Enter verification code</DialogTitle>
                            <p class="mt-1 text-sm text-neutral-600">
                                We sent a 6-digit code to {{ maskedPhone }}.
                            </p>

                            <form class="mt-6 space-y-4" @submit.prevent="submitOtp">
                                <Input
                                    v-model="otpForm.code"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="6"
                                    autocomplete="one-time-code"
                                    label="Code"
                                    placeholder="000000"
                                    :error="otpForm.errors.code"
                                />
                                <Button type="submit" variant="primary" class="w-full" :loading="otpForm.processing">
                                    Verify and continue
                                </Button>
                            </form>

                            <div class="mt-4 flex items-center justify-between border-t border-neutral-100 pt-4">
                                <button
                                    type="button"
                                    class="text-sm font-medium text-primary-600 hover:text-primary-500 disabled:opacity-50"
                                    :disabled="phoneForm.processing"
                                    @click="resendOtp"
                                >
                                    Resend code
                                </button>
                                <button type="button" class="text-sm text-neutral-500 hover:text-neutral-700" @click="closeModal">
                                    Edit number
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
