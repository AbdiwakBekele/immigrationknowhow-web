<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const form = useForm({
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const submit = () => {
    form.post(route('admin.library-categories.store'));
};
</script>

<template>
    <Head title="Create Library Category" />

    <AdminLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Create Category</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Add a category to organize library items.
                    </p>
                </div>
                <Link
                    :href="route('admin.library.index')"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Back to Library
                </Link>
            </div>

            <form class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Category Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        placeholder="e.g. Immigration Law Basics"
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
                        placeholder="Short description of this category."
                    />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Sort Order</label>
                    <input
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                    >
                    <p class="mt-1 text-xs text-gray-500">Lower values appear first in the category list.</p>
                    <p v-if="form.errors.sort_order" class="mt-1 text-xs text-red-600">{{ form.errors.sort_order }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300">
                    Active category
                </label>
                <p v-if="form.errors.is_active" class="mt-1 text-xs text-red-600">{{ form.errors.is_active }}</p>

                <div class="flex items-center justify-end gap-3">
                    <Link
                        :href="route('admin.library.index')"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Category' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
