<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import { 
    MagnifyingGlassIcon, 
    ShieldCheckIcon, 
    ChatBubbleLeftRightIcon,
    StarIcon,
    ArrowRightIcon,
    CheckCircleIcon,
    UserGroupIcon,
    BookOpenIcon
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

defineProps({
    featuredProviders: { type: Array, default: () => [] },
    serviceTypes: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    testimonials: { type: Array, default: () => [] },
});

const page = usePage();
const companyName = computed(() => page.props.branding?.company_name || 'ImmigrationKnowHow');
const siteTagline = computed(() => page.props.branding?.site_tagline || 'Connect with trusted attorneys, tax experts, translators, and more to navigate your immigration journey with confidence.');
const footerTagline = computed(() => page.props.branding?.footer_tagline || 'Connecting immigrants with trusted service providers since 2024.');
const supportEmail = computed(() => page.props.branding?.support_email || null);
const supportPhone = computed(() => page.props.branding?.support_phone || null);
const supportAddress = computed(() => page.props.branding?.support_address || null);

const features = [
    {
        icon: MagnifyingGlassIcon,
        title: 'Find Trusted Providers',
        description: 'Search and connect with verified immigration attorneys, tax experts, translators, and more in your area.',
    },
    {
        icon: ShieldCheckIcon,
        title: 'Verified Professionals',
        description: 'Every provider undergoes identity verification. View credentials, reviews, and ratings before connecting.',
    },
    {
        icon: ChatBubbleLeftRightIcon,
        title: 'Direct Communication',
        description: 'Message providers directly through our secure platform. Get quotes and answers to your questions.',
    },
    {
        icon: BookOpenIcon,
        title: 'Immigration Library',
        description: 'Access e-books, guides, and resources to help you navigate your immigration journey.',
    },
];
</script>

<template>
    <Head :title="`Find Trusted Immigration Services | ${companyName}`" />

    <div class="min-h-screen bg-white">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <Link href="/" class="flex items-center gap-2">
                            <BrandLogo context="site" subtitle="" mark-class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-primary-50 text-primary-600" name-class="text-xl font-display font-bold text-gray-900" initials-class="font-display text-base font-bold uppercase tracking-wide" />
                        </Link>
                    </div>
                    <div class="hidden md:flex items-center gap-6">
                        <Link href="/marketplace" class="text-gray-600 hover:text-gray-900 font-medium">
                            Find Providers
                        </Link>
                        <Link href="/library" class="text-gray-600 hover:text-gray-900 font-medium">
                            Library
                        </Link>
                        <Link href="/login" class="text-gray-600 hover:text-gray-900 font-medium">
                            Sign In
                        </Link>
                        <Link href="/register" class="btn-primary">
                            Get Started
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 overflow-hidden">
            <div class="absolute inset-0 bg-grid-white/10"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 class="text-4xl lg:text-5xl xl:text-6xl font-display font-bold text-white leading-tight">
                            Your Trusted Guide to 
                            <span class="text-secondary-400">Immigration Services</span>
                        </h1>
                        <p class="mt-6 text-lg lg:text-xl text-primary-100 leading-relaxed">
                            {{ siteTagline }}
                        </p>
                        <div class="mt-10 flex flex-col sm:flex-row gap-4">
                            <Link href="/marketplace" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-primary-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors shadow-lg">
                                <MagnifyingGlassIcon class="h-5 w-5" />
                                Find a Provider
                            </Link>
                            <Link href="/register?role=provider" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-400 transition-colors border border-primary-400">
                                Become a Provider
                                <ArrowRightIcon class="h-5 w-5" />
                            </Link>
                        </div>
                    </div>
                    <div class="hidden lg:block">
                        <div class="relative">
                            <div class="absolute -inset-4 bg-white/10 rounded-3xl blur-xl"></div>
                            <div class="relative bg-white rounded-2xl shadow-2xl p-6">
                                <div class="flex items-center gap-4 mb-6">
                                    <div class="h-14 w-14 bg-primary-100 rounded-full flex items-center justify-center">
                                        <UserGroupIcon class="h-7 w-7 text-primary-600" />
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Quick Search</h3>
                                        <p class="text-sm text-gray-500">Find the right provider for you</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <select class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        <option value="">Select a service type...</option>
                                        <option v-for="service in serviceTypes" :key="service.value" :value="service.value">
                                            {{ service.label }}
                                        </option>
                                    </select>
                                    <input type="text" placeholder="City or ZIP code" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                    <Link href="/marketplace" class="block w-full px-4 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-colors text-center">
                                        Search Providers
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="py-12 bg-gray-50 border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-display font-bold text-primary-600">{{ stats.providers || '500+' }}</div>
                        <div class="mt-1 text-gray-600">Verified Providers</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-display font-bold text-primary-600">{{ stats.services || '15+' }}</div>
                        <div class="mt-1 text-gray-600">Service Categories</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-display font-bold text-primary-600">{{ stats.connections || '10K+' }}</div>
                        <div class="mt-1 text-gray-600">Successful Connections</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-display font-bold text-primary-600">{{ stats.rating || '4.8' }}</div>
                        <div class="mt-1 text-gray-600">Average Rating</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 lg:py-28">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-3xl lg:text-4xl font-display font-bold text-gray-900">
                        Why Choose {{ companyName }}?
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        We make it easy to find and connect with trusted immigration service providers
                    </p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div v-for="feature in features" :key="feature.title" class="bg-white border border-gray-100 rounded-2xl p-6 hover:shadow-lg transition-shadow">
                        <div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
                            <component :is="feature.icon" class="h-6 w-6 text-primary-600" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ feature.title }}</h3>
                        <p class="text-gray-600">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Service Types Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-12">
                    <h2 class="text-3xl lg:text-4xl font-display font-bold text-gray-900">
                        Services We Cover
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        Find professionals across all immigration-related services
                    </p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <Link 
                        v-for="service in serviceTypes.slice(0, 10)" 
                        :key="service.value"
                        :href="`/marketplace?service_type=${service.value}`"
                        class="bg-white border border-gray-100 rounded-xl p-4 text-center hover:border-primary-300 hover:shadow-md transition-all group"
                    >
                        <div class="text-2xl mb-2">{{ service.icon || '📋' }}</div>
                        <div class="font-medium text-gray-900 group-hover:text-primary-600">{{ service.label }}</div>
                    </Link>
                </div>
                <div class="text-center mt-8">
                    <Link href="/marketplace" class="inline-flex items-center gap-2 text-primary-600 hover:text-primary-700 font-medium">
                        View all services
                        <ArrowRightIcon class="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- Featured Providers Section -->
        <section v-if="featuredProviders.length" class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-12">
                    <div>
                        <h2 class="text-3xl font-display font-bold text-gray-900">Featured Providers</h2>
                        <p class="mt-2 text-gray-600">Top-rated professionals ready to help</p>
                    </div>
                    <Link href="/marketplace" class="hidden sm:inline-flex items-center gap-2 text-primary-600 hover:text-primary-700 font-medium">
                        View all
                        <ArrowRightIcon class="h-4 w-4" />
                    </Link>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div 
                        v-for="provider in featuredProviders" 
                        :key="provider.id"
                        class="bg-white border border-gray-100 rounded-2xl p-6 hover:shadow-lg transition-shadow"
                    >
                        <div class="flex items-start gap-4">
                            <img 
                                :src="provider.user?.avatar || '/images/default-avatar.png'" 
                                :alt="provider.business_name"
                                class="h-16 w-16 rounded-full object-cover"
                            />
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 truncate">{{ provider.business_name }}</h3>
                                <p class="text-sm text-gray-500">{{ provider.service_type_label }}</p>
                                <div class="flex items-center gap-1 mt-1">
                                    <div class="flex">
                                        <StarSolid v-for="i in 5" :key="i" class="h-4 w-4" :class="i <= Math.round(provider.average_rating || 0) ? 'text-secondary-500' : 'text-gray-200'" />
                                    </div>
                                    <span class="text-sm text-gray-600 ml-1">({{ provider.reviews_count || 0 }})</span>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-gray-600 text-sm line-clamp-2">{{ provider.bio }}</p>
                        <div class="mt-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <CheckCircleIcon v-if="provider.is_verified" class="h-5 w-5 text-green-500" />
                                <span v-if="provider.offers_free_consultation" class="text-xs bg-accent-100 text-accent-700 px-2 py-1 rounded-full">Free Consultation</span>
                            </div>
                            <Link :href="`/marketplace/${provider.slug}`" class="text-primary-600 hover:text-primary-700 font-medium text-sm">
                                View Profile →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section v-if="testimonials.length" class="py-20 bg-primary-600">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-display font-bold text-white">What Our Users Say</h2>
                </div>
                <div class="grid md:grid-cols-3 gap-8">
                    <div 
                        v-for="testimonial in testimonials" 
                        :key="testimonial.id"
                        class="bg-white/10 backdrop-blur-sm rounded-2xl p-6"
                    >
                        <div class="flex gap-1 mb-4">
                            <StarSolid v-for="i in 5" :key="i" class="h-5 w-5 text-secondary-400" />
                        </div>
                        <p class="text-white/90 italic">"{{ testimonial.comment }}"</p>
                        <div class="mt-4 flex items-center gap-3">
                            <div class="h-10 w-10 bg-white/20 rounded-full"></div>
                            <div>
                                <div class="font-medium text-white">{{ testimonial.user_name }}</div>
                                <div class="text-sm text-white/70">{{ testimonial.service_type }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl lg:text-4xl font-display font-bold text-gray-900">
                    Ready to Get Started?
                </h2>
                <p class="mt-4 text-lg text-gray-600">
                    Join thousands of immigrants who have found trusted service providers through our platform
                </p>
                <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4">
                    <Link href="/register" class="btn-primary btn-lg">
                        Create Free Account
                    </Link>
                    <Link href="/marketplace" class="btn-secondary btn-lg">
                        Browse Providers
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-4 gap-8">
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <BrandLogo context="site" subtitle="" mark-class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-white/10 text-white" name-class="text-xl font-display font-bold text-white" subtitle-class="text-white/70" initials-class="font-display text-base font-bold uppercase tracking-wide" />
                        </div>
                        <p class="text-sm">{{ footerTagline }}</p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white mb-4">For Users</h4>
                        <ul class="space-y-2 text-sm">
                            <li><Link href="/marketplace" class="hover:text-white">Find Providers</Link></li>
                            <li><Link href="/library" class="hover:text-white">Immigration Library</Link></li>
                            <li><Link href="/register" class="hover:text-white">Create Account</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white mb-4">For Providers</h4>
                        <ul class="space-y-2 text-sm">
                            <li><Link href="/register?role=provider" class="hover:text-white">Become a Provider</Link></li>
                            <li><Link href="/provider/benefits" class="hover:text-white">Provider Benefits</Link></li>
                            <li><Link href="/login" class="hover:text-white">Provider Login</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white mb-4">Support</h4>
                        <ul class="space-y-2 text-sm">
                            <li><Link href="/help" class="hover:text-white">Help Center</Link></li>
                            <li><Link href="/contact" class="hover:text-white">Contact Us</Link></li>
                            <li v-if="supportEmail"><a :href="`mailto:${supportEmail}`" class="hover:text-white">{{ supportEmail }}</a></li>
                            <li v-if="supportPhone"><a :href="`tel:${supportPhone}`" class="hover:text-white">{{ supportPhone }}</a></li>
                            <li v-if="supportAddress" class="text-gray-400">{{ supportAddress }}</li>
                            <li><Link href="/privacy" class="hover:text-white">Privacy Policy</Link></li>
                            <li><Link href="/terms" class="hover:text-white">Terms of Service</Link></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-12 pt-8 border-t border-gray-800 text-center text-sm">
                    &copy; {{ new Date().getFullYear() }} {{ companyName }}. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
