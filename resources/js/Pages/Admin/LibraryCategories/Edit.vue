<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    category: { type: Object, required: true },
});

const form = useForm({
    name: props.category.name ?? '',
    description: props.category.description ?? '',
    icon: props.category.icon ?? '',
    sort_order: props.category.sort_order ?? 0,
    is_active: Boolean(props.category.is_active),
});

const submit = () => {
    form.patch(route('admin.library-categories.update', props.category.slug));
};
</script>

<template>
    <Head title="Edit Library Category" />

    <AdminLayout>
        <div class="mx-auto max-w-2xl space-y-4">
            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <Link
                    :href="route('admin.library-categories.index')"
                    class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                >
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">Edit library category</h1>
                    <p class="text-xs text-slate-500">
                        Update category details and ordering.
                    </p>
                </div>
            </div>

            <form class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Category name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        required
                    >
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description (optional)</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Icon (optional)</label>
                        <input
                            v-model="form.icon"
                            type="text"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                            placeholder="Emoji or short icon token"
                        >
                        <p v-if="form.errors.icon" class="mt-1 text-xs text-red-600">{{ form.errors.icon }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Sort order</label>
                        <input
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        >
                        <p v-if="form.errors.sort_order" class="mt-1 text-xs text-red-600">{{ form.errors.sort_order }}</p>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300">
                    Active category
                </label>
                <p v-if="form.errors.is_active" class="mt-1 text-xs text-red-600">{{ form.errors.is_active }}</p>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Linked library items: {{ category.items_count }}
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link
                        :href="route('admin.library-categories.index')"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Update category' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
