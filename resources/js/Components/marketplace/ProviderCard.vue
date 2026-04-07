<script setup>
import { Link } from '@inertiajs/vue3';
import { StarIcon, MapPinIcon, CheckBadgeIcon, LanguageIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    provider: {
        type: Object,
        required: true,
    },
});

const ratingStars = (rating) => {
    return Math.round(rating);
};
</script>

<template>
    <Link 
        :href="route('marketplace.show', provider.slug)"
        class="provider-card group"
    >
        <!-- Header with avatar and verification badge -->
        <div class="flex items-start gap-4">
            <div class="relative flex-shrink-0">
                <div v-if="provider.user?.avatar" class="w-16 h-16 rounded-2xl overflow-hidden">
                    <img 
                        :src="provider.user.avatar" 
                        :alt="provider.display_name"
                        class="w-full h-full object-cover"
                    />
                </div>
                <div v-else class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center">
                    <span class="text-white font-display font-bold text-xl">
                        {{ provider.user?.initials || 'SP' }}
                    </span>
                </div>
                
                <!-- Verified badge -->
                <div 
                    v-if="provider.verification_status === 'approved'"
                    class="absolute -bottom-1 -right-1 w-6 h-6 bg-white rounded-full flex items-center justify-center shadow-sm"
                >
                    <CheckBadgeIcon class="w-5 h-5 text-emerald-500" />
                </div>
            </div>
            
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-neutral-900 truncate group-hover:text-primary-600 transition-colors">
                    {{ provider.display_name }}
                </h3>
                <p class="text-sm text-primary-600 font-medium truncate">
                    {{ provider.primary_service_type }}
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
                        {{ provider.average_rating > 0 ? provider.average_rating.toFixed(1) : 'New' }}
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
        
        <!-- Tags/Service types -->
        <div class="flex flex-wrap gap-1.5">
            <span 
                v-for="type in provider.service_types_labels?.slice(0, 3)" 
                :key="type"
                class="badge-neutral text-2xs"
            >
                {{ type }}
            </span>
            <span 
                v-if="provider.service_types_labels?.length > 3"
                class="badge-neutral text-2xs"
            >
                +{{ provider.service_types_labels.length - 3 }} more
            </span>
        </div>
        
        <!-- Footer info -->
        <div class="flex items-center justify-between pt-3 mt-auto border-t border-neutral-100">
            <div class="flex items-center gap-4 text-xs text-neutral-500">
                <span class="flex items-center gap-1">
                    <MapPinIcon class="w-3.5 h-3.5" />
                    {{ provider.location_display }}
                </span>
                <span v-if="provider.serves_remote" class="flex items-center gap-1 text-accent-600">
                    <span class="w-1.5 h-1.5 bg-accent-500 rounded-full"></span>
                    Remote OK
                </span>
            </div>
            
            <div v-if="provider.free_consultation" class="badge-success text-2xs">
                Free Consult
            </div>
        </div>
        
        <!-- Featured indicator -->
        <div 
            v-if="provider.is_featured"
            class="absolute top-3 right-3"
        >
            <span class="badge bg-secondary-100 text-secondary-700 text-2xs">
                ⭐ Featured
            </span>
        </div>
    </Link>
</template>

<style scoped>
.text-2xs {
    font-size: 0.625rem;
    line-height: 0.875rem;
}
</style>
