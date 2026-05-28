<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    author: { type: Object, required: true },
});

const form = useForm({
    name: props.author.name ?? '',
});

const submit = () => {
    form.put(route('admin.library-authors.update', props.author.slug));
};
</script>

<template>
    <Head :title="`Edit ${author.name}`" />

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
                    <h1 class="text-lg font-semibold text-slate-900">Edit library author</h1>
                    <p class="text-xs text-slate-500">
                        Used by {{ author.items_count }} library item(s).
                    </p>
                </div>
            </div>

            <form class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Author name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        required
                    >
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Update author
                    </button>
                    <Link
                        :href="route('admin.library-categories.index')"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
