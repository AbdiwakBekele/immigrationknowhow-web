<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <Head title="DV Lottery" />

    <AppLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                        Immigration resource
                    </p>
                        <h1 class="mt-2 admin-title">DV Lottery</h1>
                        <p class="admin-subtitle">
                            Official Diversity Visa information for users and families.
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Official Website</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Always submit applications through the official U.S. Department of State portal.
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a
                        href="https://dvprogram.state.gov/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                    >
                        Open Official DV Lottery Website
                    </a>
                    <span class="text-xs text-slate-500">https://dvprogram.state.gov/</span>
                </div>
            </section>

            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Never use unofficial third-party links for DV submissions. This page links only to the official government URL.
            </section>
        </div>
    </AppLayout>
</template>
