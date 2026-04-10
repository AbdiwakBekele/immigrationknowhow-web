<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import {
    PencilSquareIcon,
    BriefcaseIcon,
    GlobeAltIcon,
    MapPinIcon,
    PhoneIcon,
    EnvelopeIcon,
    CheckBadgeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    provider: { type: Object, default: () => ({}) },
});

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') return 'Not set';
    return `$${Number(value).toFixed(2)}`;
};
</script>

<template>
    <Head title="My Profile" />

    <ProviderLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <img
                            :src="user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(provider?.business_name || user?.first_name || 'P')}&background=3B95F3&color=fff&size=120`"
                            class="w-20 h-20 rounded-2xl object-cover bg-slate-100"
                        />
                        <div>
                            <h1 class="text-2xl font-display font-bold text-slate-900">
                                {{ provider?.business_name || 'Provider Profile' }}
                            </h1>
                            <p class="text-slate-500 mt-1">{{ provider?.tagline || 'No tagline added yet.' }}</p>
                            <div class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                                <CheckBadgeIcon class="h-4 w-4 text-emerald-600" />
                                <span class="capitalize">{{ provider?.verification_status || 'pending' }}</span>
                            </div>
                        </div>
                    </div>

                    <Link
                        :href="route('provider.profile.edit')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl font-medium transition-colors"
                    >
                        <PencilSquareIcon class="h-5 w-5" />
                        Edit Profile
                    </Link>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center gap-2 text-slate-700 mb-2">
                        <StarSolid class="h-5 w-5 text-yellow-500" />
                        <span class="font-semibold">Rating</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">{{ Number(provider?.average_rating || 0).toFixed(1) }}</p>
                    <p class="text-sm text-slate-500">{{ provider?.total_reviews || 0 }} reviews</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center gap-2 text-slate-700 mb-2">
                        <BriefcaseIcon class="h-5 w-5" />
                        <span class="font-semibold">Service Types</span>
                    </div>
                    <p class="text-sm text-slate-700">{{ provider?.service_types?.join(', ') || 'Not specified' }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center gap-2 text-slate-700 mb-2">
                        <GlobeAltIcon class="h-5 w-5" />
                        <span class="font-semibold">Languages</span>
                    </div>
                    <p class="text-sm text-slate-700">{{ provider?.languages_offered?.join(', ') || 'Not specified' }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-lg font-display font-bold text-slate-900 mb-3">About</h2>
                <p class="text-slate-700 whitespace-pre-line">{{ provider?.bio || 'No bio added yet.' }}</p>
                <p class="text-slate-600 whitespace-pre-line mt-3">{{ provider?.description || 'No detailed description yet.' }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Contact</h2>
                <div class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div class="flex items-center gap-2 text-slate-700">
                        <EnvelopeIcon class="h-4 w-4 text-slate-500" />
                        <span>{{ provider?.business_email || 'No email' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-700">
                        <PhoneIcon class="h-4 w-4 text-slate-500" />
                        <span>{{ provider?.business_phone || 'No phone' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-700 sm:col-span-2">
                        <MapPinIcon class="h-4 w-4 text-slate-500" />
                        <span>{{ provider?.service_areas?.join(', ') || 'No specific service areas set' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-700 sm:col-span-2">
                        <GlobeAltIcon class="h-4 w-4 text-slate-500" />
                        <span>{{ provider?.website || 'No website' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Services & Expertise</h2>
                <div class="space-y-3 text-sm text-slate-700">
                    <p><span class="font-semibold text-slate-900">Specializations:</span> {{ provider?.specializations?.join(', ') || 'Not specified' }}</p>
                    <p><span class="font-semibold text-slate-900">Years of Experience:</span> {{ provider?.years_experience ?? 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">Serves Remote:</span> {{ provider?.serves_remote ? 'Yes' : 'No' }}</p>
                    <p><span class="font-semibold text-slate-900">Serves In-Person:</span> {{ provider?.serves_in_person ? 'Yes' : 'No' }}</p>
                    <p><span class="font-semibold text-slate-900">Service Radius (miles):</span> {{ provider?.service_radius_miles ?? 'Not set' }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Pricing</h2>
                <div class="space-y-3 text-sm text-slate-700">
                    <p><span class="font-semibold text-slate-900">Pricing Model:</span> {{ provider?.pricing_model || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">Hourly Rate:</span> {{ formatCurrency(provider?.hourly_rate) }}</p>
                    <p><span class="font-semibold text-slate-900">Consultation Fee:</span> {{ formatCurrency(provider?.consultation_fee) }}</p>
                    <p><span class="font-semibold text-slate-900">Free Consultation:</span> {{ provider?.free_consultation ? 'Yes' : 'No' }}</p>
                    <p><span class="font-semibold text-slate-900">Pricing Notes:</span> {{ provider?.pricing_notes || 'No pricing notes' }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Credentials</h2>
                <div class="space-y-3 text-sm text-slate-700 mb-4">
                    <p><span class="font-semibold text-slate-900">License Number:</span> {{ provider?.license_number || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">License State:</span> {{ provider?.license_state || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">License Expiry:</span> {{ provider?.license_expiry || 'Not set' }}</p>
                </div>

                <div>
                    <h3 class="font-semibold text-slate-900 mb-2">Certifications</h3>
                    <div v-if="provider?.certifications?.length" class="space-y-2">
                        <div
                            v-for="(cert, index) in provider.certifications"
                            :key="index"
                            class="text-sm text-slate-700 p-3 rounded-lg bg-slate-50"
                        >
                            <span class="font-medium text-slate-900">{{ cert.name || 'Untitled certification' }}</span>
                            <span v-if="cert.issuer"> - {{ cert.issuer }}</span>
                            <span v-if="cert.year"> ({{ cert.year }})</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500">No certifications added.</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Social & Availability</h2>
                <div class="space-y-3 text-sm text-slate-700">
                    <p><span class="font-semibold text-slate-900">LinkedIn:</span> {{ provider?.linkedin_url || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">Facebook:</span> {{ provider?.facebook_url || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">Twitter:</span> {{ provider?.twitter_url || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">Instagram:</span> {{ provider?.instagram_url || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">YouTube:</span> {{ provider?.youtube_url || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">TikTok:</span> {{ provider?.tiktok_url || 'Not set' }}</p>
                    <p><span class="font-semibold text-slate-900">Accepting Clients:</span> {{ provider?.accepting_clients ? 'Yes' : 'No' }}</p>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
