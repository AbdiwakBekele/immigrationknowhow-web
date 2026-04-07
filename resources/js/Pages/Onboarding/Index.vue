<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
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

// Form data
const formData = ref({
    // User steps
    services_needed: [],
    location: {
        city: '',
        state: '',
        country: 'US',
        postal_code: '',
    },
    language: {
        languages: ['en'],
        preferred: 'en',
    },
    // Provider steps
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
    languages: {
        offered: ['en'],
    },
    ...props.existingData,
});

const saving = ref(false);

const nextStep = async () => {
    if (isLastStep.value) {
        await completeOnboarding();
        return;
    }
    
    // Save progress
    saving.value = true;
    router.post(route('onboarding.progress'), {
        step: currentStep.value.key,
        data: formData.value[currentStep.value.key] || {},
    }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
            currentStepIndex.value++;
        },
    });
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

const languageOptions = computed(() => {
    return Object.entries(props.languages).map(([code, name]) => ({
        value: code,
        label: name,
    }));
});

const pricingModels = [
    { value: 'hourly', label: 'Hourly Rate' },
    { value: 'flat_rate', label: 'Flat Rate' },
    { value: 'consultation', label: 'Consultation Based' },
    { value: 'custom', label: 'Custom Pricing' },
];
</script>

<template>
    <Head title="Complete Your Profile" />

    <div class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-accent-50">
        <!-- Progress bar -->
        <div class="fixed top-0 left-0 right-0 h-1 bg-neutral-200 z-50">
            <div 
                class="h-full bg-gradient-to-r from-primary-500 to-accent-500 transition-all duration-500"
                :style="{ width: `${progress}%` }"
            ></div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-12">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl flex items-center justify-center shadow-soft mx-auto mb-6">
                    <span class="text-white font-display font-bold text-2xl">IK</span>
                </div>
                <h1 class="text-2xl font-display font-bold text-neutral-900">
                    {{ currentStep.title }}
                </h1>
                <p class="text-neutral-600 mt-1">{{ currentStep.description }}</p>
            </div>

            <!-- Step indicators -->
            <div class="flex items-center justify-center gap-2 mb-8">
                <template v-for="(step, index) in steps" :key="step.key">
                    <div 
                        :class="[
                            'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-300',
                            index < currentStepIndex ? 'bg-emerald-500 text-white' :
                            index === currentStepIndex ? 'bg-primary-600 text-white' :
                            'bg-neutral-200 text-neutral-500'
                        ]"
                    >
                        <CheckIcon v-if="index < currentStepIndex" class="w-4 h-4" />
                        <span v-else>{{ index + 1 }}</span>
                    </div>
                    <div 
                        v-if="index < steps.length - 1"
                        :class="[
                            'w-8 h-0.5 transition-all duration-300',
                            index < currentStepIndex ? 'bg-emerald-500' : 'bg-neutral-200'
                        ]"
                    ></div>
                </template>
            </div>

            <!-- Step content -->
            <div class="bg-white rounded-2xl shadow-soft-lg p-8">
                <!-- Welcome step -->
                <div v-if="currentStep.key === 'welcome'" class="text-center py-8">
                    <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-4xl">👋</span>
                    </div>
                    <h2 class="text-xl font-semibold text-neutral-900 mb-2">
                        Hi {{ user.first_name }}!
                    </h2>
                    <p class="text-neutral-600 max-w-md mx-auto">
                        <template v-if="isProvider">
                            Let's set up your service provider profile. This will help immigrants find and connect with you.
                        </template>
                        <template v-else>
                            Let's personalize your experience so we can help you find the right service providers.
                        </template>
                    </p>
                </div>

                <!-- Services Needed (User) -->
                <div v-else-if="currentStep.key === 'services' && !isProvider" class="space-y-6">
                    <p class="text-neutral-600 mb-4">What services are you looking for?</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            v-for="service in serviceTypes"
                            :key="service.value"
                            class="relative"
                        >
                            <input
                                type="checkbox"
                                :value="service.value"
                                v-model="formData.services_needed"
                                class="peer sr-only"
                            />
                            <div class="p-4 rounded-xl border-2 border-neutral-200 cursor-pointer transition-all peer-checked:border-primary-500 peer-checked:bg-primary-50 hover:border-neutral-300">
                                <div class="font-medium text-neutral-900">{{ service.label }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Services Offered (Provider) -->
                <div v-else-if="currentStep.key === 'services' && isProvider" class="space-y-6">
                    <p class="text-neutral-600 mb-4">What services do you offer?</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            v-for="service in serviceTypes"
                            :key="service.value"
                            class="relative"
                        >
                            <input
                                type="checkbox"
                                :value="service.value"
                                v-model="formData.services.types"
                                class="peer sr-only"
                            />
                            <div class="p-4 rounded-xl border-2 border-neutral-200 cursor-pointer transition-all peer-checked:border-primary-500 peer-checked:bg-primary-50 hover:border-neutral-300">
                                <div class="font-medium text-neutral-900">{{ service.label }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Business Info (Provider) -->
                <div v-else-if="currentStep.key === 'business'" class="space-y-5">
                    <Input
                        v-model="formData.business.business_name"
                        label="Business Name"
                        placeholder="Your business or practice name"
                    />
                    <Input
                        v-model="formData.business.tagline"
                        label="Tagline"
                        placeholder="A short description of your services"
                        helper="This appears below your name in search results"
                    />
                    <div>
                        <label class="label">Bio</label>
                        <textarea
                            v-model="formData.business.bio"
                            rows="4"
                            class="input"
                            placeholder="Tell potential clients about yourself and your experience..."
                        ></textarea>
                    </div>
                    <Input
                        v-model="formData.business.years_experience"
                        type="number"
                        label="Years of Experience"
                        placeholder="e.g., 5"
                    />
                </div>

                <!-- Location -->
                <div v-else-if="currentStep.key === 'location'" class="space-y-5">
                    <Input
                        v-model="formData.location.city"
                        label="City"
                        placeholder="e.g., New York"
                    />
                    <Input
                        v-model="formData.location.state"
                        label="State"
                        placeholder="e.g., NY"
                    />
                    <Input
                        v-model="formData.location.postal_code"
                        label="ZIP Code"
                        placeholder="e.g., 10001"
                    />
                </div>

                <!-- Language (User) -->
                <div v-else-if="currentStep.key === 'language'" class="space-y-5">
                    <Select
                        v-model="formData.language.preferred"
                        :options="languageOptions"
                        label="Preferred Language"
                    />
                    <div>
                        <label class="label">Languages You Speak</label>
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            <label
                                v-for="(name, code) in languages"
                                :key="code"
                                class="flex items-center gap-2 p-2 rounded-lg hover:bg-neutral-50 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="code"
                                    v-model="formData.language.languages"
                                    class="w-4 h-4 rounded border-neutral-300 text-primary-600"
                                />
                                <span class="text-sm text-neutral-700">{{ name }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Pricing (Provider) -->
                <div v-else-if="currentStep.key === 'pricing'" class="space-y-5">
                    <Select
                        v-model="formData.pricing.model"
                        :options="pricingModels"
                        label="Pricing Model"
                    />
                    <Input
                        v-if="formData.pricing.model === 'hourly'"
                        v-model="formData.pricing.hourly_rate"
                        type="number"
                        label="Hourly Rate ($)"
                        placeholder="e.g., 150"
                    />
                    <Input
                        v-model="formData.pricing.consultation_fee"
                        type="number"
                        label="Consultation Fee ($)"
                        placeholder="e.g., 50"
                        helper="Leave empty or 0 for free consultations"
                    />
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="formData.pricing.free_consultation"
                            class="w-4 h-4 rounded border-neutral-300 text-primary-600"
                        />
                        <span class="text-sm text-neutral-700">Offer free initial consultations</span>
                    </label>
                </div>

                <!-- Service Area (Provider) -->
                <div v-else-if="currentStep.key === 'service-area'" class="space-y-5">
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="formData['service-area'].in_person"
                                class="w-4 h-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">I offer in-person services</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="formData['service-area'].remote"
                                class="w-4 h-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">I offer remote/virtual services</span>
                        </label>
                    </div>
                    <Input
                        v-if="formData['service-area'].in_person"
                        v-model="formData['service-area'].radius"
                        type="number"
                        label="Service Radius (miles)"
                        placeholder="e.g., 25"
                    />
                </div>

                <!-- Languages (Provider) -->
                <div v-else-if="currentStep.key === 'languages' && isProvider" class="space-y-5">
                    <p class="text-neutral-600 mb-4">What languages can you serve clients in?</p>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="(name, code) in languages"
                            :key="code"
                            class="flex items-center gap-2 p-2 rounded-lg hover:bg-neutral-50 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="code"
                                v-model="formData.languages.offered"
                                class="w-4 h-4 rounded border-neutral-300 text-primary-600"
                            />
                            <span class="text-sm text-neutral-700">{{ name }}</span>
                        </label>
                    </div>
                </div>

                <!-- Complete step -->
                <div v-else-if="currentStep.key === 'complete'" class="text-center py-8">
                    <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <CheckIcon class="w-10 h-10 text-emerald-600" />
                    </div>
                    <h2 class="text-xl font-semibold text-neutral-900 mb-2">
                        You're all set!
                    </h2>
                    <p class="text-neutral-600 max-w-md mx-auto">
                        <template v-if="isProvider">
                            Your provider profile is ready. Start connecting with clients today!
                        </template>
                        <template v-else>
                            Your profile is complete. Start exploring service providers now!
                        </template>
                    </p>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between mt-8 pt-6 border-t border-neutral-100">
                    <Button
                        v-if="!isFirstStep"
                        variant="ghost"
                        @click="prevStep"
                    >
                        <ArrowLeftIcon class="w-4 h-4" />
                        Back
                    </Button>
                    <div v-else></div>

                    <Button
                        variant="primary"
                        :loading="saving"
                        @click="nextStep"
                    >
                        {{ isLastStep ? 'Complete Setup' : 'Continue' }}
                        <ArrowRightIcon v-if="!isLastStep" class="w-4 h-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
