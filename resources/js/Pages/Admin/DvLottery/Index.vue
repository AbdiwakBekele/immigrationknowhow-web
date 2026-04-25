<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    title: props.content.title ?? '',
    subtitle: props.content.subtitle ?? '',
    description: props.content.description ?? '',
    official_url: props.content.official_url ?? '',
    cta_label: props.content.cta_label ?? '',
    warning_text: props.content.warning_text ?? '',
});

const save = () => {
    form.patch(route('admin.dv-lottery.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="DV Lottery" />

    <AdminLayout>
        <div class="admin-page-container space-y-4">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Immigration resource
                        </p>
                        <h1 class="mt-2 admin-title">DV Lottery</h1>
                        <p class="admin-subtitle">
                            Official Diversity Visa information for administrators and platform guidance.
                        </p>
                    </div>
                </div>
            </section>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>

            <form class="space-y-4" @submit.prevent="save">
                <section class="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">DV Lottery content</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        These values are shown on Admin, User, and Provider DV Lottery pages.
                    </p>

                    <div class="mt-5 grid gap-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Page title</label>
                            <input v-model="form.title" type="text" class="input w-full" maxlength="120" />
                            <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Page subtitle</label>
                            <input v-model="form.subtitle" type="text" class="input w-full" maxlength="255" />
                            <p v-if="form.errors.subtitle" class="mt-1 text-xs text-rose-600">{{ form.errors.subtitle }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Description</label>
                            <textarea v-model="form.description" rows="3" class="input w-full"></textarea>
                            <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Official URL</label>
                                <input v-model="form.official_url" type="url" class="input w-full" maxlength="255" />
                                <p v-if="form.errors.official_url" class="mt-1 text-xs text-rose-600">{{ form.errors.official_url }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Button label</label>
                                <input v-model="form.cta_label" type="text" class="input w-full" maxlength="120" />
                                <p v-if="form.errors.cta_label" class="mt-1 text-xs text-rose-600">{{ form.errors.cta_label }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Warning text</label>
                            <textarea v-model="form.warning_text" rows="3" class="input w-full"></textarea>
                            <p v-if="form.errors.warning_text" class="mt-1 text-xs text-rose-600">{{ form.errors.warning_text }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Save DV Lottery Content' }}
                    </button>
                </div>
            </form>

            <section class="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Current display list</h2>
                <p class="mt-2 text-sm text-slate-600">
                    This is the current saved DV Lottery content shown in the app.
                </p>

                <dl class="mt-5 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Title</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.title }}</dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subtitle</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.subtitle }}</dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.description }}</dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Official URL</dt>
                        <dd class="text-sm text-sky-700">
                            <a :href="props.content.official_url" target="_blank" rel="noopener noreferrer" class="underline">
                                {{ props.content.official_url }}
                            </a>
                        </dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Button Label</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.cta_label }}</dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Warning</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.warning_text }}</dd>
                    </div>
                </dl>

                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    Displays on:
                    <span class="font-semibold">/admin/dv-lottery</span>,
                    <span class="font-semibold">/user/dv-lottery</span>,
                    <span class="font-semibold">/provider/dv-lottery</span>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
