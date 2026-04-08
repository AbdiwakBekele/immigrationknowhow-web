<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import AuthFlowProgress from '@/Components/auth/AuthFlowProgress.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import { ArrowLeftIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    address: { type: String, default: '' },
    city: { type: String, default: '' },
    country: { type: String, default: 'US' },
    postal_code: { type: String, default: '' },
    preferred_language: { type: String, default: 'en' },
    countryOptions: { type: Array, required: true },
    languageOptions: { type: Array, required: true },
});

const phoneForm = useForm({
    serve_client_in_location: false,
    address: props.address,
    city: props.city,
    country: props.country,
    postal_code: props.postal_code,
    preferred_language: props.preferred_language,
});

const submitAddress = () => {
    phoneForm.post(route('address-detail.send'), {
        preserveScroll: true,
    });
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(route('register'));
};
</script>

<template>
    <Head title="Address details" />

    <GuestLayout>
        <template #title>Address details</template>
        <template #subtitle />
        <template #progress>
            <AuthFlowProgress :current-step="2" :total-steps="5" />
        </template>
        <template #side-image>
            <img
                src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1400&q=80"
                alt="Planning and paperwork"
                class="h-full w-full object-cover"
            />
        </template>

        <form class="space-y-6" @submit.prevent="submitAddress">
            <div class="space-y-2">
                <p class="text-xs font-medium text-neutral-600">Address information</p>
                <label class="mt-1 flex cursor-pointer items-center gap-2">
                    <input
                        v-model="phoneForm.serve_client_in_location"
                        type="checkbox"
                        class="h-4 w-4 rounded border-neutral-300 text-primary-600"
                    />
                    <span class="text-sm text-neutral-700">I serve the client in their location</span>
                </label>
                <Input
                    v-model="phoneForm.address"
                    label="Street address"
                    placeholder="Street, apt / unit"
                    :error="phoneForm.errors.address"
                    size="compact"
                    autocomplete="street-address"
                />
                <div class="grid grid-cols-2 gap-2">
                    <Input
                        v-model="phoneForm.city"
                        label="City"
                        placeholder="City"
                        :error="phoneForm.errors.city"
                        size="compact"
                        required
                        autocomplete="address-level2"
                    />
                    <Input
                        v-model="phoneForm.postal_code"
                        label="Postal code"
                        placeholder="ZIP / postal"
                        :error="phoneForm.errors.postal_code"
                        size="compact"
                        required
                        autocomplete="postal-code"
                    />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <Select
                        v-model="phoneForm.country"
                        :options="countryOptions"
                        label="Country"
                        placeholder="Country"
                        :error="phoneForm.errors.country"
                        size="compact"
                        required
                    />
                    <Select
                        v-model="phoneForm.preferred_language"
                        :options="languageOptions"
                        label="Language"
                        placeholder="Select language"
                        :error="phoneForm.errors.preferred_language"
                        size="compact"
                        required
                    />
                </div>
            </div>

            <div class="flex items-center justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="!rounded-md border border-neutral-200 bg-white !px-2.5 !py-1.5 !text-xs text-neutral-700 hover:bg-neutral-50"
                    @click="goBack"
                >
                    <ArrowLeftIcon class="h-3.5 w-3.5" />
                    Back
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    size="sm"
                    :loading="phoneForm.processing"
                    class="min-w-[10rem] !py-1.5 !text-xs !rounded-md"
                >
                    Continue
                </Button>
            </div>
        </form>

        <template #footer />
    </GuestLayout>
</template>
