<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    serviceType: { type: Object, required: true },
});

const form = useForm({
    label: props.serviceType.label || '',
    value: props.serviceType.value || '',
    icon: props.serviceType.icon || '',
    for_user: Boolean(props.serviceType.for_user),
    for_provider: Boolean(props.serviceType.for_provider),
    include_certificate: Boolean(props.serviceType.include_certificate),
    is_active: Boolean(props.serviceType.is_active),
    sort_order: props.serviceType.sort_order ?? 0,
});

const submit = () => {
    form.patch(route('admin.service-types.update', props.serviceType.id));
};

const sectionClass = 'rounded-xl border border-slate-200 bg-white p-4 shadow-sm';
const labelClass = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600';
const errorClass = 'mt-1 text-xs text-red-600';
</script>

<template>
    <Head title="Edit Service Type" />

    <AdminLayout>
        <div class="mx-auto max-w-2xl space-y-4">
            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <Link
                    href="/admin/service-types"
                    class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                >
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">Edit service type</h1>
                    <p class="text-xs text-slate-500">Update label, visibility, sort order, and active status.</p>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Identity</h2>
                    <div class="space-y-3">
                        <div>
                            <label :class="labelClass">Label</label>
                            <input v-model="form.label" type="text" class="input w-full" maxlength="120" />
                            <p v-if="form.errors.label" :class="errorClass">{{ form.errors.label }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Value (stored key)</label>
                            <input v-model="form.value" type="text" class="input w-full font-mono text-sm" maxlength="120" />
                            <p v-if="form.errors.value" :class="errorClass">{{ form.errors.value }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Icon (optional)</label>
                            <input v-model="form.icon" type="text" class="input w-full" maxlength="120" />
                            <p v-if="form.errors.icon" :class="errorClass">{{ form.errors.icon }}</p>
                        </div>
                    </div>
                </div>

                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Audience &amp; display</h2>
                    <div class="space-y-3">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.for_user" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600" />
                            <span class="text-sm text-slate-700">Show to users</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.for_provider" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600" />
                            <span class="text-sm text-slate-700">Show to providers</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.include_certificate" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600" />
                            <span class="text-sm text-slate-700">Include certificate upload for providers</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600" />
                            <span class="text-sm text-slate-700">Active</span>
                        </label>
                        <p v-if="form.errors.audience" :class="errorClass">{{ form.errors.audience }}</p>

                        <div class="pt-2">
                            <label :class="labelClass">Sort order</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="input w-full max-w-[12rem]" />
                            <p v-if="form.errors.sort_order" :class="errorClass">{{ form.errors.sort_order }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving…' : 'Update service type' }}
                    </button>
                    <Link href="/admin/service-types" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
