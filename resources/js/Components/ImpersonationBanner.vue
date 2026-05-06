<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { EyeIcon } from '@heroicons/vue/24/solid';

const page = usePage();
const impersonation = computed(() => page.props.impersonation);
const leaving = ref(false);

const leave = () => {
    leaving.value = true;
    router.post(route('impersonation.leave'), {}, {
        replace: true,
        onFinish: () => {
            leaving.value = false;
        },
    });
};
</script>

<template>
    <div
        v-if="impersonation"
        class="mb-6 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        role="status"
    >
        <div class="flex min-w-0 items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                <EyeIcon class="h-5 w-5" />
            </div>
            <div class="min-w-0 text-sm leading-relaxed text-amber-950">
                <p class="font-semibold">
                    Impersonation mode
                </p>
                <p class="mt-1 text-amber-900/95">
                    Viewing as
                    <span class="font-medium">{{ impersonation.viewing_as_full_name }}</span>
                    <span class="text-amber-800/85">({{ impersonation.viewing_as_email }})</span>.
                    Administrator account:
                    <span class="font-medium">{{ impersonation.original_full_name }}</span>
                    <span class="text-amber-800/85">({{ impersonation.original_email }})</span>.
                </p>
            </div>
        </div>
        <button
            type="button"
            class="shrink-0 rounded-xl bg-amber-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-950 disabled:opacity-60"
            :disabled="leaving"
            @click="leave"
        >
            Return to Admin
        </button>
    </div>
</template>
