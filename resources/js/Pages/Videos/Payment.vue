<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronRightIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import { loadStripe } from '@stripe/stripe-js';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    checkoutClientSecret: { type: String, required: true },
    stripePublishableKey: { type: String, required: true },
});

const checkoutMountEl = ref(null);
const loadError = ref('');
const isMounting = ref(true);
let embeddedCheckout = null;

onMounted(async () => {
    loadError.value = '';
    isMounting.value = true;

    try {
        const stripe = await loadStripe(props.stripePublishableKey);
        if (!stripe) {
            loadError.value = 'Unable to load Stripe. Check your connection and try again.';
            return;
        }

        embeddedCheckout = await stripe.initEmbeddedCheckout({
            clientSecret: props.checkoutClientSecret,
        });

        if (!checkoutMountEl.value) {
            loadError.value = 'Payment container is not ready.';
            return;
        }

        embeddedCheckout.mount(checkoutMountEl.value);
    } catch (e) {
        loadError.value = e?.message || 'Could not start the payment form. Please try again.';
    } finally {
        isMounting.value = false;
    }
});

onBeforeUnmount(() => {
    if (embeddedCheckout && typeof embeddedCheckout.destroy === 'function') {
        embeddedCheckout.destroy();
        embeddedCheckout = null;
    }
});
</script>

<template>
    <Head :title="`Pay - ${item.title}`" />

    <AppLayout>
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link href="/videos" class="hover:text-gray-700">Videos</Link>
                <ChevronRightIcon class="h-4 w-4 shrink-0" />
                <Link :href="route('videos.show', item.slug)" class="hover:text-gray-700 truncate">
                    {{ item.title }}
                </Link>
                <ChevronRightIcon class="h-4 w-4 shrink-0" />
                <span class="text-gray-900">Pay</span>
            </nav>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 text-white">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-white/15 p-2">
                            <LockClosedIcon class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-white/80">Secure checkout</p>
                            <h1 class="mt-1 text-lg font-display font-bold leading-snug truncate">{{ item.title }}</h1>
                            <p class="mt-2 text-sm text-white/90">{{ item.currency }} {{ item.price }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div v-if="loadError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ loadError }}
                    </div>

                    <div v-else class="relative min-h-[420px] rounded-xl border border-gray-100 bg-white">
                        <div
                            v-if="isMounting"
                            class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/90"
                        >
                            <p class="text-sm font-medium text-gray-600">Loading payment form...</p>
                        </div>
                        <div id="videos-embedded-checkout" ref="checkoutMountEl" class="min-h-[420px] p-1 sm:p-2" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

