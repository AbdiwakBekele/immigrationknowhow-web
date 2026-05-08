<script setup>
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import LocationCountryStatePick from '@/Components/address/LocationCountryStatePick.vue';
import { ArrowLeftIcon } from '@heroicons/vue/20/solid';
import { parsePlaceToAddressFields } from '@/utils/googlePlaceAddress';
import { SIGNUP_FLOW_STEPS_PROVIDER, SIGNUP_FLOW_STEPS_USER, SIGNUP_STEP } from '@/constants/authFlowProgress';

const providerServiceLocationOptions = [
    { value: 'usa', label: 'USA' },
    { value: 'uk', label: 'UK' },
    { value: 'europe', label: 'Europe' },
    { value: 'canada', label: 'Canada' },
    { value: 'other', label: 'Other' },
];
const serviceLocationWithStates = ['usa'];
const coverageCountryToIso = {
    usa: 'US',
    canada: 'CA',
    uk: 'GB',
};
const coverageStateOptions = ref([]);

const props = defineProps({
    isProvider: { type: Boolean, default: false },
    address: { type: String, default: '' },
    city: { type: String, default: '' },
    state: { type: String, default: '' },
    country: { type: String, default: 'US' },
    postal_code: { type: String, default: '' },
    county: { type: String, default: '' },
    location_label: { type: String, default: '' },
    preferred_language: { type: String, default: 'en' },
    countryOptions: { type: Array, required: true },
    stateOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, required: true },
    coverageArea: {
        type: Object,
        default: () => ({ country: 'US', state: '', postal_code: '' }),
    },
    serviceArea: {
        type: Object,
        default: () => ({
            remote: false,
            in_person: true,
            radius: null,
            areas: [],
            serve_client_in_location: false,
        }),
    },
});

const pageTitle = computed(() => (props.isProvider ? 'Coverage area' : 'Address details'));

const progressTotal = computed(() => (props.isProvider ? SIGNUP_FLOW_STEPS_PROVIDER : SIGNUP_FLOW_STEPS_USER));

const phoneForm = useForm({
    serve_client_in_location: props.serviceArea?.serve_client_in_location ?? false,
    address: props.address,
    city: props.city,
    state: props.state,
    country: props.country,
    postal_code: props.postal_code,
    county: props.county,
    location_label: props.location_label,
    preferred_language: props.preferred_language,
    coverage_country: props.coverageArea?.country || 'usa',
    coverage_state: props.coverageArea?.state || '',
    coverage_postal_code: props.coverageArea?.postal_code || '',
    service_area: {
        remote: props.serviceArea?.remote ?? false,
        in_person: props.serviceArea?.in_person ?? true,
        radius: props.serviceArea?.radius ?? null,
        areas: props.serviceArea?.areas ?? [],
    },
});

const autocompleteStatus = ref('');
let autocompleteDebounce = null;

