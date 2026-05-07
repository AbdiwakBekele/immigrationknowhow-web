<script setup>
import { ref, computed, watch } from 'vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { parsePlaceToAddressFields } from '@/utils/googlePlaceAddress';

const country = defineModel('country', { required: true });
const state = defineModel('state', { required: true });
const city = defineModel('city', { required: true });
const postalCode = defineModel('postalCode', { required: true });
const county = defineModel('county', { default: '' });
const locationLabel = defineModel('locationLabel', { default: '' });

const props = defineProps({
    countryOptions: { type: Array, required: true },
    initialStateOptions: { type: Array, default: () => [] },
    /** Only country + state (e.g. provider coverage area). City/ZIP UI is hidden. */
    countryStateOnly: { type: Boolean, default: false },
    /**
     * locationMode:
     * - "local": US-only city/ZIP/county search backed by /api/locations/search (uszips table).
     * - "google": street-address autocomplete backed by address-detail.autocomplete (Google Places).
     */
    locationMode: { type: String, default: 'local' },
});

const stateOptions = ref(props.initialStateOptions || []);
const normalizedCountryOptions = computed(() => {
    const options = Array.isArray(props.countryOptions) ? [...props.countryOptions] : [];
    const hasOther = options.some((option) => {
        if (option && typeof option === 'object') {
            return String(option.value || '').toUpperCase() === 'OTHER';
        }
        return String(option || '').toUpperCase() === 'OTHER';
    });

    if (!hasOther) {
        options.push({ value: 'OTHER', label: 'Other' });
    }

    return options;
});
const locationQuery = ref((locationLabel.value ?? '') || '');
const locationResults = ref([]);
const locationSearchLoading = ref(false);
const locationDropdownOpen = ref(false);
const locationSearchMessage = ref('');
let locationSearchTimer = null;

const hasStateDropdown = computed(() => stateOptions.value.length > 0);
const canSearchUsLocations = computed(() => country.value === 'US' && Boolean(state.value));
const shouldUseGooglePlaces = computed(() => props.locationMode === 'google');
const googleAutocompleteStatus = ref('');
let googleAutocompleteTimer = null;
const googlePlaceOptions = ref([]);
const googleDropdownOpen = ref(false);
let googlePlacesTimer = null;
const googlePlaceResolving = ref(false);
const lastResolvedGooglePlace = ref('');
const selectedLocationSummary = computed(() => {
    if (locationLabel.value) {
        return locationLabel.value;
    }

    const cityState = [city.value, state.value].filter(Boolean).join(', ');
    return [cityState, postalCode.value].filter(Boolean).join(' ');
});

const typeLabel = (type) => ({ zip: 'ZIP', city: 'City', county: 'County' }[type] || 'Location');

const resetSelectedLocation = () => {
    city.value = '';
    postalCode.value = '';
    county.value = '';
    locationLabel.value = '';
    locationQuery.value = '';
    locationResults.value = [];
    locationDropdownOpen.value = false;
    locationSearchMessage.value = '';
    googleAutocompleteStatus.value = '';
    googlePlaceOptions.value = [];
    googleDropdownOpen.value = false;
};

if (!country.value) {
    country.value = 'US';
}

const loadStateOptions = async (countryCode) => {
    try {
        const response = await fetch(route('locations.states', { country: countryCode }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`State lookup failed with status ${response.status}`);
        }

        const payload = await response.json();
        stateOptions.value = Array.isArray(payload.states) ? payload.states : [];
    } catch {
        stateOptions.value = [];
    }
};

