<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    initialMessages: { type: Array, default: () => [] },
    askRouteName: { type: String, required: true },
    checkoutRouteName: { type: String, required: true },
    isAddonActive: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const userAvatarSrc = computed(() => {
    const u = user.value;
    if (!u) return '';
    const candidate = String(u.avatar_url || u.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
});
const hasUserAvatar = computed(() => Boolean(userAvatarSrc.value));
const userInitials = computed(() => String(user.value?.initials || '').trim() || 'U');

const lastFlashResponse = computed(() => page.props.flash?.ai_assistant_response || null);
const flashError = computed(() => page.props.flash?.error || null);

const messages = ref([]);
const chatEl = ref(null);

const formatTime = (iso) => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleString(undefined, { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
};

const scrollToBottom = async () => {
    await nextTick();
    if (!chatEl.value) return;
    chatEl.value.scrollTop = chatEl.value.scrollHeight;
};

onMounted(() => {
    messages.value = Array.isArray(props.initialMessages) ? props.initialMessages : [];
    scrollToBottom();
});

watch(messages, () => scrollToBottom(), { deep: true });

const form = useForm({ question: '' });

const pushUserMessage = (text) => {
    const t = String(text || '').trim();
    if (!t) return null;
    const msg = {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        role: 'user',
        text: t,
        ts: new Date().toISOString(),
    };
    messages.value.push(msg);
    return msg;
};

const pushAssistantMessage = (text) => {
    const t = String(text || '').trim();
    if (!t) return null;
    const msg = {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        role: 'assistant',
        text: t,
        ts: new Date().toISOString(),
    };
    messages.value.push(msg);
    return msg;
};

const submitQuestion = () => {
    if (!props.isAddonActive) return;
    const q = String(form.question || '').trim();
    if (!q) return;

    pushUserMessage(q);

    form.post(route(props.askRouteName), {
        preserveScroll: true,
        onSuccess: () => {
            const res = lastFlashResponse.value;
            if (res?.answer) {
                pushAssistantMessage(res.answer);
            }
            form.reset();
        },
    });
};

const checkout = () => {
    useForm({}).post(route(props.checkoutRouteName));
};
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Chat</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Your messages are saved to your account and will appear here in order.
                    </p>
                </div>
            </div>
            <p v-if="flashError" class="mt-2 text-xs font-medium text-rose-700">{{ flashError }}</p>
        </div>

        <div
            ref="chatEl"
            :class="[
                'overflow-y-auto',
                messages.length === 0 && !form.processing ? 'max-h-0 p-0' : 'max-h-[520px] p-4 sm:p-5',
            ]"
        >
            <div v-if="messages.length > 0" class="space-y-3">
                <div v-for="m in messages" :key="m.id" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="flex max-w-[92%] items-end gap-2 sm:max-w-[80%]" :class="m.role === 'user' ? 'flex-row-reverse' : 'flex-row'">
                        <div class="shrink-0">
                            <img
                                v-if="m.role === 'user' && hasUserAvatar"
                                :src="userAvatarSrc"
                                alt="User avatar"
                                class="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200"
                            />
                            <div
                                v-else-if="m.role === 'user'"
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-600 text-[11px] font-bold text-white ring-1 ring-primary-500/50"
                                aria-hidden="true"
                            >
                                {{ userInitials }}
                            </div>
                            <div
                                v-else
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-[11px] font-bold text-white ring-1 ring-slate-800/50"
                                aria-hidden="true"
                            >
                                AI
                            </div>
                        </div>

                        <div class="min-w-0">
                            <div class="mb-1 flex items-center gap-2" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                                <p class="text-[11px] font-semibold text-slate-600">
                                    {{ m.role === 'user' ? 'You' : 'AI Assistant' }}
                                </p>
                                <p v-if="m.ts" class="text-[10px] text-slate-400">{{ formatTime(m.ts) }}</p>
                            </div>
                            <div
                                class="rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm ring-1"
                                :class="m.role === 'user'
                                    ? 'bg-primary-600 text-white ring-primary-600/30'
                                    : 'bg-white text-slate-800 ring-slate-200'"
                            >
                                <p class="whitespace-pre-line">{{ m.text }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="form.processing" class="mt-3 flex justify-start">
                <div class="flex max-w-[92%] items-end gap-2 sm:max-w-[80%]">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-[11px] font-bold text-white ring-1 ring-slate-800/50" aria-hidden="true">
                        AI
                    </div>
                    <div class="min-w-0">
                        <div class="mb-1 flex items-center gap-2">
                            <p class="text-[11px] font-semibold text-slate-600">AI Assistant</p>
                            <p class="text-[10px] text-slate-400">typing…</p>
                        </div>
                        <div class="rounded-2xl bg-white px-4 py-3 text-sm text-slate-700 shadow-sm ring-1 ring-slate-200">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400"></span>
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400 [animation-delay:120ms]"></span>
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400 [animation-delay:240ms]"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 p-4 sm:p-5">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Message
            </label>
            <textarea
                v-model="form.question"
                rows="3"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 outline-none focus:border-primary-300 focus:bg-white focus:ring-2 focus:ring-primary-100"
                :placeholder="placeholder || 'Type your question...'"
                :disabled="!isAddonActive || form.processing"
                @keydown.enter.exact.prevent="submitQuestion"
                @keydown.enter.shift.exact.stop
            />
            <div class="mt-3 flex items-center justify-between gap-4">
                <p class="text-xs text-slate-500">
                    General guidance only; verify with official sources.
                </p>
                <Button :disabled="form.processing || !String(form.question || '').trim() || !isAddonActive" @click="submitQuestion">
                    {{ form.processing ? 'Asking...' : 'Send' }}
                </Button>
            </div>
            <p v-if="!isAddonActive" class="mt-2 text-xs text-amber-700">Subscribe to unlock AI chat.</p>
        </div>
    </div>
</template>

