<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ShieldCheckIcon,
    CheckBadgeIcon,
    ClockIcon,
    EnvelopeIcon,
    MagnifyingGlassIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    ArrowPathIcon,
    InformationCircleIcon,
    LockClosedIcon,
} from '@heroicons/vue/24/outline';
import { CheckBadgeIcon as CheckBadgeSolid } from '@heroicons/vue/24/solid';
import { ref, computed } from 'vue';

const props = defineProps({
    provider: { type: Object, required: true },
    backgroundCheck: { type: Object, default: null },
    history: { type: Array, default: () => [] },
    canInitiate: { type: Boolean, default: true },
    user: { type: Object, required: true },
});

const showForm = ref(false);

const form = useForm({
    first_name: props.user.first_name || '',
    middle_name: '',
    last_name: props.user.last_name || '',
    email: props.user.email || '',
    phone: props.user.phone || '',
    zipcode: '',
    dob: '',
    ssn: '',
    driver_license_number: '',
    driver_license_state: '',
    consent: false,
});

const states = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'DC'
];

const statusIcons = {
    pending: ClockIcon,
    invited: EnvelopeIcon,
    completed: MagnifyingGlassIcon,
    clear: CheckBadgeIcon,
    consider: ExclamationTriangleIcon,
    suspended: XCircleIcon,
    dispute: DocumentTextIcon,
    expired: CalendarDaysIcon,
};

const getStatusIcon = (status) => statusIcons[status] || ClockIcon;

const submit = () => {
    form.post('/provider/background-check', {
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    });
};

const formatSSN = (e) => {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 9) value = value.slice(0, 9);
    
    if (value.length > 5) {
        value = value.slice(0, 3) + '-' + value.slice(3, 5) + '-' + value.slice(5);
    } else if (value.length > 3) {
        value = value.slice(0, 3) + '-' + value.slice(3);
    }
    
    form.ssn = value;
};

const maxDob = computed(() => {
    const date = new Date();
    date.setFullYear(date.getFullYear() - 18);
    return date.toISOString().split('T')[0];
});
</script>

