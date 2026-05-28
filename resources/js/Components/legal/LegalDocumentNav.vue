<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { getLegalScrollRoot, scrollToLegalSection } from '@/utils/legalScroll';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    activeId: {
        type: String,
        default: 'overview',
    },
    scrollContainer: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:activeId']);

const scrollToSection = (id) => {
    scrollToLegalSection(id, props.scrollContainer ?? null);
    emit('update:activeId', id);
};

const navRef = ref(null);
let observer = null;

onMounted(() => {
    const targets = props.items
        .map((item) => document.getElementById(item.id))
        .filter(Boolean);

    if (targets.length === 0) {
        return;
    }

    const root = getLegalScrollRoot(props.scrollContainer ?? null);

    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((e) => e.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

            if (visible.length > 0 && visible[0].target.id) {
                emit('update:activeId', visible[0].target.id);
            }
        },
        {
            root,
            rootMargin: root ? '-8% 0px -55% 0px' : '-20% 0px -55% 0px',
            threshold: [0, 0.15, 0.5, 1],
        },
    );

    targets.forEach((el) => observer.observe(el));
});

onUnmounted(() => {
    observer?.disconnect();
});
</script>

<template>
    <nav ref="navRef" class="legal-doc-nav" aria-label="On this page">
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
            On this page
        </p>
        <ul class="space-y-0.5">
            <li v-for="item in items" :key="item.id">
                <button
                    type="button"
                    class="legal-doc-nav__link w-full rounded-xl px-2 py-2 text-left text-sm leading-snug transition"
                    :class="
                        activeId === item.id
                            ? 'bg-sky-50 font-semibold text-sky-800 ring-1 ring-sky-200/80'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                    "
                    @click="scrollToSection(item.id)"
                >
                    {{ item.label }}
                </button>
            </li>
        </ul>
    </nav>
</template>