const fetchGooglePlacesOptions = async () => {
    const query = String(locationQuery.value || '').trim();
    if (!canSearchUsLocations.value || query.length < 3) {
        googlePlaceOptions.value = [];
        googleDropdownOpen.value = false;
        return;
    }

    locationSearchLoading.value = true;
    googleAutocompleteStatus.value = '';

    try {
        const response = await fetch(route('address-detail.places', { query, state: state.value }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Places list failed (${response.status})`);
        }

        const payload = await response.json();
        const rawOptions = Array.isArray(payload?.data) ? payload.data : [];

        // Prefer showing only suggestions that match the currently-selected state.
        // Places Autocomplete doesn't support state-only restriction reliably, so we filter by display text.
        const selectedState = String(state.value || '').trim().toUpperCase();
        const filteredOptions = selectedState && selectedState.length === 2
            ? rawOptions.filter((opt) => {
                const text = String(opt?.text || '').toUpperCase();
                return (
                    text.includes(`, ${selectedState},`)
                    || text.includes(`, ${selectedState} `)
                    || text.endsWith(`, ${selectedState}`)
                    || text.includes(` ${selectedState},`)
                );
            })
            : rawOptions;

        googlePlaceOptions.value = filteredOptions;
        googleDropdownOpen.value = filteredOptions.length > 0;
        if (!filteredOptions.length) {
            googleAutocompleteStatus.value = 'No matching addresses found.';
        }
    } catch {
        googlePlaceOptions.value = [];
        googleDropdownOpen.value = false;
        googleAutocompleteStatus.value = 'Street address lookup is unavailable right now.';
    } finally {
        locationSearchLoading.value = false;
    }
};

const selectGooglePlaceOption = async (option) => {
    const place = option?.place;
    const text = option?.text;
    if (!place) return;

    // Optimistically commit the user's click so the UI reflects selection immediately,
    // even if the place-details request is still in flight.
    locationLabel.value = text || locationLabel.value || locationQuery.value;
    locationQuery.value = locationLabel.value || locationQuery.value;
    googleDropdownOpen.value = false;

    locationSearchLoading.value = true;
    googlePlaceResolving.value = true;
    googleAutocompleteStatus.value = '';
    lastResolvedGooglePlace.value = '';

    try {
        const response = await fetch(route('address-detail.place', { place }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Place details failed (${response.status})`);
        }

        const payload = await response.json();
        if (!payload?.ok || !payload?.data) {
            googleAutocompleteStatus.value = 'Unable to resolve that address.';
            return;
        }

        const parsed = parsePlaceToAddressFields(payload.data);
        const selectedState = String(state.value || '').toUpperCase();
        const resolvedState = String(parsed.state || '').toUpperCase();
        if (selectedState && resolvedState && selectedState !== resolvedState) {
            googleAutocompleteStatus.value = `That address is in ${resolvedState}. Please pick an address in ${selectedState}.`;
            // Don't keep a "selected" summary if the selection is invalid.
            // Force the user to pick a matching-state address.
            city.value = '';
            postalCode.value = '';
            county.value = '';
            locationLabel.value = '';
            // Keep the user's text so they can quickly pick another option.
            locationQuery.value = text || locationQuery.value;
            googleDropdownOpen.value = true;
            return;
        }

        country.value = parsed.country || country.value;
        await loadStateOptions(country.value);
        state.value = parsed.state || state.value;
        city.value = parsed.city || city.value;
        postalCode.value = parsed.postal_code || postalCode.value;
        locationLabel.value = payload.data?.formattedAddress || text || parsed.address_line_1 || locationQuery.value;
        locationQuery.value = locationLabel.value || locationQuery.value;
        googlePlaceOptions.value = [];
        googleDropdownOpen.value = false;
        googleAutocompleteStatus.value = '';
        lastResolvedGooglePlace.value = place;
    } catch {
        googleAutocompleteStatus.value = 'Street address lookup is unavailable right now.';
    } finally {
        locationSearchLoading.value = false;
        googlePlaceResolving.value = false;
    }
};

