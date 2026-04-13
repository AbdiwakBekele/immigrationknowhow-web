<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronRightIcon, BanknotesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    instructions: {
        type: String,
        required: true,
    },
    pending: {
        type: Boolean,
        default: false,
    },
    submitted: {
        type: Object,
        default: null,
    },
    formDefaults: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    manual_payment_reference: props.formDefaults?.manual_payment_reference ?? '',
    manual_payment_note: props.formDefaults?.manual_payment_note ?? '',
});

const submit = () => {
    form.post(route('library.manual-payment', props.item.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Pay — ${item.title}`" />

    <AppLayout>
        <div class="mx-auto max-w-xl px-4 py-8 sm:px-6 lg:px-8">
            <nav class="mb-6 flex items-center gap-2 text-sm text-gray-500">
                <Link href="/library" class="hover:text-gray-700">Library</Link>
                <ChevronRightIcon class="h-4 w-4 shrink-0" />
                <Link :href="route('library.show', item.slug)" class="truncate hover:text-gray-700">
                    {{ item.title }}
                </Link>
                <ChevronRightIcon class="h-4 w-4 shrink-0" />
                <span class="text-gray-900">Pay</span>
            </nav>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 text-white">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-white/15 p-2">
                            <BanknotesIcon class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-white/80">Manual payment</p>
                            <h1 class="mt-1 truncate font-display text-lg font-bold leading-snug">
                                {{ item.title }}
                            </h1>
                            <p class="mt-2 text-sm text-white/90">
                                {{ item.currency }} {{ item.price }} ·
                                {{ item.type === 'ebook' ? 'E-book' : item.type === 'video' ? 'Video' : 'Audiobook' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 p-6">
                    <div
                        v-if="pending && submitted"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                    >
                        <p class="font-semibold">Request received</p>
                        <p class="mt-1 text-amber-900/90">
                            We are verifying your payment. You will have access in your library once an administrator
                            approves it.
                        </p>
                        <dl v-if="submitted.manual_payment_requested_at" class="mt-3 space-y-1 text-xs text-amber-900/80">
                            <div class="flex gap-2">
                                <dt class="font-medium">Submitted</dt>
                                <dd>{{ new Date(submitted.manual_payment_requested_at).toLocaleString() }}</dd>
                            </div>
                            <div v-if="submitted.manual_payment_reference" class="flex gap-2">
                                <dt class="font-medium">Reference</dt>
                                <dd class="break-all">{{ submitted.manual_payment_reference }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">How to pay</h2>
                        <p
                            class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-600"
                        >
                            {{ instructions }}
                        </p>
                    </div>

                    <form v-if="!pending" class="space-y-4" @submit.prevent="submit">
                        <div>
                            <label for="manual_payment_reference" class="block text-sm font-medium text-gray-700">
                                Payment reference <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="manual_payment_reference"
                                v-model="form.manual_payment_reference"
                                type="text"
                                required
                                autocomplete="off"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                placeholder="Transaction ID, confirmation number, or short description"
                            />
                            <p v-if="form.errors.manual_payment_reference" class="mt-1 text-sm text-red-600">
                                {{ form.errors.manual_payment_reference }}
                            </p>
                        </div>
                        <div>
                            <label for="manual_payment_note" class="block text-sm font-medium text-gray-700">
                                Note (optional)
                            </label>
                            <textarea
                                id="manual_payment_note"
                                v-model="form.manual_payment_note"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                placeholder="Anything else we should know"
                            />
                            <p v-if="form.errors.manual_payment_note" class="mt-1 text-sm text-red-600">
                                {{ form.errors.manual_payment_note }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3 text-sm font-semibold text-white shadow-md transition hover:from-primary-500 hover:to-primary-600 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Sending…' : 'Submit payment details' }}
                        </button>
                    </form>

                    <form v-else class="space-y-4" @submit.prevent="submit">
                        <p class="text-sm text-gray-600">
                            Need to update your reference or note? Change the fields below and submit again.
                        </p>
                        <div>
                            <label for="manual_payment_reference_edit" class="block text-sm font-medium text-gray-700">
                                Payment reference <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="manual_payment_reference_edit"
                                v-model="form.manual_payment_reference"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div>
                            <label for="manual_payment_note_edit" class="block text-sm font-medium text-gray-700">
                                Note (optional)
                            </label>
                            <textarea
                                id="manual_payment_note_edit"
                                v-model="form.manual_payment_note"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <button
                            type="submit"
                            class="flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Updating…' : 'Update details' }}
                        </button>
                    </form>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50/80 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        :href="route('library.show', item.slug)"
                        class="text-center text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        ← Back to item
                    </Link>
                    <p class="text-center text-xs text-gray-500 sm:text-right">Paid outside this site — staff will confirm.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
