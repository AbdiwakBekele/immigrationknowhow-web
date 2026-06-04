<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { ExclamationTriangleIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const roleAccounts = page.props.auth?.role_accounts ?? null;

const modalOpen = ref(false);

const deleteForm = useForm({
    password: '',
    confirmation: '',
});

const hasMultipleRoles = () => Boolean(roleAccounts?.can_switch || (roleAccounts?.has_seeker && roleAccounts?.has_provider));

const openModal = () => {
    deleteForm.reset();
    deleteForm.clearErrors();
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
    deleteForm.reset();
    deleteForm.clearErrors();
};

const deleteAccount = () => {
    deleteForm.delete(route('account.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <section class="w-full rounded-lg border border-red-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="rounded-lg border border-red-100 bg-red-50 p-2 text-red-700">
                <ExclamationTriangleIcon class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold text-slate-950">Delete account</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Permanently removes your login and profile access. This cannot be undone.
                </p>
            </div>
        </div>
        <button
            type="button"
            class="mt-5 w-full rounded-lg border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 transition hover:bg-red-50 sm:w-auto"
            @click="openModal"
        >
            Delete account
        </button>
    </section>

    <TransitionRoot as="template" :show="modalOpen">
        <Dialog as="div" class="relative z-50" @close="closeModal">
            <TransitionChild
                as="template"
                enter="ease-out duration-200"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-150"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-slate-900/60" aria-hidden="true" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6">
                <div class="flex min-h-full items-center justify-center">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
                            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                                <div class="flex items-start gap-3">
                                    <div class="rounded-xl border border-red-100 bg-red-50 p-2.5 text-red-700">
                                        <ExclamationTriangleIcon class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <DialogTitle class="text-lg font-semibold text-slate-950">
                                            Delete your account?
                                        </DialogTitle>
                                        <p class="mt-1 text-sm text-slate-600">
                                            Confirm below to permanently delete your account.
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100"
                                    :disabled="deleteForm.processing"
                                    @click="closeModal"
                                >
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>

                            <form class="space-y-5 px-6 py-6" @submit.prevent="deleteAccount">
                                <div class="rounded-xl border border-red-100 bg-red-50/80 px-4 py-3 text-sm text-red-900">
                                    <p class="font-semibold">You will lose access to:</p>
                                    <ul class="mt-2 list-inside list-disc space-y-1 text-red-800">
                                        <li>Your profile and saved preferences</li>
                                        <li>Messages and inquiries</li>
                                        <li>Purchases and subscriptions tied to this email</li>
                                        <li v-if="hasMultipleRoles()">Both service seeker and provider data on this login</li>
                                    </ul>
                                </div>

                                <Input
                                    v-model="deleteForm.password"
                                    type="password"
                                    label="Current password"
                                    autocomplete="current-password"
                                    :error="deleteForm.errors.password"
                                />
                                <Input
                                    v-model="deleteForm.confirmation"
                                    label='Type DELETE to confirm'
                                    placeholder="DELETE"
                                    :error="deleteForm.errors.confirmation"
                                />

                                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        class="w-full sm:w-auto"
                                        :disabled="deleteForm.processing"
                                        @click="closeModal"
                                    >
                                        Cancel
                                    </Button>
                                    <button
                                        type="submit"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-red-600 bg-red-600 px-5 py-3.5 text-base font-semibold text-white shadow-sm transition hover:border-red-700 hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-100 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                                        :disabled="deleteForm.processing || deleteForm.confirmation !== 'DELETE' || !deleteForm.password"
                                    >
                                        <svg
                                            v-if="deleteForm.processing"
                                            class="h-5 w-5 animate-spin"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                        </svg>
                                        <TrashIcon v-else class="h-4 w-4" />
                                        Permanently delete account
                                    </button>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
