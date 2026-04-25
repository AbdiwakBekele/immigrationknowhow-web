<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { getCommunityGuestKey, communityPostPath, communityPostShareUrl } from '@/utils/community';
import { debounce } from 'lodash-es';
import {
    ArrowTopRightOnSquareIcon,
    BookOpenIcon,
    BookmarkIcon,
    BriefcaseIcon,
    ChatBubbleBottomCenterTextIcon,
    ChatBubbleLeftRightIcon,
    GlobeAltIcon,
    HeartIcon,
    HomeIcon,
    MegaphoneIcon,
    NewspaperIcon,
    ScaleIcon,
    ShareIcon,
    SparklesIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    dashboardContext: {
        type: String,
        default: 'guest',
    },
});

const page = usePage();
const authUser = computed(() => page.props?.auth?.user ?? null);
const layoutComponent = computed(() => (
    props.dashboardContext === 'provider' ? ProviderLayout : AppLayout
));

const sectionLabels = {
    feed: 'Feed',
    'ask-intro': 'Intro',
    'ask-announcement': 'Announcement',
    'immigration-legal': 'Immigration & Legal',
    'career-finance': 'Career & Finance',
    'health-wellness': 'Health & Wellness',
    'daily-living': 'Daily Living & Settling In',
    'culture-community': 'Culture & Community',
    'immigration-news': 'Latest Immigration News',
};

const sections = [
    'immigration-legal',
    'career-finance',
    'health-wellness',
    'daily-living',
    'culture-community',
];
const countries = ['US', 'EU', 'CA', 'GB'];

const activeSection = ref('feed');
const search = ref('');
const country = ref('US');

const posts = ref([]);
const recentPosts = ref([]);
const postsLoading = ref(false);
const postsError = ref('');

const newsItems = ref([]);
const newsLoading = ref(false);
const newsError = ref('');

const commentModalPost = ref(null);
const shareModalPost = ref(null);
const comments = ref([]);
const commentsLoading = ref(false);
const newComment = ref('');
const commentError = ref('');

const engagement = ref({});

const filteredPosts = computed(() => {
    const query = search.value.trim().toLowerCase();
    return posts.value.filter((post) => {
        const sectionMatch = activeSection.value === 'feed' ? true : post.category === activeSection.value;
        const queryMatch = !query
            || post.title.toLowerCase().includes(query)
            || post.description.toLowerCase().includes(query)
            || post.tag.toLowerCase().includes(query);
        return sectionMatch && queryMatch;
    });
});

const filteredNewsItems = computed(() => {
    const query = search.value.trim().toLowerCase();
    return newsItems.value.filter((item) => !query
        || item.title.toLowerCase().includes(query)
        || item.summary.toLowerCase().includes(query)
        || item.source.toLowerCase().includes(query));
});

function getActionCount(post, key) {
    const local = engagement.value[post.id]?.[key];
    if (typeof local === 'number') return local;
    if (key === 'likes') return Number(post.likes_count ?? 0);
    if (key === 'comments') return Number(post.comments_count ?? 0);
    if (key === 'shares') return Number(post.shares_count ?? 0);
    return Number(post.bookmarks_count ?? 0);
}

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
        .map((p) => p.charAt(0).toUpperCase())
        .join('');
}

