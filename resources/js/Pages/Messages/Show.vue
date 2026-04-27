<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ArrowLeftIcon,
    PaperAirplaneIcon,
    PaperClipIcon,
    PhotoIcon,
    DocumentIcon,
    XMarkIcon,
    EllipsisVerticalIcon,
    ArchiveBoxIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    conversation: Object,
    isProvider: Boolean,
    otherParticipant: Object,
    contractClose: { type: Object, default: null },
});
const messagesContainer = ref(null);
const fileInput = ref(null);
const showLeadInfo = ref(false);
const showCloseModal = ref(false);
const showOfferModal = ref(false);

const form = useForm({
    body: '',
    attachments: [],
});

const closeContractForm = useForm({
    reason: '',
    reason_details: '',
    review_rating: 5,
    review_comment: '',
    provider_payment_amount: null,
});
const offerForm = useForm({
    offered_rate: null,
});

const attachmentPreviews = ref([]);
const closeReasonOptions = [
    { value: 'scope_completed', label: 'Work completed successfully' },
    { value: 'goals_not_met', label: 'Project goals were not met' },
    { value: 'communication_issues', label: 'Communication issues' },
    { value: 'budget_or_rate', label: 'Budget or rate mismatch' },
    { value: 'timeline_delays', label: 'Timeline delays' },
    { value: 'change_of_plans', label: 'Change of plans' },
    { value: 'other', label: 'Other' },
];
const layoutComponent = computed(() => (props.isProvider ? ProviderLayout : AppLayout));
const messagesIndexHref = computed(() =>
    props.isProvider ? route('provider.messages.index') : route('messages.index'),
);

const formatTime = (date) => {
    const d = new Date(date);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatDate = (date) => {
    const d = new Date(date);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });
};

const groupedMessages = computed(() => {
    const groups = [];
    let currentDate = null;

    (props.conversation?.messages ?? []).forEach(message => {
        const messageDate = new Date(message.created_at).toDateString();
        
        if (messageDate !== currentDate) {
            currentDate = messageDate;
            groups.push({
                date: message.created_at,
                messages: [],
            });
        }
        
        groups[groups.length - 1].messages.push(message);
    });

    return groups;
});

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

let pollTimer = null;
const pollConversation = () => {
    if (document.visibilityState !== 'visible') {
        return;
    }
    router.reload({ only: ['conversation'], preserveScroll: true });
};

onMounted(() => {
    scrollToBottom();
    pollTimer = window.setInterval(pollConversation, 4000);
    document.addEventListener('visibilitychange', pollConversation);
});

onUnmounted(() => {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
    }
    document.removeEventListener('visibilitychange', pollConversation);
});

watch(() => props.conversation?.messages, () => {
    scrollToBottom();
}, { deep: true });

const sendMessage = () => {
    if (!form.body.trim() && form.attachments.length === 0) return;

    form.post(route('messages.send', props.conversation.uuid), {
        preserveScroll: true,
        preserveState: false,
        forceFormData: form.attachments.length > 0,
        onSuccess: () => {
            form.reset();
            attachmentPreviews.value = [];
            scrollToBottom();
        },
    });
};

const handleKeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileSelect = (e) => {
    const files = Array.from(e.target.files);
    
    files.forEach(file => {
        if (form.attachments.length >= 5) return;
        
        form.attachments.push(file);
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                attachmentPreviews.value.push({
                    name: file.name,
                    type: 'image',
                    url: e.target.result,
                });
            };
            reader.readAsDataURL(file);
        } else {
            attachmentPreviews.value.push({
                name: file.name,
                type: 'file',
                size: formatFileSize(file.size),
            });
        }
    });
    
    e.target.value = '';
};

const removeAttachment = (index) => {
    form.attachments.splice(index, 1);
    attachmentPreviews.value.splice(index, 1);
};

const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const archiveConversation = () => {
    router.post(route('messages.archive', props.conversation.uuid));
};

