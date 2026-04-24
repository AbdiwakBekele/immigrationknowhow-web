<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const sections = [
    { value: 'feed', label: 'Feed' },
    { value: 'ask-intro', label: 'Intro' },
    { value: 'ask-announcement', label: 'Announcement' },
    { value: 'immigration-legal', label: 'Immigration & Legal' },
    { value: 'career-finance', label: 'Career & Finance' },
    { value: 'health-wellness', label: 'Health & Wellness' },
    { value: 'daily-living', label: 'Daily Living & Settling In' },
    { value: 'culture-community', label: 'Culture & Community' },
];

const active = ref('feed');
const search = ref('');
const posts = ref([]);
const loading = ref(false);

const filtered = computed(() => posts.value);

async function loadPosts() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            category: active.value,
            search: search.value || '',
        });
        const response = await fetch(`/api/community/posts?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        posts.value = data?.posts?.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function react(post, type) {
    await fetch(`/api/community/posts/${post.id}/react`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ type }),
    });

    if (type === 'like') post.likes_count += 1;
    if (type === 'share') post.shares_count += 1;
    if (type === 'bookmark') post.bookmarks_count += 1;
}

async function comment(post) {
    const content = window.prompt('Write your comment');
    if (!content) return;
    const response = await fetch(`/api/community/posts/${post.id}/comments`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ content }),
    });
    if (response.ok) post.comments_count += 1;
}

function choose(section) {
    active.value = section;
    void loadPosts();
}

onMounted(() => {
    void loadPosts();
});
</script>

<template>
    <Head title="Community" />
    <AppLayout>
        <section class="bg-slate-50 py-6">
            <div class="mx-[40px] flex gap-3">
                <aside class="w-72 shrink-0 rounded-2xl border border-slate-200 bg-white p-3">
                    <h1 class="text-xl font-bold text-slate-900">Community</h1>
                    <div class="mt-3 space-y-1">
                        <button
                            v-for="section in sections"
                            :key="section.value"
                            type="button"
                            class="w-full rounded-lg px-2 py-1.5 text-left text-sm font-medium transition"
                            :class="active === section.value ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50'"
                            @click="choose(section.value)"
                        >
                            {{ section.label }}
                        </button>
                    </div>
                </aside>

                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ sections.find((s) => s.value === active)?.label || 'Feed' }}
                        </h2>
                        <input
                            v-model="search"
                            type="search"
                            class="admin-input max-w-sm"
                            placeholder="Search posts..."
                            @input="loadPosts"
                        >
                    </div>

                    <div class="mt-4 space-y-3">
                        <p v-if="loading" class="text-sm text-slate-500">Loading posts...</p>
                        <article
                            v-for="post in filtered"
                            :key="post.id"
                            class="rounded-xl border border-slate-200 p-3"
                        >
                            <img
                                v-if="post.image_url"
                                :src="post.image_url"
                                alt=""
                                class="mb-2 h-44 w-full rounded-lg object-cover"
                            >
                            <span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">
                                {{ post.tag }}
                            </span>
                            <h3 class="mt-1 text-base font-semibold text-slate-900">{{ post.title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ post.description }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <button class="rounded-full border border-slate-200 px-2 py-1" @click="react(post, 'like')">❤️ {{ post.likes_count }}</button>
                                <button class="rounded-full border border-slate-200 px-2 py-1" @click="comment(post)">💬 {{ post.comments_count }}</button>
                                <button class="rounded-full border border-slate-200 px-2 py-1" @click="react(post, 'share')">🔗 {{ post.shares_count }}</button>
                                <button class="rounded-full border border-slate-200 px-2 py-1" @click="react(post, 'bookmark')">🔖 {{ post.bookmarks_count }}</button>
                            </div>
                        </article>
                        <p v-if="!loading && filtered.length === 0" class="text-sm text-slate-500">
                            No posts found.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
