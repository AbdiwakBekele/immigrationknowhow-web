<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ArrowLeftIcon,
    ChatBubbleLeftRightIcon,
    PhoneIcon,
    EnvelopeIcon,
    MapPinIcon,
    ClockIcon,
    ExclamationCircleIcon,
    CheckCircleIcon,
    XCircleIcon,
    CalendarIcon,
    UserIcon,
    DocumentTextIcon,
    ChevronDownIcon
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    lead: { type: Object, required: true },
    conversation: { type: Object, default: null },
    activityLog: { type: Array, default: () => [] },
});

const statusOptions = [
    { value: 'new', label: 'New', color: 'bg-yellow-100 text-yellow-700' },
    { value: 'contacted', label: 'Contacted', color: 'bg-blue-100 text-blue-700' },
    { value: 'in_progress', label: 'In Progress', color: 'bg-purple-100 text-purple-700' },
    { value: 'converted', label: 'Converted', color: 'bg-green-100 text-green-700' },
    { value: 'closed', label: 'Closed', color: 'bg-gray-100 text-gray-600' },
    { value: 'declined', label: 'Declined', color: 'bg-red-100 text-red-700' },
];

const showNoteModal = ref(false);
const noteForm = useForm({
    note: '',
});

const updateStatus = (newStatus) => {
    router.patch(`/provider/leads/${props.lead.uuid}/status`, {
        status: newStatus,
    }, {
        preserveScroll: true,
    });
};

