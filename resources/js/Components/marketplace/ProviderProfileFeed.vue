<script setup>
import { computed } from 'vue';
import {
    ArrowTopRightOnSquareIcon,
    LinkIcon,
    NewspaperIcon,
    PlayCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    businessName: { type: String, default: '' },
    /** Resolved public URL for the provider avatar (listing header photo). */
    avatarUrl: { type: String, default: '' },
    /** Hide the “Posts” title block (e.g. when wrapped in another page section). */
    hideIntro: { type: Boolean, default: false },
});

const hasPosts = computed(() => Array.isArray(props.posts) && props.posts.length > 0);

const fallbackAvatar = computed(() => {
    const name = props.businessName?.trim() || 'Provider';
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=3B95F3&color=fff&size=80`;
});

const headerAvatar = computed(() => (props.avatarUrl?.trim() ? props.avatarUrl : fallbackAvatar.value));

const formatDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatRelative = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const sec = Math.floor((Date.now() - d.getTime()) / 1000);
    if (sec < 45) return 'Just now';
    if (sec < 3600) return `${Math.floor(sec / 60)}m ago`;
    if (sec < 86400) return `${Math.floor(sec / 3600)}h ago`;
    if (sec < 604800) return `${Math.floor(sec / 86400)}d ago`;
    return formatDate(iso);
};

const embedFrameClass = (post) => {
    if (post.platform === 'tiktok') {
        return 'aspect-[9/16] max-h-[520px] w-full max-w-[340px] mx-auto min-h-[360px]';
    }
    if (post.platform === 'instagram') {
        return 'aspect-[4/5] min-h-[420px] w-full max-w-[420px] mx-auto';
    }
    return 'aspect-video w-full min-h-[200px]';
};

const articleHeadline = (post) => post.title?.trim() || 'Article';
</script>

<template>
    <div v-if="hasPosts" class="space-y-4">
        <div v-if="!hideIntro" class="flex items-end justify-between gap-3 px-0.5">
            <div>
                <h2 class="text-lg font-display font-bold text-slate-900">Posts</h2>
                <p class="text-sm text-slate-500">
                    Updates, videos, and reading {{ businessName ? `from ${businessName}` : 'from this provider' }}.
                </p>
            </div>
        </div>

        <div class="space-y-4">
            <article
                v-for="post in posts"
                :key="post.uuid"
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <!-- Feed header (Facebook-style) -->
                <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 sm:px-5 sm:py-4">
                    <img
                        :src="headerAvatar"
                        alt=""
                        class="h-10 w-10 shrink-0 rounded-full bg-slate-100 object-cover ring-2 ring-white"
                        loading="lazy"
                    />
                    <div class="min-w-0 flex-1 pt-0.5">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="truncate font-semibold text-slate-900">{{ businessName || 'Provider' }}</span>
                            <span class="text-slate-300">·</span>
                            <time
                                class="text-sm text-slate-500"
                                :datetime="post.created_at"
                                :title="formatDate(post.created_at)"
                            >{{ formatRelative(post.created_at) }}</time>
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700"
                            >
                                <PlayCircleIcon v-if="post.type === 'video'" class="h-3.5 w-3.5 text-primary-600" />
                                <NewspaperIcon v-else class="h-3.5 w-3.5 text-sky-600" />
                                {{ post.platform_label || (post.type === 'video' ? 'Video' : 'Article') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-4 pb-4 pt-3 sm:px-5 sm:pb-5 sm:pt-4">
                    <h3 v-if="post.title" class="text-base font-semibold leading-snug text-slate-900">
                        {{ post.title }}
                    </h3>
                    <p
                        v-if="post.caption"
                        class="mt-2 text-sm leading-relaxed text-slate-600 whitespace-pre-line"
                        :class="{ 'mt-3': !post.title }"
                    >
                        {{ post.caption }}
                    </p>

                    <!-- Video embed -->
                    <div
                        v-if="post.type === 'video' && post.can_embed && post.embed_src"
                        :class="['mt-4 overflow-hidden rounded-xl bg-black ring-1 ring-slate-200/80', embedFrameClass(post)]"
                    >
                        <iframe
                            :src="post.embed_src"
                            class="h-full w-full border-0"
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                            title="Embedded video"
                        />
                    </div>

                    <!-- Video thumbnail fallback (e.g. YouTube when embed blocked) -->
                    <a
                        v-else-if="post.type === 'video' && post.thumbnail_url"
                        :href="post.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group relative mt-4 block overflow-hidden rounded-xl ring-1 ring-slate-200/80"
                    >
                        <img
                            :src="post.thumbnail_url"
                            alt=""
                            class="aspect-video w-full object-cover transition duration-300 group-hover:opacity-95"
                            loading="lazy"
                        />
                        <span
                            class="absolute inset-0 flex items-center justify-center bg-slate-900/25 transition group-hover:bg-slate-900/35"
                        >
                            <span
                                class="flex h-14 w-14 items-center justify-center rounded-full bg-white/95 text-primary-600 shadow-lg"
                            >
                                <PlayCircleIcon class="h-9 w-9" />
                            </span>
                        </span>
                    </a>

                    <!-- Article link card -->
                    <a
                        v-if="post.type === 'article'"
                        :href="post.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 flex overflow-hidden rounded-xl border border-slate-200 bg-slate-50/80 text-left transition hover:border-primary-200 hover:bg-primary-50/30"
                    >
                        <div
                            class="flex w-12 shrink-0 items-center justify-center bg-slate-200/80 text-slate-500 sm:w-14"
                        >
                            <LinkIcon class="h-6 w-6 sm:h-7 sm:w-7" />
                        </div>
                        <div class="min-w-0 flex-1 px-3 py-3 sm:px-4 sm:py-3.5">
                            <p v-if="post.link_hostname" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                {{ post.link_hostname }}
                            </p>
                            <p class="truncate text-sm font-semibold text-slate-900 sm:text-base">
                                {{ articleHeadline(post) }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Tap to open
                                <template v-if="post.link_hostname"> · {{ post.link_hostname }}</template>
                            </p>
                        </div>
                    </a>

                    <!-- Open link (videos without embed / “other” platforms) -->
                    <a
                        v-if="post.type === 'video' && !(post.can_embed && post.embed_src) && !post.thumbnail_url"
                        :href="post.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-500"
                    >
                        Watch video
                        <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                    </a>

                    <a
                        v-if="post.type === 'video' && (post.can_embed || post.thumbnail_url)"
                        :href="post.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-primary-600"
                    >
                        Open on {{ post.platform_label || 'original site' }}
                        <ArrowTopRightOnSquareIcon class="h-4 w-4 opacity-70" />
                    </a>
                </div>
            </article>
        </div>
    </div>
</template>
