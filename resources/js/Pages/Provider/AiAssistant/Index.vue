<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    subscription: Object,
    isAddonActive: Boolean,
    monthlyPrice: String,
    currency: String,
});

const page = usePage();
const lastResponse = computed(() => page.props.flash?.ai_assistant_response || null);
const flashError = computed(() => page.props.flash?.error || null);
const flashSuccess = computed(() => page.props.flash?.success || null);
const form = useForm({
    question: '',
});

const submitQuestion = () => {
    form.post(route('provider.ai-assistant.ask'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const checkout = () => {
    useForm({}).post(route('provider.ai-assistant.checkout'));
};

const statusLabel = computed(() => {
    if (!props.subscription) return 'Not subscribed';
    return props.subscription.status || 'inactive';
});
</script>

<template>
    <Head title="AI Assistant" />

    <ProviderLayout>
        <div class="mx-auto max-w-5xl space-y-6 pb-10">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">AI Assistant</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Ask about public programs, USCIS-style processes at a general level, client education angles, peers on the
                    marketplace, and library books you can reference. Same add-on subscription as members; billing is per
                    your account email.
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ currency }} {{ monthlyPrice }} / month
                    </span>
                    <span
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-semibold',
                            isAddonActive ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700',
                        ]"
                    >
                        {{ isAddonActive ? 'Active add-on' : `Status: ${statusLabel}` }}
                    </span>
                    <Button v-if="!isAddonActive" size="sm" @click="checkout">Buy add-on</Button>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div v-if="lastResponse" class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h2 class="text-lg font-semibold text-slate-900">Answer</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ lastResponse.answer }}</p>
                </div>

                <label class="mb-2 block text-sm font-medium text-slate-700">Ask the AI assistant</label>
                <textarea
                    v-model="form.question"
                    rows="5"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 outline-none focus:border-primary-300 focus:bg-white focus:ring-2 focus:ring-primary-100"
                    placeholder="Example: Summarize EBT eligibility basics my clients often confuse. Any library books on SNAP for newcomers?"
                />
                <div class="mt-3 flex items-center justify-between gap-4">
                    <p class="text-xs text-slate-500">
                        General information only—not legal advice. Verify with official sources before relying on guidance.
                    </p>
                    <Button :disabled="form.processing || !form.question.trim() || !isAddonActive" @click="submitQuestion">
                        {{ form.processing ? 'Asking...' : 'Ask AI' }}
                    </Button>
                </div>
                <p v-if="flashError" class="mt-2 text-xs font-medium text-rose-700">{{ flashError }}</p>
                <p v-else-if="flashSuccess" class="mt-2 text-xs font-medium text-emerald-700">{{ flashSuccess }}</p>
                <p v-if="!isAddonActive" class="mt-2 text-xs text-amber-700">Subscribe to unlock AI chat.</p>
            </div>

            <div v-if="lastResponse" class="space-y-4">
                <div class="rounded-xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-slate-50/80 p-4 shadow-sm">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Recommended</h2>
                            <p class="mt-0.5 text-[11px] leading-snug text-slate-500">
                                Library titles matched to your question.
                            </p>
                        </div>
                    </div>
                    <div v-if="lastResponse.books?.length" class="mt-3 grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                        <a
                            v-for="book in lastResponse.books"
                            :key="book.id"
                            :href="route('library.show', book.slug)"
                            class="group flex gap-2.5 overflow-hidden rounded-lg bg-white p-2 shadow-sm ring-1 ring-slate-200/70 transition hover:ring-primary-300/50 hover:shadow-md"
                        >
                            <div
                                class="relative h-[4.5rem] w-[3.15rem] shrink-0 overflow-hidden rounded-md bg-gradient-to-br from-slate-100 to-slate-200"
                            >
                                <img
                                    v-if="book.cover_image_url"
                                    :src="book.cover_image_url"
                                    :alt="book.title"
                                    class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-slate-400"
                                    aria-hidden="true"
                                >
                                    <svg class="h-7 w-7 opacity-55" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1"
                                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                                        />
                                    </svg>
                                </div>
                                <span
                                    class="absolute bottom-0 left-0 right-0 bg-slate-900/75 px-0.5 py-0.5 text-center text-[8px] font-semibold tracking-wide text-white uppercase"
                                >
                                    {{ book.type_label }}
                                </span>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col justify-between gap-1 py-0.5">
                                <div class="min-w-0">
                                    <h3 class="line-clamp-2 text-xs font-semibold leading-snug text-slate-900 group-hover:text-primary-800">
                                        {{ book.title }}
                                    </h3>
                                    <p v-if="book.author" class="mt-0.5 truncate text-[10px] text-slate-500">
                                        {{ book.author }}
                                    </p>
                                </div>
                                <div class="rounded-md border border-primary-100/80 bg-primary-50/70 px-2 py-1.5">
                                    <p class="text-[8px] font-bold tracking-wider text-primary-700 uppercase">
                                        Recommended for
                                    </p>
                                    <p class="mt-0.5 text-[10px] leading-snug text-slate-700 line-clamp-2">
                                        {{ book.recommended_for }}
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <p v-else class="mt-3 text-sm text-slate-600">
                        No library titles were matched to this question. Try naming a topic from the catalog, or browse the
                        library.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Provider matches</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Marketplace providers that fit the inferred request (discovery / referrals).</p>
                    <div v-if="lastResponse.providers?.length" class="mt-3 grid gap-3 md:grid-cols-2">
                        <a
                            v-for="provider in lastResponse.providers"
                            :key="provider.id"
                            :href="route('marketplace.show', provider.slug)"
                            class="rounded-xl border border-slate-200 p-4 transition hover:border-primary-300 hover:bg-slate-50"
                        >
                            <p class="font-semibold text-slate-900">{{ provider.business_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ provider.location || 'Location not specified' }}</p>
                            <p class="mt-1 text-xs text-slate-600">
                                Languages: {{ provider.languages_offered?.join(', ') || 'Not listed' }}
                            </p>
                        </a>
                    </div>
                    <p v-else class="mt-2 text-sm text-slate-600">
                        No providers matched this request yet. Try including location, language, or service type.
                    </p>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
