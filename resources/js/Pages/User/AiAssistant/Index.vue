<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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
    form.post(route('user.ai-assistant.ask'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const checkout = () => {
    useForm({}).post(route('user.ai-assistant.checkout'));
};

const statusLabel = computed(() => {
    if (!props.subscription) return 'Not subscribed';
    return props.subscription.status || 'inactive';
});
</script>

<template>
    <Head title="AI Assistant" />

    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">AI Assistant</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Ask questions about public resources like EBT, DMV, and other immigration support topics.
                    The assistant can also suggest providers based on your request.
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
                    placeholder="Example: How do I get an EBT card in Texas? Also find Spanish-speaking providers near Houston."
                />
                <div class="mt-3 flex items-center justify-between">
                    <p class="text-xs text-slate-500">General guidance only; always verify with official sources.</p>
                    <Button :disabled="form.processing || !form.question.trim() || !isAddonActive" @click="submitQuestion">
                        {{ form.processing ? 'Asking...' : 'Ask AI' }}
                    </Button>
                </div>
                <p v-if="flashError" class="mt-2 text-xs font-medium text-rose-700">{{ flashError }}</p>
                <p v-else-if="flashSuccess" class="mt-2 text-xs font-medium text-emerald-700">{{ flashSuccess }}</p>
                <p v-if="!isAddonActive" class="mt-2 text-xs text-amber-700">Subscribe to unlock AI chat.</p>
            </div>

            <div v-if="lastResponse" class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Provider matches</h2>
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
    </AppLayout>
</template>
