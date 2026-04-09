<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ArrowLeftIcon,
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
    UserIcon,
    EnvelopeOpenIcon,
    IdentificationIcon,
    CalendarIcon,
} from '@heroicons/vue/24/outline';
import { CheckBadgeIcon as CheckBadgeSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    backgroundCheck: { type: Object, required: true },
});

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

const statusColors = {
    pending: 'bg-gray-100 text-gray-800 border-gray-200',
    invited: 'bg-blue-100 text-blue-800 border-blue-200',
    completed: 'bg-amber-100 text-amber-800 border-amber-200',
    clear: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    consider: 'bg-orange-100 text-orange-800 border-orange-200',
    suspended: 'bg-red-100 text-red-800 border-red-200',
    dispute: 'bg-purple-100 text-purple-800 border-purple-200',
    expired: 'bg-gray-100 text-gray-600 border-gray-200',
};
</script>

<template>
    <Head title="Background Check Details" />

    <ProviderLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Back Link -->
            <Link 
                href="/provider/background-check"
                class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-6"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Back to Background Checks
            </Link>

            <!-- Header Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                <!-- Status Banner -->
                <div 
                    class="px-6 py-4 border-b"
                    :class="statusColors[backgroundCheck.status]"
                >
                    <div class="flex items-center gap-3">
                        <component :is="getStatusIcon(backgroundCheck.status)" class="h-6 w-6" />
                        <div>
                            <h1 class="text-lg font-semibold">{{ backgroundCheck.status_display.label }}</h1>
                            <p class="text-sm opacity-90">{{ backgroundCheck.status_display.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Candidate Info -->
                        <div class="space-y-4">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Candidate Information</h2>
                            
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <UserIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Full Name</div>
                                    <div class="font-medium text-gray-900">{{ backgroundCheck.full_name }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <EnvelopeOpenIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Email</div>
                                    <div class="font-medium text-gray-900">{{ backgroundCheck.email }}</div>
                                </div>
                            </div>

                            <div v-if="backgroundCheck.masked_ssn" class="flex items-center gap-3">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <IdentificationIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">SSN</div>
                                    <div class="font-medium text-gray-900 font-mono">{{ backgroundCheck.masked_ssn }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Check Info -->
                        <div class="space-y-4">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Check Details</h2>
                            
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <ShieldCheckIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Package</div>
                                    <div class="font-medium text-gray-900 capitalize">{{ backgroundCheck.package?.replace('_', ' ') || 'Standard' }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <CalendarIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Initiated</div>
                                    <div class="font-medium text-gray-900">{{ backgroundCheck.initiated_at }}</div>
                                </div>
                            </div>

                            <div v-if="backgroundCheck.completed_at" class="flex items-center gap-3">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <CheckBadgeIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Completed</div>
                                    <div class="font-medium text-gray-900">{{ backgroundCheck.completed_at }}</div>
                                </div>
                            </div>

                            <div v-if="backgroundCheck.expires_at" class="flex items-center gap-3">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <CalendarDaysIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Expires</div>
                                    <div class="font-medium text-gray-900">
                                        {{ backgroundCheck.expires_at }}
                                        <span v-if="backgroundCheck.days_until_expiry > 0" class="text-sm text-gray-500 ml-1">
                                            ({{ backgroundCheck.days_until_expiry }} days)
                                        </span>
                                        <span v-else-if="backgroundCheck.days_until_expiry <= 0" class="text-sm text-red-500 ml-1">
                                            (Expired)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status-specific messaging -->
            <div v-if="backgroundCheck.status === 'invited'" class="bg-blue-50 border border-blue-200 rounded-2xl p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <EnvelopeIcon class="h-6 w-6 text-blue-600" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-blue-800">Check Your Email</h2>
                        <p class="text-blue-700 mt-1">
                            An invitation has been sent to <strong>{{ backgroundCheck.email }}</strong>. 
                            Please check your inbox (and spam folder) for an email from Checkr to complete your background check.
                        </p>
                        <p class="text-blue-600 text-sm mt-2">
                            The invitation link will expire in 7 days.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="backgroundCheck.status === 'clear'" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-emerald-100 rounded-full">
                        <CheckBadgeSolid class="h-6 w-6 text-emerald-600" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-emerald-800">Background Check Cleared</h2>
                        <p class="text-emerald-700 mt-1">
                            Your background check has been verified. A trust badge is now displayed on your public profile, 
                            helping potential clients see that you've been verified.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="backgroundCheck.status === 'consider'" class="bg-orange-50 border border-orange-200 rounded-2xl p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-orange-100 rounded-full">
                        <ExclamationTriangleIcon class="h-6 w-6 text-orange-600" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-orange-800">Additional Review Required</h2>
                        <p class="text-orange-700 mt-1">
                            Your background check has returned results that require review. This doesn't necessarily mean 
                            there's a problem — some results simply need human verification.
                        </p>
                        <p class="text-orange-600 text-sm mt-2">
                            If you believe there's an error, you can dispute the results through Checkr. 
                            You should have received details via email.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between">
                <Link 
                    href="/provider/background-check"
                    class="px-4 py-2.5 border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    ← Back to Overview
                </Link>

                <form 
                    v-if="['invited', 'completed'].includes(backgroundCheck.status)"
                    :action="`/provider/background-check/${backgroundCheck.id}/refresh`"
                    method="POST"
                >
                    <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                    <button 
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-colors"
                    >
                        <ArrowPathIcon class="h-5 w-5" />
                        Refresh Status
                    </button>
                </form>
            </div>
        </div>
    </ProviderLayout>
</template>
