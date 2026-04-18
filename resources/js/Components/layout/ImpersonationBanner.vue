<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ArrowUturnLeftIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const impersonation = computed(() => page.props.impersonation ?? { active: false });
const viewedAsName = computed(() => page.props.auth?.user?.full_name ?? 'this user');

const exit = () => {
    router.post(route('impersonate.stop'));
};
</script>

<template>
    <div
        v-if="impersonation.active"
        class="relative z-[60] border-b border-amber-200/90 bg-amber-50 px-4 py-2.5 text-center text-sm text-amber-950 shadow-sm"
    >
        <span class="font-medium">Viewing as {{ viewedAsName }}</span>
        <span v-if="impersonation.original_admin_name" class="text-amber-800">
            — signed in as {{ impersonation.original_admin_name }}
        </span>
        <button
            type="button"
            class="ml-3 inline-flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-1 text-xs font-semibold text-white shadow-sm hover:bg-amber-700"
            @click="exit"
        >
            <ArrowUturnLeftIcon class="h-4 w-4 shrink-0" />
            Return to admin
        </button>
    </div>
</template>
