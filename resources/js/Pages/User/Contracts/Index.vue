<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    MagnifyingGlassIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClipboardDocumentListIcon,
    ChatBubbleLeftRightIcon,
    ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    leads: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'new', label: 'New' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'converted', label: 'Completed' },
    { value: 'closed', label: 'Ended' },
    { value: 'declined', label: 'Declined' },
];

const applyFilters = () => {
    router.get(route('contracts.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});
watch(status, applyFilters);

const statusClasses = (currentStatus) => {
    const map = {
        new: 'bg-blue-100 text-blue-700',
        contacted: 'bg-indigo-100 text-indigo-700',
        in_progress: 'bg-amber-100 text-amber-700',
        converted: 'bg-emerald-100 text-emerald-700',
        closed: 'bg-slate-100 text-slate-700',
        declined: 'bg-rose-100 text-rose-700',
    };
    return map[currentStatus] || 'bg-slate-100 text-slate-700';
};

const humanStatus = (currentStatus) => currentStatus?.replace('_', ' ');
const titleCase = (value) => (value ? value.split(' ').map((v) => v.charAt(0).toUpperCase() + v.slice(1)).join(' ') : '');

const offerStage = (lead) => {
    if (lead.contract_accepted_at) return 'Active contract';
    if (lead.contract_sent_at) return 'Offer sent';
    if (lead.has_exchanged_messages) return 'Ready to send offer';
    return 'Awaiting message exchange';
};

const offerStageClasses = (lead) => {
    if (lead.contract_accepted_at) return 'bg-emerald-100 text-emerald-700';
    if (lead.contract_sent_at) return 'bg-indigo-100 text-indigo-700';
    if (lead.has_exchanged_messages) return 'bg-sky-100 text-sky-700';
    return 'bg-slate-100 text-slate-600';
};

const canSendContract = (lead) => Boolean(lead.can_send_contract);
const canEnd = (lead) => ['in_progress', 'converted'].includes(lead.status);
const showEndModal = ref(false);
const selectedLead = ref(null);

const endForm = useForm({
    reason: '',
    reason_details: '',
    review_rating: 5,
    review_comment: '',
});
const closeReasonOptions = [
    { value: 'scope_completed', label: 'Work completed successfully' },
    { value: 'goals_not_met', label: 'Project goals were not met' },
    { value: 'communication_issues', label: 'Communication issues' },
    { value: 'budget_or_rate', label: 'Budget or rate mismatch' },
    { value: 'timeline_delays', label: 'Timeline delays' },
    { value: 'change_of_plans', label: 'Change of plans' },
    { value: 'other', label: 'Other' },
];

const sendContract = (lead) => {
    router.patch(route('contracts.send', lead.uuid), {}, { preserveScroll: true });
};

const openEndModal = (lead) => {
    selectedLead.value = lead;
    endForm.reset();
    endForm.clearErrors();
    endForm.review_rating = 5;
    showEndModal.value = true;
};

const closeEndModal = () => {
    showEndModal.value = false;
    selectedLead.value = null;
    endForm.reset();
    endForm.clearErrors();
};

const submitEndContract = () => {
    if (!selectedLead.value) return;

    endForm.transform((data) => {
        const reason = (data.reason || '').trim();
        const details = (data.reason_details || '').trim();
        return {
            ...data,
            reason: details ? `${reason}: ${details}` : reason,
        };
    }).patch(route('contracts.end', selectedLead.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            closeEndModal();
        },
    });
};

const hasLeads = computed(() => (props.leads?.data?.length || 0) > 0);
</script>

<template>
    <Head title="Contracts" />

    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl bg-gradient-to-r from-sky-600 via-indigo-600 to-violet-600 px-6 py-8 text-white shadow-xl sm:px-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sky-100">
                            Contracts
                        </p>
                        <h1 class="mt-2 text-3xl font-display font-bold sm:text-4xl">
                            Manage offers and active work
                        </h1>
                        <p class="mt-3 text-sm text-sky-50 sm:text-base">
                            Send offers, wait for acceptance, and track contracts with your providers in one place.
                        </p>
                    </div>
                    <div class="grid w-full grid-cols-2 gap-3 lg:max-w-3xl lg:grid-cols-4">
                        <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Total</p>
                            <p class="text-lg font-semibold tabular-nums">{{ stats.total || 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Active</p>
                            <p class="text-lg font-semibold tabular-nums text-amber-100">{{ stats.active || 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Completed</p>
                            <p class="text-lg font-semibold tabular-nums text-emerald-100">{{ stats.completed || 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Ended</p>
                            <p class="text-lg font-semibold tabular-nums text-sky-100">{{ stats.ended || 0 }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by provider or message..."
                            class="w-full rounded-lg border border-slate-200 py-2.5 pl-10 pr-3 text-sm text-slate-700 outline-none focus:border-sky-300 focus:ring-2 focus:ring-sky-100"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-300 focus:ring-2 focus:ring-sky-100"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="hasLeads" class="grid gap-4">
                <div
                    v-for="lead in leads.data"
                    :key="lead.uuid"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-lg font-semibold text-slate-900">
                                {{ lead.service_provider?.business_name || 'Provider' }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ lead.message || 'No message provided.' }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusClasses(lead.status)"
                                >
                                    {{ titleCase(humanStatus(lead.status)) }}
                                </span>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="offerStageClasses(lead)"
                                >
                                    {{ offerStage(lead) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            <Link
                                v-if="lead.conversation?.uuid"
                                :href="route('messages.show', lead.conversation.uuid)"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                <ChatBubbleLeftRightIcon class="h-4 w-4" />
                                Open chat
                                <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
                            </Link>
                            <button
                                v-if="canSendContract(lead)"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-emerald-700"
                                @click="sendContract(lead)"
                            >
                                <CheckCircleIcon class="h-4 w-4" />
                                Send contract
                            </button>
                            <button
                                v-if="canEnd(lead)"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100"
                                @click="openEndModal(lead)"
                            >
                                <XCircleIcon class="h-4 w-4" />
                                Close contract
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                <ClipboardDocumentListIcon class="mx-auto h-10 w-10 text-slate-300" />
                <p class="mt-3 text-sm text-slate-500">No contracts yet.</p>
            </div>

            <div v-if="leads.links?.length > 3" class="flex flex-wrap items-center justify-center gap-2 pt-1">
                <template v-for="link in leads.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :class="[
                            'rounded-lg px-3 py-2 text-sm transition-colors',
                            link.active
                                ? 'bg-primary-600 text-white'
                                : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                        ]"
                        v-html="link.label"
                        preserve-scroll
                    />
                    <span
                        v-else
                        class="cursor-not-allowed rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showEndModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                    <div class="fixed inset-0 bg-black/50" @click="closeEndModal"></div>
                    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold text-slate-900">Close contract and leave review</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Your rating and review will appear on the provider profile for other users.
                        </p>

                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Star rating</label>
                                <div class="mt-2 flex items-center gap-2">
                                    <button
                                        v-for="star in 5"
                                        :key="star"
                                        type="button"
                                        class="rounded-md p-1 transition hover:bg-slate-100"
                                        @click="endForm.review_rating = star"
                                    >
                                        <StarSolid
                                            class="h-7 w-7"
                                            :class="star <= endForm.review_rating ? 'text-yellow-400' : 'text-slate-300'"
                                        />
                                    </button>
                                </div>
                                <p v-if="endForm.errors.review_rating" class="mt-1 text-sm text-rose-600">{{ endForm.errors.review_rating }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Review description</label>
                                <textarea
                                    v-model="endForm.review_comment"
                                    rows="4"
                                    minlength="10"
                                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-sky-300 focus:ring-2 focus:ring-sky-100"
                                    placeholder="Describe your experience with this provider..."
                                />
                                <p v-if="endForm.errors.review_comment" class="mt-1 text-sm text-rose-600">{{ endForm.errors.review_comment }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Reason for ending contract</label>
                                <select
                                    v-model="endForm.reason"
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
                                <p v-if="endForm.errors.reason" class="mt-1 text-sm text-rose-600">{{ endForm.errors.reason }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Optional details</label>
                                <textarea
                                    v-model="endForm.reason_details"
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
                                @click="closeEndModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-60"
                                :disabled="endForm.processing || !endForm.reason"
                                @click="submitEndContract"
                            >
                                {{ endForm.processing ? 'Submitting...' : 'Close and submit review' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

