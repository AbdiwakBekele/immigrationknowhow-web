<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const categories = [
    { value: 'feed', label: 'Feed' },
    { value: 'ask-intro', label: 'Intro' },
    { value: 'ask-announcement', label: 'Announcement' },
    { value: 'immigration-legal', label: 'Immigration & Legal' },
    { value: 'career-finance', label: 'Career & Finance' },
    { value: 'health-wellness', label: 'Health & Wellness' },
    { value: 'daily-living', label: 'Daily Living & Settling In' },
    { value: 'culture-community', label: 'Culture & Community' },
];

const form = ref({
    title: '',
    description: '',
    tag: '',
    category: 'feed',
    video_url: '',
    is_published: true,
});

const search = ref('');
const posts = ref([]);
const loading = ref(false);
const stats = ref({ total: 0, published: 0, drafts: 0 });
const selectedCategory = ref('all');
const selectedStatus = ref('all');
const editingId = ref(null);
const showPostModal = ref(false);
const imageFile = ref(null);
const videoFile = ref(null);

const submitLabel = computed(() => (editingId.value ? 'Update Post' : 'Create Post'));
const visiblePosts = computed(() => {
    if (selectedStatus.value === 'all') return posts.value;
    return posts.value.filter((post) => (selectedStatus.value === 'published' ? post.is_published : !post.is_published));
});

function getCsrfToken() {
    const metaToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    if (metaToken) return metaToken;

    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookieRow) => cookieRow.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return xsrfCookie ? decodeURIComponent(xsrfCookie) : '';
}

function getRequestHeaders() {
    const csrfToken = getCsrfToken();
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken, 'X-XSRF-TOKEN': csrfToken } : {}),
    };
}

