<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { communityPostShareUrl, getCommunityGuestKey } from '@/utils/community';
import {
    BookmarkIcon,
    ChatBubbleBottomCenterTextIcon,
    EnvelopeIcon,
    HeartIcon,
    LinkIcon,
    ShareIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    postId: {
        type: Number,
        required: true,
    },
    dashboardContext: {
        type: String,
        default: 'guest',
    },
});

const layoutComponent = computed(() => (
    props.dashboardContext === 'provider' ? ProviderLayout : AppLayout
));
const page = usePage();
const authUser = computed(() => page.props?.auth?.user ?? null);

const sectionLabel = {
    feed: 'Feed',
    'ask-intro': 'Intro',
    'ask-announcement': 'Announcement',
    'immigration-legal': 'Immigration & Legal',
    'career-finance': 'Career & Finance',
    'health-wellness': 'Health & Wellness',
    'daily-living': 'Daily Living & Settling In',
    'culture-community': 'Culture & Community',
};

const post = ref(null);
const loadError = ref('');
const postLoading = ref(true);
const comments = ref([]);
const commentsLoading = ref(false);
const newComment = ref('');
const commentError = ref('');

const engagement = ref({
    likes: 0,
    comments: 0,
    shares: 0,
    bookmarks: 0,
    liked: false,
    bookmarked: false,
});

function reactionPayload(type) {
    const payload = { type };
    if (!authUser.value) {
        payload.guest_key = getCommunityGuestKey();
    }
    return payload;
}

function commentPayload(content) {
    const payload = { content };
    if (!authUser.value) {
        payload.guest_key = getCommunityGuestKey();
    }
    return payload;
}