const addNote = () => {
    noteForm.post(`/provider/leads/${props.lead.uuid}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            showNoteModal.value = false;
            noteForm.reset();
        },
    });
};

const startConversation = () => {
    router.post(`/provider/leads/${props.lead.uuid}/conversation`, {}, {
        onSuccess: (page) => {
            // Redirect to conversation
        },
    });
};

const getStatusColor = (status) => {
    return statusOptions.find(s => s.value === status)?.color || 'bg-gray-100 text-gray-600';
};

const getUrgencyInfo = (urgency) => {
    const info = {
        low: { label: 'Low Priority', color: 'text-gray-500', bg: 'bg-gray-100' },
        normal: { label: 'Normal Priority', color: 'text-blue-600', bg: 'bg-blue-100' },
        high: { label: 'High Priority', color: 'text-orange-600', bg: 'bg-orange-100' },
        urgent: { label: 'Urgent - Immediate', color: 'text-red-600', bg: 'bg-red-100' },
    };
    return info[urgency] || info.normal;
};

const formatDate = (date, full = false) => {
    if (!date) return '';
    const options = full 
        ? { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' }
        : { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
    return new Date(date).toLocaleDateString('en-US', options);
};

const resolveAvatar = (person) => {
    const candidate = (person?.avatar_url || person?.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
};

const firstInitial = (...values) => {
    for (const value of values) {
        const text = (value || '').trim();
        if (text) return text.charAt(0).toUpperCase();
    }
    return 'U';
};
</script>

<template>
    <Head :title="`Lead from ${lead.user?.full_name || 'Anonymous'}`" />

    <ProviderLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Back Button -->
            <Link href="/provider/leads" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-6">
                <ArrowLeftIcon class="h-4 w-4" />
                Back to Leads
            </Link>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Lead Header -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <img
                                    v-if="resolveAvatar(lead.user)"
                                    :src="resolveAvatar(lead.user)"
                                    class="h-16 w-16 rounded-full object-cover"
                                    alt="Lead user avatar"
                                />
                                <div
                                    v-else
                                    class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-600 text-xl font-bold text-white"
                                    aria-hidden="true"
                                >
                                    {{ firstInitial(lead.user?.first_name, lead.user?.last_name, lead.user?.full_name) }}
                                </div>
                                <div>
                                    <h1 class="text-xl font-display font-bold text-gray-900">
                                        {{ lead.user?.full_name || 'Anonymous' }}
                                    </h1>
                                    <p class="text-gray-500">{{ lead.service_type_label }}</p>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span 
                                            class="px-3 py-1 rounded-full text-sm font-medium"
                                            :class="getStatusColor(lead.status)"
                                        >
                                            {{ lead.status.replace('_', ' ') }}
                                        </span>
                                        <span 
                                            class="px-2 py-1 rounded text-xs font-medium"
                                            :class="[getUrgencyInfo(lead.urgency).bg, getUrgencyInfo(lead.urgency).color]"
                                        >
                                            {{ getUrgencyInfo(lead.urgency).label }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Dropdown -->
                            <Menu as="div" class="relative">
                                <MenuButton class="btn-secondary btn-sm">
                                    Update Status
                                    <ChevronDownIcon class="h-4 w-4 ml-1" />
                                </MenuButton>
                                <MenuItems class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-10">
                                    <MenuItem 
                                        v-for="status in statusOptions" 
                                        :key="status.value"
                                        v-slot="{ active }"
                                    >
                                        <button 
                                            @click="updateStatus(status.value)"
                                            class="w-full px-4 py-2 text-sm text-left flex items-center gap-2"
                                            :class="[active ? 'bg-gray-50' : '', lead.status === status.value ? 'font-medium' : '']"
                                        >
                                            <span class="w-2 h-2 rounded-full" :class="status.color.replace('text-', 'bg-').split(' ')[0]"></span>
                                            {{ status.label }}
                                            <CheckCircleIcon v-if="lead.status === status.value" class="h-4 w-4 ml-auto text-primary-600" />
                                        </button>
                                    </MenuItem>
                                </MenuItems>
                            </Menu>
                        </div>
                    </div>

                    <!-- Inquiry Message -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Inquiry Message</h2>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-gray-700 whitespace-pre-line">{{ lead.message || 'No message provided.' }}</p>
                        </div>
                        <div class="flex items-center gap-4 mt-4 text-sm text-gray-500">
                            <div class="flex items-center gap-1">
                                <CalendarIcon class="h-4 w-4" />
                                {{ formatDate(lead.created_at, true) }}
                            </div>
                            <div v-if="lead.preferred_contact_method" class="flex items-center gap-1">
                                <ChatBubbleLeftRightIcon class="h-4 w-4" />
                                Prefers: {{ lead.preferred_contact_method }}
                            </div>
                            <div v-if="lead.preferred_contact_time" class="flex items-center gap-1">
                                <ClockIcon class="h-4 w-4" />
                                Best time: {{ lead.preferred_contact_time }}
                            </div>
                        </div>
                    </div>

                    <!-- Request Metadata -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Request Details</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div class="rounded-xl bg-gray-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Budget Range</div>
                                <div class="text-gray-900 font-medium">{{ lead.budget_range || 'Not specified' }}</div>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Needed By</div>
                                <div class="text-gray-900 font-medium">{{ lead.needed_by ? formatDate(lead.needed_by) : 'Flexible' }}</div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Requirements</h3>
                            <div v-if="lead.requirements?.length" class="flex flex-wrap gap-2">
                                <span
                                    v-for="(req, index) in lead.requirements"
                                    :key="`${index}-${req}`"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg bg-primary-50 text-primary-700 text-sm"
                                >
                                    {{ req }}
                                </span>
                            </div>
                            <p v-else class="text-sm text-gray-500">No specific requirements provided.</p>
                        </div>
                    </div>

                    <!-- Activity Timeline -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Activity</h2>
                            <button @click="showNoteModal = true" class="btn-secondary btn-sm">
                                Add Note
                            </button>
                        </div>

                        <div v-if="activityLog.length" class="space-y-4">
                            <div 
                                v-for="activity in activityLog" 
                                :key="activity.id"
                                class="flex gap-3"
                            >
                                <div class="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                    <DocumentTextIcon v-if="activity.type === 'note'" class="h-4 w-4 text-gray-500" />
                                    <CheckCircleIcon v-else-if="activity.type === 'status_change'" class="h-4 w-4 text-green-500" />
                                    <ChatBubbleLeftRightIcon v-else class="h-4 w-4 text-primary-500" />
                                </div>
                                <div class="flex-1">
                                    <p class="text-gray-900">{{ activity.description }}</p>
                                    <p class="text-sm text-gray-500 mt-1">{{ formatDate(activity.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            No activity recorded yet
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <Link 
                                v-if="conversation"
                                :href="route('provider.messages.show', conversation.uuid)"
                                class="btn-primary w-full"
                            >
                                <ChatBubbleLeftRightIcon class="h-5 w-5 mr-2" />
                                View Conversation
                            </Link>
                            <button 
                                v-else
                                @click="startConversation"
                                class="btn-primary w-full"
                            >
                                <ChatBubbleLeftRightIcon class="h-5 w-5 mr-2" />
                                Start Conversation
                            </button>

                            <a 
                                v-if="lead.user?.email"
                                :href="`mailto:${lead.user.email}`"
                                class="btn-secondary w-full flex items-center justify-center"
                            >
                                <EnvelopeIcon class="h-5 w-5 mr-2" />
                                Send Email
                            </a>

                            <a 
                                v-if="lead.user?.phone"
                                :href="`tel:${lead.user.phone}`"
                                class="btn-secondary w-full flex items-center justify-center"
                            >
                                <PhoneIcon class="h-5 w-5 mr-2" />
                                Call
                            </a>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Contact Information</h3>
                        <div class="space-y-3">
                            <div v-if="lead.user?.email" class="flex items-center gap-3">
                                <EnvelopeIcon class="h-5 w-5 text-gray-400" />
                                <a :href="`mailto:${lead.user.email}`" class="text-primary-600 hover:underline">
                                    {{ lead.user.email }}
                                </a>
                            </div>
                            <div v-if="lead.user?.phone" class="flex items-center gap-3">
                                <PhoneIcon class="h-5 w-5 text-gray-400" />
                                <a :href="`tel:${lead.user.phone}`" class="text-primary-600 hover:underline">
                                    {{ lead.user.phone }}
                                </a>
                            </div>
                            <div v-if="lead.user?.city || lead.user?.state" class="flex items-center gap-3">
                                <MapPinIcon class="h-5 w-5 text-gray-400" />
                                <span class="text-gray-600">
                                    {{ [lead.user?.city, lead.user?.state].filter(Boolean).join(', ') }}
                                </span>
                            </div>
                            <div v-if="lead.user?.preferred_language" class="flex items-start gap-3">
                                <UserIcon class="h-5 w-5 text-gray-400 mt-0.5" />
                                <div>
                                    <div class="text-sm text-gray-500">Preferred Language</div>
                                    <div class="text-gray-900">{{ lead.user.preferred_language }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lead Details -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Lead Details</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Lead ID</span>
                                <span class="font-mono text-gray-900">#{{ lead.id }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Received</span>
                                <span class="text-gray-900">{{ formatDate(lead.created_at) }}</span>
                            </div>
                            <div v-if="lead.responded_at" class="flex justify-between">
                                <span class="text-gray-500">First Contact</span>
                                <span class="text-gray-900">{{ formatDate(lead.responded_at) }}</span>
                            </div>
                            <div v-if="lead.converted_at" class="flex justify-between">
                                <span class="text-gray-500">Converted</span>
                                <span class="text-gray-900">{{ formatDate(lead.converted_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Note Modal -->
        <Teleport to="body">
            <div v-if="showNoteModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showNoteModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Note</h3>
                        <form @submit.prevent="addNote">
                            <textarea 
                                v-model="noteForm.note"
                                rows="4"
                                class="input w-full"
                                placeholder="Enter your note..."
                                required
                            ></textarea>
                            <div class="flex gap-3 mt-4">
                                <button type="button" @click="showNoteModal = false" class="btn-secondary flex-1">
                                    Cancel
                                </button>
                                <button 
                                    type="submit" 
                                    class="btn-primary flex-1"
                                    :disabled="noteForm.processing"
                                >
                                    {{ noteForm.processing ? 'Saving...' : 'Save Note' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </ProviderLayout>
</template>
