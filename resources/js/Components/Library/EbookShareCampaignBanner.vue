<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed } from 'vue';
import { ChevronRightIcon, GiftIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    campaignHref: { type: String, default: '' },
});

const page = usePage();

const shareCampaignHref = computed(() => (
    props.campaignHref || route('library.share')
));

const campaign = computed(() => page.props.ebook_share_campaign ?? null);

const visible = computed(() => {
    const data = campaign.value;
    if (!data?.eligible) {
        return false;
    }

    return data.can_start || (data.rewarded && data.coupon_code);
});

const title = computed(() => {
    if (campaign.value?.rewarded && campaign.value?.coupon_code) {
        return 'Your free ebook coupon is ready';
    }

    return 'Share 5 ebooks, get 1 free';
});

const subtitle = computed(() => {
    const data = campaign.value;
    if (!data) {
        return '';
    }

    if (data.rewarded && data.coupon_code) {
        return `Use code ${data.coupon_code} on any paid ebook.`;
    }

    return `${data.confirmed_shares || 0} of ${data.required_shares || 5} shares complete — share on Facebook or X from any ebook page.`;
});

const linkLabel = computed(() => (
    campaign.value?.rewarded ? 'View coupon & next steps' : 'Open share campaign'
));

const progressPercent = computed(() => {
    const data = campaign.value;
    const required = Number(data?.required_shares || 5);
    const confirmed = Number(data?.confirmed_shares || 0);
    if (required <= 0) {
        return 0;
    }
    return Math.min(100, Math.round((confirmed / required) * 100));
});
</script>

<template>
    <Link
        v-if="visible"
        :href="shareCampaignHref"
        class="group relative mb-6 block overflow-hidden rounded-[24px] border border-emerald-200/80 bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-700 p-[1px] shadow-lg shadow-emerald-900/10 transition hover:-translate-y-0.5 hover:shadow-xl"
    >
        <div class="relative overflow-hidden rounded-[23px] bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 px-4 py-4 sm:px-5">
            <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />

            <div class="relative flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/20 backdrop-blur-sm">
                    <GiftIcon class="h-6 w-6" />
                </div>

                <div class="min-w-0 flex-1 text-white">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-50/90">
                        <SparklesIcon class="h-3.5 w-3.5" />
                        Share &amp; Earn
                    </div>
                    <p class="mt-1 text-sm font-semibold sm:text-base">{{ title }}</p>
                    <p class="mt-1 text-sm text-emerald-50/85">{{ subtitle }}</p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/15">
                        <div
                            class="h-full rounded-full bg-white transition-all duration-500"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                </div>

                <div class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-white sm:flex">
                    {{ linkLabel }}
                    <ChevronRightIcon class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </div>
            </div>
        </div>
    </Link>
</template>
