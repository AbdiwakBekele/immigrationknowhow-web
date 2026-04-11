<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/layout/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    invite: Object,
    token: String,
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('affiliate.invites.store', { token: props.token }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Accept affiliate invitation" />

    <GuestLayout>
        <template #title>Accept your affiliate invitation</template>
        <template #subtitle>{{ invite.email }}</template>

        <form class="space-y-3" @submit.prevent="submit">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <p class="font-medium text-slate-900">{{ invite.name }}</p>
                <p class="mt-1">Set your password to activate the invitation. You can complete your affiliate profile on the next screen.</p>
            </div>
            <Input :model-value="invite.email" label="Invitation email" disabled />
            <div class="grid gap-3 sm:grid-cols-2">
                <Input v-model="form.password" type="password" label="Password" :error="form.errors.password" required />
                <Input v-model="form.password_confirmation" type="password" label="Confirm password" :error="form.errors.password_confirmation" required />
            </div>
            <Button type="submit" :loading="form.processing" class="w-full">Set password and continue</Button>
        </form>
    </GuestLayout>
</template>
