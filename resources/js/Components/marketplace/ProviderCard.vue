<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { StarIcon, MapPinIcon, CheckBadgeIcon } from '@heroicons/vue/20/solid';
import { HeartIcon as HeartSolid } from '@heroicons/vue/24/solid';
import ProfileSharePanel from '@/Components/marketplace/ProfileSharePanel.vue';

const props = defineProps({
    provider: {
        type: Object,
        required: true,
    },
});

/** Laravel / JSON often sends average_rating as a string; normalize before math. */
const numericRating = (rating) => {
    const n = Number(rating);
    return Number.isFinite(n) ? n : 0;
};

const ratingStars = (rating) => {
    return Math.round(numericRating(rating));
};

const averageRatingText = (rating) => {
    const n = numericRating(rating);
    return n > 0 ? n.toFixed(1) : 'New';
};

const personName = computed(() => {
    const u = props.provider?.user || {};
    const first = (u.first_name || '').trim();
    const last = (u.last_name || '').trim();
    return [first, last].filter(Boolean).join(' ');
});

const displayTitle = computed(() => {
    // Provider personal name should appear first (main title).
    return personName.value || props.provider?.business_name || 'Provider';
});

const businessSubtitle = computed(() => {
    const business = (props.provider?.business_name || '').trim();
    if (!business) return '';
    // If personal name is missing, business is already the title.
    if (!personName.value) return '';
    return business;
});

const subtitleService = computed(() => {
    return props.provider?.primary_service_type || props.provider?.service_types_labels?.[0] || '';
});

const locationDisplay = computed(() => {
    if (props.provider?.location_display) return props.provider.location_display;
    const u = props.provider?.user || {};
    const parts = [u.city, u.state].filter(Boolean);
    return parts.join(', ') || 'Location not specified';
});

const serviceTypeLabels = computed(() => {
    const labels = props.provider?.service_types_labels;
    if (Array.isArray(labels) && labels.length) return labels;
    return [];
});

const pricingDisplay = computed(() => {
    const p = props.provider || {};
    if (p.free_consultation) return 'Free consultation';
    if (p.hourly_rate) return `$${Number(p.hourly_rate).toFixed(0)}/hr`;
    if (p.consultation_fee) return `$${Number(p.consultation_fee).toFixed(0)} consult`;
    return '';
});

const coveragePills = computed(() => {
    const p = props.provider || {};
    const pills = [];
    if (p.serves_remote) pills.push('Remote');
    if (p.serves_in_person) pills.push('In-person');
    if (p.service_radius_miles) pills.push(`${p.service_radius_miles} mi radius`);
    if (Array.isArray(p.service_areas) && p.service_areas.length) pills.push(`${p.service_areas.length} areas`);
    return pills;
});

