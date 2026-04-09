<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    CheckBadgeIcon,
    MapPinIcon,
    PhoneIcon,
    GlobeAltIcon,
    ClockIcon,
    ChatBubbleLeftRightIcon,
    CurrencyDollarIcon,
    LanguageIcon,
    BriefcaseIcon,
    ShieldCheckIcon,
    ArrowLeftIcon,
    ShareIcon,
    HeartIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid, CheckBadgeIcon as CheckBadgeSolid } from '@heroicons/vue/24/solid';
import { StarIcon as StarOutline } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    provider: { type: Object, required: true },
    similarProviders: { type: Array, default: () => [] },
    canContactProvider: { type: Boolean, default: false },
    serviceTypeLabels: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const showInquiryModal = ref(false);

const inquiryForm = useForm({
    service_type: props.provider.service_types?.[0] || 'other',
    message: '',
    /** Required by LeadController — in-app messaging */
    preferred_contact_method: 'message',
    requirements: [],
    urgency: 'normal',
});

const submitInquiry = () => {
    inquiryForm.post(route('leads.store', props.provider.slug), {
        preserveScroll: true,
        onSuccess: () => {
            showInquiryModal.value = false;
            inquiryForm.reset();
        },
    });
};

// Safely get rating as number
const averageRating = computed(() => {
    const rating = props.provider.average_rating;
    return rating ? Number(rating) : 0;
});

const totalReviews = computed(() => props.provider.total_reviews || 0);

// Language labels
const languageLabels = {
    en: 'English', es: 'Spanish', zh: 'Chinese', hi: 'Hindi', ar: 'Arabic',
    pt: 'Portuguese', fr: 'French', de: 'German', ja: 'Japanese', ko: 'Korean',
    vi: 'Vietnamese', tl: 'Tagalog', ru: 'Russian', it: 'Italian', pl: 'Polish',
};

const getLanguageLabel = (code) => languageLabels[code] || code;

// Format pricing display
const pricingDisplay = computed(() => {
    const p = props.provider;
    if (p.hourly_rate) return `$${Number(p.hourly_rate).toFixed(0)}/hr`;
    if (p.consultation_fee) return `$${Number(p.consultation_fee).toFixed(0)} consultation`;
    return 'Contact for pricing';
});

// Reviews from provider (loaded via relationship)
const reviews = computed(() => props.provider.reviews || []);

const ratingDistribution = computed(() => {
    const dist = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    reviews.value.forEach(r => {
        const rating = Math.round(r.rating || 0);
        if (rating >= 1 && rating <= 5) dist[rating]++;
    });
    const total = reviews.value.length || 1;
    return Object.entries(dist).reverse().map(([rating, count]) => ({
        rating: parseInt(rating),
        count,
        percentage: Math.round((count / total) * 100),
    }));
});

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { 
        year: 'numeric', month: 'short', day: 'numeric' 
    });
};
</script>

