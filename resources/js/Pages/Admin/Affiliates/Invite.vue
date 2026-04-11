<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({ commissionTypes: Array });

const form = useForm({
    name: '',
    email: '',
    phone: '',
    notes: '',
    commission_type_override: '',
    commission_value_override: '',
});

const submit = () => form.post(route('admin.affiliates.invite.store'));
</script>

<template>
    <Head title="Invite Affiliate" />

    <AdminLayout>
        <div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-semibold text-slate-900">Invite affiliate</h1>
            <form class="mt-5 space-y-3" @submit.prevent="submit">
                <Input v-model="form.name" label="Name" :error="form.errors.name" required />
                <Input v-model="form.email" type="email" label="Email" :error="form.errors.email" required />
                <Input v-model="form.phone" label="Phone" :error="form.errors.phone" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Select v-model="form.commission_type_override" :options="commissionTypes" label="Commission override type" :error="form.errors.commission_type_override" />
                    <Input v-model="form.commission_value_override" label="Commission override value" :error="form.errors.commission_value_override" />
                </div>
                <Input v-model="form.notes" label="Notes" :error="form.errors.notes" />
                <Button type="submit" :loading="form.processing">Send invite</Button>
            </form>
        </div>
    </AdminLayout>
</template>
