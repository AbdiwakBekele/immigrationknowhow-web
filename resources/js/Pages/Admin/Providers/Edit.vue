<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    provider: { type: Object, required: true },
    serviceTypes: { type: Array, default: () => [] },
});

const form = useForm({
    business_name: props.provider.business_name || '',
    primary_service_type: props.provider.service_types?.[0] || props.serviceTypes[0]?.value || '',
    bio: props.provider.bio || '',
    is_active: !!props.provider.is_active,
    is_featured: !!props.provider.is_featured,
    is_verified: !!props.provider.is_verified,
});

const submit = () => {
    form.put(`/admin/providers/${props.provider.slug}`);
};
</script>

<template>
    <Head :title="`Edit Provider · ${provider.business_name}`" />

    <AdminLayout>
        <div class="mx-auto max-w-3xl space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex items-center gap-2">
                    <Link
                        :href="`/admin/providers/${provider.slug}`"
                        class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">Edit Provider</h1>
                        <p class="text-xs text-slate-500">Update provider profile and account flags</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Business Name</label>
                        <input v-model="form.business_name" type="text" class="input w-full" />
                        <p v-if="form.errors.business_name" class="mt-1 text-xs text-red-600">{{ form.errors.business_name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Service Type</label>
                        <select v-model="form.primary_service_type" class="input w-full">
                            <option v-for="type in serviceTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.primary_service_type" class="mt-1 text-xs text-red-600">{{ form.errors.primary_service_type }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Bio</label>
                        <textarea v-model="form.bio" class="input w-full min-h-28" />
                        <p v-if="form.errors.bio" class="mt-1 text-xs text-red-600">{{ form.errors.bio }}</p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3">
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Active</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Featured</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.is_verified" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Verified</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-sky-700 disabled:opacity-60"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                        <Link
                            :href="`/admin/providers/${provider.slug}`"
                            class="inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