const getServiceTypeLabel = (type) => {
    const labels = {
        immigration_attorney: 'Immigration Law',
        tax_accountant: 'Tax Services',
        tutor: 'Tutoring',
        translator: 'Translation',
        real_estate: 'Real Estate',
    };
    return labels[type] || type;
};

const getStatusColor = (status) => {
    const colors = {
        new: 'bg-blue-100 text-blue-700',
        contacted: 'bg-yellow-100 text-yellow-700',
        in_progress: 'bg-purple-100 text-purple-700',
        converted: 'bg-green-100 text-green-700',
        closed: 'bg-slate-100 text-slate-700',
        declined: 'bg-red-100 text-red-700',
    };
    return colors[status] || 'bg-slate-100 text-slate-700';
};

const offerStage = computed(() => {
    const lead = props.conversation?.lead;
    if (!lead) return 'No offer yet';
    const contract = lead.contract;
    if (contract?.state === 'accepted' || contract?.state === 'in_progress') return 'Offer accepted - Contract active';
    if (contract?.state === 'ended') return 'Contract ended';
    if (contract?.state === 'withdrawn') return 'Offer withdrawn';
    if (contract?.state === 'offered') return 'Offer sent - Waiting provider acceptance';
    if (lead.contract_accepted_at) return 'Offer accepted - Contract active';
    if (lead.contract_sent_at) return 'Offer sent - Waiting provider acceptance';
    if (props.conversation?.has_exchanged_messages) return 'Ready to send offer';
    return 'Exchange messages first';
});

const canSendOffer = computed(() => {
    const lead = props.conversation?.lead;
    if (!lead || props.isProvider) return false;
    const contractState = lead.contract?.state;
    if (contractState) {
        return ['draft', 'withdrawn', 'cancelled'].includes(contractState);
    }
    return !lead.contract_sent_at && !lead.contract_accepted_at && ['new', 'contacted'].includes(lead.status);
});

const canWithdrawOffer = computed(() => {
    const lead = props.conversation?.lead;
    if (!lead || props.isProvider) return false;
    const contractState = lead.contract?.state;
    if (contractState) {
        return contractState === 'offered';
    }
    return Boolean(lead.contract_sent_at) && !lead.contract_accepted_at && ['new', 'contacted'].includes(lead.status);
});

const providerDefaultRate = computed(() => {
    const raw = props.conversation?.lead?.provider_hourly_rate
        ?? props.conversation?.service_provider?.hourly_rate
        ?? props.conversation?.serviceProvider?.hourly_rate;
    if (raw === null || raw === undefined || raw === '') return null;
    const n = Number(raw);
    return Number.isFinite(n) ? n : null;
});

const formatUsd = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return null;
    return `$${n.toFixed(2)} USD`;
};

const openOfferModal = () => {
    offerForm.clearErrors();
    offerForm.offered_rate = providerDefaultRate.value;
    showOfferModal.value = true;
};

const closeOfferModal = () => {
    showOfferModal.value = false;
    offerForm.reset();
    offerForm.clearErrors();
};

const sendOffer = () => {
    const lead = props.conversation?.lead;
    if (!lead?.uuid) return;
    offerForm.patch(route('contracts.send', lead.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            closeOfferModal();
        },
    });
};

const withdrawOffer = () => {
    const lead = props.conversation?.lead;
    const contract = lead?.contract;
    if (contract?.uuid) {
        router.patch(route('contracts.withdraw.by-contract', contract.uuid), {}, {
            preserveScroll: true,
        });
        return;
    }
    if (!lead?.uuid) return;
    router.patch(route('contracts.withdraw', lead.uuid), {}, {
        preserveScroll: true,
    });
};

const canCloseContract = computed(() => {
    const lead = props.conversation?.lead;
    if (!lead || props.isProvider) return false;
    return ['in_progress', 'converted'].includes(lead.status);
});

