<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    StarIcon as StarSolid,
    FunnelIcon,
    ChatBubbleLeftIcon,
    FlagIcon,
    ChevronDownIcon
} from '@heroicons/vue/24/solid';
import { StarIcon as StarOutline, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    reviews: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const showResponseModal = ref(false);
const selectedReview = ref(null);
const showReportModal = ref(false);

const responseForm = useForm({
    response: '',
});

const reportForm = useForm({
    reason: '',
    details: '',
});

const openResponseModal = (review) => {
    selectedReview.value = review;
    responseForm.response = review.provider_response || '';
    showResponseModal.value = true;
};

const submitResponse = () => {
    const url = selectedReview.value.provider_response 
        ? `/provider/reviews/${selectedReview.value.uuid}/response`
        : `/provider/reviews/${selectedReview.value.uuid}/respond`;
    
    responseForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            showResponseModal.value = false;
            selectedReview.value = null;
            responseForm.reset();
        },
    });
};

const deleteResponse = () => {
    if (!confirm('Are you sure you want to remove your response?')) return;
    
    router.delete(`/provider/reviews/${selectedReview.value.uuid}/response`, {
        preserveScroll: true,
        onSuccess: () => {
            showResponseModal.value = false;
            selectedReview.value = null;
        },
    });
};

const openReportModal = (review) => {
    selectedReview.value = review;
    showReportModal.value = true;
};

const submitReport = () => {
    reportForm.post(`/provider/reviews/${selectedReview.value.uuid}/report`, {
        preserveScroll: true,
        onSuccess: () => {
            showReportModal.value = false;
            selectedReview.value = null;
            reportForm.reset();
        },
    });
};