const runGoogleStreetAutocomplete = async () => {
    const query = String(locationQuery.value || '').trim();
    if (!canSearchUsLocations.value || query.length < 3) {
        googleAutocompleteStatus.value = '';
        return;
    }

    locationSearchLoading.value = true;
    googleAutocompleteStatus.value = '';

    try {
        const response = await fetch(route('address-detail.autocomplete', { query }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Google autocomplete failed (${response.status})`);
        }

        const payload = await response.json();
        if (!payload?.ok || !payload?.data) {
            googleAutocompleteStatus.value = 'No matching address found for that input.';
            return;
        }

        const parsed = parsePlaceToAddressFields(payload.data);
        country.value = parsed.country || country.value;
        await loadStateOptions(country.value);
        state.value = parsed.state || state.value;
        city.value = parsed.city || city.value;
        postalCode.value = parsed.postal_code || postalCode.value;
        locationLabel.value = payload.data?.formattedAddress || parsed.address_line_1 || locationQuery.value;
        locationQuery.value = locationLabel.value || locationQuery.value;
        googleAutocompleteStatus.value = '';
    } catch {
        googleAutocompleteStatus.value = 'Street address lookup is unavailable right now.';
    } finally {
        locationSearchLoading.value = false;
    }
};

const searchLocations = async () => {
    const query = locationQuery.value.trim();
    if (!canSearchUsLocations.value || query.length < 2) {
        locationResults.value = [];
        locationSearchMessage.value = '';
        return;
    }

    locationSearchLoading.value = true;
    locationSearchMessage.value = '';

    try {
        const response = await fetch(route('locations.search', {
            country: country.value,
            state_id: state.value,
            q: query,
        }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Location search failed with status ${response.status}`);
        }

        const payload = await response.json();
        locationResults.value = Array.isArray(payload.results) ? payload.results : [];
        locationSearchMessage.value = locationResults.value.length ? '' : 'No matching locations found in this state.';
    } catch {
        locationResults.value = [];
        locationSearchMessage.value = 'Location search is unavailable right now.';
    } finally {
        locationSearchLoading.value = false;
    }
};

const queueLocationSearch = () => {
    clearTimeout(locationSearchTimer);
    clearTimeout(googleAutocompleteTimer);
    clearTimeout(googlePlacesTimer);

    const query = locationQuery.value.trim();
    if (query.length < (shouldUseGooglePlaces.value ? 3 : 2)) {
        locationResults.value = [];
        locationDropdownOpen.value = false;
        locationSearchMessage.value = '';
        googleAutocompleteStatus.value = '';
        googlePlaceOptions.value = [];
        googleDropdownOpen.value = false;
        return;
    }

    if (shouldUseGooglePlaces.value) {
        googlePlacesTimer = setTimeout(() => {
            void fetchGooglePlacesOptions();
        }, 280);
        return;
    }

    locationDropdownOpen.value = true;
    locationSearchTimer = setTimeout(searchLocations, 250);
};

const selectLocationResult = (result) => {
    country.value = 'US';
    state.value = result.state_id || state.value;
    city.value = result.city || result.value || '';
    postalCode.value = result.zip || '';
    county.value = result.county || '';
    locationLabel.value = result.label || '';
    locationQuery.value = result.label || '';
    locationResults.value = [];
    locationDropdownOpen.value = false;
    locationSearchMessage.value = '';
};

const hideLocationDropdown = () => {
    setTimeout(() => {
        locationDropdownOpen.value = false;
    }, 180);
};

watch(
    () => country.value,
    async (next, prev) => {
        if (next === prev) return;

        state.value = '';
        resetSelectedLocation();
        await loadStateOptions(next);
    },
);

watch(
    () => state.value,
    (next, prev) => {
        if (next === prev) return;
        resetSelectedLocation();
    },
);

watch(
    () => locationLabel.value,
    (label) => {
        if (label && locationQuery.value !== label) {
            locationQuery.value = label;
        }
    },
);

