<script setup>
import { Head } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';

defineProps({
    content: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="DV Lottery" />

    <ProviderLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Immigration resource
                        </p>
                        <h1 class="mt-2 admin-title">{{ content.title || 'DV Lottery' }}</h1>
                        <p class="admin-subtitle">
                            {{ content.short_description || 'Official Diversity Visa information for providers and their clients.' }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Official Website</h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ content.description || 'Always submit applications through the official U.S. Department of State portal.' }}
                </p>
                <p class="mt-2 text-sm font-medium text-slate-700">
                    DV entry period:
                    <span class="font-semibold">{{ content.open_from || 'Not set' }}</span>
                    -
                    <span class="font-semibold">{{ content.open_to || 'Not set' }}</span>
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a
                        :href="content.official_url || 'https://dvprogram.state.gov/'"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                    >
                        {{ content.cta_label || 'Open Official DV Lottery Website' }}
                    </a>
                    <span class="text-xs text-slate-500">{{ content.official_url || 'https://dvprogram.state.gov/' }}</span>
                </div>
            </section>

            <section
                :class="[
                    'rounded-xl p-4 text-sm',
                    content.is_closed
                        ? 'border border-rose-200 bg-rose-50 text-rose-900'
                        : content.is_closing_soon
                            ? 'border border-amber-200 bg-amber-50 text-amber-900'
                            : 'border border-slate-200 bg-slate-50 text-slate-700',
                ]"
            >
                {{ content.status_message || 'DV Lottery is currently open.' }}
            </section>
        </div>
    </ProviderLayout>
</template>