const parseMoneyToCents = (val) => {
    if (val === null || val === undefined || val === '') return 0;
    const n = Number(val);
    if (Number.isNaN(n) || n < 0) return 0;
    return Math.round(n * 100);
};

const closeSummaryCents = computed(() => {
    const fee = Number(props.contractClose?.serviceFeeCents || 0);
    const toProvider = parseMoneyToCents(closeContractForm.provider_payment_amount);
    return { fee, toProvider, total: fee + toProvider };
});

const openCloseContractModal = () => {
    closeContractForm.reset();
    closeContractForm.clearErrors();
    closeContractForm.review_rating = 5;
    closeContractForm.provider_payment_amount = null;
    showCloseModal.value = true;
};

const closeCloseContractModal = () => {
    showCloseModal.value = false;
    closeContractForm.reset();
    closeContractForm.clearErrors();
};

const submitCloseContract = () => {
    const lead = props.conversation?.lead;
    if (!lead?.uuid) return;

    closeContractForm.transform((data) => {
        const reason = (data.reason || '').trim();
        const details = (data.reason_details || '').trim();
        return {
            ...data,
            reason: details ? `${reason}: ${details}` : reason,
            provider_payment_amount: data.provider_payment_amount === null || data.provider_payment_amount === ''
                ? null
                : data.provider_payment_amount,
        };
    }).patch(route('contracts.end', lead.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            closeCloseContractModal();
        },
    });
};

const canProviderAcceptOffer = computed(() => {
    const lead = props.conversation?.lead;
    if (!lead || !props.isProvider) return false;
    return Boolean(lead.contract_sent_at) && !lead.contract_accepted_at && ['new', 'contacted'].includes(lead.status);
});

const acceptOffer = () => {
    const lead = props.conversation?.lead;
    if (!lead?.uuid) return;
    router.patch(route('provider.leads.status', lead.uuid), { status: 'in_progress' }, {
        preserveScroll: true,
    });
};

const getAvatarSrc = (person) => {
    const raw = person?.avatar || '';
    if (!raw) return null;
    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
        return raw;
    }
    return `/storage/${raw}`;
};

const getInitials = (person) => {
    const first = (person?.first_name || '').trim();
    const last = (person?.last_name || '').trim();
    if (first || last) {
        return `${first.charAt(0)}${last.charAt(0)}`.toUpperCase();
    }
    return '?';
};
</script>