async function loadPosts() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            includeDrafts: '1',
            search: search.value || '',
        });
        if (selectedCategory.value !== 'all') {
            params.set('category', selectedCategory.value);
        }
        const response = await fetch(`/admin/community/api/posts?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        posts.value = data?.posts?.data ?? [];
        stats.value = data?.stats ?? { total: 0, published: 0, drafts: 0 };
    } finally {
        loading.value = false;
    }
}

async function submitPost() {
    const url = editingId.value
        ? `/admin/community/api/posts/${editingId.value}`
        : '/admin/community/api/posts';

    const payload = new FormData();
    payload.append('title', form.value.title || '');
    payload.append('description', form.value.description || '');
    payload.append('tag', form.value.tag || '');
    payload.append('category', form.value.category || '');
    payload.append('video_url', form.value.video_url || '');
    payload.append('is_published', form.value.is_published ? '1' : '0');
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        payload.append('_token', csrfToken);
    }

    if (imageFile.value) {
        payload.append('image', imageFile.value);
    }
    if (videoFile.value) {
        payload.append('video', videoFile.value);
    }
    if (editingId.value) {
        payload.append('_method', 'PATCH');
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: getRequestHeaders(),
        credentials: 'same-origin',
        body: payload,
    });

    if (!response.ok) {
        let errorMessage = 'Unable to save post. Please check required fields.';
        try {
            const errorData = await response.json();
            const firstFieldError = errorData?.errors
                ? Object.values(errorData.errors)?.[0]?.[0]
                : null;
            errorMessage = firstFieldError || errorData?.message || errorMessage;
            console.error('Community post save failed', {
                status: response.status,
                errorData,
            });
        } catch (parseError) {
            console.error('Community post save failed and response was not JSON', {
                status: response.status,
                parseError,
            });
        }
        alert(errorMessage);
        return;
    }

    showPostModal.value = false;
    resetForm();
    await loadPosts();
}

function editPost(post) {
    editingId.value = post.id;
    form.value = {
        title: post.title,
        description: post.description,
        tag: post.tag,
        category: post.category,
        video_url: post.video_url || '',
        is_published: post.is_published,
    };
    imageFile.value = null;
    videoFile.value = null;
    showPostModal.value = true;
}

function resetForm() {
    editingId.value = null;
    form.value = {
        title: '',
        description: '',
        tag: '',
        category: 'feed',
        video_url: '',
        is_published: true,
    };
    imageFile.value = null;
    videoFile.value = null;
}

function openCreateModal() {
    resetForm();
    showPostModal.value = true;
}

async function deletePost(postId) {
    if (!window.confirm('Delete this post?')) return;
    await fetch(`/admin/community/api/posts/${postId}`, {
        method: 'DELETE',
        headers: getRequestHeaders(),
        credentials: 'same-origin',
    });
    await loadPosts();
}

async function togglePublish(post) {
    await fetch(`/admin/community/api/posts/${post.id}`, {
        method: 'PATCH',
        headers: {
            ...getRequestHeaders(),
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            title: post.title,
            description: post.description,
            tag: post.tag,
            category: post.category,
            image_url: post.image_url,
            is_published: !post.is_published,
        }),
    });
    await loadPosts();
}

onMounted(() => {
    void loadPosts();
});
</script>

<template>
    <Head title="Community Management" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <h1 class="admin-title">Community Management</h1>
                <p class="admin-subtitle">Create, publish, and manage dynamic community feed posts.</p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">Total: {{ stats.total }}</span>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">Published: {{ stats.published }}</span>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">Drafts: {{ stats.drafts }}</span>
                </div>
            </section>

            <section>
                <article class="admin-panel">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex flex-1 flex-wrap items-center gap-2">
                            <input
                                v-model="search"
                                class="admin-input max-w-xs"
                                type="search"
                                placeholder="Search posts..."
                                @input="loadPosts"
                            >
                            <select
                                v-model="selectedCategory"
                                class="admin-select max-w-[220px]"
                                @change="loadPosts"
                            >
                                <option value="all">All categories</option>
                                <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                                    {{ cat.label }}
                                </option>
                            </select>
                            <select
                                v-model="selectedStatus"
                                class="admin-select max-w-[180px]"
                            >
                                <option value="all">All status</option>
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:from-blue-700 hover:to-indigo-700 hover:shadow-md"
                                @click="openCreateModal"
                            >
                                Add Post
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                                @click="loadPosts"
                            >
                                Refresh
                            </button>
                        </div>
                    </div>

                    <div v-if="loading" class="text-sm text-slate-500">Loading posts...</div>
                    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="post in visiblePosts"
                            :key="post.id"
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div v-if="post.image_url" class="h-40 w-full bg-slate-100">
                                <img :src="post.image_url" alt="Post image" class="h-full w-full object-cover">
                            </div>
                            <div
                                v-else
                                class="relative h-40 w-full overflow-hidden bg-gradient-to-br from-slate-700 via-slate-600 to-slate-500"
                            >
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.22),transparent_45%)]" />
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.25),transparent_50%)]" />
                                <div class="relative flex h-full items-end p-3">
                                    <span class="rounded-full bg-white/20 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                                        Community Post
                                    </span>
                                </div>
                            </div>
                            <div class="p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-slate-900">{{ post.title }}</h3>
                                        <p class="mt-1 line-clamp-4 text-sm text-slate-600">{{ post.description }}</p>
                                    </div>
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="post.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ post.is_published ? 'Published' : 'Draft' }}
                                    </span>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-700">
                                    <span class="rounded-full bg-slate-100 px-2 py-1">{{ post.category }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1">Tag: {{ post.tag || 'N/A' }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1">Likes: {{ post.likes_count }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1">Comments: {{ post.comments_count }}</span>
                                </div>

                                <div class="mt-3 flex items-center justify-between">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                        @click="togglePublish(post)"
                                    >
                                        {{ post.is_published ? 'Unpublish' : 'Publish' }}
                                    </button>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 transition-all hover:-translate-y-0.5 hover:bg-indigo-100"
                                            title="Edit post"
                                            @click="editPost(post)"
                                        >
                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition-all hover:-translate-y-0.5 hover:bg-rose-100"
                                            title="Delete post"
                                            @click="deletePost(post.id)"
                                        >
                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4h8v2" />
                                                <path d="M19 6l-1 14H6L5 6" />
                                                <path d="M10 11v6M14 11v6" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <p v-if="!loading && visiblePosts.length === 0" class="mt-3 text-sm text-slate-500">No community posts found for current filters.</p>
                </article>
            </section>
        </div>

        <div
            v-if="showPostModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showPostModal = false"
        >
            <article class="w-full max-w-2xl rounded-2xl bg-white p-4 shadow-2xl">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">{{ submitLabel }}</h2>
                    <p class="text-xs text-slate-500">Fields marked with <span class="text-rose-600">*</span> are required.</p>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                        @click="showPostModal = false"
                    >
                        Close
                    </button>
                </div>
                <div class="grid gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Title <span class="text-rose-600">*</span></label>
                        <input v-model="form.title" class="admin-input" placeholder="Title" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Description <span class="text-rose-600">*</span></label>
                        <textarea v-model="form.description" class="admin-input min-h-24" placeholder="Description" required />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="form.tag" class="admin-input" placeholder="Tag (optional)">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Category</label>
                            <select v-model="form.category" class="admin-select">
                                <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                                    {{ cat.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Image Upload</label>
                            <input
                                type="file"
                                accept="image/*"
                                class="admin-input"
                                @change="(event) => (imageFile = event.target.files?.[0] || null)"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Video Upload</label>
                            <input
                                type="file"
                                accept="video/*"
                                class="admin-input"
                                @change="(event) => (videoFile = event.target.files?.[0] || null)"
                            >
                        </div>
                    </div>
                    <input v-model="form.video_url" class="admin-input" placeholder="Video URL (optional)">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_published" type="checkbox">
                        Publish immediately
                    </label>
                    <div class="mt-2 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                            @click="showPostModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:from-blue-700 hover:to-indigo-700 hover:shadow-md"
                            @click="submitPost"
                        >
                            {{ submitLabel }}
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </AdminLayout>
</template>