const resolveAvatar = (person) => {
    const candidate = (person?.avatar_url || person?.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
};

const sharePayload = computed(() => {
    const p = props.provider;
    const title = displayTitle.value;
    let description = (p.tagline || '').trim();
    if (!description && p.bio) {
        description = String(p.bio).replace(/\s+/g, ' ').trim().slice(0, 200);
    }
    if (!description) {
        description = `View ${title} on the marketplace.`;
    }
    const path = route('marketplace.show', p.slug);
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const url = path.startsWith('http') ? path : `${origin}${path.startsWith('/') ? path : `/${path}`}`;

    let image = resolveAvatar(p.user);
    if (image) {
        image = image.startsWith('http') ? image : `${origin}${image.startsWith('/') ? image : `/${image}`}`;
    } else {
        image = `https://ui-avatars.com/api/?name=${encodeURIComponent(title)}&background=3B95F3&color=fff&size=512`;
    }

    return { url, title, description, image };
});
</script>

<template>
    <article class="provider-card group relative">
        <Link
            :href="route('marketplace.show', provider.slug)"
            class="absolute inset-0 z-0 rounded-2xl"
            :aria-label="`View profile: ${displayTitle}`"
        />
        <div class="relative z-10 flex flex-col gap-3 pointer-events-none">
        <!-- Header with avatar and verification badge -->
        <div class="flex items-start gap-3">
            <div class="relative flex-shrink-0">
                <div v-if="resolveAvatar(provider.user)" class="w-14 h-14 rounded-2xl overflow-hidden">
                    <img 
                        :src="resolveAvatar(provider.user)"
                        :alt="displayTitle"
                        class="w-full h-full object-cover"
                    />
                </div>
                <div v-else class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center">
                    <span class="text-white font-display font-bold text-xl">
                        {{ provider.user?.initials || 'SP' }}
                    </span>
                </div>
                
                <!-- Verified badge -->
                <div 
                    v-if="provider.background_check_status === 'clear'"
                    class="absolute -bottom-1 -right-1 w-6 h-6 bg-white rounded-full flex items-center justify-center shadow-sm"
                >
                    <CheckBadgeIcon class="w-5 h-5 text-emerald-500" />
                </div>
            </div>
            
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-neutral-900 truncate group-hover:text-primary-600 transition-colors">
                    {{ displayTitle }}
                </h3>
                <p v-if="businessSubtitle" class="text-xs text-neutral-500 truncate">
                    {{ businessSubtitle }}
                </p>
                <p v-if="subtitleService" class="text-sm text-primary-600 font-medium truncate">
                    {{ subtitleService }}
                </p>
                
                <!-- Rating -->
                <div class="flex items-center gap-1.5 mt-1">
                    <div class="flex items-center">
                        <StarIcon 
                            v-for="i in 5" 
                            :key="i"
                            :class="[
                                'w-4 h-4',
                                i <= ratingStars(provider.average_rating) ? 'text-secondary-400' : 'text-neutral-200'
                            ]"
                        />
                    </div>
                    <span class="text-sm text-neutral-600">
                        {{ averageRatingText(provider.average_rating) }}
                        <span v-if="provider.total_reviews > 0" class="text-neutral-400">
                            ({{ provider.total_reviews }})
                        </span>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Bio/Tagline -->
        <p v-if="provider.tagline || provider.bio" class="text-sm text-neutral-600 line-clamp-2">
            {{ provider.tagline || provider.bio }}
        </p>

        <!-- Pricing / Coverage (compact but complete) -->
        <div v-if="pricingDisplay || coveragePills.length" class="flex flex-wrap items-center gap-1.5">
            <span v-if="pricingDisplay" class="badge-primary text-2xs">
                {{ pricingDisplay }}
            </span>
            <span
                v-for="pill in coveragePills.slice(0, 3)"
                :key="pill"
                class="badge-neutral text-2xs"
            >
                {{ pill }}
            </span>
            <span v-if="coveragePills.length > 3" class="badge-neutral text-2xs">
                +{{ coveragePills.length - 3 }} more
            </span>
        </div>
        
        <!-- Tags/Service types -->
        <div class="flex flex-wrap gap-1.5">
            <span 
                v-for="type in serviceTypeLabels.slice(0, 3)" 
                :key="type"
                class="badge-neutral text-2xs"
            >
                {{ type }}
            </span>
            <span 
                v-if="serviceTypeLabels.length > 3"
                class="badge-neutral text-2xs"
            >
                +{{ serviceTypeLabels.length - 3 }} more
            </span>
        </div>
        
        <!-- Footer info -->
        <div class="flex items-center justify-between gap-2 pt-2.5 mt-auto border-t border-neutral-100">
            <div class="flex min-w-0 flex-1 items-center gap-4 text-xs text-neutral-500">
                <span class="flex min-w-0 items-center gap-1">
                    <MapPinIcon class="w-3.5 h-3.5 flex-shrink-0" />
                    <span class="truncate">{{ locationDisplay }}</span>
                </span>
                <span v-if="provider.serves_remote" class="flex flex-shrink-0 items-center gap-1 text-accent-600">
                    <span class="w-1.5 h-1.5 bg-accent-500 rounded-full"></span>
                    Remote OK
                </span>
            </div>

            <div class="flex flex-shrink-0 items-center gap-1.5 pointer-events-auto">
                <div v-if="provider.free_consultation" class="badge-success text-2xs">
                    Free Consult
                </div>
                <ProfileSharePanel :share="sharePayload" menu-align="right" />
            </div>
        </div>
        
        <!-- Featured indicator -->
        <div 
            v-if="provider.is_featured || provider.is_favorited"
            class="absolute top-3 right-3 flex items-center gap-1.5"
        >
            <span
                v-if="provider.is_favorited"
                class="inline-flex items-center rounded-full border border-red-100 bg-red-50 px-2 py-1 text-2xs font-semibold text-red-700"
                title="Saved to favorites"
            >
                <HeartSolid class="h-3.5 w-3.5" />
            </span>
            <span v-if="provider.is_featured" class="badge bg-secondary-100 text-secondary-700 text-2xs">
                ⭐ Featured
            </span>
        </div>
        </div>
    </article>
</template>

<style scoped>
.text-2xs {
    font-size: 0.625rem;
    line-height: 0.875rem;
}
</style>
