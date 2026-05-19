<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import LibraryCartPanel from '@/Components/Library/LibraryCartPanel.vue';
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    currency: { type: String, default: 'USD' },
    stripeConfigured: { type: Boolean, default: false },
    manualPaymentsAvailable: { type: Boolean, default: false },
});

const checkoutData = { cart_portal: 'provider' };

const manualPayHref = computed(() => {
    if (props.items.length !== 1) return '';
    return route('library.pay', { item: props.items[0].slug, portal: 'provider' });
});

const itemHref = (slug) => route('provider.library.show', slug);
</script>

<template>
    <Head title="Library cart" />

    <ProviderLayout>
        <LibraryCartPanel
            :items="items"
            :total="total"
            :currency="currency"
            :stripe-configured="stripeConfigured"
            :manual-payments-available="manualPaymentsAvailable"
            :manual-pay-href="manualPayHref"
            :checkout-data="checkoutData"
            :item-href="itemHref"
            :continue-href="route('provider.library.index')"
            continue-label="Continue browsing"
            :browse-href="route('provider.library.index')"
            browse-label="Browse library"
            subtitle="Same cart as the member library — checkout once, titles appear in My Library."
            empty-description="Add paid titles from the library catalog, then check out here."
        />
    </ProviderLayout>
</template>