<template>
    <Head :title="`Chat with ${otherParticipant.first_name}`" />

    <component :is="layoutComponent" :fullWidth="true" :noPadding="true">
        <div class="flex h-[calc(100vh-4rem)] flex-col bg-gradient-to-b from-slate-50 to-slate-100/60">
            <!-- Header -->
            <div class="flex-shrink-0 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Link 
                            :href="messagesIndexHref"
                            class="p-2 -ml-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                        >
                            <ArrowLeftIcon class="w-5 h-5" />
                        </Link>
                        
                        <div class="flex items-center gap-3">
                            <img
                                v-if="getAvatarSrc(otherParticipant)"
                                :src="getAvatarSrc(otherParticipant)"
                                :alt="otherParticipant.first_name"
                                class="h-10 w-10 rounded-full object-cover ring-1 ring-slate-200"
                            />
                            <div
                                v-else
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-sm font-semibold text-white ring-1 ring-slate-200"
                            >
                                {{ getInitials(otherParticipant) }}
                            </div>
                            <div>
                                <h1 class="font-semibold text-slate-900">
                                    {{ otherParticipant.first_name }} {{ otherParticipant.last_name }}
                                </h1>
                                <p v-if="conversation.lead" class="text-sm text-slate-500">
                                    {{ getServiceTypeLabel(conversation.lead.service_type) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            v-if="conversation.lead"
                            @click="showLeadInfo = !showLeadInfo"
                            :class="[
                                'p-2 rounded-lg transition-colors',
                                showLeadInfo ? 'bg-primary-100 text-primary-600' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            <InformationCircleIcon class="w-5 h-5" />
                        </button>
                        
                        <Menu as="div" class="relative">
                            <MenuButton class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                                <EllipsisVerticalIcon class="w-5 h-5" />
                            </MenuButton>
                            <MenuItems class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-10">
                                <MenuItem v-slot="{ active }">
                                    <button 
                                        @click="archiveConversation"
                                        :class="[
                                            'flex items-center gap-3 w-full px-4 py-2 text-sm',
                                            active ? 'bg-slate-50 text-slate-900' : 'text-slate-700'
                                        ]"
                                    >
                                        <ArchiveBoxIcon class="w-4 h-4" />
                                        Archive conversation
                                    </button>
                                </MenuItem>
                            </MenuItems>
                        </Menu>
                    </div>
                </div>
            </div>

            <!-- Lead Info Panel (collapsible) -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="showLeadInfo && conversation.lead" class="flex-shrink-0 bg-primary-50 border-b border-primary-100 px-4 py-4">
                    <div class="max-w-4xl mx-auto">
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <div>
                                <span class="text-primary-600 font-medium">Status:</span>
                                <span :class="['ml-2 px-2 py-0.5 rounded-full text-xs font-medium', getStatusColor(conversation.lead.status)]">
                                    {{ conversation.lead.status }}
                                </span>
                            </div>
                            <div>
                                <span class="text-primary-600 font-medium">Urgency:</span>
                                <span class="ml-2 text-slate-700 capitalize">{{ conversation.lead.urgency }}</span>
                            </div>
                            <div>
                                <span class="text-primary-600 font-medium">Created:</span>
                                <span class="ml-2 text-slate-700">{{ new Date(conversation.lead.created_at).toLocaleDateString() }}</span>
                            </div>
                        </div>
                        <p v-if="conversation.lead.message" class="mt-2 text-sm text-slate-600 line-clamp-2">
                            {{ conversation.lead.message }}
                        </p>
                    </div>
                </div>
            </Transition>

            <!-- Messages + Offer Sidebar -->
            <div class="flex-1 overflow-hidden px-4 py-4">
                <div class="mx-auto grid h-full w-full max-w-6xl gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div ref="messagesContainer" class="min-h-0 overflow-y-auto rounded-2xl border border-slate-200 bg-white px-4 py-6 shadow-sm">
                        <div class="space-y-8">
                    <div v-if="form.errors.body" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {{ form.errors.body }}
                    </div>
                    <div v-for="group in groupedMessages" :key="group.date" class="space-y-4">
                        <!-- Date Separator -->
                        <div class="flex items-center justify-center">
                            <span class="px-3 py-1 bg-slate-200 text-slate-600 text-xs font-medium rounded-full">
                                {{ formatDate(group.date) }}
                            </span>
                        </div>

                        <!-- Messages in this group -->
                        <div 
                            v-for="message in group.messages" 
                            :key="message.uuid"
                            :class="[
                                'flex',
                                message.is_mine ? 'justify-end' : 'justify-start'
                            ]"
                        >
                            <div :class="['flex gap-3 max-w-[75%]', message.is_mine && 'flex-row-reverse']">
                                <img
                                    v-if="!message.is_mine && getAvatarSrc(message.sender)"
                                    :src="getAvatarSrc(message.sender)"
                                    :alt="message.sender?.first_name"
                                    class="h-8 w-8 flex-shrink-0 rounded-full object-cover ring-1 ring-slate-200"
                                />
                                <div
                                    v-else-if="!message.is_mine"
                                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-xs font-semibold text-white ring-1 ring-slate-200"
                                >
                                    {{ getInitials(message.sender) }}
                                </div>
                                
                                <div :class="[
                                    'rounded-2xl px-4 py-3',
                                    message.is_mine
                                        ? 'rounded-br-md bg-primary-600 text-white shadow-sm'
                                        : 'rounded-bl-md border border-slate-200 bg-slate-50 text-slate-900'
                                ]">
                                    <!-- System message -->
                                    <p v-if="message.is_system_message" class="text-sm italic opacity-80">
                                        {{ message.body }}
                                    </p>
                                    
                                    <!-- Regular message -->
                                    <template v-else>
                                        <p class="whitespace-pre-wrap break-words">{{ message.body }}</p>
                                        
                                        <!-- Attachments -->
                                        <div v-if="message.attachments?.length" class="mt-2 space-y-2">
                                            <div 
                                                v-for="(attachment, i) in message.attachments" 
                                                :key="i"
                                                :class="[
                                                    'flex items-center gap-2 p-2 rounded-lg',
                                                    message.is_mine ? 'bg-primary-500/30' : 'bg-slate-100'
                                                ]"
                                            >
                                                <DocumentIcon class="w-5 h-5 flex-shrink-0" />
                                                <span class="text-sm truncate">{{ attachment.name }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <span :class="[
                                        'block text-xs mt-1',
                                        message.is_mine ? 'text-primary-200' : 'text-slate-400'
                                    ]">
                                        {{ formatTime(message.created_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                    <aside class="hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:block">
                        <h3 class="text-sm font-semibold text-slate-900">Offer Stage</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ offerStage }}</p>
                        <div v-if="conversation.lead" class="mt-3">
                            <span :class="['rounded-full px-2 py-1 text-xs font-medium', getStatusColor(conversation.lead.status)]">
                                {{ conversation.lead.status }}
                            </span>
                        </div>
                        <button
                            v-if="!isProvider && !canWithdrawOffer"
                            type="button"
                            class="mt-4 w-full rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors"
                            :class="canSendOffer ? 'bg-primary-600 text-white hover:bg-primary-500' : 'cursor-not-allowed bg-slate-100 text-slate-400'"
                            :disabled="!canSendOffer"
                            @click="openOfferModal"
                        >
                            Give Offer
                        </button>
                        <button
                            v-if="canWithdrawOffer"
                            type="button"
                            class="mt-3 w-full rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition-colors hover:bg-rose-100"
                            @click="withdrawOffer"
                        >
                            Remove Offer
                        </button>
                        <p
                            v-if="conversation.lead?.contract?.offered_rate !== null && conversation.lead?.contract?.offered_rate !== undefined"
                            class="mt-2 text-xs text-slate-600"
                        >
                            Offered rate: {{ formatUsd(conversation.lead.contract.offered_rate) }}
                        </p>
                        <p
                            v-if="conversation.lead?.contract?.agreed_rate !== null && conversation.lead?.contract?.agreed_rate !== undefined"
                            class="mt-1 text-xs text-emerald-700"
                        >
                            Agreed rate: {{ formatUsd(conversation.lead.contract.agreed_rate) }}
                        </p>
                        <p v-if="!isProvider" class="mt-2 text-xs text-slate-500">
                            Click Give Offer to send your contract offer.
                        </p>
                        <button
                            v-if="canCloseContract"
                            type="button"
                            class="mt-3 w-full rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition-colors hover:bg-rose-100"
                            @click="openCloseContractModal"
                        >
                            Close Contract
                        </button>
                        <p v-if="canCloseContract" class="mt-2 text-xs text-slate-500">
                            Closing requires leaving a review.
                        </p>
                        <button
                            v-if="isProvider && canProviderAcceptOffer"
                            type="button"
                            class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-500"
                            @click="acceptOffer"
                        >
                            Accept Offer
                        </button>
                        <p v-if="isProvider && !canProviderAcceptOffer" class="mt-2 text-xs text-slate-500">
                            Waiting for a user offer before acceptance.
                        </p>
                    </aside>
                </div>
            </div>

            <Teleport to="body">
                <div v-if="showOfferModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                        <div class="fixed inset-0 bg-black/50" @click="closeOfferModal"></div>
                        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                            <button
                                type="button"
                                class="absolute right-4 top-4 text-slate-400 transition-colors hover:text-slate-600"
                                @click="closeOfferModal"
                            >
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                            <h3 class="text-lg font-semibold text-slate-900">Send contract offer</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                We prefilled the provider&apos;s rate. You can override it before sending.
                            </p>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-slate-700" for="offer-rate-msg">
                                    Offered rate (USD)
                                </label>
                                <input
                                    id="offer-rate-msg"
                                    v-model="offerForm.offered_rate"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                    placeholder="0.00"
                                />
                                <p v-if="offerForm.errors.offered_rate" class="mt-1 text-xs text-rose-600">
                                    {{ offerForm.errors.offered_rate }}
                                </p>
                            </div>
                            <div class="mt-6 flex items-center gap-3">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    @click="closeOfferModal"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60"
                                    :disabled="offerForm.processing"
                                    @click="sendOffer"
                                >
                                    {{ offerForm.processing ? 'Sending...' : 'Send Offer' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="showCloseModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                        <div class="fixed inset-0 bg-black/50" @click="closeCloseContractModal"></div>
                        <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                            <button
                                type="button"
                                class="absolute right-4 top-4 text-slate-400 transition-colors hover:text-slate-600"
                                @click="closeCloseContractModal"
                            >
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                            <h3 class="text-lg font-semibold text-slate-900">Close contract and leave review</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Your review will be shown on the provider profile for other users.
                            </p>
                            <div
                                v-if="contractClose"
                                class="mt-4 rounded-xl border border-slate-200 bg-slate-50/90 px-3 py-3 text-sm text-slate-800"
                            >
                                <p class="font-medium text-slate-900">Closing payment</p>
                                <p class="mt-1 text-slate-600">
                                    <span class="font-medium">Service category rate (platform):</span>
                                    ${{ (Number(contractClose.serviceFeeCents) / 100).toFixed(2) }} {{ contractClose.currency || 'USD' }}
                                </p>
                                <p class="mt-2 text-xs text-slate-500">
                                    This is the same monthly category rate you saw when subscribing; it is charged once at contract close
                                    (together with any amount you add for the provider).
                                </p>
                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-slate-700" for="provider-pay-msg">
                                        Optional payment to provider (USD)
                                    </label>
                                    <input
                                        id="provider-pay-msg"
                                        v-model="closeContractForm.provider_payment_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                        placeholder="0.00"
                                    />
                                </div>
                                <p class="mt-2 text-slate-700">
                                    <span class="font-medium">Total due:</span>
                                    ${{ (closeSummaryCents.total / 100).toFixed(2) }}
                                    <span v-if="closeSummaryCents.total === 0" class="text-slate-500">(no card charge)</span>
                                </p>
                                <p
                                    v-if="closeSummaryCents.total > 0 && closeSummaryCents.total < (contractClose.minCardChargeCents || 50)"
                                    class="mt-1 text-xs text-amber-800"
                                >
                                    Card payments require a minimum of ${{
                                        ((contractClose.minCardChargeCents || 50) / 100).toFixed(2)
                                    }}. Add to the provider amount or set both to $0.00 to close without payment.
                                </p>
                                <p v-if="closeSummaryCents.total > 0 && !contractClose.stripeReady" class="mt-1 text-xs text-rose-700">
                                    Stripe is not configured. Use $0.00 for both, or set STRIPE_SECRET in the app environment to pay
                                    online.
                                </p>
                                <p
                                    v-if="closeContractForm.errors?.provider_payment_amount"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ closeContractForm.errors.provider_payment_amount }}
                                </p>
                            </div>

                            <div class="mt-5 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Star rating</label>
                                    <div class="mt-2 flex items-center gap-2">
                                        <button
                                            v-for="star in 5"
                                            :key="star"
                                            type="button"
                                            class="rounded-md p-1 transition hover:bg-slate-100"
                                            @click="closeContractForm.review_rating = star"
                                        >
                                            <StarSolid
                                                class="h-7 w-7"
                                                :class="star <= closeContractForm.review_rating ? 'text-yellow-400' : 'text-slate-300'"
                                            />
                                        </button>
                                    </div>
                                    <p v-if="closeContractForm.errors.review_rating" class="mt-1 text-sm text-rose-600">
                                        {{ closeContractForm.errors.review_rating }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Review description</label>
                                    <textarea
                                        v-model="closeContractForm.review_comment"
                                        rows="4"
                                        minlength="10"
                                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-sky-300 focus:ring-2 focus:ring-sky-100"
                                        placeholder="Describe your experience with this provider..."
                                    />
                                    <p v-if="closeContractForm.errors.review_comment" class="mt-1 text-sm text-rose-600">
                                        {{ closeContractForm.errors.review_comment }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Reason for ending contract</label>
                                    <select
                                        v-model="closeContractForm.reason"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-sky-300 focus:ring-2 focus:ring-sky-100"
                                    >
                                        <option value="" disabled>Select a reason</option>
                                        <option
                                            v-for="option in closeReasonOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <p v-if="closeContractForm.errors.reason" class="mt-1 text-sm text-rose-600">
                                        {{ closeContractForm.errors.reason }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Optional details</label>
                                    <textarea
                                        v-model="closeContractForm.reason_details"
                                        rows="2"
                                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-sky-300 focus:ring-2 focus:ring-sky-100"
                                        placeholder="Add more context (optional)"
                                    />
                                </div>
                            </div>

                            <div class="mt-6 flex items-center gap-3">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    @click="closeCloseContractModal"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-60"
                                    :disabled="closeContractForm.processing || !closeContractForm.reason"
                                    @click="submitCloseContract"
                                >
                                    {{
                                        closeContractForm.processing
                                            ? 'Submitting...'
                                            : closeSummaryCents.total > 0
                                              ? (contractClose?.stripeReady ? 'Pay & close (Stripe)' : 'Close and submit')
                                              : 'Close and submit review'
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Message Input -->
            <div class="flex-shrink-0 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur">
                <div class="mx-auto max-w-6xl">
                    <!-- Attachment Previews -->
                    <div v-if="attachmentPreviews.length" class="flex flex-wrap gap-2 mb-3">
                        <div 
                            v-for="(preview, index) in attachmentPreviews" 
                            :key="index"
                            class="relative group"
                        >
                            <div v-if="preview.type === 'image'" class="w-20 h-20 rounded-lg overflow-hidden">
                                <img :src="preview.url" :alt="preview.name" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="flex items-center gap-2 px-3 py-2 bg-slate-100 rounded-lg">
                                <DocumentIcon class="w-5 h-5 text-slate-500" />
                                <span class="text-sm text-slate-700 truncate max-w-[100px]">{{ preview.name }}</span>
                            </div>
                            <button 
                                @click="removeAttachment(index)"
                                class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                <XMarkIcon class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="sendMessage" class="flex items-end gap-3">
                        <input 
                            ref="fileInput"
                            type="file"
                            multiple
                            accept="image/*,.pdf,.doc,.docx"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                        
                        <button
                            type="button"
                            @click="triggerFileInput"
                            class="rounded-xl p-3 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                        >
                            <PaperClipIcon class="w-5 h-5" />
                        </button>
                        
                        <div class="flex-1 relative">
                            <textarea
                                v-model="form.body"
                                @keydown="handleKeydown"
                                placeholder="Type your message..."
                                rows="1"
                                class="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                style="min-height: 48px; max-height: 120px;"
                            ></textarea>
                        </div>
                        
                        <button 
                            type="submit"
                            :disabled="form.processing || (!form.body.trim() && form.attachments.length === 0)"
                            :class="[
                                'p-3 rounded-xl transition-all',
                                (form.body.trim() || form.attachments.length > 0)
                                    ? 'bg-primary-600 text-white hover:bg-primary-500'
                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                            ]"
                        >
                            <PaperAirplaneIcon class="w-5 h-5" />
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </component>
</template>