async function loadPosts() {
    postsLoading.value = true;
    postsError.value = '';
    try {
        const params = new URLSearchParams({
            category: activeSection.value === 'immigration-news' ? 'feed' : activeSection.value,
            search: search.value || '',
            guest_key: getCommunityGuestKey(),
        });
        const requestUrl = `/api/community/posts?${params.toString()}`;
        console.info('[community][ui] loadPosts:start', {
            activeSection: activeSection.value,
            effectiveCategory: params.get('category'),
            search: search.value,
            guestKeyPresent: Boolean(params.get('guest_key')),
            requestUrl,
        });
        const response = await fetch(requestUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const rawText = await response.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (parseError) {
            console.error('[community][ui] loadPosts:non-json-response', {
                status: response.status,
                statusText: response.statusText,
                bodyPreview: rawText.slice(0, 1000),
                parseError,
            });
            throw new Error('Community posts endpoint returned non-JSON.');
        }
        console.info('[community][ui] loadPosts:response', {
            status: response.status,
            ok: response.ok,
            postsCount: data?.posts?.data?.length ?? 0,
            total: data?.posts?.total ?? null,
            currentPage: data?.posts?.current_page ?? null,
            error: data?.error ?? null,
        });
        posts.value = data?.posts?.data ?? [];
        if (data?.error) {
            postsError.value = data.error;
            console.warn('[community][ui] loadPosts:api-error', {
                error: data.error,
                activeSection: activeSection.value,
                search: search.value,
            });
        }
        if (!posts.value.length) {
            console.warn('[community][ui] loadPosts:empty-results', {
                activeSection: activeSection.value,
                effectiveCategory: params.get('category'),
                search: search.value,
                apiTotal: data?.posts?.total ?? null,
            });
        }
    } catch (error) {
        posts.value = [];
        postsError.value = 'Unable to load community posts.';
        console.error('[community][ui] loadPosts:failed', {
            activeSection: activeSection.value,
            search: search.value,
            errorMessage: error?.message ?? String(error),
            error,
        });
    } finally {
        postsLoading.value = false;
    }
}

async function loadRecentPosts() {
    try {
        const params = new URLSearchParams({
            category: 'feed',
            search: '',
            guest_key: getCommunityGuestKey(),
        });
        const response = await fetch(`/api/community/posts?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        recentPosts.value = (data?.posts?.data ?? []).slice(0, 5);
    } catch {
        recentPosts.value = [];
    }
}

async function fetchNews(nextCountry) {
    newsLoading.value = true;
    newsError.value = '';
    try {
        const response = await fetch(`/api/community/news?country=${nextCountry}&limit=10`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        newsItems.value = data?.items ?? [];
        if (data?.error) {
            newsError.value = data.error;
        }
    } catch {
        newsItems.value = [];
        newsError.value = 'Unable to load immigration news right now.';
    } finally {
        newsLoading.value = false;
    }
}

async function reactToPost(postId, type) {
    const response = await fetch(`/api/community/posts/${postId}/react`, {
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

    const current = engagement.value[postId] || {
        likes: 0,
        comments: 0,
        shares: 0,
        bookmarks: 0,
        liked: false,
        bookmarked: false,
    };

    engagement.value = {
        ...engagement.value,
        [postId]: {
            ...current,
            likes: Number(data?.counts?.likes_count ?? current.likes),
            comments: Number(data?.counts?.comments_count ?? current.comments),
            shares: Number(data?.counts?.shares_count ?? current.shares),
            bookmarks: Number(data?.counts?.bookmarks_count ?? current.bookmarks),
            liked: type === 'like' ? Boolean(data?.active) : current.liked,
            bookmarked: type === 'bookmark' ? Boolean(data?.active) : current.bookmarked,
        },
    };
}

async function onToggleLike(postId) {
    try {
        await reactToPost(postId, 'like');
    } catch (error) {
        alert(error?.message || 'Unable to update like.');
    }
}

async function onToggleBookmark(postId) {
    try {
        await reactToPost(postId, 'bookmark');
    } catch (error) {
        alert(error?.message || 'Unable to update bookmark.');
    }
}

async function onComment(post) {
    commentModalPost.value = post;
    newComment.value = '';
    commentError.value = '';
    commentsLoading.value = true;
    try {
        const response = await fetch(`/api/community/posts/${post.id}/comments`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        comments.value = data?.comments ?? [];
        if (data?.error) commentError.value = data.error;
    } catch {
        comments.value = [];
        commentError.value = 'Unable to load comments.';
    } finally {
        commentsLoading.value = false;
    }
}

async function submitComment() {
    if (!commentModalPost.value) return;
    const content = newComment.value.trim();
    if (!content) {
        commentError.value = 'Please write a comment before submitting.';
        return;
    }
    try {
        const response = await fetch(`/api/community/posts/${commentModalPost.value.id}/comments`, {
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
        if (data?.comment) {
            comments.value = [data.comment, ...comments.value];
        }
        const current = engagement.value[commentModalPost.value.id];
        if (current) {
            engagement.value = {
                ...engagement.value,
                [commentModalPost.value.id]: {
                    ...current,
                    comments: Number(data?.counts?.comments_count ?? current.comments + 1),
                },
            };
        }
        newComment.value = '';
        commentError.value = '';
    } catch {
        commentError.value = 'Unable to add comment right now.';
    }
}

function onShare(post) {
    shareModalPost.value = post;
}

async function onCopyShareLink() {
    if (!shareModalPost.value) return;
    const shareUrl = communityPostShareUrl(shareModalPost.value.id);
    await navigator.clipboard.writeText(shareUrl);
    try {
        await reactToPost(shareModalPost.value.id, 'share');
    } catch {
        // ignore
    }
}

function openShareTarget(type) {
    if (!shareModalPost.value) return;
    const shareUrl = communityPostShareUrl(shareModalPost.value.id);
    const encodedUrl = encodeURIComponent(shareUrl);
    const encodedTitle = encodeURIComponent(shareModalPost.value.title);
    const targets = {
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
        x: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`,
        linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
        whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
        email: `mailto:?subject=${encodedTitle}&body=${encodedUrl}`,
    };
    window.open(targets[type], '_blank', 'noopener,noreferrer');
    reactToPost(shareModalPost.value.id, 'share').catch(() => undefined);
}

async function onNativeShare() {
    if (!shareModalPost.value || !navigator.share) return;
    const shareUrl = communityPostShareUrl(shareModalPost.value.id);
    await navigator.share({ title: shareModalPost.value.title, url: shareUrl });
    reactToPost(shareModalPost.value.id, 'share').catch(() => undefined);
}

const debouncedLoadPosts = debounce(() => {
    if (activeSection.value !== 'immigration-news') {
        void loadPosts();
    }
}, 200);

watch([activeSection, search], debouncedLoadPosts);

watch(posts, (nextPosts) => {
    const next = {};
    for (const post of nextPosts) {
        const current = engagement.value[post.id];
        const reactions = new Set(post.user_reactions ?? []);
        next[post.id] = {
            likes: current?.likes ?? post.likes_count ?? 0,
            comments: current?.comments ?? post.comments_count ?? 0,
            shares: current?.shares ?? post.shares_count ?? 0,
            bookmarks: current?.bookmarks ?? post.bookmarks_count ?? 0,
            liked: current?.liked ?? reactions.has('like'),
            bookmarked: current?.bookmarked ?? reactions.has('bookmark'),
        };
    }
    engagement.value = next;
}, { immediate: true });

watch([posts, filteredPosts, activeSection, search], () => {
    const query = search.value.trim().toLowerCase();
    const bySection = posts.value.filter((post) => (
        activeSection.value === 'feed' ? true : post.category === activeSection.value
    ));
    console.info('[community][ui] render-diagnostics', {
        activeSection: activeSection.value,
        search: search.value,
        query,
        rawPostsCount: posts.value.length,
        sectionMatchedCount: bySection.length,
        filteredPostsCount: filteredPosts.value.length,
        sampleCategories: posts.value.slice(0, 10).map((post) => post.category),
        sampleTitles: posts.value.slice(0, 5).map((post) => post.title),
    });
}, { deep: true });

onMounted(() => {
    void loadPosts();
    void loadRecentPosts();
});
</script>

<template>
    <Head title="Community" />
    <component :is="layoutComponent">
        <section class="bg-[#f8fafc] py-2">
            <div class="mx-2 flex w-auto flex-col gap-2 px-0 md:mx-3 md:flex-row">
                <aside class="w-full rounded-2xl border border-[#dbe3ef] bg-white p-2.5 md:w-72 md:shrink-0">
                    <h1 class="text-2xl font-extrabold text-[#111827]">Community Feed</h1>

                    <div class="mt-3 border-t border-[#eef2f7] pt-3">
                        <button
                            class="mb-2 w-full rounded-lg px-2 py-1.5 text-left text-sm font-semibold transition-colors"
                            :class="activeSection === 'feed' ? 'bg-[#e8f0ff] text-[#1d4ed8]' : 'text-[#111827] hover:bg-[#f3f6fb]'"
                            type="button"
                            @click="activeSection = 'feed'"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100">
                                    <HomeIcon class="h-3.5 w-3.5 text-[#1d4ed8]" />
                                </span>
                                Feed
                            </span>
                        </button>

                        <h2 class="mb-1 text-xs font-bold uppercase tracking-wide text-[#64748b]">Ask Community</h2>
                        <button
                            class="mb-1 ml-3 w-[calc(100%-12px)] rounded-lg px-2 py-1.5 text-left text-sm font-medium transition-colors"
                            :class="activeSection === 'ask-intro' ? 'bg-[#e8f0ff] text-[#1d4ed8]' : 'text-[#111827] hover:bg-[#f3f6fb]'"
                            type="button"
                            @click="activeSection = 'ask-intro'"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-100">
                                    <ChatBubbleLeftRightIcon class="h-3.5 w-3.5 text-[#0284c7]" />
                                </span>
                                Intro
                            </span>
                        </button>
                        <button
                            class="mb-2 ml-3 w-[calc(100%-12px)] rounded-lg px-2 py-1.5 text-left text-sm font-medium transition-colors"
                            :class="activeSection === 'ask-announcement' ? 'bg-[#e8f0ff] text-[#1d4ed8]' : 'text-[#111827] hover:bg-[#f3f6fb]'"
                            type="button"
                            @click="activeSection = 'ask-announcement'"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-orange-100">
                                    <MegaphoneIcon class="h-3.5 w-3.5 text-[#ea580c]" />
                                </span>
                                Announcement
                            </span>
                        </button>

                        <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-[#64748b]">Immigrant Resources</h2>
                        <button
                            v-for="section in sections"
                            :key="section"
                            class="mb-1 ml-3 w-[calc(100%-12px)] rounded-lg px-2 py-1.5 text-left text-sm font-medium transition-colors"
                            :class="activeSection === section ? 'bg-[#e8f0ff] text-[#1d4ed8]' : 'text-[#111827] hover:bg-[#f3f6fb]'"
                            type="button"
                            @click="activeSection = section"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span v-if="section === 'immigration-legal'" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100">
                                    <ScaleIcon class="h-3.5 w-3.5 text-[#059669]" />
                                </span>
                                <span v-else-if="section === 'career-finance'" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100">
                                    <BriefcaseIcon class="h-3.5 w-3.5 text-[#4f46e5]" />
                                </span>
                                <span v-else-if="section === 'health-wellness'" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-100">
                                    <HeartIcon class="h-3.5 w-3.5 text-[#e11d48]" />
                                </span>
                                <span v-else-if="section === 'daily-living'" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100">
                                    <BookOpenIcon class="h-3.5 w-3.5 text-[#d97706]" />
                                </span>
                                <span v-else class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-fuchsia-100">
                                    <UserGroupIcon class="h-3.5 w-3.5 text-[#c026d3]" />
                                </span>
                                {{ sectionLabels[section] }}
                            </span>
                        </button>

                        <h2 class="mb-1.5 mt-3 text-xs font-bold uppercase tracking-wide text-[#64748b]">Immigration News</h2>
                        <button
                            class="ml-3 w-[calc(100%-12px)] rounded-lg px-2 py-1.5 text-left text-sm font-medium transition-colors"
                            :class="activeSection === 'immigration-news' ? 'bg-[#e8f0ff] text-[#1d4ed8]' : 'text-[#111827] hover:bg-[#f3f6fb]'"
                            type="button"
                            @click="() => { activeSection = 'immigration-news'; if (!newsItems.length && !newsLoading) fetchNews(country); }"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-teal-100">
                                    <NewspaperIcon class="h-3.5 w-3.5 text-[#0f766e]" />
                                </span>
                                Latest Immigrant News
                            </span>
                        </button>
                    </div>
                </aside>

                <div class="min-w-0 flex-1">
                    <div class="mx-auto grid max-w-[1180px] gap-2 xl:grid-cols-[minmax(0,760px)_300px]">
                        <div class="rounded-2xl border border-[#dbe3ef] bg-white p-2 md:p-2.5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <h2 class="text-xl font-bold text-[#111827]">{{ sectionLabels[activeSection] }}</h2>
                                <input
                                    v-model="search"
                                    :placeholder="`Search in ${sectionLabels[activeSection]}...`"
                                    class="h-10 w-full rounded-full border border-[#cdd9ea] px-3 text-sm outline-none focus:border-[#3b82f6] sm:w-80"
                                >
                            </div>

                            <div v-if="activeSection === 'immigration-news'" class="mt-4">
                                <div class="mb-3 flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-semibold text-[#1f2937]">Country:</span>
                                    <button
                                        v-for="countryOption in countries"
                                        :key="countryOption"
                                        type="button"
                                        class="rounded-full border px-2.5 py-1.5 text-sm"
                                        :class="country === countryOption ? 'border-[#1d4ed8] bg-[#1d4ed8] text-white' : 'border-[#cdd9ea] bg-white text-[#1f2937]'"
                                        @click="() => { country = countryOption; fetchNews(countryOption); }"
                                    >
                                        {{ countryOption }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full bg-[#0f766e] px-2.5 py-1.5 text-sm font-semibold text-white"
                                        @click="fetchNews(country)"
                                    >
                                        Load Latest News
                                    </button>
                                </div>

                                <p v-if="newsLoading" class="text-sm text-[#475569]">Loading latest news...</p>
                                <p v-if="newsError" class="text-sm text-[#b91c1c]">{{ newsError }}</p>

                                <div class="mt-2 grid gap-4 md:grid-cols-2">
                                    <article v-for="item in filteredNewsItems" :key="item.id" class="m-1 rounded-xl border border-[#dde5f1] bg-white p-3 shadow-sm">
                                        <div v-if="item.image" class="relative mb-2 h-36 overflow-hidden rounded-lg bg-[#e5e7eb]">
                                            <img :src="item.image" :alt="item.title" class="h-full w-full object-cover">
                                        </div>
                                        <p class="text-xs font-semibold uppercase text-[#334155]">{{ item.source || 'Google News' }}</p>
                                        <h3 class="mt-1 text-base font-bold text-[#111827]">{{ item.title }}</h3>
                                        <p class="mt-1.5 text-sm text-[#4b5563]">{{ item.summary }}</p>
                                        <a :href="item.url" target="_blank" rel="noreferrer" class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:text-blue-700">
                                            <span>Read full story</span>
                                            <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                                        </a>
                                    </article>
                                </div>
                            </div>

                            <div v-else class="mt-4 space-y-3">
                                <p v-if="postsLoading" class="rounded-lg border border-dashed border-[#c9d5e6] p-4 text-center text-sm text-[#64748b]">
                                    Loading community posts...
                                </p>
                                <p v-if="postsError" class="text-sm text-[#b91c1c]">{{ postsError }}</p>

                                <article v-for="post in filteredPosts" :key="post.id" class="rounded-xl border border-[#dde5f1] p-2.5">
                                    <Link :href="communityPostPath(post.id)" class="group block rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-[#1d4ed8] focus-visible:ring-offset-2">
                                        <div v-if="post.image_url" class="relative mb-2 h-40 overflow-hidden rounded-lg bg-[#e5e7eb]">
                                            <img :src="post.image_url" :alt="post.title" class="h-full w-full object-cover">
                                        </div>
                                        <div class="mb-1.5 inline-block rounded-full bg-[#eef2ff] px-2 py-1 text-xs font-semibold text-[#3730a3]">{{ post.tag }}</div>
                                        <h3 class="text-lg font-bold text-[#111827] group-hover:underline">{{ post.title }}</h3>
                                        <p class="mt-1.5 line-clamp-3 text-sm text-[#4b5563]">{{ post.description }}</p>
                                    </Link>

                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition" :class="engagement[post.id]?.liked ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-[#d7e0ee] bg-white text-[#334155] hover:bg-[#f8fafc]'" @click="onToggleLike(post.id)">
                                            <HeartIcon class="h-4 w-4" />
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px] tabular-nums">{{ getActionCount(post, 'likes') }}</span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-[#d7e0ee] bg-white px-3 py-1.5 text-xs font-semibold text-[#334155] transition hover:bg-[#f8fafc]" @click="onComment(post)">
                                            <ChatBubbleBottomCenterTextIcon class="h-4 w-4" />
                                            Comment
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ getActionCount(post, 'comments') }}</span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-[#d7e0ee] bg-white px-3 py-1.5 text-xs font-semibold text-[#334155] transition hover:bg-[#f8fafc]" @click="onShare(post)">
                                            <ShareIcon class="h-4 w-4" />
                                            Share
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ getActionCount(post, 'shares') }}</span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition" :class="engagement[post.id]?.bookmarked ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-[#d7e0ee] bg-white text-[#334155] hover:bg-[#f8fafc]'" @click="onToggleBookmark(post.id)">
                                            <BookmarkIcon class="h-4 w-4" />
                                            Bookmark
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ getActionCount(post, 'bookmarks') }}</span>
                                        </button>
                                    </div>
                                </article>

                                <p v-if="!postsLoading && filteredPosts.length === 0" class="rounded-lg border border-dashed border-[#c9d5e6] p-4 text-center text-sm text-[#64748b]">
                                    No posts found for this section. Try another category or search.
                                </p>
                            </div>
                        </div>

                        <aside class="h-fit rounded-2xl border border-[#dbe3ef] bg-white p-3">
                            <h3 class="text-base font-bold text-[#111827]">Recent Posts</h3>
                            <p class="mt-1 text-xs text-[#64748b]">Latest updates from the community feed.</p>
                            <div class="mt-3 space-y-2.5">
                                <Link v-for="post in recentPosts" :key="`recent-${post.id}`" :href="communityPostPath(post.id)" class="block rounded-xl border border-[#e4eaf4] p-2 transition hover:bg-[#f8fafc]">
                                    <div class="flex items-start gap-2.5">
                                        <div v-if="post.image_url" class="relative h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-[#e2e8f0]">
                                            <img :src="post.image_url" :alt="post.title" class="h-full w-full object-cover">
                                        </div>
                                        <div v-else class="h-12 w-12 shrink-0 rounded-lg bg-gradient-to-br from-slate-600 to-slate-400" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-[#111827]">{{ post.title }}</p>
                                            <p class="mt-0.5 truncate text-xs text-[#475569]">{{ sectionLabels[post.category] }}</p>
                                        </div>
                                    </div>
                                </Link>
                                <p v-if="recentPosts.length === 0" class="rounded-lg border border-dashed border-[#d4dcea] p-3 text-center text-xs text-[#64748b]">
                                    No recent posts available.
                                </p>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>

            <div v-if="commentModalPost" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-2xl bg-white p-4 shadow-2xl">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-[#111827]">Add Comment</h3>
                        <button type="button" class="rounded-full px-2 py-1 text-sm font-semibold text-[#475569] hover:bg-[#f1f5f9]" @click="commentModalPost = null">
                            Close
                        </button>
                    </div>
                    <p class="mb-2 text-sm font-semibold text-[#1f2937]">{{ commentModalPost.title }}</p>
                    <div class="mb-3 max-h-48 space-y-2 overflow-y-auto rounded-xl border border-[#e2e8f0] bg-[#f8fafc] p-2">
                        <p v-if="commentsLoading" class="text-xs text-[#64748b]">Loading comments...</p>
                        <template v-else-if="comments.length">
                            <article v-for="comment in comments" :key="comment.id" class="rounded-lg border border-[#e2e8f0] bg-white p-2">
                                <div class="flex items-start gap-2">
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
                            </article>
                        </template>
                        <p v-else class="text-xs text-[#64748b]">No comments yet. Be the first to comment.</p>
                    </div>
                    <textarea v-model="newComment" rows="5" placeholder="Write your comment..." class="w-full rounded-xl border border-[#d1d9e6] px-3 py-2 text-sm outline-none focus:border-[#3b82f6]" />
                    <p v-if="commentError" class="mt-2 text-sm text-[#b91c1c]">{{ commentError }}</p>
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" class="rounded-full border border-[#cbd5e1] px-4 py-2 text-sm font-semibold text-[#334155]" @click="commentModalPost = null">Cancel</button>
                        <button type="button" class="rounded-full bg-[#1d4ed8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1e40af]" @click="submitComment">Add Comment</button>
                    </div>
                </div>
            </div>

            <div v-if="shareModalPost" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-2xl bg-white p-4 shadow-2xl">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-[#111827]">Share Post</h3>
                        <button type="button" class="rounded-full px-2 py-1 text-sm font-semibold text-[#475569] hover:bg-[#f1f5f9]" @click="shareModalPost = null">
                            Close
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-3 py-2 text-sm font-semibold text-[#1d4ed8] hover:bg-blue-50" @click="openShareTarget('facebook')">f</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-3 py-2 text-sm font-semibold text-[#0f172a] hover:bg-slate-50" @click="openShareTarget('x')">X</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-3 py-2 text-sm font-semibold text-[#0a66c2] hover:bg-blue-50" @click="openShareTarget('linkedin')">in</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-3 py-2 text-sm font-semibold text-[#16a34a] hover:bg-emerald-50" @click="openShareTarget('whatsapp')">wa</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-3 py-2 text-sm font-semibold text-[#334155] hover:bg-slate-50" @click="openShareTarget('email')">@</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-3 py-2 text-sm font-semibold text-[#334155] hover:bg-slate-50" @click="onCopyShareLink">
                            <GlobeAltIcon class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="button" class="rounded-full bg-[#1d4ed8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1e40af]" @click="onNativeShare">More Options</button>
                    </div>
                </div>
            </div>
        </section>
    </component>
</template>
