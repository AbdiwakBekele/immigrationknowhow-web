<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import LibraryCartPanel from '@/Components/Library/LibraryCartPanel.vue';
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    currency: { type: String, default: 'USD' },
    stripeConfigured: { type: Boolean, default: false },
    manualPaymentsAvailable: { type: Boolean, default: false },
});

const manualPayHref = computed(() => {
    if (props.items.length !== 1) return '';
    return route('library.pay', props.items[0].slug);
});

const itemHref = (slug) => route('library.show', slug);
</script>

<template>
    <Head title="Cart" />

    <AppLayout>
        <LibraryCartPanel
            :items="items"
            :total="total"
            :currency="currency"
            :stripe-configured="stripeConfigured"
            :manual-payments-available="manualPaymentsAvailable"
            :manual-pay-href="manualPayHref"
            :item-href="itemHref"
            :continue-href="route('library.index')"
            continue-label="Continue shopping"
            :browse-href="route('library.index')"
            browse-label="Browse library"
            subtitle="eBooks and audiobooks — pay once, read or listen in your library."
        />
    </AppLayout>
</template>
