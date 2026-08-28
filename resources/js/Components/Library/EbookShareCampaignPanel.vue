<script setup>
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    BookOpenIcon,
    CheckCircleIcon,
    CheckIcon,
    ClipboardDocumentIcon,
    GiftIcon,
    ShareIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    campaign: { type: Object, required: true },
    backHref: { type: String, required: true },
    backLabel: { type: String, required: true },
    browseHref: { type: String, required: true },
    browseLabel: { type: String, default: 'Browse ebooks' },
    redeemHint: {
        type: String,
        default: 'Redeem it on any paid ebook in the library.',
    },
    howToSteps: {
        type: Array,
        default: () => [
            'Open any ebook detail page in the library.',
            'Tap Share for free book on Facebook or X.',
            'Complete five different ebook shares to unlock your coupon.',
        ],
    },
});

const campaignState = ref({ ...props.campaign });
const copied = ref(false);

watch(
    () => props.campaign,
    (value) => {
        campaignState.value = { ...value };
    },
    { deep: true },
);

const requiredShares = computed(() => Number(campaignState.value.required_shares || 5));
const confirmedShares = computed(() => Number(campaignState.value.confirmed_shares || 0));
const remainingShares = computed(() => Math.max(0, requiredShares.value - confirmedShares.value));

const progressPercent = computed(() => {
    if (requiredShares.value <= 0) {
        return 0;
    }
    return Math.min(100, Math.round((confirmedShares.value / requiredShares.value) * 100));
});

const canShareMore = computed(() => campaignState.value.can_start && !campaignState.value.rewarded);
const isRewarded = computed(() => Boolean(campaignState.value.rewarded && campaignState.value.coupon_code));

const milestones = computed(() => {
    return Array.from({ length: requiredShares.value }, (_, index) => ({
        number: index + 1,
        complete: index < confirmedShares.value,
    }));
});

const stepIcons = [BookOpenIcon, ShareIcon, GiftIcon];

function platformLabel(platform) {
    if (platform === 'facebook') {
        return 'Facebook';
    }
    if (platform === 'x') {
        return 'X';
    }
    return platform || 'Social';
}

function platformClass(platform) {
    if (platform === 'facebook') {
        return 'bg-blue-50 text-blue-700 ring-blue-100';
    }
    if (platform === 'x') {
        return 'bg-slate-100 text-slate-800 ring-slate-200';
    }
    return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
}

