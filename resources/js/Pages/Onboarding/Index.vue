<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { CheckIcon, ArrowRightIcon, ArrowLeftIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    user: Object,
    isProvider: Boolean,
    serviceTypes: Array,
    languages: Object,
    existingData: Object,
    steps: Array,
});

const currentStepIndex = ref(0);
const currentStep = computed(() => props.steps[currentStepIndex.value]);
const isFirstStep = computed(() => currentStepIndex.value === 0);
const isLastStep = computed(() => currentStepIndex.value === props.steps.length - 1);
const progress = computed(() => ((currentStepIndex.value + 1) / props.steps.length) * 100);

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

const stepPayload = () => {
    const key = currentStep.value.key;
    if (key === 'services' && !props.isProvider) {
        return { services_needed: formData.value.services_needed };
    }
    return formData.value[key] || {};
};

const nextStep = async () => {
    if (isLastStep.value) {
        await completeOnboarding();
        return;
    }

    saving.value = true;
    router.post(
        route('onboarding.progress'),
        {
            step: currentStep.value.key,
            data: stepPayload(),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
                currentStepIndex.value++;
            },
        },
    );
};

const prevStep = () => {
    if (!isFirstStep.value) {
        currentStepIndex.value--;
    }
};

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
    <Head title="Complete your profile" />

    <div class="min-h-screen bg-neutral-50">
        <div class="fixed left-0 right-0 top-0 z-40 h-0.5 bg-neutral-200">
            <div
                class="h-full bg-primary-600 transition-all duration-300"
                :style="{ width: `${progress}%` }"
            />
        </div>

        <div class="mx-auto max-w-xl px-4 pb-16 pt-12">
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-primary-600 text-sm font-bold text-white shadow-sm"
                >
                    IK
                </div>
                <h1 class="text-xl font-semibold text-neutral-900">
                    {{ currentStep.title }}
                </h1>
                <p class="mt-1 text-sm text-neutral-600">
                    {{ currentStep.description }}
                </p>
            </div>

            <div class="mb-6 flex items-center justify-center gap-1.5">
                <template v-for="(step, index) in steps" :key="step.key">
                    <div
                        :class="[
                            'flex h-7 min-w-[1.75rem] items-center justify-center rounded-full px-2 text-xs font-medium transition-colors',
                            index < currentStepIndex
                                ? 'bg-emerald-600 text-white'
                                : index === currentStepIndex
                                  ? 'bg-primary-600 text-white'
                                  : 'bg-neutral-200 text-neutral-500',
                        ]"
                    >
                        <CheckIcon v-if="index < currentStepIndex" class="h-3.5 w-3.5" />
                        <span v-else>{{ index + 1 }}</span>
                    </div>
                    <div
                        v-if="index < steps.length - 1"
                        :class="['h-0.5 w-4 rounded-full', index < currentStepIndex ? 'bg-emerald-500' : 'bg-neutral-200']"
                    />
                </template>
            </div>

            <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 shadow-sm sm:p-8">
                <!-- Services (user) -->
                <div v-if="currentStep.key === 'services' && !isProvider" class="space-y-4">
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

                <!-- Business -->
                <div v-else-if="currentStep.key === 'business'" class="space-y-4">
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

                <!-- Pricing -->
                <div v-else-if="currentStep.key === 'pricing'" class="space-y-4">
                    <div>
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
                </div>

                <!-- Service area -->
                <div v-else-if="currentStep.key === 'service-area'" class="space-y-4">
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

                <!-- Complete -->
                <div v-else-if="currentStep.key === 'complete'" class="py-4 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
                    >
                        <CheckIcon class="h-8 w-8" />
                    </div>
                    <h2 class="text-lg font-semibold text-neutral-900">Ready to go</h2>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-neutral-600">
                        <template v-if="isProvider">Your public profile will be created and you can refine it anytime.</template>
                        <template v-else>You can start browsing providers and save favorites.</template>
                    </p>
                </div>

                <div class="mt-8 flex items-center justify-between gap-4 border-t border-neutral-100 pt-6">
                    <Button v-if="!isFirstStep" variant="ghost" @click="prevStep">
                        <ArrowLeftIcon class="h-4 w-4" />
                        Back
                    </Button>
                    <div v-else />

                    <Button variant="primary" :loading="saving" @click="nextStep">
                        {{ isLastStep ? 'Finish' : 'Continue' }}
                        <ArrowRightIcon v-if="!isLastStep" class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