<template>
    <Head title="Background Check" />

    <ProviderLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-display font-bold text-gray-900">Background Check</h1>
                <p class="text-gray-500 mt-1">Complete a background check to become a verified provider and build trust with clients</p>
            </div>

            <div v-if="form.errors.error" class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <XCircleIcon class="h-5 w-5 text-red-600" />
                    <p class="text-red-800">{{ form.errors.error }}</p>
                </div>
            </div>

            <!-- Cleared Status -->
            <div v-if="backgroundCheck?.status === 'clear' && backgroundCheck?.is_valid" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-emerald-100 rounded-full">
                        <CheckBadgeSolid class="h-8 w-8 text-emerald-600" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-emerald-800">Background Check Cleared!</h2>
                        <p class="text-emerald-700">Your background check has been verified. A trust badge is displayed on your profile.</p>
                        <div class="mt-2 text-sm text-emerald-600">
                            <span v-if="backgroundCheck.expires_at">Expires: {{ backgroundCheck.expires_at }}</span>
                            <span v-if="backgroundCheck.days_until_expiry > 0" class="ml-2">({{ backgroundCheck.days_until_expiry }} days remaining)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Check Status (not clear) -->
            <div v-else-if="backgroundCheck && !['pending', 'expired'].includes(backgroundCheck.status)" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-full" :class="backgroundCheck.status_display.badge_classes">
                        <component :is="getStatusIcon(backgroundCheck.status)" class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ backgroundCheck.status_display.label }}
                        </h2>
                        <p class="text-gray-600 mt-1">
                            {{ backgroundCheck.status_display.description }}
                        </p>
                        <div class="mt-3 text-sm text-gray-500">
                            <span>Initiated: {{ backgroundCheck.initiated_at }}</span>
                            <span v-if="backgroundCheck.completed_at" class="ml-4">Completed: {{ backgroundCheck.completed_at }}</span>
                        </div>

                        <!-- Refresh button for invited/in-progress checks -->
                        <form 
                            v-if="['invited', 'completed'].includes(backgroundCheck.status)"
                            :action="`/provider/background-check/${backgroundCheck.id}/refresh`"
                            method="POST"
                            class="mt-4"
                        >
                            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                            <button type="submit" class="inline-flex items-center gap-2 text-sm text-sky-600 hover:text-sky-700">
                                <ArrowPathIcon class="h-4 w-4" />
                                Refresh Status
                            </button>
                        </form>
                    </div>
                    <Link 
                        :href="`/provider/background-check/${backgroundCheck.id}`"
                        class="text-sm text-sky-600 hover:text-sky-700 font-medium"
                    >
                        View Details →
                    </Link>
                </div>
            </div>

            <!-- Consider/Suspended Status -->
            <div v-if="backgroundCheck?.status === 'consider'" class="bg-orange-50 border border-orange-200 rounded-2xl p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-orange-100 rounded-full">
                        <ExclamationTriangleIcon class="h-6 w-6 text-orange-600" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-orange-800">Review Required</h2>
                        <p class="text-orange-700 mt-1">
                            Your background check requires additional review. You may receive a follow-up from Checkr. 
                            If you believe there's an error, you can dispute the results.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Initiate New Check -->
            <div v-if="canInitiate && !showForm" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-sky-100 rounded-full">
                        <ShieldCheckIcon class="h-6 w-6 text-sky-600" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-900">Start Your Background Check</h2>
                        <p class="text-gray-600 mt-1">
                            A background check helps build trust with potential clients. The check is conducted by 
                            <a href="https://checkr.com" target="_blank" class="text-sky-600 hover:underline">Checkr</a>, 
                            a trusted third-party screening service.
                        </p>
                        <ul class="mt-3 space-y-2 text-sm text-gray-600">
                            <li class="flex items-center gap-2">
                                <CheckBadgeIcon class="h-4 w-4 text-green-500" />
                                Verified badge on your profile
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckBadgeIcon class="h-4 w-4 text-green-500" />
                                Increased client trust
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckBadgeIcon class="h-4 w-4 text-green-500" />
                                Higher visibility in search results
                            </li>
                        </ul>
                        <button 
                            @click="showForm = true"
                            class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 text-white rounded-xl font-medium hover:bg-sky-700 transition-colors"
                        >
                            <ShieldCheckIcon class="h-5 w-5" />
                            Start Background Check
                        </button>
                    </div>
                </div>
            </div>

            <!-- Background Check Form -->
            <div v-if="showForm" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Background Check Information</h2>
                <p class="text-sm text-gray-500 mb-6">Please provide accurate information. This will be verified against official records.</p>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Name Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.first_name"
                                type="text"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                required
                            />
                            <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                            <input 
                                v-model="form.middle_name"
                                type="text"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.last_name"
                                type="text"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                required
                            />
                            <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.email"
                                type="email"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                required
                            />
                            <p class="mt-1 text-xs text-gray-500">You'll receive the background check link at this email</p>
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Phone <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.phone"
                                type="tel"
                                placeholder="(555) 123-4567"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                required
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <!-- DOB and Zipcode -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Date of Birth <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.dob"
                                type="date"
                                :max="maxDob"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                required
                            />
                            <p v-if="form.errors.dob" class="mt-1 text-sm text-red-600">{{ form.errors.dob }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                ZIP Code <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.zipcode"
                                type="text"
                                maxlength="10"
                                placeholder="12345"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                required
                            />
                            <p v-if="form.errors.zipcode" class="mt-1 text-sm text-red-600">{{ form.errors.zipcode }}</p>
                        </div>
                    </div>

                    <!-- SSN -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Social Security Number <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                :value="form.ssn"
                                @input="formatSSN"
                                type="text"
                                placeholder="XXX-XX-XXXX"
                                maxlength="11"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500 pr-10"
                                required
                            />
                            <LockClosedIcon class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Your SSN is encrypted and only used for identity verification</p>
                        <p v-if="form.errors.ssn" class="mt-1 text-sm text-red-600">{{ form.errors.ssn }}</p>
                    </div>

                    <!-- Driver's License (Optional) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Driver's License Number <span class="text-gray-400">(Optional)</span>
                            </label>
                            <input 
                                v-model="form.driver_license_number"
                                type="text"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                            />
                            <p v-if="form.errors.driver_license_number" class="mt-1 text-sm text-red-600">{{ form.errors.driver_license_number }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                License State
                            </label>
                            <select 
                                v-model="form.driver_license_state"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                :disabled="!form.driver_license_number"
                            >
                                <option value="">Select State</option>
                                <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                            </select>
                            <p v-if="form.errors.driver_license_state" class="mt-1 text-sm text-red-600">{{ form.errors.driver_license_state }}</p>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <InformationCircleIcon class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-amber-800">
                                <p class="font-medium mb-1">What happens next?</p>
                                <ol class="list-decimal list-inside space-y-1 text-amber-700">
                                    <li>You'll receive an email from Checkr to complete the process</li>
                                    <li>Checkr will guide you through payment ($35 fee paid by you)</li>
                                    <li>Results typically arrive within 2-5 business days</li>
                                    <li>Once cleared, your profile will display a verified badge</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Consent -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input 
                                v-model="form.consent"
                                type="checkbox"
                                class="mt-1 h-4 w-4 text-sky-600 rounded border-gray-300 focus:ring-sky-500"
                            />
                            <span class="text-sm text-gray-700">
                                I consent to a background check being conducted by Checkr. I understand that this check may include 
                                criminal history, sex offender registry, and other public records searches. I certify that all 
                                information provided is accurate and complete.
                                <span class="text-red-500">*</span>
                            </span>
                        </label>
                        <p v-if="form.errors.consent" class="mt-2 text-sm text-red-600">{{ form.errors.consent }}</p>
                    </div>

                    <!-- Submit -->
                    <div class="flex gap-3">
                        <button 
                            type="button" 
                            @click="showForm = false; form.reset();"
                            class="px-4 py-2.5 border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="flex-1 px-4 py-2.5 bg-sky-600 text-white rounded-xl font-medium hover:bg-sky-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!form.consent || form.processing"
                        >
                            {{ form.processing ? 'Submitting...' : 'Submit & Continue to Payment' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- History -->
            <div v-if="history.length > 0" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Background Check History</h2>
                <div class="space-y-3">
                    <Link 
                        v-for="item in history" 
                        :key="item.id"
                        :href="`/provider/background-check/${item.id}`"
                        class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded-lg transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full" :class="item.status_display.badge_classes">
                                <component :is="getStatusIcon(item.status)" class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">{{ item.status_display.label }}</div>
                                <div class="text-sm text-gray-500">Initiated {{ item.initiated_at }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span 
                                class="px-3 py-1 rounded-full text-sm font-medium"
                                :class="item.status_display.badge_classes"
                            >
                                {{ item.status_display.label }}
                            </span>
                            <div v-if="item.expires_at" class="text-xs text-gray-500 mt-1">
                                Expires: {{ item.expires_at }}
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
