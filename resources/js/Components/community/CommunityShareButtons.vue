<script setup>
import { computed, ref, watch } from 'vue';
import { CheckIcon, ClipboardDocumentIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';
import { communityPostShareUrl, copyTextToClipboard } from '@/utils/community';

const props = defineProps({
    postId: {
        type: Number,
        required: true,
    },
    postTitle: {
        type: String,
        default: '',
    },
    buttonClass: {
        type: String,
        default: 'inline-flex items-center justify-center rounded-xl border border-[#d7e0ee] px-3 py-2 text-sm font-semibold text-[#334155] hover:bg-slate-50',
    },
});

const emit = defineEmits(['shared']);

const linkCopied = ref(false);
let linkCopiedTimer = null;

const shareUrl = computed(() => communityPostShareUrl(props.postId));
const encodedUrl = computed(() => encodeURIComponent(shareUrl.value));
const encodedTitle = computed(() => encodeURIComponent(props.postTitle || ''));

const links = computed(() => ({
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`,
    x: `https://twitter.com/intent/tweet?url=${encodedUrl.value}&text=${encodedTitle.value}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl.value}`,
    whatsapp: `https://wa.me/?text=${encodedTitle.value}%20${encodedUrl.value}`,
    email: `mailto:?subject=${encodedTitle.value}&body=${encodedUrl.value}`,
}));

watch(
    () => props.postId,
    () => {
        linkCopied.value = false;
        if (linkCopiedTimer) {
            clearTimeout(linkCopiedTimer);
            linkCopiedTimer = null;
        }
    },
);

function recordShare() {
    emit('shared');
}

async function copyLink() {
    const copied = await copyTextToClipboard(shareUrl.value);
    if (!copied) {
        return;
    }

    linkCopied.value = true;
    if (linkCopiedTimer) {
        clearTimeout(linkCopiedTimer);
    }
    linkCopiedTimer = setTimeout(() => {
        linkCopied.value = false;
        linkCopiedTimer = null;
    }, 2000);

    recordShare();
}
</script>

<template>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        <a
            :href="links.facebook"
            target="_blank"
            rel="noopener noreferrer"
            :class="[buttonClass, 'text-[#1d4ed8] hover:bg-blue-50']"
            aria-label="Share on Facebook"
        >
            f
        </a>
        <a
            :href="links.x"
            target="_blank"
            rel="noopener noreferrer"
            :class="[buttonClass, 'text-[#0f172a] hover:bg-slate-50']"
            aria-label="Share on X"
        >
            X
        </a>
        <a
            :href="links.linkedin"
            target="_blank"
            rel="noopener noreferrer"
            :class="[buttonClass, 'text-[#0a66c2] hover:bg-blue-50']"
            aria-label="Share on LinkedIn"
        >
            in
        </a>
        <a
            :href="links.whatsapp"
            target="_blank"
            rel="noopener noreferrer"
            :class="[buttonClass, 'text-[#16a34a] hover:bg-emerald-50']"
            aria-label="Share on WhatsApp"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.884 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
        </a>
        <a
            :href="links.email"
            :class="buttonClass"
            aria-label="Share via email"
        >
            <EnvelopeIcon class="h-4 w-4 shrink-0" />
        </a>
        <button
            type="button"
            :class="buttonClass"
            :aria-label="linkCopied ? 'Link copied' : 'Copy link'"
            @click="copyLink"
        >
            <CheckIcon v-if="linkCopied" class="h-4 w-4 shrink-0 text-emerald-600" />
            <ClipboardDocumentIcon v-else class="h-4 w-4 shrink-0" />
        </button>
    </div>
</template>
