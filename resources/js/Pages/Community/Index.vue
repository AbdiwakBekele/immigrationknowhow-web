<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import CommunityShareButtons from '@/Components/community/CommunityShareButtons.vue';
import { getCommunityGuestKey, communityPostPath, communityPostShareUrl, hasPostVideo } from '@/utils/community';
import { communityDescriptionPlainText } from '@/utils/communityContent';
import { debounce } from 'lodash-es';
import {
    ArrowTopRightOnSquareIcon,
    BookOpenIcon,
    BookmarkIcon,
    BriefcaseIcon,
    ChatBubbleBottomCenterTextIcon,
    ChatBubbleLeftRightIcon,
    HeartIcon,
    HomeIcon,
    MegaphoneIcon,
    NewspaperIcon,
    ScaleIcon,
    ShareIcon,
    UserCircleIcon,
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
const loadingMore = ref(false);
const postsError = ref('');
const postsPage = ref(1);
const postsLastPage = ref(1);
const loadMoreSentinel = ref(null);
let loadMoreObserver = null;

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

const hasMorePosts = computed(() => postsPage.value < postsLastPage.value);

const filteredPosts = computed(() => {
    const query = search.value.trim().toLowerCase();
    return posts.value.filter((post) => {
        const sectionMatch = activeSection.value === 'feed' ? true : post.category === activeSection.value;
        const queryMatch = !query
            || post.title.toLowerCase().includes(query)
            || communityDescriptionPlainText(post.description, 0).toLowerCase().includes(query)
            || (post.tag ?? '').toLowerCase().includes(query);
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

async function loadPosts({ reset = true } = {}) {
    if (activeSection.value === 'immigration-news') {
        return;
    }

    const nextPage = reset ? 1 : postsPage.value + 1;

    if (!reset) {
        if (loadingMore.value || postsLoading.value || !hasMorePosts.value) {
            return;
        }
        loadingMore.value = true;
    } else {
        postsLoading.value = true;
        postsError.value = '';
    }

    try {
        const params = new URLSearchParams({
            category: activeSection.value === 'immigration-news' ? 'feed' : activeSection.value,
            search: search.value || '',
            guest_key: getCommunityGuestKey(),
            page: String(nextPage),
        });
        const response = await fetch(`/api/community/posts?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const rawText = await response.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch {
            throw new Error('Community posts endpoint returned non-JSON.');
        }

        const payload = data?.posts ?? {};
        const incoming = payload.data ?? [];

        postsPage.value = payload.current_page ?? nextPage;
        postsLastPage.value = payload.last_page ?? postsPage.value;

        if (reset) {
            posts.value = incoming;
        } else {
            const existingIds = new Set(posts.value.map((post) => post.id));
            posts.value = [
                ...posts.value,
                ...incoming.filter((post) => !existingIds.has(post.id)),
            ];
        }

        if (data?.error) {
            postsError.value = data.error;
        }
    } catch {
        if (reset) {
            posts.value = [];
        }
        postsError.value = 'Unable to load community posts.';
    } finally {
        postsLoading.value = false;
        loadingMore.value = false;
    }
}

function loadMorePosts() {
    void loadPosts({ reset: false });
}

function disconnectLoadMoreObserver() {
    if (loadMoreObserver) {
        loadMoreObserver.disconnect();
        loadMoreObserver = null;
    }
}

function setupLoadMoreObserver() {
    disconnectLoadMoreObserver();

    if (!loadMoreSentinel.value || activeSection.value === 'immigration-news') {
        return;
    }

    loadMoreObserver = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                loadMorePosts();
            }
        },
        { root: null, rootMargin: '240px 0px', threshold: 0 },
    );

    loadMoreObserver.observe(loadMoreSentinel.value);
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

function recordShareAction() {
    if (!shareModalPost.value) return;
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
        void loadPosts({ reset: true });
    }
}, 200);

watch([activeSection, search], debouncedLoadPosts);

watch(loadMoreSentinel, () => {
    void nextTick(() => setupLoadMoreObserver());
});

watch(activeSection, () => {
    void nextTick(() => setupLoadMoreObserver());
});

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

onMounted(async () => {
    await loadPosts({ reset: true });
    void loadRecentPosts();
    await nextTick();
    setupLoadMoreObserver();
});

onBeforeUnmount(() => {
    disconnectLoadMoreObserver();
    debouncedLoadPosts.cancel();
});
</script>

<template>
    <Head title="Community" />
    <component :is="layoutComponent">
        <section class="min-h-screen bg-slate-50 py-4 md:py-6">
            <div class="mx-auto max-w-[1380px] px-3 sm:px-4 lg:px-6">
                <div class="flex w-auto flex-col gap-3 md:flex-row md:gap-4">
                <aside class="w-full rounded-3xl border border-slate-200 bg-white p-3 shadow-sm md:sticky md:top-6 md:w-72 md:h-fit md:shrink-0">
                    <h1 class="text-[1.65rem] font-black tracking-tight text-slate-900">Community Feed</h1>

                    <div class="mt-3 border-t border-slate-200/80 pt-3">
                        <button
                            class="mb-2 w-full rounded-xl px-2.5 py-2 text-left text-[15px] font-semibold transition-all duration-200"
                            :class="activeSection === 'feed' ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/50' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'"
                            type="button"
                            @click="activeSection = 'feed'"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100/90">
                                    <HomeIcon class="h-3.5 w-3.5 text-blue-700" />
                                </span>
                                Feed
                            </span>
                        </button>

                        <h2 class="mb-1 text-[13px] font-bold uppercase tracking-[0.12em] text-slate-500">Ask Community</h2>
                        <button
                            class="mb-1 ml-3 w-[calc(100%-12px)] rounded-xl px-2.5 py-2 text-left text-[15px] font-medium transition-all duration-200"
                            :class="activeSection === 'ask-intro' ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/50' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'"
                            type="button"
                            @click="activeSection = 'ask-intro'"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-100/90">
                                    <ChatBubbleLeftRightIcon class="h-3.5 w-3.5 text-sky-700" />
                                </span>
                                Intro
                            </span>
                        </button>
                        <button
                            class="mb-2 ml-3 w-[calc(100%-12px)] rounded-xl px-2.5 py-2 text-left text-[15px] font-medium transition-all duration-200"
                            :class="activeSection === 'ask-announcement' ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/50' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'"
                            type="button"
                            @click="activeSection = 'ask-announcement'"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-orange-100/90">
                                    <MegaphoneIcon class="h-3.5 w-3.5 text-orange-700" />
                                </span>
                                Announcement
                            </span>
                        </button>

                        <h2 class="mb-2 text-[13px] font-bold uppercase tracking-[0.12em] text-slate-500">Immigrant Resources</h2>
                        <button
                            v-for="section in sections"
                            :key="section"
                            class="mb-1 ml-3 w-[calc(100%-12px)] rounded-xl px-2.5 py-2 text-left text-[15px] font-medium transition-all duration-200"
                            :class="activeSection === section ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/50' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'"
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

                        <h2 class="mb-1.5 mt-3 text-[13px] font-bold uppercase tracking-[0.12em] text-slate-500">Immigration News</h2>
                        <button
                            class="ml-3 w-[calc(100%-12px)] rounded-xl px-2.5 py-2 text-left text-[15px] font-medium transition-all duration-200"
                            :class="activeSection === 'immigration-news' ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/50' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'"
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
                    <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm md:p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <h2 class="text-2xl font-bold text-[#111827]">{{ sectionLabels[activeSection] }}</h2>
                                <input
                                    v-model="search"
                                    :placeholder="`Search in ${sectionLabels[activeSection]}...`"
                                    class="h-11 w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 text-[15px] text-slate-900 outline-none ring-blue-200 transition placeholder:text-slate-500 focus:border-blue-500 focus:bg-white focus:ring-2 sm:w-80"
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

                                <div class="mt-3 grid gap-4 md:grid-cols-2">
                                    <article v-for="item in filteredNewsItems" :key="item.id" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
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

                                    <article v-for="post in filteredPosts" :key="post.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                                    <Link :href="communityPostPath(post.id)" class="group block rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-[#1d4ed8] focus-visible:ring-offset-2">
                                        <div
                                            v-if="post.image_url || hasPostVideo(post)"
                                            class="relative mb-3 h-52 overflow-hidden rounded-xl bg-[#e5e7eb] sm:h-56 md:h-64"
                                        >
                                            <img
                                                v-if="post.image_url"
                                                :src="post.image_url"
                                                :alt="post.title"
                                                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                                            >
                                            <div
                                                v-else
                                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-700 via-slate-600 to-slate-500"
                                            >
                                                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                                                    Video post
                                                </span>
                                            </div>
                                            <span
                                                v-if="hasPostVideo(post)"
                                                class="absolute bottom-3 right-3 inline-flex items-center rounded-full bg-black/65 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm"
                                            >
                                                ▶ Video
                                            </span>
                                        </div>
                                        <div class="mb-2 flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                {{ sectionLabels[post.category] || post.category }}
                                            </span>
                                            <span
                                                v-if="post.contributor_name"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 py-1 pl-1 pr-2.5 text-xs font-semibold text-indigo-800"
                                            >
                                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                                                    <UserCircleIcon class="h-4 w-4" aria-hidden="true" />
                                                </span>
                                                <span class="text-[10px] font-medium uppercase tracking-wide text-indigo-600">Contributor</span>
                                                <span class="text-indigo-900">{{ post.contributor_name }}</span>
                                            </span>
                                            <span
                                                v-if="post.contributor_country"
                                                class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800"
                                            >
                                                {{ post.contributor_country }}
                                            </span>
                                            <span
                                                v-if="post.tag"
                                                class="rounded-full bg-[#eef2ff] px-2.5 py-1 text-xs font-semibold text-[#3730a3]"
                                            >
                                                {{ post.tag }}
                                            </span>
                                        </div>
                                        <h3 class="text-xl font-bold text-[#111827] group-hover:underline">{{ post.title }}</h3>
                                        <p class="mt-1.5 line-clamp-3 text-[15px] text-[#4b5563]">{{ communityDescriptionPlainText(post.description) }}</p>
                                    </Link>

                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-semibold shadow-sm transition-all duration-200" :class="engagement[post.id]?.liked ? 'border-rose-200 bg-rose-50 text-rose-700 ring-1 ring-rose-200/70' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'" @click="onToggleLike(post.id)">
                                            <HeartIcon class="h-4 w-4" />
                                            <span>Like</span>
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px] tabular-nums">{{ getActionCount(post, 'likes') }}</span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:border-slate-300 hover:bg-slate-50" @click="onComment(post)">
                                            <ChatBubbleBottomCenterTextIcon class="h-4 w-4" />
                                            Comment
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ getActionCount(post, 'comments') }}</span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:border-slate-300 hover:bg-slate-50" @click="onShare(post)">
                                            <ShareIcon class="h-4 w-4" />
                                            Share
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ getActionCount(post, 'shares') }}</span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-semibold shadow-sm transition-all duration-200" :class="engagement[post.id]?.bookmarked ? 'border-blue-200 bg-blue-50 text-blue-700 ring-1 ring-blue-200/70' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'" @click="onToggleBookmark(post.id)">
                                            <BookmarkIcon class="h-4 w-4" />
                                            Save
                                            <span class="rounded-full bg-black/5 px-1.5 py-0.5 text-[11px]">{{ getActionCount(post, 'bookmarks') }}</span>
                                        </button>
                                    </div>
                                </article>

                                <p v-if="!postsLoading && filteredPosts.length === 0" class="rounded-lg border border-dashed border-[#c9d5e6] p-4 text-center text-sm text-[#64748b]">
                                    No posts found for this section. Try another category or search.
                                </p>

                                <div
                                    v-if="hasMorePosts || loadingMore"
                                    ref="loadMoreSentinel"
                                    class="flex min-h-[4rem] items-center justify-center py-4"
                                >
                                    <p v-if="loadingMore" class="text-sm text-[#64748b]">Loading more posts...</p>
                                </div>
                            </div>
                        </div>

                        <aside class="h-fit rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 class="text-base font-bold text-[#111827]">Recent Posts</h3>
                            <p class="mt-1 text-xs text-[#64748b]">Latest updates from the community feed.</p>
                            <div class="mt-3 space-y-2.5">
                                <Link v-for="post in recentPosts" :key="`recent-${post.id}`" :href="communityPostPath(post.id)" class="block rounded-2xl border border-slate-200 p-2.5 transition-all hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-sm">
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
                    <CommunityShareButtons
                        v-if="shareModalPost"
                        :post-id="shareModalPost.id"
                        :post-title="shareModalPost.title"
                        @shared="recordShareAction"
                    />
                    <div class="mt-3 flex justify-end">
                        <button type="button" class="rounded-full bg-[#1d4ed8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1e40af]" @click="onNativeShare">More Options</button>
                    </div>
                </div>
            </div>
        </section>
    </component>
</template>





