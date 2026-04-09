<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    ArrowLeftIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ClockIcon,
    CurrencyDollarIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid, CheckBadgeIcon as CheckBadgeSolid } from '@heroicons/vue/24/solid';
import { ref, computed } from 'vue';

const props = defineProps({
    provider: Object,
    serviceTypes: Array,
});

const form = useForm({
    service_type: props.serviceTypes[0]?.value || '',
    message: '',
    requirements: [],
    preferred_contact_method: 'message',
    preferred_contact_time: '',
    urgency: 'normal',
    needed_by: '',
    budget_range: '',
});

const requirementInput = ref('');

const addRequirement = () => {
    if (requirementInput.value.trim() && form.requirements.length < 10) {
        form.requirements.push(requirementInput.value.trim());
        requirementInput.value = '';
    }
};

const removeRequirement = (index) => {
    form.requirements.splice(index, 1);
};

const handleKeydown = (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        addRequirement();
    }
};

const submit = () => {
    form.post(route('leads.store', props.provider.slug));
};

const urgencyOptions = [
    { value: 'low', label: 'Not urgent', description: 'Within the next few months' },
    { value: 'normal', label: 'Normal', description: 'Within the next few weeks' },
    { value: 'high', label: 'High priority', description: 'Within the next week' },
    { value: 'urgent', label: 'Urgent', description: 'As soon as possible' },
];

const contactMethods = [
    { value: 'message', label: 'In-app message', icon: ChatBubbleLeftRightIcon },
    { value: 'email', label: 'Email', icon: null },
    { value: 'phone', label: 'Phone call', icon: null },
];

const messageLength = computed(() => form.message.length);
const isMessageValid = computed(() => form.message.length >= 20);
</script>

