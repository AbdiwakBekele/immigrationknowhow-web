<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';

defineProps({
    roleAccounts: { type: Object, default: () => ({}) },
    backUrl: { type: String, required: true },
});

const form = useForm({
    number_of_children: '',
    children_ages_text: '',
    dogs_count: '',
});

const submit = () => {
    form.post(route('account-roles.seeker.store'));
};
</script>

<template>
    <Head title="Create service seeker account" />

    <AppLayout>
        <div class="bg-slate-100 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <Link :href="backUrl" class="text-sm font-medium text-sky-600 hover:text-sky-700">
                        Back to profile
                    </Link>

                    <h1 class="mt-4 text-2xl font-semibold text-slate-950">Create service seeker account</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Your name, contact details, and location are already on file. Add household details so providers can match your needs.
                    </p>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <Input
                            v-model="form.number_of_children"
                            label="Number of children"
                            type="number"
                            min="0"
                            placeholder="0"
                            :error="form.errors.number_of_children"
                        />
                        <Input
                            v-model="form.children_ages_text"
                            label="Children ages"
                            placeholder="e.g. 4, 9"
                            :error="form.errors.children_ages_text"
                        />
                        <Input
                            v-model="form.dogs_count"
                            label="Number of pets"
                            type="number"
                            min="0"
                            placeholder="0"
                            :error="form.errors.dogs_count"
                        />

                        <Button type="submit" :loading="form.processing">
                            Create seeker account
                        </Button>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