const applyFilter = (key, value) => {
    router.get('/provider/reviews', {
        ...props.filters,
        [key]: value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const ratingDistribution = computed(() => {
    const total = Object.values(props.stats.distribution || {}).reduce((a, b) => a + b, 0) || 1;
    return [5, 4, 3, 2, 1].map(rating => ({
        rating,
        count: props.stats.distribution?.[rating] || 0,
        percentage: Math.round(((props.stats.distribution?.[rating] || 0) / total) * 100),
    }));
});
</script>

<template>
    <Head title="Reviews" />

    <ProviderLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-display font-bold text-gray-900">Reviews</h1>
                <p class="text-gray-500 mt-1">Manage and respond to client reviews</p>
            </div>

            <!-- Stats Overview -->
            <div class="grid sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.total || 0 }}</div>
                    <div class="text-sm text-gray-500">Total Reviews</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <span class="text-3xl font-display font-bold text-gray-900">{{ Number(stats.average || 0).toFixed(1) }}</span>
                        <StarSolid class="h-6 w-6 text-yellow-500" />
                    </div>
                    <div class="text-sm text-gray-500">Average Rating</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                    <div class="text-3xl font-display font-bold text-yellow-600">{{ stats.distribution?.[5] || 0 }}</div>
                    <div class="text-sm text-gray-500">5-Star Reviews</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                    <div class="text-3xl font-display font-bold text-orange-600">{{ stats.awaiting_response || 0 }}</div>
                    <div class="text-sm text-gray-500">Awaiting Response</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Rating Distribution -->
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Rating Distribution</h2>
                    <div class="space-y-3">
                        <div v-for="item in ratingDistribution" :key="item.rating" class="flex items-center gap-3">
                            <div class="flex items-center gap-1 w-12">
                                <span class="text-sm font-medium">{{ item.rating }}</span>
                                <StarSolid class="h-4 w-4 text-yellow-500" />
                            </div>
                            <div class="flex-1 h-3 bg-gray-100 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-yellow-500 rounded-full transition-all"
                                    :style="{ width: `${item.percentage}%` }"
                                ></div>
                            </div>
                            <span class="text-sm text-gray-500 w-8">{{ item.count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Reviews List -->
                <div class="lg:col-span-2">
                    <!-- Filters -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                        <div class="flex flex-wrap gap-3">
                            <select 
                                :value="filters.rating || ''"
                                @change="applyFilter('rating', $event.target.value)"
                                class="input text-sm"
                            >
                                <option value="">All Ratings</option>
                                <option v-for="i in 5" :key="i" :value="6 - i">{{ 6 - i }} Stars</option>
                            </select>
                            <select 
                                :value="filters.responded || ''"
                                @change="applyFilter('responded', $event.target.value)"
                                class="input text-sm"
                            >
                                <option value="">All Reviews</option>
                                <option value="no">Awaiting Response</option>
                                <option value="yes">Responded</option>
                            </select>
                            <select 
                                :value="filters.sort || 'newest'"
                                @change="applyFilter('sort', $event.target.value)"
                                class="input text-sm"
                            >
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="highest">Highest Rated</option>
                                <option value="lowest">Lowest Rated</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div v-if="reviews.data?.length" class="space-y-4">
                        <div 
                            v-for="review in reviews.data" 
                            :key="review.id"
                            class="bg-white rounded-xl border border-gray-100 p-6"
                        >
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <img 
                                        :src="review.user?.avatar || '/images/default-avatar.png'" 
                                        class="h-10 w-10 rounded-full"
                                    />
                                    <div>
                                        <div class="font-medium text-gray-900">{{ review.user?.full_name || 'Anonymous' }}</div>
                                        <div class="text-sm text-gray-500">{{ formatDate(review.created_at) }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <template v-for="i in 5" :key="i">
                                        <StarSolid v-if="i <= review.rating" class="h-5 w-5 text-yellow-500" />
                                        <StarOutline v-else class="h-5 w-5 text-gray-300" />
                                    </template>
                                </div>
                            </div>

                            <!-- Review Content -->
                            <p class="text-gray-700 mb-4">{{ review.comment }}</p>

                            <!-- Sub-ratings -->
                            <div v-if="review.communication_rating || review.expertise_rating || review.value_rating" class="flex flex-wrap gap-4 mb-4 text-sm">
                                <div v-if="review.communication_rating" class="flex items-center gap-1 text-gray-500">
                                    <span>Communication:</span>
                                    <span class="font-medium text-gray-700">{{ review.communication_rating }}/5</span>
                                </div>
                                <div v-if="review.expertise_rating" class="flex items-center gap-1 text-gray-500">
                                    <span>Expertise:</span>
                                    <span class="font-medium text-gray-700">{{ review.expertise_rating }}/5</span>
                                </div>
                                <div v-if="review.value_rating" class="flex items-center gap-1 text-gray-500">
                                    <span>Value:</span>
                                    <span class="font-medium text-gray-700">{{ review.value_rating }}/5</span>
                                </div>
                            </div>

                            <!-- Provider Response -->
                            <div v-if="review.provider_response" class="bg-gray-50 rounded-lg p-4 border-l-4 border-primary-500 mb-4">
                                <div class="text-sm font-medium text-gray-900 mb-1">Your Response</div>
                                <p class="text-gray-600 text-sm">{{ review.provider_response }}</p>
                                <div class="text-xs text-gray-400 mt-2">{{ formatDate(review.provider_responded_at) }}</div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                                <button 
                                    @click="openResponseModal(review)"
                                    class="btn-secondary btn-sm"
                                >
                                    <ChatBubbleLeftIcon class="h-4 w-4 mr-1" />
                                    {{ review.provider_response ? 'Edit Response' : 'Respond' }}
                                </button>
                                <button 
                                    v-if="!review.is_flagged"
                                    @click="openReportModal(review)"
                                    class="btn-ghost btn-sm text-gray-500"
                                >
                                    <FlagIcon class="h-4 w-4 mr-1" />
                                    Report
                                </button>
                                <span v-else class="text-xs text-orange-600 bg-orange-50 px-2 py-1 rounded">
                                    Reported
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="bg-white rounded-xl border border-gray-100 p-12 text-center">
                        <StarOutline class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No reviews yet</h3>
                        <p class="text-gray-500">Reviews from your clients will appear here.</p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="reviews.links?.length > 3" class="mt-6 flex justify-center">
                        <nav class="flex gap-1">
                            <Link 
                                v-for="link in reviews.links" 
                                :key="link.label"
                                :href="link.url"
                                class="px-3 py-2 text-sm rounded-lg"
                                :class="link.active ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                                v-html="link.label"
                            />
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Response Modal -->
        <Teleport to="body">
            <div v-if="showResponseModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showResponseModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ selectedReview?.provider_response ? 'Edit Your Response' : 'Respond to Review' }}
                        </h3>
                        
                        <!-- Original Review Preview -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="flex">
                                    <StarSolid v-for="i in selectedReview?.rating" :key="i" class="h-4 w-4 text-yellow-500" />
                                </div>
                                <span class="text-sm text-gray-500">by {{ selectedReview?.user?.full_name }}</span>
                            </div>
                            <p class="text-sm text-gray-600">{{ selectedReview?.comment }}</p>
                        </div>

                        <form @submit.prevent="submitResponse">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Your Response</label>
                                <textarea 
                                    v-model="responseForm.response"
                                    rows="4"
                                    class="input w-full"
                                    placeholder="Thank you for your feedback..."
                                    required
                                    minlength="10"
                                    maxlength="1000"
                                ></textarea>
                                <p class="text-xs text-gray-500 mt-1">{{ responseForm.response.length }}/1000 characters</p>
                            </div>

                            <div class="flex gap-3">
                                <button type="button" @click="showResponseModal = false" class="btn-secondary flex-1">
                                    Cancel
                                </button>
                                <button 
                                    v-if="selectedReview?.provider_response"
                                    type="button"
                                    @click="deleteResponse"
                                    class="btn-ghost text-red-600"
                                >
                                    Remove
                                </button>
                                <button 
                                    type="submit" 
                                    class="btn-primary flex-1"
                                    :disabled="responseForm.processing"
                                >
                                    {{ responseForm.processing ? 'Posting...' : 'Post Response' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Report Modal -->
        <Teleport to="body">
            <div v-if="showReportModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showReportModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Review</h3>
                        <p class="text-gray-600 text-sm mb-4">
                            Please select a reason for reporting this review. Our team will investigate.
                        </p>
                        
                        <form @submit.prevent="submitReport" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50" :class="reportForm.reason === 'inappropriate' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                                        <input v-model="reportForm.reason" type="radio" value="inappropriate" class="text-primary-600" />
                                        <span>Inappropriate content</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50" :class="reportForm.reason === 'fake' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                                        <input v-model="reportForm.reason" type="radio" value="fake" class="text-primary-600" />
                                        <span>Fake or fraudulent</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50" :class="reportForm.reason === 'spam' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                                        <input v-model="reportForm.reason" type="radio" value="spam" class="text-primary-600" />
                                        <span>Spam</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50" :class="reportForm.reason === 'other' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                                        <input v-model="reportForm.reason" type="radio" value="other" class="text-primary-600" />
                                        <span>Other</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Details (optional)</label>
                                <textarea 
                                    v-model="reportForm.details"
                                    rows="3"
                                    class="input w-full"
                                    placeholder="Provide more context..."
                                ></textarea>
                            </div>

                            <div class="flex gap-3">
                                <button type="button" @click="showReportModal = false" class="btn-secondary flex-1">
                                    Cancel
                                </button>
                                <button 
                                    type="submit" 
                                    class="btn-primary flex-1"
                                    :disabled="!reportForm.reason || reportForm.processing"
                                >
                                    {{ reportForm.processing ? 'Submitting...' : 'Submit Report' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </ProviderLayout>
</template>
