<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    FolderIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const deleteCategory = (category) => {
    if (!window.confirm(`Delete "${category.name}" category?`)) return;

    router.delete(route('admin.library-categories.destroy', category.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Library Categories" />

    <AdminLayout>
        <div class="admin-page-container space-y-4">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Library settings
                        </p>
                        <h1 class="mt-2 admin-title">Library categories</h1>
                        <p class="admin-subtitle">
                            Organize ebooks and audiobooks into clear category groups.
                        </p>
                    </div>

                    <Link
                        :href="route('admin.library-categories.create')"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                    >
                        <PlusIcon class="h-5 w-5" />
                        Add category
                    </Link>
                </div>
            </section>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>

            <section class="admin-table-wrap">
                <div class="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
                    <FolderIcon class="h-5 w-5 text-sky-600" />
                    <p class="font-semibold text-slate-900">All categories ({{ categories.length }})</p>
                </div>

                <div v-if="categories.length" class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Sort</th>
                                <th class="px-4 py-2.5">Name</th>
                                <th class="px-4 py-2.5">Slug</th>
                                <th class="px-4 py-2.5">Icon</th>
                                <th class="px-4 py-2.5">Items</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="category in categories" :key="category.id" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ category.sort_order }}
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ category.name }}</p>
                                    <p v-if="category.description" class="mt-0.5 line-clamp-1 text-xs text-slate-500">
                                        {{ category.description }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                    {{ category.slug }}
                                </td>
                                <td class="px-4 py-3 text-lg leading-none text-slate-700">
                                    <span v-if="category.icon">{{ category.icon }}</span>
                                    <span v-else class="text-xs text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ category.active_items_count }} active / {{ category.items_count }} total
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="category.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ category.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="route('admin.library-categories.edit', category.slug)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-50"
                                        >
                                            <PencilIcon class="h-3.5 w-3.5" />
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                            @click="deleteCategory(category)"
                                        >
                                            <TrashIcon class="h-3.5 w-3.5" />
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="px-4 py-12 text-center text-sm text-slate-500">
                    No categories yet.
                    <Link :href="route('admin.library-categories.create')" class="font-semibold text-sky-600 hover:text-sky-700">
                        Add the first category
                    </Link>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