<template>
    <Head :title="`Contact ${provider.business_name}`" />

    <AppLayout>
        <div class="min-h-screen bg-slate-50">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Back Link -->
                <Link 
                    :href="route('marketplace.show', provider.slug)"
                    class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 mb-6"
                >
                    <ArrowLeftIcon class="w-4 h-4" />
                    Back to profile
                </Link>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Form -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-soft p-6 lg:p-8">
                            <h1 class="text-2xl font-display font-bold text-slate-900 mb-2">
                                Contact {{ provider.business_name }}
                            </h1>
                            <p class="text-slate-500 mb-8">
                                Fill out this form to send an inquiry. The provider will respond within 24-48 hours.
                            </p>

                            <form @submit.prevent="submit" class="space-y-6">
                                <!-- Service Type -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        What service do you need?
                                    </label>
                                    <select 
                                        v-model="form.service_type"
                                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    >
                                        <option v-for="type in serviceTypes" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.service_type" class="mt-2 text-sm text-red-600">
                                        {{ form.errors.service_type }}
                                    </p>
                                </div>

                                <!-- Message -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        Describe what you need help with
                                    </label>
                                    <textarea 
                                        v-model="form.message"
                                        rows="5"
                                        placeholder="Please describe your situation and what assistance you're looking for..."
                                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                    ></textarea>
                                    <div class="flex items-center justify-between mt-2">
                                        <p :class="['text-sm', isMessageValid ? 'text-green-600' : 'text-slate-400']">
                                            {{ messageLength }}/20 characters minimum
                                        </p>
                                        <p v-if="!isMessageValid && messageLength > 0" class="text-sm text-amber-600">
                                            Please provide more details
                                        </p>
                                    </div>
                                    <p v-if="form.errors.message" class="mt-2 text-sm text-red-600">
                                        {{ form.errors.message }}
                                    </p>
                                </div>

                                <!-- Specific Requirements (optional) -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        Specific requirements (optional)
                                    </label>
                                    <div class="flex gap-2 mb-3">
                                        <input 
                                            v-model="requirementInput"
                                            @keydown="handleKeydown"
                                            type="text"
                                            placeholder="Add a requirement and press Enter"
                                            class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        />
                                        <button 
                                            type="button"
                                            @click="addRequirement"
                                            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-colors"
                                        >
                                            Add
                                        </button>
                                    </div>
                                    <div v-if="form.requirements.length" class="flex flex-wrap gap-2">
                                        <span 
                                            v-for="(req, index) in form.requirements" 
                                            :key="index"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary-50 text-primary-700 rounded-lg text-sm"
                                        >
                                            {{ req }}
                                            <button type="button" @click="removeRequirement(index)" class="hover:text-primary-900">
                                                ×
                                            </button>
                                        </span>
                                    </div>
                                </div>

                                <!-- Urgency -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-3">
                                        How urgent is your request?
                                    </label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button 
                                            v-for="option in urgencyOptions" 
                                            :key="option.value"
                                            type="button"
                                            @click="form.urgency = option.value"
                                            :class="[
                                                'flex flex-col items-start p-4 border rounded-xl transition-all text-left',
                                                form.urgency === option.value 
                                                    ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500' 
                                                    : 'border-slate-200 hover:border-slate-300'
                                            ]"
                                        >
                                            <span :class="['font-medium', form.urgency === option.value ? 'text-primary-700' : 'text-slate-900']">
                                                {{ option.label }}
                                            </span>
                                            <span class="text-xs text-slate-500 mt-1">{{ option.description }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Needed By Date (optional) -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        When do you need this completed? (optional)
                                    </label>
                                    <input 
                                        v-model="form.needed_by"
                                        type="date"
                                        :min="new Date().toISOString().split('T')[0]"
                                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    />
                                </div>

                                <!-- Budget Range (optional) -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        Budget range (optional)
                                    </label>
                                    <input 
                                        v-model="form.budget_range"
                                        type="text"
                                        placeholder="e.g., $500-$1000"
                                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    />
                                </div>

                                <!-- Preferred Contact Method -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-3">
                                        Preferred contact method
                                    </label>
                                    <div class="flex flex-wrap gap-3">
                                        <button 
                                            v-for="method in contactMethods" 
                                            :key="method.value"
                                            type="button"
                                            @click="form.preferred_contact_method = method.value"
                                            :class="[
                                                'flex items-center gap-2 px-4 py-2.5 border rounded-xl transition-all',
                                                form.preferred_contact_method === method.value 
                                                    ? 'border-primary-500 bg-primary-50 text-primary-700' 
                                                    : 'border-slate-200 text-slate-700 hover:border-slate-300'
                                            ]"
                                        >
                                            {{ method.label }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <button 
                                    type="submit"
                                    :disabled="form.processing || !isMessageValid"
                                    :class="[
                                        'w-full flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-semibold transition-all',
                                        form.processing || !isMessageValid
                                            ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                            : 'bg-primary-600 hover:bg-primary-500 text-white'
                                    ]"
                                >
                                    <ChatBubbleLeftRightIcon class="w-5 h-5" />
                                    {{ form.processing ? 'Sending...' : 'Send Inquiry' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Provider Sidebar -->
                    <div class="space-y-6">
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <div class="flex items-center gap-4 mb-4">
                                <img 
                                    :src="provider.user?.avatar || '/img/default-avatar.png'"
                                    :alt="provider.business_name"
                                    class="w-16 h-16 rounded-xl object-cover"
                                />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-slate-900">{{ provider.business_name }}</h3>
                                        <CheckBadgeSolid v-if="provider.background_check_status === 'clear'" class="w-5 h-5 text-primary-500" />
                                    </div>
                                    <div class="flex items-center gap-1 mt-1">
                                        <StarSolid class="w-4 h-4 text-secondary-500" />
                                        <span class="text-sm text-slate-600">{{ provider.average_rating?.toFixed(1) || 'New' }}</span>
                                        <span class="text-sm text-slate-400">({{ provider.total_reviews }} reviews)</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 text-sm">
                                <div v-if="provider.free_consultation" class="flex items-center gap-3 text-green-600">
                                    <CheckCircleIcon class="w-5 h-5" />
                                    <span>Free consultation available</span>
                                </div>
                                <div class="flex items-center gap-3 text-slate-600">
                                    <ClockIcon class="w-5 h-5 text-slate-400" />
                                    <span>Usually responds within 24 hours</span>
                                </div>
                                <div v-if="provider.hourly_rate" class="flex items-center gap-3 text-slate-600">
                                    <CurrencyDollarIcon class="w-5 h-5 text-slate-400" />
                                    <span>${{ provider.hourly_rate }}/hour</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-amber-50 rounded-2xl p-6">
                            <div class="flex items-start gap-3">
                                <ExclamationTriangleIcon class="w-6 h-6 text-amber-600 flex-shrink-0" />
                                <div>
                                    <h4 class="font-medium text-amber-800 mb-1">Tips for a better response</h4>
                                    <ul class="text-sm text-amber-700 space-y-1">
                                        <li>• Be specific about your situation</li>
                                        <li>• Include relevant dates and deadlines</li>
                                        <li>• Mention any documents you have ready</li>
                                        <li>• Ask specific questions</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
