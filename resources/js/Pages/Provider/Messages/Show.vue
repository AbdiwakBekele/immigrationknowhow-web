<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import {
    ArrowLeftIcon,
    PaperAirplaneIcon,
    PaperClipIcon,
    DocumentIcon,
    XMarkIcon,
    EllipsisVerticalIcon,
    ArchiveBoxIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed, nextTick, onMounted, watch } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    conversation: Object,
    isProvider: Boolean,
    otherParticipant: Object,
});

const page = usePage();
const authId = computed(() => page.props.auth?.user?.id);

const messagesContainer = ref(null);
const fileInput = ref(null);
const showLeadInfo = ref(false);

const form = useForm({
    body: '',
    attachments: [],
});

const attachmentPreviews = ref([]);

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
    const list = props.conversation?.messages ?? [];

    list.forEach((message) => {
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

const isFromMe = (message) => {
    if (typeof message.is_mine === 'boolean') {
        return message.is_mine;
    }
    return authId.value != null && message.sender_id === authId.value;
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

onMounted(() => {
    scrollToBottom();
});

watch(() => props.conversation?.messages, () => {
    scrollToBottom();
}, { deep: true });

const sendMessage = () => {
    if (!form.body.trim() && form.attachments.length === 0) return;

    form.post(route('provider.messages.send', props.conversation.uuid), {
        preserveScroll: true,
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

    files.forEach((file) => {
        if (form.attachments.length >= 5) return;

        form.attachments.push(file);

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                attachmentPreviews.value.push({
                    name: file.name,
                    type: 'image',
                    url: ev.target.result,
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
    router.post(route('provider.messages.archive', props.conversation.uuid));
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
    if (lead.contract_accepted_at) return 'Offer accepted - Contract active';
    if (lead.contract_sent_at) return 'Offer sent by user';
    return 'No offer sent yet';
});

const canAcceptOffer = computed(() => {
    const lead = props.conversation?.lead;
    if (!lead || !props.isProvider) return false;
    return Boolean(lead.contract_sent_at) && !lead.contract_accepted_at && ['new', 'contacted'].includes(lead.status);
});

const acceptOffer = () => {
    const lead = props.conversation?.lead;
    if (!lead?.uuid) return;
    router.patch(route('provider.leads.status', lead.uuid), { status: 'in_progress' }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Chat with ${otherParticipant.first_name}`" />

    <ProviderLayout>
        <div class="h-[calc(100vh-4rem)] flex flex-col bg-slate-50">
            <div class="flex-shrink-0 bg-white border-b border-slate-200 px-4 py-3">
                <div class="max-w-4xl mx-auto flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Link
                            :href="route('provider.messages.index')"
                            class="p-2 -ml-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                        >
                            <ArrowLeftIcon class="w-5 h-5" />
                        </Link>

                        <div class="flex items-center gap-3">
                            <img
                                :src="otherParticipant.avatar || '/img/default-avatar.png'"
                                :alt="otherParticipant.first_name"
                                class="w-10 h-10 rounded-full object-cover"
                            />
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
                            type="button"
                            @click="showLeadInfo = !showLeadInfo"
                            :class="[
                                'p-2 rounded-lg transition-colors',
                                showLeadInfo ? 'bg-primary-100 text-primary-600' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100',
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
                                        type="button"
                                        @click="archiveConversation"
                                        :class="[
                                            'flex items-center gap-3 w-full px-4 py-2 text-sm',
                                            active ? 'bg-slate-50 text-slate-900' : 'text-slate-700',
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

            <div class="flex-1 overflow-hidden px-4 py-4">
                <div class="mx-auto grid h-full w-full max-w-6xl gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div ref="messagesContainer" class="min-h-0 overflow-y-auto rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <div class="space-y-8">
                    <div v-if="form.errors.body" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {{ form.errors.body }}
                    </div>
                    <div
                        v-if="!(conversation?.messages?.length)"
                        class="rounded-2xl border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-slate-500 text-sm"
                    >
                        No messages in this thread yet. When the client sends an inquiry, it will appear here — you can reply below.
                    </div>
                    <div v-for="group in groupedMessages" :key="group.date" class="space-y-4">
                        <div class="flex items-center justify-center">
                            <span class="px-3 py-1 bg-slate-200 text-slate-600 text-xs font-medium rounded-full">
                                {{ formatDate(group.date) }}
                            </span>
                        </div>

                        <div
                            v-for="message in group.messages"
                            :key="message.uuid || message.id"
                            :class="[
                                'flex',
                                isFromMe(message) ? 'justify-end' : 'justify-start',
                            ]"
                        >
                            <div :class="['flex gap-3 max-w-[75%]', isFromMe(message) && 'flex-row-reverse']">
                                <img
                                    v-if="!isFromMe(message)"
                                    :src="message.sender?.avatar || '/img/default-avatar.png'"
                                    :alt="message.sender?.first_name"
                                    class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                />

                                <div :class="[
                                    'rounded-2xl px-4 py-3',
                                    isFromMe(message)
                                        ? 'bg-primary-600 text-white rounded-br-md'
                                        : 'bg-white text-slate-900 shadow-sm rounded-bl-md',
                                ]">
                                    <p v-if="message.is_system_message" class="text-sm italic opacity-80">
                                        {{ message.body }}
                                    </p>

                                    <template v-else>
                                        <p class="whitespace-pre-wrap break-words">{{ message.body }}</p>

                                        <div v-if="message.attachments?.length" class="mt-2 space-y-2">
                                            <div
                                                v-for="(attachment, i) in message.attachments"
                                                :key="i"
                                                :class="[
                                                    'flex items-center gap-2 p-2 rounded-lg',
                                                    isFromMe(message) ? 'bg-primary-500/30' : 'bg-slate-100',
                                                ]"
                                            >
                                                <DocumentIcon class="w-5 h-5 flex-shrink-0" />
                                                <span class="text-sm truncate">{{ attachment.name }}</span>
                                            </div>
                                        </div>
                                    </template>

                                    <span :class="[
                                        'block text-xs mt-1',
                                        isFromMe(message) ? 'text-primary-200' : 'text-slate-400',
                                    ]">
                                        {{ formatTime(message.created_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                    <aside class="hidden rounded-2xl border border-slate-200 bg-white p-4 lg:block">
                        <h3 class="text-sm font-semibold text-slate-900">Offer Stage</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ offerStage }}</p>
                        <div v-if="conversation.lead" class="mt-3">
                            <span :class="['rounded-full px-2 py-1 text-xs font-medium', getStatusColor(conversation.lead.status)]">
                                {{ conversation.lead.status }}
                            </span>
                        </div>
                        <button
                            v-if="canAcceptOffer"
                            type="button"
                            class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-500"
                            @click="acceptOffer"
                        >
                            Accept Offer
                        </button>
                        <p v-else class="mt-2 text-xs text-slate-500">
                            Waiting for user offer before acceptance.
                        </p>
                    </aside>
                </div>
            </div>

            <div class="flex-shrink-0 bg-white border-t border-slate-200 px-4 py-4">
                <div class="mx-auto max-w-6xl">
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
                                type="button"
                                @click="removeAttachment(index)"
                                class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                <XMarkIcon class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <form class="flex items-end gap-3" @submit.prevent="sendMessage">
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
                            class="p-3 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
                            @click="triggerFileInput"
                        >
                            <PaperClipIcon class="w-5 h-5" />
                        </button>

                        <div class="flex-1 relative">
                            <textarea
                                v-model="form.body"
                                placeholder="Type your message..."
                                rows="1"
                                class="w-full px-4 py-3 bg-slate-100 border-0 rounded-2xl text-slate-900 placeholder-slate-400 resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"
                                style="min-height: 48px; max-height: 120px;"
                                @keydown="handleKeydown"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing || (!form.body.trim() && form.attachments.length === 0)"
                            :class="[
                                'p-3 rounded-xl transition-all',
                                (form.body.trim() || form.attachments.length > 0)
                                    ? 'bg-primary-600 text-white hover:bg-primary-500'
                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed',
                            ]"
                        >
                            <PaperAirplaneIcon class="w-5 h-5" />
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
