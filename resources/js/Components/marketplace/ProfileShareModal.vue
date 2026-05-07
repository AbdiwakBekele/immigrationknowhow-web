<script setup>
import { computed, ref, watch } from 'vue';
import { XMarkIcon, ClipboardDocumentIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    share: {
        type: Object,
        required: true,
        validator: (v) => v && typeof v.url === 'string' && typeof v.title === 'string',
    },
});

const emit = defineEmits(['update:modelValue']);

const open = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const copied = ref(false);
let copyTimer = null;

watch(
    () => props.modelValue,
    (v) => {
        if (!v) {
            copied.value = false;
            if (copyTimer) {
                clearTimeout(copyTimer);
                copyTimer = null;
            }
        }
    },
);

const encodedUrl = computed(() => encodeURIComponent(props.share.url));
const tweetText = computed(() => encodeURIComponent(props.share.title));

const links = computed(() => ({
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`,
    x: `https://twitter.com/intent/tweet?url=${encodedUrl.value}&text=${tweetText.value}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl.value}`,
    whatsapp: `https://wa.me/?text=${encodeURIComponent(`${props.share.title}\n${props.share.url}`)}`,
    email: `mailto:?subject=${encodeURIComponent(props.share.title)}&body=${encodeURIComponent(`${props.share.description || ''}\n\n${props.share.url}`)}`,
}));

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.share.url);
        copied.value = true;
        if (copyTimer) clearTimeout(copyTimer);
        copyTimer = setTimeout(() => {
            copied.value = false;
            copyTimer = null;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const close = () => {
    open.value = false;
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="share-profile-title"
        >
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="close"></div>
            <div
                class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                @click.stop
            >
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 id="share-profile-title" class="text-lg font-display font-bold text-slate-900">
                            Share profile
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">Copy the link or share on social media.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close"
                        @click="close"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Profile link</p>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                readonly
                                class="min-w-0 flex-1 truncate rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800"
                                :value="share.url"
                            />
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                                @click="copyLink"
                            >
                                <CheckIcon v-if="copied" class="h-4 w-4" />
                                <ClipboardDocumentIcon v-else class="h-4 w-4" />
                                {{ copied ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Social</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <a
                                :href="links.facebook"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                @click="close"
                            >
                                Facebook
                            </a>
                            <a
                                :href="links.x"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                @click="close"
                            >
                                X
                            </a>
                            <a
                                :href="links.linkedin"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                @click="close"
                            >
                                LinkedIn
                            </a>
                            <a
                                :href="links.whatsapp"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                                @click="close"
                            >
                                WhatsApp
                            </a>
                            <a
                                :href="links.email"
                                class="col-span-2 flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 sm:col-span-1"
                                @click="close"
                            >
                                Email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
