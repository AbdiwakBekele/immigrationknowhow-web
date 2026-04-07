<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ShieldCheckIcon,
    DocumentTextIcon,
    CameraIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    ArrowUpTrayIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';
import { CheckBadgeIcon } from '@heroicons/vue/24/solid';
import { ref, computed } from 'vue';

const props = defineProps({
    isVerified: { type: Boolean, default: false },
    verification: { type: Object, default: null },
    history: { type: Array, default: () => [] },
    canSubmit: { type: Boolean, default: true },
});

const showUploadForm = ref(false);

const form = useForm({
    document_type: 'drivers_license',
    document_front: null,
    document_back: null,
    selfie: null,
    additional_documents: [],
});

const documentTypes = [
    { value: 'drivers_license', label: "Driver's License" },
    { value: 'passport', label: 'Passport' },
    { value: 'state_id', label: 'State ID' },
    { value: 'professional_license', label: 'Professional License' },
];

const frontPreview = ref(null);
const backPreview = ref(null);
const selfiePreview = ref(null);

const handleFileSelect = (field, event) => {
    const file = event.target.files[0];
    if (!file) return;

    form[field] = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
        if (field === 'document_front') frontPreview.value = e.target.result;
        if (field === 'document_back') backPreview.value = e.target.result;
        if (field === 'selfie') selfiePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const submit = () => {
    form.post('/provider/verification', {
        forceFormData: true,
        onSuccess: () => {
            showUploadForm.value = false;
            form.reset();
            frontPreview.value = null;
            backPreview.value = null;
            selfiePreview.value = null;
        },
    });
};

const getStatusInfo = (status) => {
    const info = {
        pending: { 
            color: 'text-yellow-600 bg-yellow-100', 
            icon: ClockIcon,
            label: 'Pending Review'
        },
        under_review: { 
            color: 'text-blue-600 bg-blue-100', 
            icon: ClockIcon,
            label: 'Under Review'
        },
        approved: { 
            color: 'text-green-600 bg-green-100', 
            icon: CheckCircleIcon,
            label: 'Approved'
        },
        rejected: { 
            color: 'text-red-600 bg-red-100', 
            icon: XCircleIcon,
            label: 'Rejected'
        },
        expired: { 
            color: 'text-gray-600 bg-gray-100', 
            icon: ExclamationTriangleIcon,
            label: 'Expired'
        },
    };
    return info[status] || info.pending;
};
</script>

<template>
    <Head title="Identity Verification" />

    <ProviderLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-display font-bold text-gray-900">Identity Verification</h1>
                <p class="text-gray-500 mt-1">Verify your identity to build trust with potential clients</p>
            </div>

            <!-- Verified Status -->
            <div v-if="isVerified" class="bg-green-50 border border-green-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-green-100 rounded-full">
                        <CheckBadgeIcon class="h-8 w-8 text-green-600" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-green-800">You're Verified!</h2>
                        <p class="text-green-700">Your identity has been verified. A badge is displayed on your profile.</p>
                    </div>
                </div>
            </div>

            <!-- Current Verification Status -->
            <div v-else-if="verification && !['rejected', 'expired'].includes(verification.status)" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-full" :class="getStatusInfo(verification.status).color">
                        <component :is="getStatusInfo(verification.status).icon" class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ getStatusInfo(verification.status).label }}
                        </h2>
                        <p class="text-gray-600 mt-1">
                            <template v-if="verification.status === 'pending'">
                                Your documents have been submitted and are waiting to be reviewed. This usually takes 2-3 business days.
                            </template>
                            <template v-else-if="verification.status === 'under_review'">
                                Our team is currently reviewing your documents. You'll receive a notification once complete.
                            </template>
                        </p>
                        <div class="mt-3 text-sm text-gray-500">
                            <span>Submitted: {{ verification.submitted_at }}</span>
                            <span v-if="verification.document_type" class="ml-4">Document: {{ verification.document_type }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejected/Can Resubmit -->
            <div v-else-if="verification?.status === 'rejected'" class="bg-red-50 border border-red-200 rounded-2xl p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-red-100 rounded-full">
                        <XCircleIcon class="h-6 w-6 text-red-600" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-red-800">Verification Rejected</h2>
                        <p class="text-red-700 mt-1">
                            {{ verification.rejection_reason || 'Your verification was not approved. Please review the feedback and submit again.' }}
                        </p>
                        <button 
                            @click="showUploadForm = true"
                            class="btn-primary mt-4"
                        >
                            Resubmit Verification
                        </button>
                    </div>
                </div>
            </div>

            <!-- Start Verification -->
            <div v-else-if="canSubmit && !showUploadForm" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center mb-6">
                <ShieldCheckIcon class="h-16 w-16 text-primary-600 mx-auto mb-4" />
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Get Verified</h2>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    Verified providers see higher engagement and more leads. Complete identity verification to earn your badge.
                </p>
                <button 
                    @click="showUploadForm = true"
                    class="btn-primary btn-lg"
                >
                    Start Verification
                </button>
            </div>

            <!-- Upload Form -->
            <div v-if="showUploadForm" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-6">Submit Verification Documents</h2>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Document Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label 
                                v-for="type in documentTypes" 
                                :key="type.value"
                                class="flex items-center gap-3 p-4 border rounded-xl cursor-pointer transition-colors"
                                :class="form.document_type === type.value ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:border-gray-300'"
                            >
                                <input 
                                    v-model="form.document_type" 
                                    type="radio" 
                                    :value="type.value"
                                    class="text-primary-600"
                                />
                                <span class="font-medium text-gray-900">{{ type.label }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Document Front -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Document Front <span class="text-red-500">*</span>
                        </label>
                        <div 
                            class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors"
                            :class="frontPreview ? 'border-primary-300 bg-primary-50' : 'border-gray-300 hover:border-gray-400'"
                            @click="$refs.frontInput.click()"
                        >
                            <img v-if="frontPreview" :src="frontPreview" class="max-h-48 mx-auto rounded-lg" />
                            <div v-else>
                                <DocumentTextIcon class="h-12 w-12 text-gray-400 mx-auto mb-2" />
                                <p class="text-gray-600">Click to upload front of document</p>
                                <p class="text-sm text-gray-400">JPG, PNG up to 5MB</p>
                            </div>
                        </div>
                        <input 
                            ref="frontInput"
                            type="file" 
                            accept="image/*"
                            class="hidden"
                            @change="handleFileSelect('document_front', $event)"
                        />
                        <p v-if="form.errors.document_front" class="mt-1 text-sm text-red-600">{{ form.errors.document_front }}</p>
                    </div>

                    <!-- Document Back (optional for passport) -->
                    <div v-if="form.document_type !== 'passport'">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Document Back <span class="text-gray-400">(if applicable)</span>
                        </label>
                        <div 
                            class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors"
                            :class="backPreview ? 'border-primary-300 bg-primary-50' : 'border-gray-300 hover:border-gray-400'"
                            @click="$refs.backInput.click()"
                        >
                            <img v-if="backPreview" :src="backPreview" class="max-h-48 mx-auto rounded-lg" />
                            <div v-else>
                                <DocumentTextIcon class="h-12 w-12 text-gray-400 mx-auto mb-2" />
                                <p class="text-gray-600">Click to upload back of document</p>
                            </div>
                        </div>
                        <input 
                            ref="backInput"
                            type="file" 
                            accept="image/*"
                            class="hidden"
                            @change="handleFileSelect('document_back', $event)"
                        />
                    </div>

                    <!-- Selfie -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Selfie with Document <span class="text-red-500">*</span>
                        </label>
                        <div 
                            class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors"
                            :class="selfiePreview ? 'border-primary-300 bg-primary-50' : 'border-gray-300 hover:border-gray-400'"
                            @click="$refs.selfieInput.click()"
                        >
                            <img v-if="selfiePreview" :src="selfiePreview" class="max-h-48 mx-auto rounded-lg" />
                            <div v-else>
                                <CameraIcon class="h-12 w-12 text-gray-400 mx-auto mb-2" />
                                <p class="text-gray-600">Take a selfie holding your document</p>
                                <p class="text-sm text-gray-400">Face and document must be clearly visible</p>
                            </div>
                        </div>
                        <input 
                            ref="selfieInput"
                            type="file" 
                            accept="image/*"
                            class="hidden"
                            @change="handleFileSelect('selfie', $event)"
                        />
                        <p v-if="form.errors.selfie" class="mt-1 text-sm text-red-600">{{ form.errors.selfie }}</p>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <InformationCircleIcon class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-1">Tips for faster approval:</p>
                                <ul class="list-disc list-inside space-y-1 text-blue-700">
                                    <li>Ensure all text on documents is clearly readable</li>
                                    <li>Avoid glare or shadows on documents</li>
                                    <li>Your face should be fully visible in the selfie</li>
                                    <li>Document should not be expired</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex gap-3">
                        <button 
                            type="button" 
                            @click="showUploadForm = false"
                            class="btn-secondary"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="btn-primary flex-1"
                            :disabled="!form.document_front || !form.selfie || form.processing"
                        >
                            <ArrowUpTrayIcon v-if="!form.processing" class="h-5 w-5 mr-2" />
                            {{ form.processing ? 'Uploading...' : 'Submit for Review' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Verification History -->
            <div v-if="history.length > 1" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Verification History</h2>
                <div class="space-y-3">
                    <div 
                        v-for="item in history" 
                        :key="item.id"
                        class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0"
                    >
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full" :class="getStatusInfo(item.status).color">
                                <component :is="getStatusInfo(item.status).icon" class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">{{ item.document_type }}</div>
                                <div class="text-sm text-gray-500">Submitted {{ item.submitted_at }}</div>
                            </div>
                        </div>
                        <span 
                            class="px-3 py-1 rounded-full text-sm font-medium"
                            :class="getStatusInfo(item.status).color"
                        >
                            {{ item.status_label }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
