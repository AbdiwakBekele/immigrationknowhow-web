<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import EbookShareCampaignPanel from '@/Components/Library/EbookShareCampaignPanel.vue';

defineProps({
    campaign: { type: Object, required: true },
});

const providerSteps = [
    'Open any ebook in your provider library.',
    'Tap Share for free book on Facebook or X.',
    'Complete five different ebook shares to unlock your coupon.',
];
</script>

<template>
    <ProviderLayout>
        <Head title="Share ebooks, earn a free book" />

        <EbookShareCampaignPanel
            :campaign="campaign"
            :back-href="route('provider.library.index')"
            back-label="Back to My Library"
            :browse-href="route('provider.library.index')"
            browse-label="Browse library"
            redeem-hint="Redeem it on any paid ebook in your provider library."
            :how-to-steps="providerSteps"
        />
    </ProviderLayout>
</template>
