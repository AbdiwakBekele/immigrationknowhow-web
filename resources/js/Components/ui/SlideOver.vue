<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    widthClass: {
        type: String,
        default: 'max-w-3xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const close = () => {
    if (!props.closeable) return;
    emit('close');
};

const handleKeydown = (event) => {
    if (event.key === 'Escape' && props.open && props.closeable) {
        close();
    }
};

watch(
    () => props.open,
    (isOpen) => {
        document.body.classList.toggle('overflow-hidden', isOpen);
    }
);

onBeforeUnmount(() => {
    document.body.classList.remove('overflow-hidden');
});

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeydown);
    onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
}

const panelClasses = computed(() => [
    'relative ml-auto flex h-full w-full flex-col bg-white shadow-[0_20px_60px_-20px_rgba(15,23,42,0.35)] transition-transform duration-300',
    props.widthClass,
    props.open ? 'translate-x-0' : 'translate-x-full',
]);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[90]"
            aria-modal="true"
            role="dialog"
        >
            <div
                class="absolute inset-0 bg-slate-950/45 backdrop-blur-[2px]"
                @click="close"
            />

            <div class="absolute inset-y-0 right-0 flex w-full justify-end">
                <div :class="panelClasses">
                    <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
                                    {{ title }}
                                </h2>
                                <p
                                    v-if="description"
                                    class="mt-2 text-sm leading-6 text-slate-500"
                                >
                                    {{ description }}
                                </p>
                            </div>

                            <button
                                v-if="closeable"
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                @click="close"
                            >
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <slot />
                    </div>

                    <div
                        v-if="$slots.footer"
                        class="border-t border-slate-200 bg-white px-6 py-4 sm:px-8"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>