function commentInitials(comment) {
    const text = String(comment?.author_name ?? '').trim();
    if (!text) return 'U';
    return text
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function youtubeVideoIdFromUrl(url) {
    try {
        const u = new URL(url);
        if (u.hostname === 'youtu.be') {
            return u.pathname.replace('/', '').slice(0, 32) || null;
        }
        if (u.hostname.includes('youtube.com')) {
            if (u.pathname === '/watch') return u.searchParams.get('v');
            const embed = u.pathname.match(/^\/embed\/([^/]+)/);
            if (embed) return embed[1] || null;
            const shorts = u.pathname.match(/^\/shorts\/([^/]+)/);
            if (shorts) return shorts[1] || null;
        }
    } catch {
        return null;
    }
    return null;
}

async function loadPost() {
    postLoading.value = true;
    loadError.value = '';
    try {
        const sp = new URLSearchParams({ guest_key: getCommunityGuestKey() });
        const res = await fetch(`/api/community/posts/${props.postId}?${sp.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await res.json();
        if (!res.ok || !data?.post) {
            post.value = null;
            loadError.value = data?.error || 'This post could not be found.';
            return;
        }
        post.value = data.post;
        const reactions = new Set(data.post.user_reactions || []);
        engagement.value = {
            likes: Number(data.post.likes_count || 0),
            comments: Number(data.post.comments_count || 0),
            shares: Number(data.post.shares_count || 0),
            bookmarks: Number(data.post.bookmarks_count || 0),
            liked: reactions.has('like'),
            bookmarked: reactions.has('bookmark'),
        };
    } catch {
        post.value = null;
        loadError.value = 'Unable to load this post.';
    } finally {
        postLoading.value = false;
    }
}

async function loadComments() {
    if (!post.value) return;
    commentsLoading.value = true;
    try {
        const res = await fetch(`/api/community/posts/${post.value.id}/comments`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await res.json();
        comments.value = data?.comments ?? [];
        if (data?.error) commentError.value = data.error;
    } catch {
        comments.value = [];
        commentError.value = 'Unable to load comments.';
    } finally {
        commentsLoading.value = false;
    }
}

async function reactToPost(type) {
    if (!post.value) return;
    const response = await fetch(`/api/community/posts/${post.value.id}/react`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(reactionPayload(type)),
    });
    const data = await response.json();
    if (!response.ok || data?.error) {
        throw new Error(data?.error || data?.message || 'Action failed.');
    }
    engagement.value = {
        ...engagement.value,
        likes: Number(data?.counts?.likes_count ?? engagement.value.likes),
        comments: Number(data?.counts?.comments_count ?? engagement.value.comments),
        shares: Number(data?.counts?.shares_count ?? engagement.value.shares),
        bookmarks: Number(data?.counts?.bookmarks_count ?? engagement.value.bookmarks),
        liked: type === 'like' ? Boolean(data?.active) : engagement.value.liked,
        bookmarked: type === 'bookmark' ? Boolean(data?.active) : engagement.value.bookmarked,
    };
}

async function handleSubmitComment() {
    if (!post.value) return;
    const content = newComment.value.trim();
    if (!content) {
        commentError.value = 'Please write a comment before submitting.';
        return;
    }
    commentError.value = '';
    try {
        const response = await fetch(`/api/community/posts/${post.value.id}/comments`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(commentPayload(content)),
        });
        const data = await response.json();
        if (!response.ok || data?.error) {
            commentError.value = data?.error || 'Unable to add comment.';
            return;
        }
        if (data?.comment) comments.value = [data.comment, ...comments.value];
        engagement.value = {
            ...engagement.value,
            comments: Number(data?.counts?.comments_count ?? engagement.value.comments + 1),
        };
        newComment.value = '';
    } catch {
        commentError.value = 'Unable to add comment right now.';
    }
}

function openShareTarget(type) {
    if (!post.value) return;
    const shareUrl = communityPostShareUrl(post.value.id);
    const encodedUrl = encodeURIComponent(shareUrl);
    const encodedTitle = encodeURIComponent(post.value.title);
    const targets = {
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
        x: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`,
        linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
        whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
        email: `mailto:?subject=${encodedTitle}&body=${encodedUrl}`,
    };
    window.open(targets[type], '_blank', 'noopener,noreferrer');
    reactToPost('share').catch(() => undefined);
}

async function onCopyShareLink() {
    if (!post.value) return;
    await navigator.clipboard.writeText(communityPostShareUrl(post.value.id));
    reactToPost('share').catch(() => undefined);
}

async function onNativeShare() {
    if (!post.value || !navigator.share) return;
    const shareUrl = communityPostShareUrl(post.value.id);
    await navigator.share({ title: post.value.title, url: shareUrl });
    reactToPost('share').catch(() => undefined);
}

onMounted(async () => {
    await loadPost();
    if (post.value) await loadComments();
});
</script>

<template>
    <Head :title="post?.title ? `${post.title} | Community` : 'Community Post'" />
    <component :is="layoutComponent">
        <section class="bg-[#f8fafc] py-2">
            <div class="mx-auto max-w-3xl px-3">
                <Link href="/community" class="mb-4 inline-block text-sm font-semibold text-[#1d4ed8] hover:underline">
                    ← Back to Community
                </Link>

                <div v-if="postLoading" class="text-center text-sm text-[#64748b]">Loading post...</div>
                <div v-else-if="loadError || !post" class="text-center">
                    <p class="text-sm text-[#b91c1c]">{{ loadError || 'Not found.' }}</p>
                    <Link href="/community" class="mt-4 inline-block text-sm font-semibold text-[#1d4ed8] hover:underline">
                        Back to Community
                    </Link>
                </div>

                <template v-else>
                    <article class="rounded-2xl border border-[#dbe3ef] bg-white p-4 md:p-5">
                        <div class="mb-2 text-xs font-semibold text-[#64748b]">
                            {{ sectionLabel[post.category] || 'Community' }} · {{ post.tag }}
                        </div>
                        <h1 class="text-2xl font-bold text-[#111827] md:text-3xl">{{ post.title }}</h1>
                        <p v-if="post.created_at" class="mt-1 text-xs text-[#94a3b8]">
                            {{ new Date(post.created_at).toLocaleString() }}
                        </p>

                        <div v-if="post.image_url" class="relative mt-4 h-64 w-full overflow-hidden rounded-xl bg-[#e5e7eb] md:h-80">
                            <img :src="post.image_url" :alt="post.title" class="h-full w-full object-cover">
                        </div>

                        <a v-if="post.video_url && !youtubeVideoIdFromUrl(post.video_url)" :href="post.video_url" target="_blank" rel="noreferrer" class="mt-4 inline-block text-sm font-semibold text-[#1d4ed8] hover:underline">
                            Open video
                        </a>

                        <div v-if="post.video_url && youtubeVideoIdFromUrl(post.video_url)" class="relative mt-4 aspect-video w-full overflow-hidden rounded-xl bg-black">
                            <iframe :src="`https://www.youtube.com/embed/${youtubeVideoIdFromUrl(post.video_url)}`" title="Post video" class="absolute inset-0 h-full w-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
                        </div>

                        <p class="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-[#374151]">{{ post.description }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition" :class="engagement.liked ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-[#d7e0ee] bg-white text-[#334155] hover:bg-[#f8fafc]'" @click="reactToPost('like').catch((e) => alert(e?.message || 'Error'))">
                                <HeartIcon class="h-4 w-4" />
                                <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ engagement.likes }}</span>
                            </button>
                            <a href="#comments" class="inline-flex items-center gap-1.5 rounded-full border border-[#d7e0ee] bg-white px-3 py-1.5 text-xs font-semibold text-[#334155] transition hover:bg-[#f8fafc]">
                                <ChatBubbleBottomCenterTextIcon class="h-4 w-4" />
                                Comment <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ engagement.comments }}</span>
                            </a>
                            <a href="#share-this-post" class="inline-flex items-center gap-1.5 rounded-full border border-[#d7e0ee] bg-white px-3 py-1.5 text-xs font-semibold text-[#334155] transition hover:bg-[#f8fafc]">
                                <ShareIcon class="h-4 w-4" />
                                Share <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ engagement.shares }}</span>
                            </a>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition" :class="engagement.bookmarked ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-[#d7e0ee] bg-white text-[#334155] hover:bg-[#f8fafc]'" @click="reactToPost('bookmark').catch((e) => alert(e?.message || 'Error'))">
                                <BookmarkIcon class="h-4 w-4" />
                                Bookmark <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ engagement.bookmarks }}</span>
                            </button>
                        </div>

                        <div id="share-this-post" class="mt-5 border-t border-[#e5e7eb] pt-4 scroll-mt-24">
                            <p class="text-xs font-semibold uppercase text-[#64748b]">Share this post</p>
                            <p class="mt-0.5 break-all text-xs text-[#94a3b8]">{{ communityPostShareUrl(post.id) }}</p>
                            <div class="mt-2 grid max-w-sm grid-cols-3 gap-2 sm:grid-cols-6">
                                <button type="button" class="rounded-xl border border-[#d7e0ee] px-2 py-2 text-xs text-[#1d4ed8] hover:bg-blue-50" @click="openShareTarget('facebook')">f</button>
                                <button type="button" class="rounded-xl border border-[#d7e0ee] px-2 py-2 text-xs text-[#0f172a] hover:bg-slate-50" @click="openShareTarget('x')">X</button>
                                <button type="button" class="rounded-xl border border-[#d7e0ee] px-2 py-2 text-xs text-[#0a66c2] hover:bg-blue-50" @click="openShareTarget('linkedin')">in</button>
                                <button type="button" class="rounded-xl border border-[#d7e0ee] px-2 py-2 text-xs text-[#16a34a] hover:bg-emerald-50" @click="openShareTarget('whatsapp')">wa</button>
                                <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-2 py-2 text-xs text-[#334155] hover:bg-slate-50" @click="openShareTarget('email')">
                                    <EnvelopeIcon class="h-4 w-4" />
                                </button>
                                <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-2 py-2 text-xs text-[#334155] hover:bg-slate-50" @click="onCopyShareLink">
                                    <LinkIcon class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-if="typeof navigator !== 'undefined' && navigator.share" class="mt-2 flex justify-end">
                                <button type="button" class="rounded-full bg-[#1d4ed8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1e40af]" @click="onNativeShare">
                                    More share options
                                </button>
                            </div>
                        </div>
                    </article>

                    <div id="comments" class="mt-6 rounded-2xl border border-[#dbe3ef] bg-white p-4 md:p-6">
                        <h2 class="text-lg font-bold text-[#111827]">Comments</h2>
                        <p v-if="commentsLoading" class="mt-2 text-sm text-[#64748b]">Loading comments...</p>
                        <div class="mt-3 max-h-80 space-y-2 overflow-y-auto">
                            <p v-if="comments.length === 0 && !commentsLoading" class="text-sm text-[#64748b]">No comments yet.</p>
                            <div v-for="comment in comments" :key="comment.id" class="rounded-lg border border-[#e2e8f0] bg-[#f8fafc] p-3">
                                <div class="flex items-start gap-2.5">
                                    <img
                                        v-if="comment.author_avatar_url"
                                        :src="comment.author_avatar_url"
                                        :alt="comment.author_name"
                                        class="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200"
                                    >
                                    <div
                                        v-else
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-700"
                                    >
                                        {{ commentInitials(comment) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-[#334155]">{{ comment.author_name }}</p>
                                        <p class="mt-1 text-sm text-[#475569]">{{ comment.content }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <textarea v-model="newComment" rows="4" placeholder="Write a comment..." class="mt-3 w-full rounded-xl border border-[#d1d9e6] px-3 py-2 text-sm outline-none focus:border-[#3b82f6]" />
                        <p v-if="commentError" class="mt-1 text-sm text-[#b91c1c]">{{ commentError }}</p>
                        <div class="mt-2 flex justify-end">
                            <button type="button" class="rounded-full bg-[#1d4ed8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1e40af]" @click="handleSubmitComment">
                                Add comment
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </component>
</template>
