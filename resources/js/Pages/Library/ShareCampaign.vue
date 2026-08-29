<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import EbookShareCampaignPanel from '@/Components/Library/EbookShareCampaignPanel.vue';

defineProps({
    campaign: { type: Object, required: true },
});
</script>

<template>
    <AppLayout>
        <Head title="Share ebooks, earn a free book" />

        <EbookShareCampaignPanel
            :campaign="campaign"
            :back-href="route('library.ebooks')"
            back-label="Back to ebooks"
            :browse-href="route('library.ebooks')"
            browse-label="Browse ebooks"
        />
    </AppLayout>
</template>
