<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { BookOpenIcon, DocumentTextIcon, SpeakerWaveIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <Head title="Library" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Library</h1>
                <p class="mt-1 text-gray-500">Organize ebooks, audiobooks, and educational resources.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-indigo-100 p-2">
                        <BookOpenIcon class="h-5 w-5 text-indigo-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Total items</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <DocumentTextIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Ebooks</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-emerald-100 p-2">
                        <SpeakerWaveIcon class="h-5 w-5 text-emerald-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">--</p>
                    <p class="text-sm text-gray-500">Audiobooks</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-gray-800">Library page is active</p>
                <p class="mt-2 text-gray-500">The sidebar link now opens this page correctly.</p>
            </div>
        </div>
    </AdminLayout>
</template>
