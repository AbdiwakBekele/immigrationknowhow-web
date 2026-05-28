<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    FolderIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    authors: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const pageSizeOptions = [10, 25, 50, 100];

const categoryPage = ref(1);
const authorPage = ref(1);
const categoryPerPage = ref(10);
const authorPerPage = ref(10);

const categoryTotal = computed(() => props.categories.length);
const authorTotal = computed(() => props.authors.length);
const categoryLastPage = computed(() => lastPage(categoryTotal.value, categoryPerPage.value));
const authorLastPage = computed(() => lastPage(authorTotal.value, authorPerPage.value));
const paginatedCategories = computed(() => paginateRows(props.categories, categoryPage.value, categoryPerPage.value));
const paginatedAuthors = computed(() => paginateRows(props.authors, authorPage.value, authorPerPage.value));
const categoryPageNumbers = computed(() => pageNumbers(categoryLastPage.value));
const authorPageNumbers = computed(() => pageNumbers(authorLastPage.value));
const categoryPaginationLabel = computed(() => paginationLabel(categoryTotal.value, categoryPage.value, categoryPerPage.value));
const authorPaginationLabel = computed(() => paginationLabel(authorTotal.value, authorPage.value, authorPerPage.value));

function lastPage(total, perPage) {
    return Math.max(1, Math.ceil(total / perPage));
}

function pageNumbers(last) {
    return Array.from({ length: last }, (_, index) => index + 1);
}

function paginateRows(rows, page, perPage) {
    const start = (page - 1) * perPage;
    return rows.slice(start, start + perPage);
}

function paginationLabel(total, page, perPage) {
    if (!total) return 'No records';

    const from = (page - 1) * perPage + 1;
    const to = Math.min(page * perPage, total);

    return `Showing ${from}-${to} of ${total}`;
}

function goToCategoryPage(pageNumber) {
    categoryPage.value = Math.min(Math.max(pageNumber, 1), categoryLastPage.value);
}

function goToAuthorPage(pageNumber) {
    authorPage.value = Math.min(Math.max(pageNumber, 1), authorLastPage.value);
}

watch(categoryPerPage, () => {
    categoryPage.value = 1;
});

watch(authorPerPage, () => {
    authorPage.value = 1;
});

watch(categoryTotal, () => {
    if (categoryPage.value > categoryLastPage.value) {
        categoryPage.value = categoryLastPage.value;
    }
});

watch(authorTotal, () => {
    if (authorPage.value > authorLastPage.value) {
        authorPage.value = authorLastPage.value;
    }
});

const deleteCategory = (category) => {
    if (!window.confirm(`Delete "${category.name}" category?`)) return;

    router.delete(route('admin.library-categories.destroy', category.slug), {
        preserveScroll: true,
    });
};

const deleteAuthor = (author) => {
    if (!window.confirm(`Delete author "${author.name}"?`)) return;

    router.delete(route('admin.library-authors.destroy', author.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Library Categories & Authors" />

    <AdminLayout>
        <div class="admin-page-container space-y-4">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Library settings
                        </p>
                        <h1 class="mt-2 admin-title">Library categories & authors</h1>
                        <p class="admin-subtitle">
                            Manage categories and authors used when creating library items.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('admin.library-categories.create')"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                        >
                            <PlusIcon class="h-5 w-5" />
                            Add category
                        </Link>
                    </div>
                </div>
            </section>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
                {{ flashError }}
            </div>

            <section class="admin-table-wrap">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <FolderIcon class="h-5 w-5 text-sky-600" />
                        <p class="font-semibold text-slate-900">Categories ({{ categories.length }})</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        Rows per page
                        <select v-model.number="categoryPerPage" class="rounded-xl border-slate-200 text-sm">
                            <option v-for="option in pageSizeOptions" :key="`category-${option}`" :value="option">
                                {{ option }}
                            </option>
                        </select>
                    </label>
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
                            <tr v-for="category in paginatedCategories" :key="category.id" class="hover:bg-slate-50/80">
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

                <div
                    v-if="categories.length"
                    class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-slate-600">{{ categoryPaginationLabel }}</p>
                    <nav class="flex flex-wrap items-center gap-1.5" aria-label="Category pagination">
                        <button
                            type="button"
                            class="inline-flex min-w-[2.5rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="categoryPage <= 1"
                            @click="goToCategoryPage(categoryPage - 1)"
                        >
                            Previous
                        </button>
                        <button
                            v-for="pageNumber in categoryPageNumbers"
                            :key="`category-page-${pageNumber}`"
                            type="button"
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-sm font-medium transition"
                            :class="pageNumber === categoryPage
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            @click="goToCategoryPage(pageNumber)"
                        >
                            {{ pageNumber }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-w-[2.5rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="categoryPage >= categoryLastPage"
                            @click="goToCategoryPage(categoryPage + 1)"
                        >
                            Next
                        </button>
                    </nav>
                </div>

                <div v-else class="px-4 py-12 text-center text-sm text-slate-500">
                    No categories yet.
                    <Link :href="route('admin.library-categories.create')" class="font-semibold text-sky-600 hover:text-sky-700">
                        Add the first category
                    </Link>
                </div>
            </section>

            <section id="library-authors" class="admin-table-wrap">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <UserGroupIcon class="h-5 w-5 text-sky-600" />
                        <p class="font-semibold text-slate-900">Authors ({{ authors.length }})</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            Rows per page
                            <select v-model.number="authorPerPage" class="rounded-xl border-slate-200 text-sm">
                                <option v-for="option in pageSizeOptions" :key="`author-${option}`" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </label>
                        <Link
                            :href="route('admin.library-authors.create')"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <PlusIcon class="h-5 w-5" />
                            Add author
                        </Link>
                    </div>
                </div>

                <div v-if="authors.length" class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Name</th>
                                <th class="px-4 py-2.5">Slug</th>
                                <th class="px-4 py-2.5">Library items</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="author in paginatedAuthors" :key="author.id" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    {{ author.name }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                    {{ author.slug }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ author.items_count }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="route('admin.library-authors.edit', author.slug)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-50"
                                        >
                                            <PencilIcon class="h-3.5 w-3.5" />
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                            :disabled="author.items_count > 0"
                                            :class="author.items_count > 0 ? 'cursor-not-allowed opacity-50' : ''"
                                            :title="author.items_count > 0 ? 'Reassign library items before deleting' : 'Delete author'"
                                            @click="deleteAuthor(author)"
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

                <div
                    v-if="authors.length"
                    class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-slate-600">{{ authorPaginationLabel }}</p>
                    <nav class="flex flex-wrap items-center gap-1.5" aria-label="Author pagination">
                        <button
                            type="button"
                            class="inline-flex min-w-[2.5rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="authorPage <= 1"
                            @click="goToAuthorPage(authorPage - 1)"
                        >
                            Previous
                        </button>
                        <button
                            v-for="pageNumber in authorPageNumbers"
                            :key="`author-page-${pageNumber}`"
                            type="button"
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-sm font-medium transition"
                            :class="pageNumber === authorPage
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            @click="goToAuthorPage(pageNumber)"
                        >
                            {{ pageNumber }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-w-[2.5rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="authorPage >= authorLastPage"
                            @click="goToAuthorPage(authorPage + 1)"
                        >
                            Next
                        </button>
                    </nav>
                </div>

                <div v-else class="px-4 py-12 text-center text-sm text-slate-500">
                    No authors yet.
                    <Link :href="route('admin.library-authors.create')" class="font-semibold text-sky-600 hover:text-sky-700">
                        Add the first author
                    </Link>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
