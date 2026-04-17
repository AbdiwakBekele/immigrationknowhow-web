<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ShareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    share: {
        type: Object,
        required: true,
        validator: (v) => v && typeof v.url === 'string' && typeof v.title === 'string',
    },
    /** Tailwind placement: menu opens under the trigger */
    menuAlign: { type: String, default: 'right' }, // 'left' | 'right'
    showWhatsApp: { type: Boolean, default: true },
    /** 'icon' = compact glyph; 'outline' = bordered pill (e.g. next to Preview on edit profile) */
    triggerVariant: { type: String, default: 'icon' },
});

const open = ref(false);
const root = ref(null);

const encodedUrl = computed(() => encodeURIComponent(props.share.url));
const tweetText = computed(() => encodeURIComponent(props.share.title));

const links = computed(() => ({
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`,
    x: `https://twitter.com/intent/tweet?url=${encodedUrl.value}&text=${tweetText.value}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl.value}`,
    whatsapp: `https://wa.me/?text=${encodeURIComponent(`${props.share.title}\n${props.share.url}`)}`,
    email: `mailto:?subject=${encodeURIComponent(props.share.title)}&body=${encodeURIComponent(`${props.share.description}\n\n${props.share.url}`)}`,
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
            v-if="triggerVariant === 'icon'"
            type="button"
            class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Share profile"
            @click.stop="open = !open"
        >
            <ShareIcon class="h-5 w-5" />
        </button>
        <button
            v-else
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            :aria-expanded="open"
            aria-haspopup="true"
            @click.stop="open = !open"
        >
            <ShareIcon class="h-4 w-4" />
            Share
        </button>

        <div
            v-show="open"
            role="menu"
            :class="[
                'absolute top-full z-30 mt-1 min-w-[11.5rem] rounded-xl border border-slate-200 bg-white py-1 shadow-lg',
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
