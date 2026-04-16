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
        <div class="admin-page-container">
            <!-- Header -->
            <section class="admin-hero-card">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Trust and safety</p>
                <h1 class="mt-2 admin-title">Background check</h1>
                <p class="admin-subtitle">
                    Complete a background check to become a verified provider and build trust with clients.
                </p>
            </section>

            <div v-if="form.errors.error" class="card border-red-200/80 bg-red-50/90 p-4">
                <div class="flex items-center gap-3">
                    <XCircleIcon class="h-5 w-5 shrink-0 text-red-600" />
                    <p class="text-sm font-medium text-red-900">{{ form.errors.error }}</p>
                </div>
            </div>

            <!-- Cleared Status -->
            <div v-if="backgroundCheck?.status === 'clear' && backgroundCheck?.is_valid" class="card border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 to-white p-6 shadow-soft">
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
            <div v-else-if="backgroundCheck && !['pending', 'expired'].includes(backgroundCheck.status)" class="card p-6 shadow-soft">
                <div class="flex items-start gap-4">
                    <div class="rounded-2xl p-3" :class="backgroundCheck.status_display.badge_classes">
                        <component :is="getStatusIcon(backgroundCheck.status)" class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ backgroundCheck.status_display.label }}
                        </h2>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600">
                            {{ backgroundCheck.status_display.description }}
                        </p>
                        <div class="mt-3 text-xs text-slate-500 sm:text-sm">
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
                            <button type="submit" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700">
                                <ArrowPathIcon class="h-4 w-4" />
                                Refresh Status
                            </button>
                        </form>
                    </div>
                    <Link 
                        :href="`/provider/background-check/${backgroundCheck.id}`"
                        class="shrink-0 text-sm font-medium text-primary-600 hover:text-primary-700"
                    >
                        View details →
                    </Link>
                </div>
            </div>

            <!-- Consider/Suspended Status -->
            <div v-if="backgroundCheck?.status === 'consider'" class="card border-orange-200/90 bg-gradient-to-br from-orange-50/95 to-white p-6 shadow-soft">
                <div class="flex items-start gap-4">
                    <div class="rounded-2xl bg-orange-100 p-3">
                        <ExclamationTriangleIcon class="h-6 w-6 text-orange-600" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-orange-900">Review required</h2>
                        <p class="mt-1 text-sm leading-relaxed text-orange-800/90">
                            Your background check requires additional review. You may receive a follow-up from Checkr. 
                            If you believe there is an error, you can dispute the results.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Initiate New Check -->
            <div v-if="canInitiate && !showForm" class="card overflow-hidden p-6 shadow-soft">
                <div class="flex items-start gap-4">
                    <div class="rounded-2xl bg-primary-100 p-3">
                        <ShieldCheckIcon class="h-6 w-6 text-primary-600" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold text-slate-900">Start your background check</h2>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600">
                            A background check helps build trust with potential clients. The check is conducted by 
                            <a href="https://checkr.com" target="_blank" rel="noopener noreferrer" class="font-medium text-primary-600 hover:underline">Checkr</a>, 
                            a trusted third-party screening service.
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-slate-600">
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
                            type="button"
                            @click="showForm = true"
                            class="btn-primary mt-5"
                        >
                            <ShieldCheckIcon class="h-5 w-5" />
                            Start background check
                        </button>
                    </div>
                </div>
            </div>

            <!-- Background Check Form -->
            <div v-if="showForm" class="card p-6 shadow-soft sm:p-8">
                <h2 class="font-display text-lg font-semibold text-slate-900">Background check information</h2>
                <p class="helper-text mb-6 mt-1 text-sm">Please provide accurate information. This will be verified against official records.</p>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Name Fields -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="label">
                                First name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.first_name"
                                type="text"
                                class="input"
                                required
                            />
                            <p v-if="form.errors.first_name" class="error-text">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="label">Middle name</label>
                            <input 
                                v-model="form.middle_name"
                                type="text"
                                class="input"
                            />
                        </div>
                        <div>
                            <label class="label">
                                Last name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.last_name"
                                type="text"
                                class="input"
                                required
                            />
                            <p v-if="form.errors.last_name" class="error-text">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="label">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.email"
                                type="email"
                                class="input"
                                required
                            />
                            <p class="helper-text">You will receive the background check link at this email.</p>
                            <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="label">
                                Phone <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.phone"
                                type="tel"
                                placeholder="(555) 123-4567"
                                class="input"
                                required
                            />
                            <p v-if="form.errors.phone" class="error-text">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <!-- DOB and Zipcode -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="label">
                                Date of birth <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.dob"
                                type="date"
                                :max="maxDob"
                                class="input"
                                required
                            />
                            <p v-if="form.errors.dob" class="error-text">{{ form.errors.dob }}</p>
                        </div>
                        <div>
                            <label class="label">
                                ZIP code <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.zipcode"
                                type="text"
                                maxlength="10"
                                placeholder="12345"
                                class="input"
                                required
                            />
                            <p v-if="form.errors.zipcode" class="error-text">{{ form.errors.zipcode }}</p>
                        </div>
                    </div>

                    <!-- SSN -->
                    <div>
                        <label class="label">
                            Social Security Number <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                :value="form.ssn"
                                type="text"
                                placeholder="XXX-XX-XXXX"
                                maxlength="11"
                                class="input pr-11"
                                required
                                @input="formatSSN"
                            />
                            <LockClosedIcon class="pointer-events-none absolute right-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        </div>
                        <p class="helper-text">Your SSN is encrypted and only used for identity verification.</p>
                        <p v-if="form.errors.ssn" class="error-text">{{ form.errors.ssn }}</p>
                    </div>

                    <!-- Driver's License (Optional) -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="label">
                                Driver's license number <span class="font-normal text-slate-400">(optional)</span>
                            </label>
                            <input 
                                v-model="form.driver_license_number"
                                type="text"
                                class="input"
                            />
                            <p v-if="form.errors.driver_license_number" class="error-text">{{ form.errors.driver_license_number }}</p>
                        </div>
                        <div>
                            <label class="label">
                                License state
                            </label>
                            <select 
                                v-model="form.driver_license_state"
                                class="input"
                                :disabled="!form.driver_license_number"
                            >
                                <option value="">Select State</option>
                                <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                            </select>
                            <p v-if="form.errors.driver_license_state" class="mt-1 text-sm text-red-600">{{ form.errors.driver_license_state }}</p>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="rounded-2xl border border-amber-200/80 bg-amber-50/90 p-4">
                        <div class="flex gap-3">
                            <InformationCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                            <div class="text-sm text-amber-900">
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
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input 
                                v-model="form.consent"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="text-sm leading-relaxed text-slate-700">
                                I consent to a background check being conducted by Checkr. I understand that this check may include 
                                criminal history, sex offender registry, and other public records searches. I certify that all 
                                information provided is accurate and complete.
                                <span class="text-red-500">*</span>
                            </span>
                        </label>
                        <p v-if="form.errors.consent" class="mt-2 text-sm text-red-600">{{ form.errors.consent }}</p>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button 
                            type="button" 
                            class="btn btn-outline sm:w-auto"
                            @click="showForm = false; form.reset();"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="btn-primary flex-1 sm:min-w-[14rem]"
                            :disabled="!form.consent || form.processing"
                        >
                            {{ form.processing ? 'Submitting…' : 'Submit and continue to payment' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- History -->
            <div v-if="history.length > 0" class="card p-6 shadow-soft">
                <h2 class="mb-4 font-display text-lg font-semibold text-slate-900">Background check history</h2>
                <div class="divide-y divide-slate-100">
                    <Link 
                        v-for="item in history" 
                        :key="item.id"
                        :href="`/provider/background-check/${item.id}`"
                        class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-slate-50"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="rounded-xl p-2" :class="item.status_display.badge_classes">
                                <component :is="getStatusIcon(item.status)" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <div class="font-medium text-slate-900">{{ item.status_display.label }}</div>
                                <div class="text-sm text-slate-500">Initiated {{ item.initiated_at }}</div>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <span 
                                class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                :class="item.status_display.badge_classes"
                            >
                                {{ item.status_display.label }}
                            </span>
                            <div v-if="item.expires_at" class="mt-1 text-xs text-slate-500">
                                Expires: {{ item.expires_at }}
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