async function copyCoupon() {
    const code = campaignState.value.coupon_code;
    if (!code) {
        return;
    }

    try {
        if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(code);
            copied.value = true;
            setTimeout(() => {
                copied.value = false;
            }, 2000);
        }
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <Link
            :href="backHref"
            class="mb-6 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm font-medium text-slate-600 shadow-sm backdrop-blur transition hover:border-slate-300 hover:text-slate-900"
        >
            <ArrowLeftIcon class="h-4 w-4" />
            {{ backLabel }}
        </Link>

        <section class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 p-6 text-white shadow-xl shadow-emerald-900/20 sm:p-8">
            <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-20 left-10 h-48 w-48 rounded-full bg-cyan-300/20 blur-3xl" />

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-50">
                        <SparklesIcon class="h-4 w-4" />
                        Share &amp; Earn
                    </div>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">
                        Share 5 ebooks, get 1 free
                    </h1>
                    <p class="mt-3 max-w-xl text-base leading-relaxed text-emerald-50/90">
                        Share five different ebooks on Facebook or X. When you finish, we email you a coupon for one free paid ebook.
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-4 rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur-sm">
                    <div class="relative flex h-20 w-20 items-center justify-center">
                        <svg class="h-20 w-20 -rotate-90" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="6" />
                            <circle
                                cx="40"
                                cy="40"
                                r="34"
                                fill="none"
                                stroke="white"
                                stroke-width="6"
                                stroke-linecap="round"
                                :stroke-dasharray="`${2 * Math.PI * 34}`"
                                :stroke-dashoffset="`${2 * Math.PI * 34 * (1 - progressPercent / 100)}`"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xl font-bold">{{ confirmedShares }}</span>
                            <span class="text-[10px] uppercase tracking-wide text-emerald-50/80">of {{ requiredShares }}</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-emerald-50/80">Campaign progress</p>
                        <p class="text-2xl font-bold">{{ progressPercent }}%</p>
                        <p class="mt-1 text-sm text-emerald-50/80">
                            {{ remainingShares }} share{{ remainingShares === 1 ? '' : 's' }} left
                        </p>
                    </div>
                </div>
            </div>

            <div class="relative mt-8 flex flex-wrap gap-2">
                <span
                    v-for="milestone in milestones"
                    :key="milestone.number"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold transition"
                    :class="milestone.complete
                        ? 'bg-white text-emerald-700 shadow-lg shadow-emerald-950/10'
                        : 'bg-white/10 text-white ring-1 ring-white/20'"
                >
                    <CheckIcon v-if="milestone.complete" class="h-5 w-5" />
                    <span v-else>{{ milestone.number }}</span>
                </span>
            </div>
        </section>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Confirmed</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ confirmedShares }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Remaining</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ remainingShares }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Goal</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ requiredShares }} shares</p>
            </div>
        </div>

        <section
            v-if="isRewarded"
            class="mt-6 overflow-hidden rounded-[24px] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-6 shadow-sm"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20">
                        <GiftIcon class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Reward unlocked</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">Your free ebook coupon</p>
                        <p class="mt-2 text-sm text-slate-600">{{ redeemHint }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:items-end">
                    <div class="rounded-2xl border border-dashed border-emerald-300 bg-white px-5 py-3 font-mono text-lg font-bold tracking-wider text-emerald-900">
                        {{ campaignState.coupon_code }}
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                        @click="copyCoupon"
                    >
                        <ClipboardDocumentIcon v-if="!copied" class="h-4 w-4" />
                        <CheckCircleIcon v-else class="h-4 w-4" />
                        {{ copied ? 'Copied!' : 'Copy coupon code' }}
                    </button>
                </div>
            </div>
        </section>

        <section v-if="campaignState.events?.length" class="mt-8">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Your shared ebooks</h2>
                    <p class="mt-1 text-sm text-slate-500">Track each share and its confirmation status.</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <article
                    v-for="event in campaignState.events"
                    :key="event.id"
                    class="group relative overflow-hidden rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md"
                >
                    <div class="flex gap-4">
                        <div class="relative shrink-0 overflow-hidden rounded-xl bg-slate-100 shadow-inner">
                            <img
                                v-if="event.cover_image_url"
                                :src="event.cover_image_url"
                                :alt="event.title"
                                class="h-24 w-[72px] object-cover transition duration-300 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex h-24 w-[72px] items-center justify-center text-slate-400"
                            >
                                <BookOpenIcon class="h-8 w-8" />
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 font-semibold text-slate-900">{{ event.title }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="event.status === 'confirmed'
                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
                                        : 'bg-amber-50 text-amber-700 ring-amber-100'"
                                >
                                    {{ event.status === 'confirmed' ? 'Confirmed' : 'Pending' }}
                                </span>
                                <span
                                    v-if="event.platform"
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="platformClass(event.platform)"
                                >
                                    {{ platformLabel(event.platform) }}
                                </span>
                            </div>
                        </div>

                        <CheckCircleIcon
                            v-if="event.status === 'confirmed'"
                            class="h-6 w-6 shrink-0 text-emerald-600"
                        />
                    </div>
                </article>
            </div>
        </section>

        <section
            v-if="canShareMore"
            class="mt-8 overflow-hidden rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
        >
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-white">
                    <ShareIcon class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">How to share</h2>
                    <p class="text-sm text-slate-500">Three quick steps to earn your free ebook.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-3">
                <div
                    v-for="(step, index) in howToSteps"
                    :key="index"
                    class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-900 shadow-sm">
                        <component :is="stepIcons[index] || ShareIcon" class="h-5 w-5" />
                    </div>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Step {{ index + 1 }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ step }}</p>
                </div>
            </div>

            <Link
                :href="browseHref"
                class="mt-6 inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                {{ browseLabel }}
            </Link>
        </section>

        <section
            v-else-if="!campaignState.eligible"
            class="mt-8 rounded-[24px] border border-slate-200 bg-slate-50 p-6 text-center"
        >
            <p class="text-sm text-slate-600">Your account is not eligible for this campaign.</p>
        </section>
    </div>
</template>
