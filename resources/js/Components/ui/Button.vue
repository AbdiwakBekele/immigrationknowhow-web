<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'primary',
    },
    size: {
        type: String,
        default: 'md',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const isDisabled = computed(() => props.disabled || props.loading);

const baseClasses =
    'inline-flex items-center justify-center gap-2 rounded-2xl font-semibold transition-all duration-200 focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60';

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'secondary':
            return 'border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:ring-slate-100';
        case 'ghost':
            return 'bg-transparent text-slate-700 hover:bg-slate-100 focus:ring-slate-100';
        case 'primary':
        default:
            return 'border border-blue-600 bg-blue-600 text-white shadow-sm hover:bg-blue-700 hover:border-blue-700 focus:ring-blue-100';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-4 py-2.5 text-sm';
        case 'lg':
            return 'px-6 py-4 text-base';
        case 'md':
        default:
            return 'px-5 py-3.5 text-base';
    }
});
</script>

<template>
    <button
        :type="type"
        :disabled="isDisabled"
        :class="[baseClasses, variantClasses, sizeClasses]"
    >
        <svg
            v-if="loading"
            class="h-5 w-5 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v8H4z"
            />
        </svg>

        <slot />
    </button>
</template>