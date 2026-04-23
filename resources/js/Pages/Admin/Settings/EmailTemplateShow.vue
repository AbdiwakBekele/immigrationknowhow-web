<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    template: { type: Object, required: true },
    supportedTokens: { type: Array, default: () => [] },
    previewTokens: { type: Object, default: () => ({}) },
});

const showPreview = ref(false);
const flash = computed(() => usePage().props.flash ?? {});

const form = useForm({
    name: props.template.name ?? '',
    subject: props.template.subject ?? '',
    body: props.template.body ?? '',
    action_label: props.template.action_label ?? '',
    action_url: props.template.action_url ?? '',
    is_active: props.template.is_active ?? true,
});

const previewText = (value) => {
    let rendered = value ?? '';

    for (const [token, sample] of Object.entries(props.previewTokens)) {
        rendered = rendered.split(token).join(String(sample ?? ''));
    }

    return rendered;
};

const save = () => {
    form.patch(route('admin.email-templates.update', props.template.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit Template - ${template.title}`" />

    <AdminLayout>
        <div class="admin-page-container space-y-4">
            <section class="admin-hero-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Messaging settings
                        </p>
                        <h1 class="mt-2 admin-title">{{ template.title }}</h1>
                        <p class="admin-subtitle">
                            {{ template.event_label }} · {{ template.role_label }}
                        </p>
                        <Link
                            :href="route('admin.email-templates.index')"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <ArrowLeftIcon class="h-4 w-4" />
                            Back to templates list
                        </Link>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="showPreview = !showPreview"
                        >
                            {{ showPreview ? 'Hide preview' : 'Preview' }}
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-60"
                            @click="save"
                        >
                            Save template
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flash.success }}
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div class="grid gap-4">
                            <div class="grid gap-1.5">
                                <label class="text-sm font-medium text-gray-700">Email title</label>
                                <input v-model="form.name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="grid gap-1.5">
                                <label class="text-sm font-medium text-gray-700">Subject</label>
                                <input v-model="form.subject" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                <p v-if="form.errors.subject" class="text-xs text-red-600">{{ form.errors.subject }}</p>
                            </div>

                            <div class="grid gap-1.5">
                                <label class="text-sm font-medium text-gray-700">Body</label>
                                <textarea v-model="form.body" rows="12" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                <p v-if="form.errors.body" class="text-xs text-red-600">{{ form.errors.body }}</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <label class="text-sm font-medium text-gray-700">Action label</label>
                                    <input v-model="form.action_label" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div class="grid gap-1.5">
                                    <label class="text-sm font-medium text-gray-700">Action URL</label>
                                    <input v-model="form.action_url" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>

                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                                Template is active
                            </label>
                        </div>
                    </section>

                    <section v-if="showPreview" class="rounded-xl border border-sky-200 bg-sky-50 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-sky-700">Preview</h2>
                        <p class="mt-3 text-sm text-gray-700">
                            <span class="font-semibold text-gray-900">Subject:</span>
                            {{ previewText(form.subject) }}
                        </p>
                        <div class="mt-3 rounded-lg border border-sky-100 bg-white p-4">
                            <p class="whitespace-pre-wrap text-sm text-gray-800">{{ previewText(form.body) }}</p>
                            <div v-if="form.action_label || form.action_url" class="mt-4">
                                <p class="text-xs font-medium text-gray-500">Action</p>
                                <p class="text-sm text-gray-800">{{ previewText(form.action_label) || 'Action button' }}</p>
                                <p class="text-xs text-gray-500">{{ previewText(form.action_url) }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Placeholders</h2>
                    <p class="mt-1 text-xs text-gray-500">Use these tokens inside subject/body/action fields.</p>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="token in supportedTokens"
                            :key="token"
                            class="rounded-lg bg-gray-50 px-3 py-2 text-xs font-medium text-gray-800"
                        >
                            {{ token }}
                        </li>
                    </ul>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>