defineExpose({
    isResolvingLocation: computed(() => locationSearchLoading.value || googlePlaceResolving.value),
    hasResolvedGooglePlace: computed(() => Boolean(lastResolvedGooglePlace.value)),
    statusMessage: computed(() => googleAutocompleteStatus.value || ''),
});
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <Select
                v-model="country"
                :options="normalizedCountryOptions"
                label="Country"
                placeholder="Select country"
                size="auth"
                required
            />
            <Select
                v-if="hasStateDropdown"
                v-model="state"
                :options="stateOptions"
                label="State"
                placeholder="Select state"
                size="auth"
                required
            />
            <Input
                v-else
                v-model="state"
                label="State / region"
                placeholder="Enter state or region"
                required
            />
        </div>

        <div v-if="!countryStateOnly && country === 'US' && locationMode === 'local'" class="space-y-3">
            <label class="mb-3 block text-base font-medium text-slate-700">
                City, ZIP, or county
                <span class="ml-0.5 text-red-500">*</span>
            </label>
            <div class="relative">
                <input
                    v-model="locationQuery"
                    type="text"
                    class="w-full rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 pr-28 text-base text-slate-900 shadow-sm outline-none transition duration-200 placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                    :disabled="!canSearchUsLocations"
                    :placeholder="canSearchUsLocations ? 'Start typing ZIP, city, or county' : 'Select a state first'"
                    autocomplete="off"
                    @input="queueLocationSearch"
                    @focus="locationQuery.length >= 2 && canSearchUsLocations ? (locationDropdownOpen = true) : null"
                    @blur="hideLocationDropdown"
                >
                <div
                    v-if="locationSearchLoading"
                    class="pointer-events-none absolute inset-y-0 right-5 flex items-center text-sm text-slate-500"
                >
                    Searching...
                </div>

                <div
                    v-if="locationDropdownOpen && canSearchUsLocations && (locationResults.length || locationSearchMessage)"
                    class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-2xl border border-slate-200 bg-white py-1.5 text-base shadow-lg"
                >
                    <button
                        v-for="result in locationResults"
                        :key="result.label"
                        type="button"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-primary-50"
                        @mousedown.prevent="selectLocationResult(result)"
                    >
                        <span class="mt-0.5 rounded-lg bg-primary-50 px-2 py-1 text-xs font-bold uppercase tracking-wide text-primary-700">
                            {{ typeLabel(result.type) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-neutral-900">{{ result.label }}</span>
                            <span v-if="result.county" class="block truncate text-sm text-neutral-500">
                                {{ result.county }} County
                            </span>
                        </span>
                    </button>
                    <p v-if="!locationResults.length && locationSearchMessage" class="px-4 py-3 text-sm text-neutral-500">
                        {{ locationSearchMessage }}
                    </p>
                </div>
            </div>
            <p v-if="selectedLocationSummary" class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-800">
                Selected location: {{ selectedLocationSummary }}
                <span v-if="county">({{ county }} County)</span>
            </p>
        </div>

        <div v-else-if="!countryStateOnly && country === 'US' && locationMode === 'google'" class="space-y-3">
            <label class="mb-3 block text-base font-medium text-slate-700">
                Street Address, City & Zip
                <span class="ml-0.5 text-red-500">*</span>
            </label>
            <div class="relative">
                <input
                    v-model="locationQuery"
                    type="text"
                    class="w-full rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 pr-28 text-base text-slate-900 shadow-sm outline-none transition duration-200 placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                    :disabled="!canSearchUsLocations"
                    :placeholder="canSearchUsLocations ? 'Start typing a street address (min 3 chars)' : 'Select a state first'"
                    autocomplete="street-address"
                    @input="queueLocationSearch"
                    @keydown.enter.prevent
                >
                <div
                    v-if="locationSearchLoading"
                    class="pointer-events-none absolute inset-y-0 right-5 flex items-center text-sm text-slate-500"
                >
                    Searching...
                </div>

                <div
                    v-if="googleDropdownOpen && canSearchUsLocations && googlePlaceOptions.length"
                    class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-2xl border border-slate-200 bg-white py-1.5 text-base shadow-lg"
                >
                    <button
                        v-for="opt in googlePlaceOptions"
                        :key="opt.place"
                        type="button"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-primary-50"
                        @pointerdown.prevent="selectGooglePlaceOption(opt)"
                        @mousedown.prevent="selectGooglePlaceOption(opt)"
                        @click.prevent="selectGooglePlaceOption(opt)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-neutral-900">{{ opt.text }}</span>
                        </span>
                    </button>
                </div>
            </div>

            <p
                v-if="googleAutocompleteStatus"
                class="text-sm text-slate-500"
            >
                {{ googleAutocompleteStatus }}
            </p>
            <p v-if="selectedLocationSummary" class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-800">
                Selected address: {{ selectedLocationSummary }}
            </p>
        </div>

        <Input
            v-else-if="!countryStateOnly"
            v-model="city"
            label="City / location"
            placeholder="Enter city or location"
            required
        />
    </div>
</template>
