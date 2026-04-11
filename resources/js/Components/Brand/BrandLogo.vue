<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    context: {
        type: String,
        default: 'site',
    },
    subtitle: {
        type: String,
        default: '',
    },
    showName: {
        type: Boolean,
        default: true,
    },
    containerClass: {
        type: String,
        default: 'flex items-center gap-3',
    },
    markClass: {
        type: String,
        default: 'flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white shadow-sm',
    },
    imageClass: {
        type: String,
        default: 'h-full w-full object-contain',
    },
    initialsClass: {
        type: String,
        default: 'font-bold text-sm uppercase tracking-wide',
    },
    nameClass: {
        type: String,
        default: 'block text-sm font-semibold text-slate-900',
    },
    subtitleClass: {
        type: String,
        default: 'block text-xs text-slate-500',
    },
});

const page = usePage();

const branding = computed(() => page.props.branding || {});
const companyName = computed(() => branding.value.company_name || 'ImmigrationKnowHow');
const logoUrl = computed(() => props.context === 'admin' ? branding.value.admin_logo_url : branding.value.site_logo_url);
const initials = computed(() => {
    return companyName.value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase() || 'IK';
});
</script>

<template>
    <div :class="containerClass">
        <div :class="markClass">
            <img v-if="logoUrl" :src="logoUrl" :alt="companyName" :class="imageClass" />
            <span v-else :class="initialsClass">{{ initials }}</span>
        </div>

        <div v-if="showName" class="min-w-0">
            <span :class="nameClass">{{ companyName }}</span>
            <span v-if="subtitle" :class="subtitleClass">{{ subtitle }}</span>
        </div>
    </div>
</template>