const loadCoverageStateOptions = async (coverageCountry) => {
    const iso = coverageCountryToIso[String(coverageCountry || '').toLowerCase()] || null;
    if (!iso) {
        coverageStateOptions.value = [];
        return;
    }

    try {
        const response = await fetch(route('locations.states', { country: iso }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) {
            throw new Error(`State lookup failed (${response.status})`);
        }
        const payload = await response.json();
        coverageStateOptions.value = Array.isArray(payload.states) ? payload.states : [];
    } catch {
        coverageStateOptions.value = [];
    }
};

onMounted(() => {
    coverageStateOptions.value = [...(props.stateOptions || [])];
    void loadCoverageStateOptions(phoneForm.coverage_country);
});

watch(
    () => phoneForm.coverage_country,
    async (next, prev) => {
        if (next === prev) return;
        phoneForm.coverage_state = '';
        phoneForm.coverage_postal_code = '';
        await loadCoverageStateOptions(next);
    },
);

const runStreetAutocomplete = async () => {
    const query = String(phoneForm.address || '').trim();
    if (query.length < 3) {
        autocompleteStatus.value = '';
        return;
    }

    try {
        const endpoint = route('address-detail.autocomplete', { query });
        const response = await fetch(endpoint, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Backend autocomplete failed (${response.status})`);
        }

        const payload = await response.json();
        if (!payload?.ok || !payload?.data) {
            autocompleteStatus.value = '';
            return;
        }

        const parsed = parsePlaceToAddressFields(payload.data);
        phoneForm.address = parsed.address_line_1;
        phoneForm.country = parsed.country;
        await nextTick();
        phoneForm.city = parsed.city;
        phoneForm.state = parsed.state;
        phoneForm.postal_code = parsed.postal_code;
        autocompleteStatus.value = '';
    } catch {
        autocompleteStatus.value = 'Unable to autocomplete this address right now.';
    }
};

const handleStreetAddressChange = () => {
    if (autocompleteDebounce) {
        clearTimeout(autocompleteDebounce);
    }
    autocompleteDebounce = setTimeout(runStreetAutocomplete, 300);
};

const submitAddress = () => {
    const debugPayload = {
        isProvider: props.isProvider,
        coverage_country: phoneForm.coverage_country,
        coverage_state: phoneForm.coverage_state,
        coverage_postal_code: phoneForm.coverage_postal_code,
        city: phoneForm.city,
        state: phoneForm.state,
        country: phoneForm.country,
        postal_code: phoneForm.postal_code,
    };

    console.log('[FLOW_DEBUG] Step 2 submit -> expecting Step 3 next', debugPayload);

    phoneForm.post(route('address-detail.send'), {
        preserveScroll: true,
        onStart: () => {
            console.log('[FLOW_DEBUG] Step 2 request started', { endpoint: route('address-detail.send') });
        },
        onSuccess: (page) => {
            console.log('[FLOW_DEBUG] Step 2 request success', {
                nextUrl: page?.url || window.location.href,
                expectedStep: 3,
            });
        },
        onError: (errors) => {
            console.log('[FLOW_DEBUG] Step 2 request validation errors', errors);
        },
        onFinish: () => {
            console.log('[FLOW_DEBUG] Step 2 request finished', { processing: phoneForm.processing });
        },
    });
};

const goBack = () => {
    router.visit(route('register'));
};
</script>

<template>
    <Head :title="pageTitle" />

    <GuestLayout>
        <template #title>{{ pageTitle }}</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="SIGNUP_STEP.ADDRESS" :total-steps="progressTotal" />
        </template>
        <template #side-image>
            <img
                src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1400&q=80"
                alt="Planning and paperwork"
                class="h-full w-full object-cover"
            />
        </template>

        <form class="space-y-7" @submit.prevent="submitAddress">
            <div v-if="isProvider" class="space-y-5">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Coverage area</p>
                <p class="text-sm text-neutral-600">
                    Select your service location where you offer services. You will enter your full business address in a later step.
                </p>

                <label class="mt-1 flex cursor-pointer items-center gap-2">
                    <input
                        v-model="phoneForm.serve_client_in_location"
                        type="checkbox"
                        class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                    />
                    <span class="text-sm text-neutral-700">I serve the client in their location</span>
                </label>

                <Select
                    v-model="phoneForm.coverage_country"
                    :options="providerServiceLocationOptions"
                    label="Service Location"
                    placeholder="Select service location"
                    size="auth"
                    required
                />

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Select
                        v-if="serviceLocationWithStates.includes(phoneForm.coverage_country) && coverageStateOptions.length > 0"
                        v-model="phoneForm.coverage_state"
                        :options="coverageStateOptions"
                        label="State"
                        placeholder="Select state"
                        size="auth"
                        required
                    />
                    <Input
                        v-else
                        v-model="phoneForm.coverage_state"
                        label="State / region"
                        placeholder="Enter state or region"
                        size="compact"
                        :required="true"
                    />

                    <Input
                        v-model="phoneForm.coverage_postal_code"
                        :label="phoneForm.coverage_country === 'usa' ? 'City / ZIP code' : 'City (optional)'"
                        :placeholder="phoneForm.coverage_country === 'usa' ? 'Enter city or ZIP code' : 'Enter city'"
                        size="compact"
                        :required="phoneForm.coverage_country === 'usa'"
                    />
                </div>
                <p v-if="phoneForm.errors.coverage_postal_code" class="text-sm font-medium text-red-600">{{ phoneForm.errors.coverage_postal_code }}</p>
                <p v-if="phoneForm.errors.coverage_country" class="text-sm font-medium text-red-600">{{ phoneForm.errors.coverage_country }}</p>
                <p v-if="phoneForm.errors.coverage_state" class="text-sm font-medium text-red-600">{{ phoneForm.errors.coverage_state }}</p>

                <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Service area</h3>
                    <p class="text-xs text-neutral-500">How you meet clients in your coverage region.</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                v-model="phoneForm.service_area.in_person"
                                type="checkbox"
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">In-person services</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                v-model="phoneForm.service_area.remote"
                                type="checkbox"
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">Remote / virtual services</span>
                        </label>
                    </div>
                    <Input
                        v-if="phoneForm.service_area.in_person"
                        v-model="phoneForm.service_area.radius"
                        type="number"
                        label="Service radius (miles)"
                        placeholder="e.g. 25"
                        size="compact"
                    />
                </div>

                <Select
                    v-model="phoneForm.preferred_language"
                    :options="languageOptions"
                    label="Language"
                    placeholder="Select language"
                    :error="phoneForm.errors.preferred_language"
                    size="auth"
                    required
                />
            </div>

            <div v-else class="space-y-5">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Address information</p>
                <label class="mt-1 flex cursor-pointer items-center gap-2">
                    <input
                        v-model="phoneForm.serve_client_in_location"
                        type="checkbox"
                        class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                    />
                    <span class="text-sm text-neutral-700">I serve the client in their location</span>
                </label>
                <div>
                    <label for="street-address" class="mb-3 block text-base font-medium text-slate-700">
                        Street address
                    </label>
                    <input
                        id="street-address"
                        v-model="phoneForm.address"
                        type="text"
                        placeholder="Street, apt / unit"
                        autocomplete="street-address"
                        class="w-full rounded-2xl border bg-white/95 px-5 py-4 text-base text-slate-900 shadow-sm outline-none transition duration-200 placeholder:text-slate-400"
                        :class="phoneForm.errors.address
                            ? 'border-red-300 focus:border-red-400 focus:ring-4 focus:ring-red-100'
                            : 'border-slate-200 focus:border-blue-400 focus:ring-4 focus:ring-blue-100'"
                        @input="handleStreetAddressChange"
                        @change="handleStreetAddressChange"
                    />
                    <p v-if="phoneForm.errors.address" class="mt-2 text-sm font-medium text-red-600">{{ phoneForm.errors.address }}</p>
                    <p v-else-if="autocompleteStatus" class="mt-2 text-sm text-slate-500">{{ autocompleteStatus }}</p>
                </div>

                <LocationCountryStatePick
                    v-model:country="phoneForm.country"
                    v-model:state="phoneForm.state"
                    v-model:city="phoneForm.city"
                    v-model:postal-code="phoneForm.postal_code"
                    v-model:county="phoneForm.county"
                    v-model:location-label="phoneForm.location_label"
                    :country-options="countryOptions"
                    :initial-state-options="stateOptions"
                    location-mode="google"
                />

                <p v-if="phoneForm.errors.country" class="text-sm font-medium text-red-600">{{ phoneForm.errors.country }}</p>
                <p v-if="phoneForm.errors.state" class="text-sm font-medium text-red-600">{{ phoneForm.errors.state }}</p>
                <p v-if="phoneForm.errors.city" class="text-sm font-medium text-red-600">{{ phoneForm.errors.city }}</p>
                <p v-if="phoneForm.errors.postal_code" class="text-sm font-medium text-red-600">{{ phoneForm.errors.postal_code }}</p>

                <Select
                    v-model="phoneForm.preferred_language"
                    :options="languageOptions"
                    label="Language"
                    placeholder="Select language"
                    :error="phoneForm.errors.preferred_language"
                    size="auth"
                    required
                />
            </div>

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    size="md"
                    class="justify-center border border-stone-300 bg-white text-stone-700 hover:bg-stone-50 sm:justify-start"
                    @click="goBack"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    :loading="phoneForm.processing"
                    class="min-w-[11rem]"
                >
                    Continue
                </Button>
            </div>
        </form>

        <template #footer />
    </GuestLayout>
</template>
