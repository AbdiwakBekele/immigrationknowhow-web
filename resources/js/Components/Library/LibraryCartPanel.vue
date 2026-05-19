<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    ArrowLeftIcon,
    DocumentTextIcon,
    LockClosedIcon,
    ShoppingCartIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    currency: { type: String, default: 'USD' },
    stripeConfigured: { type: Boolean, default: false },
    manualPaymentsAvailable: { type: Boolean, default: false },
    subtitle: { type: String, required: true },
    emptyDescription: {
        type: String,
        default: 'Browse eBooks and audiobooks, then add paid titles here before checkout.',
    },
    continueHref: { type: String, required: true },
    continueLabel: { type: String, default: 'Continue shopping' },
    browseHref: { type: String, required: true },
    browseLabel: { type: String, default: 'Browse library' },
    itemHref: { type: Function, required: true },
    checkoutData: { type: Object, default: () => ({}) },
    manualPayHref: { type: String, default: '' },
});

const page = usePage();

const itemCount = computed(() => props.items.length);

const checkoutBtnClass =
    'flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary-500/25 transition hover:from-primary-700 hover:to-primary-800';

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

const flashMessage = computed(() => {
    const flash = page.props.flash;
    if (!flash) return null;
    if (flash.error) return { type: 'error', text: flash.error };
    if (flash.success) return { type: 'success', text: flash.success };
    if (flash.info) return { type: 'info', text: flash.info };
    return null;
});
</script>

<template>
    <div class="w-full max-w-none space-y-6">
        <!-- Hero -->
        <section
            class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-primary-700 via-primary-600 to-indigo-700 px-6 py-6 text-white shadow-elevated-lg sm:px-8 sm:py-7"
        >
            <div class="pointer-events-none absolute -left-16 -top-16 h-48 w-48 rounded-full bg-white/10 blur-3xl" />
            <div class="pointer-events-none absolute -right-10 bottom-0 h-40 w-40 rounded-full bg-teal-400/20 blur-3xl" />
            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary-100">
                        Library checkout
                    </p>
                    <h1 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">
                        Your cart
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-primary-50/90 sm:text-base">
                        {{ subtitle }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="rounded-2xl border border-white/20 bg-white/10 px-5 py-3 backdrop-blur-sm">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-primary-100">Items</p>
                        <p class="mt-0.5 text-2xl font-bold tabular-nums">{{ itemCount }}</p>
                    </div>
                    <Link
                        :href="continueHref"
                        class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/20"
                    >
                        <ArrowLeftIcon class="h-4 w-4 shrink-0" />
                        {{ continueLabel }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- Flash -->
        <div
            v-if="flashMessage"
            class="rounded-2xl border px-5 py-4 text-sm font-medium sm:px-6"
            :class="
                flashMessage.type === 'error'
                    ? 'border-red-200 bg-red-50 text-red-700'
                    : flashMessage.type === 'success'
                      ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                      : 'border-primary-200 bg-primary-50 text-primary-800'
            "
            role="status"
        >
            {{ flashMessage.text }}
        </div>

        <!-- Cart content -->
        <div
            v-if="items.length"
            class="grid w-full gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(17rem,22rem)] xl:gap-8"
        >
            <!-- Items -->
            <ul class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-elevated">
                <li
                    v-for="(item, index) in items"
                    :key="item.uuid || item.id"
                    class="grid gap-4 px-5 py-5 transition-colors hover:bg-slate-50/60 sm:grid-cols-[5.5rem_minmax(0,1fr)_auto] sm:items-start sm:gap-x-5 sm:px-6 sm:py-6"
                    :class="index > 0 ? 'border-t border-slate-100' : ''"
                >
                    <Link
                        :href="itemHref(item.slug)"
                        class="relative h-28 w-[5.5rem] shrink-0 overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200/80 sm:h-32"
                    >
                        <img
                            v-if="item.cover_image_url"
                            :src="item.cover_image_url"
                            :alt="item.title"
                            class="h-full w-full object-cover object-top"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <DocumentTextIcon class="h-9 w-9 text-slate-300" />
                        </div>
                    </Link>

                    <div class="min-w-0 sm:col-start-2">
                        <Link
                            :href="itemHref(item.slug)"
                            class="block break-words text-base font-semibold leading-snug text-slate-900 hover:text-primary-700"
                        >
                            {{ item.title || 'Untitled' }}
                        </Link>
                        <span
                            class="mt-2 inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700"
                        >
                            {{ itemFormat(item) }}
                        </span>
                        <p class="mt-2 text-sm font-bold tabular-nums text-slate-900">
                            {{ formatPrice(item) }}
                        </p>
                    </div>

                    <Link
                        :href="route('library.cart.remove', { item: item.slug })"
                        method="delete"
                        as="button"
                        type="button"
                        class="inline-flex w-fit items-center gap-1.5 self-start rounded-xl border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 sm:col-start-3"
                    >
                        <TrashIcon class="h-4 w-4 shrink-0" />
                        Remove
                    </Link>
                </li>
            </ul>

            <!-- Summary -->
            <aside class="h-fit rounded-2xl border border-slate-200/80 bg-white shadow-elevated lg:sticky lg:top-24">
                <div class="space-y-5 px-5 py-5 sm:px-6 sm:py-6">
                    <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
                        Order summary
                    </h2>

                    <div class="space-y-3 border-b border-slate-100 pb-5">
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-4 text-sm">
                            <span class="leading-relaxed text-slate-600">
                                Subtotal ({{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }})
                            </span>
                            <span class="whitespace-nowrap text-right font-semibold tabular-nums text-slate-900">
                                {{ currency }} {{ total.toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-x-4">
                        <span class="text-sm font-semibold text-slate-700">Total</span>
                        <span class="whitespace-nowrap text-right font-display text-2xl font-bold tabular-nums text-slate-900">
                            {{ currency }} {{ total.toFixed(2) }}
                        </span>
                    </div>

                    <p
                        v-if="checkoutBlockedMessage"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900"
                    >
                        {{ checkoutBlockedMessage }}
                    </p>

                    <Link
                        v-if="stripeConfigured && items.length"
                        :href="route('library.cart.checkout')"
                        method="post"
                        as="button"
                        type="button"
                        :data="checkoutData"
                        :class="checkoutBtnClass"
                    >
                        <ShoppingCartIcon class="h-5 w-5 shrink-0" />
                        Proceed to checkout
                    </Link>

                    <Link
                        v-else-if="singleItemManualPay && manualPayHref"
                        :href="manualPayHref"
                        :class="checkoutBtnClass"
                    >
                        Continue to payment
                    </Link>

                    <p
                        v-if="stripeConfigured"
                        class="flex items-start gap-2.5 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500"
                    >
                        <LockClosedIcon class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <span>
                            Secure checkout with Stripe. Apple Pay, Google Pay, and cards when supported.
                        </span>
                    </p>
                </div>
            </aside>
        </div>

        <!-- Empty -->
        <div
            v-else
            class="rounded-2xl border border-slate-200/80 bg-white px-8 py-16 text-center shadow-elevated sm:py-20"
        >
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50">
                <ShoppingCartIcon class="h-8 w-8 text-primary-600" />
            </div>
            <h2 class="mt-5 font-display text-xl font-bold text-slate-900">
                Your cart is empty
            </h2>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500">
                {{ emptyDescription }}
            </p>
            <Link
                :href="browseHref"
                class="mt-8 inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-500/20 transition hover:from-primary-700 hover:to-primary-800"
            >
                {{ browseLabel }}
            </Link>
        </div>
    </div>
</template>
