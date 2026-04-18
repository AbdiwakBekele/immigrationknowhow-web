<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import LocationCountryStatePick from '@/Components/address/LocationCountryStatePick.vue';
import { ArrowLeftIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
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
});

const phoneForm = useForm({
    serve_client_in_location: false,
    address: props.address,
    city: props.city,
    state: props.state,
    country: props.country,
    postal_code: props.postal_code,
    county: props.county,
    location_label: props.location_label,
    preferred_language: props.preferred_language,
});

const autocompleteStatus = ref('');
let autocompleteDebounce = null;

const getAddressComponent = (components, type) =>
    components.find((component) => Array.isArray(component.types) && component.types.includes(type));

const componentLongText = (component) => component?.long_name ?? component?.longText ?? '';

/** Street line only — city, state, ZIP, and country come from the same internal lookup as onboarding. */
const syncStreetLineFromPlace = (place) => {
    const components = place.addressComponents ?? place.address_components ?? [];
    const streetNumber = componentLongText(getAddressComponent(components, 'street_number'));
    const route = componentLongText(getAddressComponent(components, 'route'));
    const street = [streetNumber, route].filter(Boolean).join(' ').trim();

    if (street) {
        phoneForm.address = street;
    } else if (place.formattedAddress || place.formatted_address) {
        phoneForm.address = place.formattedAddress ?? place.formatted_address;
    }
};

const runStreetAutocomplete = async () => {
    const query = String(phoneForm.address || '').trim();
    if (query.length < 3) {
        autocompleteStatus.value = '';
        return;
    }

    try {
        const endpoint = route('address-detail.autocomplete', {
            query,
        });
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

        syncStreetLineFromPlace(payload.data);
        autocompleteStatus.value = '';
    } catch {
        autocompleteStatus.value = 'Unable to autocomplete this address right now.';
    }
};

const handleStreetAddressChange = () => {
    if (autocompleteDebounce) {
        clearTimeout(autocompleteDebounce);
    }

    autocompleteDebounce = setTimeout(() => {
        runStreetAutocomplete();
    }, 300);
};

const submitAddress = () => {
    phoneForm.post(route('address-detail.send'), {
        preserveScroll: true,
    });
};

const goBack = () => {
    router.visit(route('register'));
};
</script>

<template>
    <Head title="Address details" />

    <GuestLayout>
        <template #title>Address details</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="2" :total-steps="5" />
        </template>
        <template #side-image>
            <img
                src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1400&q=80"
                alt="Planning and paperwork"
                class="h-full w-full object-cover"
            />
        </template>

        <form class="space-y-7" @submit.prevent="submitAddress">
            <div class="space-y-5">
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