<template>
    <Head :title="provider.business_name || 'Provider Profile'" />

    <AppLayout>
        <div class="min-h-screen bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Back Button -->
                <Link :href="route('marketplace.index')" class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 mb-6 group">
                    <ArrowLeftIcon class="h-4 w-4 group-hover:-translate-x-1 transition-transform" />
                    Back to Providers
                </Link>

                <div class="grid lg:grid-cols-3 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Provider Header Card -->
                        <div class="bg-white rounded-2xl shadow-soft overflow-hidden">
                            <!-- Cover Gradient -->
                            <div class="h-32 bg-gradient-to-r from-primary-600 via-primary-500 to-accent-500"></div>
                            
                            <div class="px-6 pb-6">
                                <!-- Avatar & Basic Info -->
                                <div class="flex flex-col sm:flex-row sm:items-end gap-4 -mt-12">
                                    <div class="relative">
                                        <img 
                                            :src="provider.user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(provider.business_name || 'P')}&background=3B95F3&color=fff&size=96`" 
                                            :alt="provider.business_name"
                                            class="h-24 w-24 rounded-2xl border-4 border-white shadow-lg object-cover bg-white"
                                        />
                                        <div v-if="provider.verification_status === 'approved'" class="absolute -bottom-1 -right-1 bg-white rounded-full p-0.5">
                                            <CheckBadgeSolid class="h-6 w-6 text-primary-600" />
                                        </div>
                                    </div>
                                    <div class="flex-1 sm:pb-2">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h1 class="text-2xl font-display font-bold text-slate-900">
                                                {{ provider.business_name }}
                                            </h1>
                                            <span v-if="provider.is_featured" class="px-2 py-0.5 bg-secondary-100 text-secondary-700 text-xs font-semibold rounded-full">
                                                Featured
                                            </span>
                                        </div>
                                        <p v-if="provider.tagline" class="text-slate-600 mt-1">{{ provider.tagline }}</p>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <span 
                                                v-for="label in serviceTypeLabels" 
                                                :key="label"
                                                class="px-2.5 py-1 bg-primary-50 text-primary-700 text-sm font-medium rounded-lg"
                                            >
                                                {{ label }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 sm:pb-2">
                                        <button class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors">
                                            <ShareIcon class="h-5 w-5" />
                                        </button>
                                        <button class="p-2 text-slate-400 hover:text-red-500 rounded-lg hover:bg-slate-100 transition-colors">
                                            <HeartIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Stats Row -->
                                <div class="mt-6 flex flex-wrap gap-6">
                                    <div class="flex items-center gap-2">
                                        <div class="flex">
                                            <template v-for="i in 5" :key="i">
                                                <StarSolid v-if="i <= Math.round(averageRating)" class="h-5 w-5 text-secondary-500" />
                                                <StarOutline v-else class="h-5 w-5 text-slate-300" />
                                            </template>
                                        </div>
                                        <span class="font-semibold text-slate-900">{{ averageRating.toFixed(1) }}</span>
                                        <span class="text-slate-500">({{ totalReviews }} {{ totalReviews === 1 ? 'review' : 'reviews' }})</span>
                                    </div>
                                    <div v-if="provider.years_experience" class="flex items-center gap-2 text-slate-600">
                                        <BriefcaseIcon class="h-5 w-5" />
                                        <span>{{ provider.years_experience }} years experience</span>
                                    </div>
                                    <div v-if="provider.user?.city || provider.user?.state" class="flex items-center gap-2 text-slate-600">
                                        <MapPinIcon class="h-5 w-5" />
                                        <span>{{ [provider.user?.city, provider.user?.state].filter(Boolean).join(', ') }}</span>
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span v-if="provider.serves_remote" class="px-3 py-1 bg-blue-50 text-blue-700 text-sm font-medium rounded-full">
                                        🌐 Remote Available
                                    </span>
                                    <span v-if="provider.free_consultation" class="px-3 py-1 bg-green-50 text-green-700 text-sm font-medium rounded-full">
                                        ✓ Free Consultation
                                    </span>
                                    <span v-if="provider.serves_in_person" class="px-3 py-1 bg-purple-50 text-purple-700 text-sm font-medium rounded-full">
                                        📍 In-Person
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- About Section -->
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h2 class="text-lg font-display font-bold text-slate-900 mb-4">About</h2>
                            <div class="prose prose-slate max-w-none">
                                <p class="text-slate-600 whitespace-pre-line leading-relaxed">
                                    {{ provider.bio || 'No bio provided yet.' }}
                                </p>
                            </div>
                            
                            <!-- Specializations -->
                            <div v-if="provider.specializations?.length" class="mt-6 pt-6 border-t border-slate-100">
                                <h3 class="text-sm font-semibold text-slate-900 mb-3">Specializations</h3>
                                <div class="flex flex-wrap gap-2">
                                    <span 
                                        v-for="spec in provider.specializations" 
                                        :key="spec"
                                        class="px-3 py-1 bg-slate-100 text-slate-700 text-sm rounded-full"
                                    >
                                        {{ spec }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing Section -->
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Pricing</h2>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div v-if="provider.hourly_rate" class="p-4 bg-slate-50 rounded-xl">
                                    <div class="text-sm text-slate-500 mb-1">Hourly Rate</div>
                                    <div class="text-2xl font-bold text-slate-900">${{ Number(provider.hourly_rate).toFixed(0) }}<span class="text-base font-normal text-slate-500">/hr</span></div>
                                </div>
                                <div v-if="provider.consultation_fee || provider.free_consultation" class="p-4 bg-slate-50 rounded-xl">
                                    <div class="text-sm text-slate-500 mb-1">Consultation Fee</div>
                                    <div class="text-2xl font-bold text-slate-900">
                                        <template v-if="provider.free_consultation">
                                            <span class="text-green-600">Free</span>
                                        </template>
                                        <template v-else>
                                            ${{ Number(provider.consultation_fee).toFixed(0) }}
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <p v-if="provider.pricing_notes" class="mt-4 text-sm text-slate-600">
                                {{ provider.pricing_notes }}
                            </p>
                        </div>

                        <!-- Reviews Section -->
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-lg font-display font-bold text-slate-900">Reviews</h2>
                            </div>

                            <!-- Rating Summary -->
                            <div v-if="totalReviews > 0" class="grid sm:grid-cols-2 gap-6 mb-8 pb-8 border-b border-slate-100">
                                <div class="text-center sm:text-left">
                                    <div class="text-5xl font-display font-bold text-slate-900">
                                        {{ averageRating.toFixed(1) }}
                                    </div>
                                    <div class="flex justify-center sm:justify-start mt-2">
                                        <template v-for="i in 5" :key="i">
                                            <StarSolid v-if="i <= Math.round(averageRating)" class="h-6 w-6 text-secondary-500" />
                                            <StarOutline v-else class="h-6 w-6 text-slate-300" />
                                        </template>
                                    </div>
                                    <div class="mt-1 text-slate-500">{{ totalReviews }} {{ totalReviews === 1 ? 'review' : 'reviews' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <div v-for="item in ratingDistribution" :key="item.rating" class="flex items-center gap-2">
                                        <span class="text-sm text-slate-600 w-3">{{ item.rating }}</span>
                                        <StarSolid class="h-4 w-4 text-secondary-500" />
                                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div 
                                                class="h-full bg-secondary-500 rounded-full transition-all" 
                                                :style="{ width: `${item.percentage}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-sm text-slate-500 w-8">{{ item.count }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Reviews List -->
                            <div v-if="reviews.length" class="space-y-6">
                                <div 
                                    v-for="review in reviews" 
                                    :key="review.id"
                                    class="pb-6 border-b border-slate-100 last:border-0 last:pb-0"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <img 
                                                :src="review.user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(review.user?.first_name || 'U')}&background=e2e8f0&color=64748b&size=40`" 
                                                class="h-10 w-10 rounded-full bg-slate-100"
                                            />
                                            <div>
                                                <div class="font-medium text-slate-900">
                                                    {{ review.user?.first_name }} {{ review.user?.last_name?.charAt(0) }}.
                                                </div>
                                                <div class="text-sm text-slate-500">{{ formatDate(review.created_at) }}</div>
                                            </div>
                                        </div>
                                        <div class="flex">
                                            <template v-for="i in 5" :key="i">
                                                <StarSolid v-if="i <= (review.rating || 0)" class="h-4 w-4 text-secondary-500" />
                                                <StarOutline v-else class="h-4 w-4 text-slate-300" />
                                            </template>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-slate-600">{{ review.comment }}</p>
                                    
                                    <!-- Provider Response -->
                                    <div v-if="review.provider_response" class="mt-4 ml-4 p-4 bg-slate-50 rounded-xl border-l-4 border-primary-500">
                                        <div class="text-sm font-medium text-slate-900 mb-1">Response from {{ provider.business_name }}</div>
                                        <p class="text-sm text-slate-600">{{ review.provider_response }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12">
                                <StarOutline class="h-12 w-12 text-slate-300 mx-auto mb-3" />
                                <p class="text-slate-500">No reviews yet</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Contact Card -->
                        <div class="bg-white rounded-2xl shadow-soft p-6 sticky top-24">
                            <div class="text-center mb-6">
                                <div class="text-sm text-slate-500 mb-1">Starting from</div>
                                <div class="text-3xl font-display font-bold text-slate-900">
                                    {{ pricingDisplay }}
                                </div>
                                <div v-if="provider.free_consultation" class="mt-2 inline-flex items-center gap-1 text-sm text-green-600 font-medium">
                                    <CheckBadgeIcon class="h-4 w-4" />
                                    Free consultation available
                                </div>
                            </div>

                            <button 
                                v-if="canContactProvider"
                                @click="showInquiryModal = true"
                                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-xl transition-colors mb-3"
                            >
                                <ChatBubbleLeftRightIcon class="h-5 w-5" />
                                Send Inquiry
                            </button>
                            <Link 
                                v-else-if="!user"
                                :href="route('login')"
                                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-xl transition-colors mb-3"
                            >
                                Sign in to Contact
                            </Link>

                            <a 
                                v-if="provider.website"
                                :href="provider.website"
                                target="_blank"
                                class="w-full flex items-center justify-center gap-2 px-6 py-3 border border-slate-200 text-slate-700 font-medium rounded-xl hover:bg-slate-50 transition-colors"
                            >
                                <GlobeAltIcon class="h-5 w-5" />
                                Visit Website
                            </a>

                            <!-- Quick Info -->
                            <div class="mt-6 pt-6 border-t border-slate-100 space-y-4">
                                <div v-if="provider.service_radius_miles" class="flex items-center gap-3 text-sm">
                                    <MapPinIcon class="h-5 w-5 text-slate-400" />
                                    <div>
                                        <div class="text-slate-500">Service area</div>
                                        <div class="font-medium text-slate-900">{{ provider.service_radius_miles }} mile radius</div>
                                    </div>
                                </div>
                                <div v-if="provider.languages_offered?.length" class="flex items-start gap-3 text-sm">
                                    <LanguageIcon class="h-5 w-5 text-slate-400 mt-0.5" />
                                    <div>
                                        <div class="text-slate-500">Languages</div>
                                        <div class="font-medium text-slate-900">
                                            {{ provider.languages_offered.map(getLanguageLabel).join(', ') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Trust Badges -->
                            <div class="mt-6 pt-6 border-t border-slate-100">
                                <div class="flex items-center justify-center gap-4 text-xs text-slate-500">
                                    <div v-if="provider.verification_status === 'approved'" class="flex items-center gap-1">
                                        <ShieldCheckIcon class="h-4 w-4 text-green-500" />
                                        <span>Verified</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <ChatBubbleLeftRightIcon class="h-4 w-4 text-primary-500" />
                                        <span>Secure Messaging</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Similar Providers -->
                <div v-if="similarProviders.length" class="mt-12">
                    <h2 class="text-xl font-display font-bold text-slate-900 mb-6">Similar Providers</h2>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <Link 
                            v-for="similar in similarProviders" 
                            :key="similar.id"
                            :href="route('marketplace.show', similar.slug)"
                            class="bg-white rounded-2xl shadow-soft p-5 hover:shadow-lg transition-all group"
                        >
                            <div class="flex items-center gap-4">
                                <img 
                                    :src="similar.user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(similar.business_name || 'P')}&background=3B95F3&color=fff&size=48`" 
                                    class="h-12 w-12 rounded-xl bg-slate-100"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-slate-900 truncate group-hover:text-primary-600 transition-colors">
                                        {{ similar.business_name }}
                                    </div>
                                    <div class="flex items-center gap-1 text-sm">
                                        <StarSolid class="h-4 w-4 text-secondary-500" />
                                        <span class="font-medium">{{ Number(similar.average_rating || 0).toFixed(1) }}</span>
                                        <span class="text-slate-400">({{ similar.total_reviews || 0 }})</span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inquiry Modal -->
        <Teleport to="body">
            <div v-if="showInquiryModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end sm:items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showInquiryModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
                        <button @click="showInquiryModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                            <XMarkIcon class="h-6 w-6" />
                        </button>
                        
                        <h3 class="text-xl font-display font-bold text-slate-900 mb-2">
                            Send Inquiry
                        </h3>
                        <p class="text-slate-600 mb-6">
                            Get in touch with {{ provider.business_name }}
                        </p>
                        
                        <form @submit.prevent="submitInquiry" class="space-y-4">
                            <div v-if="Object.keys(inquiryForm.errors).length" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                                <p v-for="(err, key) in inquiryForm.errors" :key="key">{{ Array.isArray(err) ? err[0] : err }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Service Needed</label>
                                <select v-model="inquiryForm.service_type" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <template v-if="provider.service_types?.length">
                                        <option v-for="(label, idx) in serviceTypeLabels" :key="idx" :value="provider.service_types[idx]">
                                            {{ label }}
                                        </option>
                                    </template>
                                    <option v-else value="other">General / other</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Your Message</label>
                                <textarea 
                                    v-model="inquiryForm.message"
                                    rows="4"
                                    minlength="20"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Describe what you're looking for, your situation, timeline, etc..."
                                    required
                                ></textarea>
                                <p class="mt-1 text-xs text-slate-500">At least 20 characters (required to send).</p>
                                <p v-if="inquiryForm.errors.message" class="mt-1 text-sm text-red-600">{{ inquiryForm.errors.message }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">How urgent is this?</label>
                                <select v-model="inquiryForm.urgency" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="low">Not urgent - flexible timeline</option>
                                    <option value="normal">Normal - within a few weeks</option>
                                    <option value="high">Urgent - need help soon</option>
                                    <option value="urgent">Urgent - immediate assistance needed</option>
                                </select>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button 
                                    type="button" 
                                    @click="showInquiryModal = false" 
                                    class="flex-1 px-6 py-3 border border-slate-200 text-slate-700 font-medium rounded-xl hover:bg-slate-50 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit" 
                                    class="flex-1 px-6 py-3 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-xl transition-colors disabled:opacity-50"
                                    :disabled="inquiryForm.processing"
                                >
                                    {{ inquiryForm.processing ? 'Sending...' : 'Send Inquiry' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
