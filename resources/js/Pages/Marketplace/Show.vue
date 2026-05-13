<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProfileShareModal from '@/Components/marketplace/ProfileShareModal.vue';
import ProviderProfileFeed from '@/Components/marketplace/ProviderProfileFeed.vue';
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
    HeartIcon,
    ShareIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid, CheckBadgeIcon as CheckBadgeSolid, HeartIcon as HeartSolid } from '@heroicons/vue/24/solid';
import { StarIcon as StarOutline } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    provider: { type: Object, required: true },
    similarProviders: { type: Array, default: () => [] },
    canContactProvider: { type: Boolean, default: false },
    serviceTypeLabels: { type: Array, default: () => [] },
    /** True when the logged-in provider is viewing their own public listing (e.g. Preview from edit). */
    isOwnListingPreview: { type: Boolean, default: false },
    canFavorite: { type: Boolean, default: false },
    isFavorited: { type: Boolean, default: false },
    /** Public profile URL, title, description, and image for sharing / Open Graph */
    providerShare: {
        type: Object,
        required: true,
        validator: (v) => v && typeof v.url === 'string' && typeof v.title === 'string',
    },
    profileFeed: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const providerPersonName = computed(() => {
    const u = props.provider?.user || {};
    const first = (u.first_name || '').trim();
    const last = (u.last_name || '').trim();
    return [first, last].filter(Boolean).join(' ');
});

/** Account-registered full name (User accessor), same as first + last when present. */
const providerRegisteredFullName = computed(() => {
    const fromUser = (props.provider?.user?.full_name || '').trim();
    if (fromUser) return fromUser;
    return providerPersonName.value;
});

const providerBusinessName = computed(() => {
    const v = (props.provider?.business_name || '').trim();
    return v || '';
});

const providerPrimaryName = computed(() => {
    return providerPersonName.value || props.provider?.business_name || 'Provider';
});

/** Main headline: registered full name first, else business / fallback. */
const providerHeadlineName = computed(() => {
    if (providerRegisteredFullName.value) return providerRegisteredFullName.value;
    return providerBusinessName.value || 'Provider';
});

/** Subtitle under headline: business only when headline is the person (not duplicate). */
const providerHeadlineSubtitle = computed(() => {
    if (!providerBusinessName.value) return '';
    if (!providerRegisteredFullName.value) return '';
    // Same string (e.g. sole prop using legal name as business): no subtitle
    if (providerBusinessName.value === providerRegisteredFullName.value) return '';
    return providerBusinessName.value;
});

const providerDisplayTitle = computed(() => {
    // Used for <Head> / avatar fallback; keep it stable even if business missing.
    return providerBusinessName.value || providerPrimaryName.value || 'Provider Profile';
});

const avatarFallbackName = computed(() => {
    // Avatar should use the service provider (personal) letters when possible.
    return providerPersonName.value || 'Service Provider';
});

const showInquiryModal = ref(false);
const inquiryIntent = ref('inquiry');
const shareModalOpen = ref(false);

const favoriteForm = useForm({});

const toggleFavorite = () => {
    if (props.isOwnListingPreview) {
        return;
    }
    if (!user.value) {
        router.visit(route('login'));
        return;
    }
    if (!props.canFavorite) {
        return;
    }
    favoriteForm.post(route('user.provider-favorites.toggle', props.provider.slug), {
        preserveScroll: true,
    });
};

const inquiryForm = useForm({
    service_type: props.provider.service_types?.[0] || 'other',
    message: '',
    /** Required by LeadController — in-app messaging */
    preferred_contact_method: 'message',
    requirements: [],
    urgency: 'normal',
    offered_rate: props.provider.hourly_rate ? Number(props.provider.hourly_rate) : null,
    intent: 'inquiry',
});

