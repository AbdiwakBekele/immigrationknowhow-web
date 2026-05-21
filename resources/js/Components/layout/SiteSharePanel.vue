<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ShareIcon } from '@heroicons/vue/24/outline';
import { siteShareMeta } from '@/utils/siteShare';

const props = defineProps({
    /** 'icon' | 'outline' | 'footer' */
    variant: { type: String, default: 'icon' },
    menuAlign: { type: String, default: 'right' },
    showWhatsApp: { type: Boolean, default: true },
    /** Override share target (defaults to current page URL). */
    url: { type: String, default: '' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});

const page = usePage();
const open = ref(false);
const root = ref(null);

const share = computed(() => {
    const branding = page.props.branding ?? {};
    const company = branding.company_name || 'ImmigrationKnowHow';
    const tagline = branding.site_tagline || branding.footer_tagline || '';

    return siteShareMeta({
        url: props.url,
        title: props.title || company,
        description: props.description || tagline || `Explore ${company}`,
    });
});

const encodedUrl = computed(() => encodeURIComponent(share.value.url));
const tweetText = computed(() => encodeURIComponent(share.value.title));

const links = computed(() => ({
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`,
    x: `https://twitter.com/intent/tweet?url=${encodedUrl.value}&text=${tweetText.value}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl.value}`,
    whatsapp: `https://wa.me/?text=${encodeURIComponent(`${share.value.title}\n${share.value.url}`)}`,
    email: `mailto:?subject=${encodeURIComponent(share.value.title)}&body=${encodeURIComponent(`${share.value.description}\n\n${share.value.url}`)}`,
}));

const menuPositionClass = computed(() =>
    props.menuAlign === 'left' ? 'left-0' : 'right-0',
);

const onDocClick = (e) => {
    if (!open.value || !root.value) return;
    if (!root.value.contains(e.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div ref="root" class="relative inline-flex">
        <button
            v-if="variant === 'icon'"
            type="button"
            class="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Share this page"
            @click.stop="open = !open"
        >
            <ShareIcon class="h-5 w-5" />
        </button>
        <button
            v-else-if="variant === 'outline'"
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            :aria-expanded="open"
            aria-haspopup="true"
            @click.stop="open = !open"
        >
            <ShareIcon class="h-4 w-4" />
            Share
        </button>
        <button
            v-else
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
            :aria-expanded="open"
            aria-haspopup="true"
            @click.stop="open = !open"
        >
            <ShareIcon class="h-4 w-4" />
            Share site
        </button>

        <div
            v-show="open"
            role="menu"
            :class="[
                'absolute top-full z-40 mt-1 min-w-[11.5rem] rounded-xl border border-slate-200 bg-white py-1 shadow-lg',
                menuPositionClass,
            ]"
            @click.stop
        >
            <a
                :href="links.facebook"
                target="_blank"
                rel="noopener noreferrer"
                role="menuitem"
                class="block px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="open = false"
            >
                Facebook
            </a>
            <a
                :href="links.x"
                target="_blank"
                rel="noopener noreferrer"
                role="menuitem"
                class="block px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="open = false"
            >
                X (Twitter)
            </a>
            <a
                :href="links.linkedin"
                target="_blank"
                rel="noopener noreferrer"
                role="menuitem"
                class="block px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="open = false"
            >
                LinkedIn
            </a>
            <a
                v-if="showWhatsApp"
                :href="links.whatsapp"
                target="_blank"
                rel="noopener noreferrer"
                role="menuitem"
                class="block px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="open = false"
            >
                WhatsApp
            </a>
            <a
                :href="links.email"
                role="menuitem"
                class="block px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="open = false"
            >
                Email
            </a>
        </div>
    </div>
</template>
