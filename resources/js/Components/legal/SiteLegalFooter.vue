<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    variant: {
        type: String,
        default: 'dark',
    },
    showCopyright: {
        type: Boolean,
        default: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    fullWidth: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <footer
        :class="[
            'site-legal-footer w-full',
            variant === 'dark'
                ? 'border-t border-slate-800 bg-slate-900 text-slate-400'
                : 'border-t border-slate-200 bg-white text-slate-600',
        ]"
    >
        <div
            :class="[
                'flex w-full flex-col gap-3 py-4 text-center sm:flex-row sm:justify-between sm:text-left',
                fullWidth ? 'max-w-none px-3 sm:px-4 lg:px-6' : 'mx-auto max-w-7xl',
                !fullWidth && (compact ? 'px-2 sm:px-3 lg:px-4' : 'px-4 sm:px-6 lg:px-8'),
            ]"
        >
            <p v-if="showCopyright" class="text-sm">
                © {{ new Date().getFullYear() }}
                {{ $page.props.branding?.company_name || 'ImmigrationKnowHow' }}. All rights reserved.
            </p>
            <div v-if="$slots.extra" class="flex items-center justify-center">
                <slot name="extra" />
            </div>
            <nav
                class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm font-medium"
                aria-label="Legal"
            >
                <Link
                    :href="route('legal.privacy')"
                    :class="[
                        'transition-colors',
                        variant === 'dark' ? 'text-slate-300 hover:text-white' : 'text-primary-700 hover:text-primary-800',
                    ]"
                >
                    Privacy Policy
                </Link>
                <span :class="variant === 'dark' ? 'text-slate-600' : 'text-slate-300'" aria-hidden="true">|</span>
                <Link
                    :href="route('legal.terms')"
                    :class="[
                        'transition-colors',
                        variant === 'dark' ? 'text-slate-300 hover:text-white' : 'text-primary-700 hover:text-primary-800',
                    ]"
                >
                    Terms of Service
                </Link>
            </nav>
        </div>
    </footer>
</template>