const submitInquiry = () => {
    inquiryForm.intent = inquiryIntent.value;
    inquiryForm.post(route('leads.store', props.provider.slug), {
        preserveScroll: true,
        onSuccess: () => {
            showInquiryModal.value = false;
            inquiryIntent.value = 'inquiry';
            inquiryForm.reset();
            inquiryForm.offered_rate = props.provider.hourly_rate ? Number(props.provider.hourly_rate) : null;
            inquiryForm.intent = 'inquiry';
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

const offerRateUnitLabel = computed(() => {
    const pricingModel = String(props.provider?.pricing_model || '').toLowerCase();
    if (pricingModel === 'flat_rate') return 'fixed';
    return 'per hour';
});

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

const resolveAvatar = (person, fallback) => {
    const candidate = (person?.avatar_url || person?.avatar || '').trim();
    if (!candidate) return fallback;
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
};

const resolveReviewAvatarSrc = (person) => {
    const candidate = String(person?.avatar_url || person?.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) return candidate;
    return `/storage/${candidate}`;
};

const reviewUserDisplayName = (person) => {
    const first = String(person?.first_name || '').trim();
    const last = String(person?.last_name || '').trim();
    const full = [first, last].filter(Boolean).join(' ').trim();
    return full || String(person?.full_name || '').trim() || 'Anonymous';
};

const reviewUserInitials = (person) => {
    const name = reviewUserDisplayName(person);
    if (!name || name === 'Anonymous') return 'A';
    const parts = name.split(/\s+/).filter(Boolean);
    const firstLetter = parts[0]?.[0] || '';
    const lastLetter = (parts.length > 1 ? parts[parts.length - 1]?.[0] : parts[0]?.[1]) || '';
    return `${firstLetter}${lastLetter}`.toUpperCase();
};

const socialLinks = computed(() => {
    const p = props.provider || {};
    const links = [
        { key: 'linkedin_url', label: 'LinkedIn', url: p.linkedin_url },
        { key: 'facebook_url', label: 'Facebook', url: p.facebook_url },
        { key: 'twitter_url', label: 'X', url: p.twitter_url },
        { key: 'instagram_url', label: 'Instagram', url: p.instagram_url },
        { key: 'youtube_url', label: 'YouTube', url: p.youtube_url },
        { key: 'tiktok_url', label: 'TikTok', url: p.tiktok_url },
    ];

    return links
        .map((l) => ({ ...l, url: (l.url || '').trim() }))
        .filter((l) => l.url);
});
</script>

<template>
    <Head :title="providerDisplayTitle">
        <meta head-key="description" name="description" :content="providerShare.description" />
        <link head-key="canonical" rel="canonical" :href="providerShare.url" />
        <meta head-key="og:title" property="og:title" :content="providerShare.title" />
        <meta head-key="og:description" property="og:description" :content="providerShare.description" />
        <meta head-key="og:url" property="og:url" :content="providerShare.url" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="providerShare.image" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="providerShare.title" />
        <meta head-key="twitter:description" name="twitter:description" :content="providerShare.description" />
        <meta head-key="twitter:image" name="twitter:image" :content="providerShare.image" />
    </Head>

    <AppLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50">
            <div class="mx-auto max-w-[1440px] px-4 py-4 sm:px-6 sm:py-5 lg:px-8 lg:py-7">
                <div
                    v-if="isOwnListingPreview"
                    class="mb-4 rounded-2xl border border-primary-200/80 bg-gradient-to-r from-primary-50 to-sky-50 px-4 py-3.5 text-sm text-primary-900 shadow-sm"
                >
                    You are previewing how your public listing looks. Visitors only see this page when your profile is active on the marketplace.
                </div>
                <!-- Back: return to edit profile when previewing own listing -->
                <Link
                    v-if="isOwnListingPreview"
                    :href="route('provider.profile.edit')"
                    class="group mb-6 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:text-slate-900"
                >
                    <ArrowLeftIcon class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                    Back to Edit Profile
                </Link>
                <Link
                    v-else
                    :href="route('marketplace.index')"
                    class="group mb-6 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:text-slate-900"
                >
                    <ArrowLeftIcon class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                    Back to Providers
                </Link>

                <div class="grid gap-4 lg:grid-cols-3 xl:gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-4">
                        <!-- Provider Header Card -->
                        <div class="relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm ring-1 ring-white/70">
                            <div class="px-5 py-5 sm:px-6">
                                <!-- Avatar & Basic Info -->
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <div class="relative">
                                        <img
                                            :src="resolveAvatar(provider.user, `https://ui-avatars.com/api/?name=${encodeURIComponent(avatarFallbackName)}&background=3B95F3&color=fff&size=96`)"
                                            :alt="providerDisplayTitle"
                                            class="h-20 w-20 sm:h-24 sm:w-24 rounded-2xl border-4 border-white bg-white object-cover shadow-xl"
                                        />
                                        <div v-if="provider.background_check_status === 'clear'" class="absolute -bottom-1 -right-1 bg-white rounded-full p-0.5">
                                            <CheckBadgeSolid class="h-6 w-6 text-primary-600" />
                                        </div>
                                    </div>
                                    <div class="flex-1 sm:pb-2">
                                        <div class="flex flex-wrap items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <h1 class="text-3xl font-display font-bold leading-tight text-slate-900 sm:text-4xl">
                                                    {{ providerHeadlineName }}
                                                </h1>
                                                <p
                                                    v-if="providerHeadlineSubtitle"
                                                    class="mt-1.5 text-lg font-medium text-slate-600 sm:text-xl"
                                                >
                                                    {{ providerHeadlineSubtitle }}
                                                </p>
                                            </div>
                                            <span
                                                v-if="provider.is_featured"
                                                class="mt-1 inline-flex flex-shrink-0 rounded-full bg-secondary-100 px-2 py-0.5 text-xs font-semibold text-secondary-700"
                                            >
                                                Featured
                                            </span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                                            <span v-if="provider.business_email" class="truncate">
                                                <span class="text-slate-400">Email:</span>
                                                <span class="ml-1 font-medium text-slate-700">{{ provider.business_email }}</span>
                                            </span>
                                            <span v-if="provider.business_phone" class="truncate">
                                                <span class="text-slate-400">Phone:</span>
                                                <span class="ml-1 font-medium text-slate-700">{{ provider.business_phone }}</span>
                                            </span>
                                        </div>
                                        <p v-if="provider.tagline" class="text-slate-600 mt-2">{{ provider.tagline }}</p>
                                        <div v-if="serviceTypeLabels.length" class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                v-for="label in serviceTypeLabels"
                                                :key="label"
                                                class="rounded-lg bg-primary-50 px-2.5 py-1 text-sm font-medium text-primary-700"
                                            >
                                                {{ label }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 sm:pb-2">
                                        <button
                                            type="button"
                                            class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                            aria-label="Share provider profile"
                                            @click="shareModalOpen = true"
                                        >
                                            <ShareIcon class="h-5 w-5" />
                                        </button>
                                        <button
                                            v-if="canFavorite"
                                            type="button"
                                            class="rounded-lg p-2 transition-colors hover:bg-slate-100"
                                            :class="isFavorited ? 'text-red-500 hover:text-red-600' : 'text-slate-400 hover:text-red-500'"
                                            :disabled="favoriteForm.processing"
                                            :aria-label="isFavorited ? 'Remove from favorites' : 'Add to favorites'"
                                            @click="toggleFavorite"
                                        >
                                            <HeartSolid v-if="isFavorited" class="h-5 w-5" />
                                            <HeartIcon v-else class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Stats Row -->
                                <div class="mt-5 flex flex-wrap gap-2.5 border-t border-slate-100 pt-4">
                                    <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div class="flex">
                                            <template v-for="i in 5" :key="i">
                                                <StarSolid v-if="i <= Math.round(averageRating)" class="h-5 w-5 text-secondary-500" />
                                                <StarOutline v-else class="h-5 w-5 text-slate-300" />
                                            </template>
                                        </div>
                                        <span class="font-semibold text-slate-900">{{ averageRating.toFixed(1) }}</span>
                                        <span class="text-slate-500">({{ totalReviews }} {{ totalReviews === 1 ? 'review' : 'reviews' }})</span>
                                    </div>
                                    <div v-if="provider.years_experience" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-600">
                                        <BriefcaseIcon class="h-5 w-5" />
                                        <span>{{ provider.years_experience }} years experience</span>
                                    </div>
                                    <div v-if="provider.user?.city || provider.user?.state" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-600">
                                        <MapPinIcon class="h-5 w-5" />
                                        <span>{{ [provider.user?.city, provider.user?.state].filter(Boolean).join(', ') }}</span>
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-if="provider.serves_remote" class="inline-flex items-center gap-1 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
                                        <GlobeAltIcon class="h-4 w-4" />
                                        Remote Available
                                    </span>
                                    <span v-if="provider.free_consultation" class="inline-flex items-center gap-1 rounded-full border border-green-100 bg-green-50 px-3 py-1 text-sm font-medium text-green-700">
                                        <CheckBadgeIcon class="h-4 w-4" />
                                        Free Consultation
                                    </span>
                                    <span v-if="provider.serves_in_person" class="inline-flex items-center gap-1 rounded-full border border-purple-100 bg-purple-50 px-3 py-1 text-sm font-medium text-purple-700">
                                        <MapPinIcon class="h-4 w-4" />
                                        In-Person
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- About Section -->
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm">
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

                        <ProviderProfileFeed
                            :posts="profileFeed"
                            :business-name="provider.business_name || ''"
                            :avatar-url="resolveAvatar(provider.user, `https://ui-avatars.com/api/?name=${encodeURIComponent(avatarFallbackName)}&background=3B95F3&color=fff&size=80`)"
                        />

                        <!-- Pricing Section -->
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm">
                            <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Pricing</h2>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div v-if="provider.hourly_rate" class="rounded-xl bg-slate-50 p-4">
                                    <div class="text-sm text-slate-500 mb-1">Hourly Rate</div>
                                    <div class="text-2xl font-bold text-slate-900">${{ Number(provider.hourly_rate).toFixed(0) }}<span class="text-base font-normal text-slate-500">/hr</span></div>
                                </div>
                                <div v-if="provider.consultation_fee || provider.free_consultation" class="rounded-xl bg-slate-50 p-4">
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

                        <!-- Coverage & Service Area -->
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm">
                            <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Coverage</h2>

                            <div class="flex flex-wrap gap-2">
                                <span v-if="provider.serves_remote" class="inline-flex items-center gap-1 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
                                    <GlobeAltIcon class="h-4 w-4" />
                                    Remote
                                </span>
                                <span v-if="provider.serves_in_person" class="inline-flex items-center gap-1 rounded-full border border-purple-100 bg-purple-50 px-3 py-1 text-sm font-medium text-purple-700">
                                    <MapPinIcon class="h-4 w-4" />
                                    In-person
                                </span>
                                <span v-if="provider.service_radius_miles" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-700">
                                    <MapPinIcon class="h-4 w-4 text-slate-500" />
                                    {{ provider.service_radius_miles }} mile radius
                                </span>
                            </div>

                            <div v-if="provider.service_areas?.length" class="mt-5 border-t border-slate-100 pt-5">
                                <h3 class="text-sm font-semibold text-slate-900 mb-3">Service areas</h3>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="(area, idx) in provider.service_areas"
                                        :key="`${area}-${idx}`"
                                        class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700"
                                    >
                                        {{ area }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Credentials -->
                        <div
                            v-if="provider.license_number || provider.license_state || provider.license_expiry || provider.certifications?.length || provider.health_certificates?.length"
                            class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm"
                        >
                            <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Credentials</h2>

                            <div v-if="provider.license_number || provider.license_state || provider.license_expiry" class="rounded-xl bg-slate-50 p-4">
                                <div class="text-sm font-semibold text-slate-900">License</div>
                                <div class="mt-1 text-sm text-slate-600">
                                    <span v-if="provider.license_number" class="font-medium text-slate-900">{{ provider.license_number }}</span>
                                    <span v-if="provider.license_state" class="text-slate-500"> · {{ provider.license_state }}</span>
                                    <span v-if="provider.license_expiry" class="text-slate-500"> · Expires {{ formatDate(provider.license_expiry) }}</span>
                                </div>
                            </div>

                            <div v-if="provider.certifications?.length" class="mt-5">
                                <h3 class="text-sm font-semibold text-slate-900 mb-3">Certifications</h3>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="(cert, idx) in provider.certifications"
                                        :key="`${cert}-${idx}`"
                                        class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700"
                                    >
                                        {{ cert }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="provider.health_certificates?.length" class="mt-5">
                                <h3 class="text-sm font-semibold text-slate-900 mb-3">Health certificates</h3>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="(cert, idx) in provider.health_certificates"
                                        :key="`${cert}-${idx}`"
                                        class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700"
                                    >
                                        {{ cert }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Reviews Section -->
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm">
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
                            <div v-if="reviews.length" class="grid gap-4 sm:grid-cols-2">
                                <div 
                                    v-for="review in reviews" 
                                    :key="review.id"
                                    class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-soft"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <template v-if="resolveReviewAvatarSrc(review.user)">
                                                <img
                                                    :src="resolveReviewAvatarSrc(review.user)"
                                                    :alt="reviewUserDisplayName(review.user)"
                                                    class="h-10 w-10 rounded-full object-cover bg-slate-100"
                                                />
                                            </template>
                                            <template v-else>
                                                <div
                                                    class="h-10 w-10 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-semibold text-sm"
                                                    :aria-label="reviewUserDisplayName(review.user)"
                                                    role="img"
                                                >
                                                    {{ reviewUserInitials(review.user) }}
                                                </div>
                                            </template>
                                            <div>
                                                <div class="font-medium text-slate-900">
                                                    {{ reviewUserDisplayName(review.user) }}
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
                                    <div v-if="review.provider_response" class="mt-4 p-4 bg-slate-50 rounded-xl border-l-4 border-primary-500">
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
                    <div class="space-y-6 lg:col-span-1">
                        <!-- Contact Card -->
                        <div class="sticky top-24 rounded-2xl border border-slate-200/70 bg-white/95 p-5 shadow-sm backdrop-blur-sm">
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
                                class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-primary-500"
                            >
                                <ChatBubbleLeftRightIcon class="h-5 w-5" />
                                Send Inquiry
                            </button>
                            <button
                                v-if="canContactProvider"
                                @click="showInquiryModal = true; inquiryIntent = 'offer'; inquiryForm.offered_rate = provider.hourly_rate ? Number(provider.hourly_rate) : null"
                                class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-6 py-3 font-semibold text-primary-700 transition-colors hover:bg-primary-100"
                            >
                                <CurrencyDollarIcon class="h-5 w-5" />
                                Give Offer
                            </button>
                            <Link 
                                v-else-if="!user"
                                :href="route('login')"
                                class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-primary-500"
                            >
                                Sign in to Contact
                            </Link>

                            <a 
                                v-if="provider.website"
                                :href="provider.website"
                                target="_blank"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-6 py-3 font-medium text-slate-700 transition-colors hover:bg-slate-50"
                            >
                                <GlobeAltIcon class="h-5 w-5" />
                                Visit Website
                            </a>

                            <!-- Quick Info -->
                            <div class="mt-6 pt-6 border-t border-slate-100 space-y-4">
                                <div v-if="provider.business_phone" class="flex items-center gap-3 text-sm">
                                    <PhoneIcon class="h-5 w-5 text-slate-400" />
                                    <div class="min-w-0">
                                        <div class="text-slate-500">Phone</div>
                                        <div class="font-medium text-slate-900 truncate">{{ provider.business_phone }}</div>
                                    </div>
                                </div>
                                <div v-if="provider.business_email" class="flex items-center gap-3 text-sm">
                                    <ChatBubbleLeftRightIcon class="h-5 w-5 text-slate-400" />
                                    <div class="min-w-0">
                                        <div class="text-slate-500">Email</div>
                                        <div class="font-medium text-slate-900 truncate">{{ provider.business_email }}</div>
                                    </div>
                                </div>
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
                                <div v-if="provider.years_experience" class="flex items-center gap-3 text-sm">
                                    <BriefcaseIcon class="h-5 w-5 text-slate-400" />
                                    <div>
                                        <div class="text-slate-500">Experience</div>
                                        <div class="font-medium text-slate-900">{{ provider.years_experience }} years</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="socialLinks.length" class="mt-6 pt-6 border-t border-slate-100">
                                <div class="text-sm font-semibold text-slate-900 mb-3">Social</div>
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        v-for="link in socialLinks"
                                        :key="link.key"
                                        :href="link.url || '#'"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                    >
                                        {{ link.label }}
                                    </a>
                                </div>
                            </div>

                            <!-- Trust Badges -->
                            <div class="mt-6 pt-6 border-t border-slate-100">
                                <div class="flex items-center justify-center gap-4 text-xs text-slate-500">
                                    <div v-if="provider.background_check_status === 'clear'" class="flex items-center gap-1">
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
                <div v-if="similarProviders.length" class="mt-10">
                    <h2 class="text-xl font-display font-bold text-slate-900 mb-6">Similar Providers</h2>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <Link 
                            v-for="similar in similarProviders" 
                            :key="similar.id"
                            :href="route('marketplace.show', similar.slug)"
                            class="group rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div class="flex items-center gap-4">
                                <img 
                                    :src="resolveAvatar(similar.user, `https://ui-avatars.com/api/?name=${encodeURIComponent(similar.business_name || 'P')}&background=3B95F3&color=fff&size=48`)"
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
                            {{ inquiryIntent === 'offer' ? 'Start Offer Conversation' : 'Send Inquiry' }}
                        </h3>
                        <p class="text-slate-600 mb-6">
                            {{ inquiryIntent === 'offer'
                                ? 'Your offer starts with the provider rate below. You can override it before sending.'
                                : `Get in touch with ${provider.business_name}` }}
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
                                    minlength="2"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Describe what you're looking for, your situation, timeline, etc..."
                                    required
                                ></textarea>
                                <p class="mt-1 text-xs text-slate-500">At least 2 characters (required to send).</p>
                                <p v-if="inquiryForm.errors.message" class="mt-1 text-sm text-red-600">{{ inquiryForm.errors.message }}</p>
                            </div>

                            <div v-if="inquiryIntent === 'offer'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">
                                    Offered Rate (USD, {{ offerRateUnitLabel }})
                                </label>
                                <input
                                    v-model="inquiryForm.offered_rate"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="0.00"
                                />
                                <p v-if="provider.hourly_rate" class="mt-1 text-xs text-slate-500">
                                    Default provider rate: ${{ Number(provider.hourly_rate).toFixed(2) }} USD ({{ offerRateUnitLabel }})
                                </p>
                                <p v-if="inquiryForm.errors.offered_rate" class="mt-1 text-sm text-red-600">
                                    {{ inquiryForm.errors.offered_rate }}
                                </p>
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
                                    {{
                                        inquiryForm.processing
                                            ? 'Sending...'
                                            : (inquiryIntent === 'offer' ? 'Send Offer' : 'Send Inquiry')
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <ProfileShareModal v-model="shareModalOpen" :share="providerShare" />
    </AppLayout>
</template>
