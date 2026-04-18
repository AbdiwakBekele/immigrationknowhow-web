<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { ArrowRightIcon } from '@heroicons/vue/20/solid';
import { SIGNUP_FLOW_STEPS_PROVIDER, SIGNUP_FLOW_STEPS_USER } from '@/constants/authFlowProgress';

const props = defineProps({
    user: Object,
    isProvider: Boolean,
    serviceTypes: Array,
    languages: Object,
    existingData: Object,
});

const progressTotal = computed(() => (props.isProvider ? SIGNUP_FLOW_STEPS_PROVIDER : SIGNUP_FLOW_STEPS_USER));
const progressCurrent = computed(() => progressTotal.value);

const onboardingHeading = computed(() =>
    props.isProvider
        ? `Step ${SIGNUP_FLOW_STEPS_PROVIDER}/${SIGNUP_FLOW_STEPS_PROVIDER} - Review and finish profile`
        : `Step ${SIGNUP_FLOW_STEPS_USER}/${SIGNUP_FLOW_STEPS_USER} - Review and finish account`,
);
const onboardingDescription = computed(() =>
    props.isProvider
        ? 'Review your details and finish provider setup.'
        : 'Review your details and finish account setup.'
);

const formData = ref({
    services_needed: [],
    business: {
        business_name: '',
        bio: '',
        tagline: '',
        business_email: props.user?.email || '',
        business_phone: '',
        website: '',
        years_experience: null,
    },
    services: {
        types: [],
        specializations: [],
    },
    pricing: {
        model: 'hourly',
        hourly_rate: null,
        consultation_fee: null,
        free_consultation: false,
        notes: '',
    },
    'service-area': {
        serve_client_in_location: false,
        remote: false,
        in_person: true,
        radius: null,
        areas: [],
    },
});

const mergeExistingOnboarding = () => {
    const e = props.existingData || {};
    if (e.services?.services_needed?.length) {
        formData.value.services_needed = e.services.services_needed;
    }
    if (e.services?.types) {
        formData.value.services.types = e.services.types;
    }
    if (e.services?.specializations) {
        formData.value.services.specializations = e.services.specializations;
    }
    if (e.business && typeof e.business === 'object') {
        formData.value.business = { ...formData.value.business, ...e.business };
    }
    if (e.pricing && typeof e.pricing === 'object') {
        formData.value.pricing = { ...formData.value.pricing, ...e.pricing };
    }
    if (e['service-area'] && typeof e['service-area'] === 'object') {
        formData.value['service-area'] = { ...formData.value['service-area'], ...e['service-area'] };
    }
};

mergeExistingOnboarding();

onMounted(() => {
    const reg = props.existingData?.registration?.service_type;
    if (!reg) {
        return;
    }
    if (!props.isProvider && (!formData.value.services_needed || formData.value.services_needed.length === 0)) {
        formData.value.services_needed = [reg];
    }
    if (props.isProvider) {
        const types = formData.value.services?.types || [];
        if (!types.includes(reg)) {
            formData.value.services.types = [...types, reg];
        }
    }
});

const saving = ref(false);

const completeOnboarding = async () => {
    saving.value = true;
    router.post(route('onboarding.complete'), formData.value, {
        onFinish: () => {
            saving.value = false;
        },
    });
};

const pricingModels = [
    { value: 'hourly', label: 'Hourly rate' },
    { value: 'flat_rate', label: 'Flat rate' },
    { value: 'consultation', label: 'Consultation-based' },
    { value: 'custom', label: 'Custom' },
];
</script>

<template>
    <Head title="Continue onboarding" />

    <div class="min-h-screen bg-neutral-50">
        <div class="mx-auto max-w-xl px-4 pb-16 pt-12">
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-primary-600 text-sm font-bold text-white shadow-sm"
                >
                    IK
                </div>
                <div class="mb-4">
                    <AuthFlowProgress :current-step="progressCurrent" :total-steps="progressTotal" />
                </div>
                <h1 class="text-xl font-semibold text-neutral-900">
                    {{ onboardingHeading }}
                </h1>
                <p class="mt-1 text-sm text-neutral-600">
                    {{ onboardingDescription }}
                </p>
            </div>

            <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 shadow-sm sm:p-8">
                <div v-if="!isProvider" class="space-y-4">
                    <p class="text-sm text-neutral-600">Select every area you want help with. You can change this later.</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label v-for="service in serviceTypes" :key="service.value" class="relative block cursor-pointer">
                            <input
                                type="checkbox"
                                :value="service.value"
                                v-model="formData.services_needed"
                                class="peer sr-only"
                            />
                            <div
                                class="rounded-xl border border-neutral-200 px-3 py-3 text-sm font-medium text-neutral-800 transition peer-checked:border-primary-500 peer-checked:bg-primary-50/60 hover:border-neutral-300"
                            >
                                {{ service.label }}
                            </div>
                        </label>
                    </div>
                </div>

                <div v-else class="space-y-6">
                    <div class="space-y-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Business</h2>
                        <Input
                            v-model="formData.business.business_name"
                            label="Business name"
                            placeholder="Your practice or company name"
                        />
                        <Input
                            v-model="formData.business.tagline"
                            label="Tagline"
                            placeholder="Short line that appears in search"
                        />
                        <div>
                            <label class="label">Bio</label>
                            <textarea
                                v-model="formData.business.bio"
                                rows="4"
                                class="input"
                                placeholder="Experience, credentials, and how you help clients..."
                            />
                        </div>
                        <Input
                            v-model="formData.business.years_experience"
                            type="number"
                            label="Years of experience"
                            placeholder="e.g. 5"
                        />
                    </div>

                    <div>
                        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-neutral-500">Pricing</h2>
                        <label class="label">Pricing model</label>
                        <select v-model="formData.pricing.model" class="input w-full py-3">
                            <option v-for="m in pricingModels" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>
                    </div>
                    <Input
                        v-if="formData.pricing.model === 'hourly'"
                        v-model="formData.pricing.hourly_rate"
                        type="number"
                        label="Hourly rate (USD)"
                        placeholder="150"
                    />
                    <Input
                        v-model="formData.pricing.consultation_fee"
                        type="number"
                        label="Consultation fee (USD)"
                        placeholder="Optional"
                        helper="Leave empty for free or custom arrangements"
                    />
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="formData.pricing.free_consultation"
                            class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                        />
                        <span class="text-sm text-neutral-700">Offer free initial consultations</span>
                    </label>
                    <div class="space-y-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Service Area</h2>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="formData['service-area'].serve_client_in_location"
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">I serve the client in their location</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="formData['service-area'].in_person"
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">In-person services</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="formData['service-area'].remote"
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">Remote / virtual services</span>
                        </label>
                        <Input
                            v-if="formData['service-area'].in_person"
                            v-model="formData['service-area'].radius"
                            type="number"
                            label="Service radius (miles)"
                            placeholder="25"
                        />
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-4 border-t border-neutral-100 pt-6">
                    <Button variant="primary" :loading="saving" @click="completeOnboarding">
                        Finish setup
                        <ArrowRightIcon class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
