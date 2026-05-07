<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { DocumentTextIcon, ShoppingCartIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    currency: { type: String, default: 'USD' },
    stripeConfigured: { type: Boolean, default: false },
    manualPaymentsAvailable: { type: Boolean, default: false },
});

const page = usePage();

const itemFormat = (item) => {
    if (item?.has_audio_companion) return 'PDF + Audio';
    if (item?.type === 'audiobook') return 'Audiobook';
    return 'eBook';
};

const formatPrice = (item) => {
    const amount = Number(item?.price || 0);
    if (!amount) return 'Free';
    return `${item.currency ?? props.currency} ${amount.toFixed(2)}`;
};

const singleItemManualPay = computed(
    () => props.items.length === 1 && !props.stripeConfigured && props.manualPaymentsAvailable,
);

const checkoutBlockedMessage = computed(() => {
    if (props.items.length === 0) return null;
    if (props.stripeConfigured) return null;
    if (singleItemManualPay.value) return null;
    return 'Card checkout is not available for multiple titles at once. Remove items until only one remains, or try again when online checkout is enabled.';
});

const checkoutData = { cart_portal: 'provider' };
</script>

<template>
    <Head title="Library cart" />

    <ProviderLayout>
        <div class="mx-auto max-w-5xl pb-10 text-neutral-950">
            <div class="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-neutral-950 sm:text-3xl">
                        Your cart
                    </h1>
                    <p class="mt-1 text-sm text-neutral-600">
                        Same cart as the member library — checkout once, titles appear in My Library below.
                    </p>
                </div>
                <Link
                    :href="route('provider.library.index')"
                    class="text-sm font-semibold text-blue-700 hover:text-blue-800"
                >
                    Continue browsing
                </Link>
            </div>

            <div
                v-if="page.props.flash?.success || page.props.flash?.info || page.props.flash?.error"
                class="mb-6 rounded-lg border px-4 py-3 text-sm"
                :class="page.props.flash?.error ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-800'"
            >
                {{ page.props.flash?.error || page.props.flash?.success || page.props.flash?.info }}
            </div>

            <div v-if="items.length" class="grid gap-8 lg:grid-cols-[1fr_20rem]">
                <ul class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <li
                        v-for="item in items"
                        :key="item.uuid || item.id"
                        class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 gap-4">
                            <Link
                                :href="route('provider.library.show', item.slug)"
                                class="relative h-24 w-20 shrink-0 overflow-hidden rounded-md bg-neutral-100"
                            >
                                <img
                                    v-if="item.cover_image_url"
                                    :src="item.cover_image_url"
                                    :alt="item.title"
                                    class="h-full w-full object-cover object-top"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center">
                                    <DocumentTextIcon class="h-10 w-10 text-neutral-300" />
                                </div>
                            </Link>
                            <div class="min-w-0">
                                <Link
                                    :href="route('provider.library.show', item.slug)"
                                    class="font-semibold text-neutral-950 hover:text-blue-700"
                                >
                                    {{ item.title }}
                                </Link>
                                <p class="mt-0.5 text-sm text-neutral-500">
                                    {{ itemFormat(item) }}
                                </p>
                                <p class="mt-2 text-sm font-semibold text-neutral-900">
                                    {{ formatPrice(item) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3 sm:flex-col sm:items-end">
                            <Link
                                :href="route('library.cart.remove', { item: item.slug })"
                                method="delete"
                                as="button"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50"
                            >
                                <TrashIcon class="h-4 w-4" />
                                Remove
                            </Link>
                        </div>
                    </li>
                </ul>

                <aside class="h-fit rounded-xl border border-neutral-200 bg-neutral-50 p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">
                        Order summary
                    </h2>
                    <div class="mt-4 flex items-end justify-between gap-4">
                        <span class="text-sm text-neutral-600">Total</span>
                        <span class="text-2xl font-bold tabular-nums text-neutral-950">
                            {{ currency }} {{ total.toFixed(2) }}
                        </span>
                    </div>

                    <p v-if="checkoutBlockedMessage" class="mt-4 text-sm leading-relaxed text-amber-800">
                        {{ checkoutBlockedMessage }}
                    </p>

                    <Link
                        v-if="stripeConfigured && items.length"
                        :href="route('library.cart.checkout')"
                        method="post"
                        as="button"
                        type="button"
                        :data="checkoutData"
                        class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        <ShoppingCartIcon class="h-4 w-4" />
                        Proceed to checkout
                    </Link>

                    <Link
                        v-else-if="singleItemManualPay"
                        :href="route('library.pay', { item: items[0].slug, portal: 'provider' })"
                        class="mt-5 flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        Continue to payment
                    </Link>

                    <p v-if="stripeConfigured" class="mt-4 text-xs leading-relaxed text-neutral-500">
                        Secure checkout with Stripe. Apple Pay, Google Pay, and cards appear when supported.
                    </p>
                </aside>
            </div>

            <div v-else class="rounded-xl border border-neutral-200 bg-white px-6 py-14 text-center shadow-sm">
                <ShoppingCartIcon class="mx-auto h-12 w-12 text-neutral-300" />
                <h2 class="mt-4 text-lg font-semibold text-neutral-950">Your cart is empty</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-600">
                    Add paid titles from the library catalog, then check out here.
                </p>
                <Link
                    :href="route('provider.library.index')"
                    class="mt-6 inline-flex rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                >
                    Browse library
                </Link>
            </div>
        </div>
    </ProviderLayout>
</template>
