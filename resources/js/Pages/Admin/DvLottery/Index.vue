<script setup>
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    title: props.content.title ?? '',
    short_description: props.content.short_description ?? '',
    official_url: props.content.official_url ?? '',
    open_from: props.content.open_from ?? '',
    open_to: props.content.open_to ?? '',
    show_in_menu_after_close: Boolean(props.content.show_in_menu_after_close),
});

const save = () => {
    form.patch(route('admin.dv-lottery.update'), {
        preserveScroll: true,
    });
};

const closeNow = () => {
    if (!window.confirm('Mark DV Lottery as closed now?')) {
        return;
    }
    router.post(route('admin.dv-lottery.close-now'), {}, { preserveScroll: true });
};

const clearDvLottery = () => {
    if (!window.confirm('Delete/clear DV Lottery setup? This will hide it from menus until reconfigured.')) {
        return;
    }
    router.delete(route('admin.dv-lottery.destroy'), { preserveScroll: true });
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
                    <h2 class="text-lg font-semibold text-slate-900">DV Lottery settings</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Configure title, short description, official URL, and strict open/close date window.
                    </p>

                    <div class="mt-5 grid gap-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Page title</label>
                            <input v-model="form.title" type="text" class="input w-full" maxlength="120" />
                            <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Short description</label>
                            <input v-model="form.short_description" type="text" class="input w-full" maxlength="255" />
                            <p v-if="form.errors.short_description" class="mt-1 text-xs text-rose-600">{{ form.errors.short_description }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Official URL</label>
                            <input v-model="form.official_url" type="url" class="input w-full" maxlength="255" />
                            <p v-if="form.errors.official_url" class="mt-1 text-xs text-rose-600">{{ form.errors.official_url }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Open from</label>
                                <input v-model="form.open_from" type="date" class="input w-full" />
                                <p v-if="form.errors.open_from" class="mt-1 text-xs text-rose-600">{{ form.errors.open_from }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Open to</label>
                                <input v-model="form.open_to" type="date" class="input w-full" />
                                <p v-if="form.errors.open_to" class="mt-1 text-xs text-rose-600">{{ form.errors.open_to }}</p>
                            </div>
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                            <input v-model="form.show_in_menu_after_close" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            <span class="text-sm text-slate-700">
                                Keep DV Lottery in menu after close. If unchecked, it disappears from menu once `Open to` passes.
                            </span>
                        </label>

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
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100"
                        @click="closeNow"
                    >
                        Close DV Lottery Now
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-900 transition hover:bg-rose-100"
                        @click="clearDvLottery"
                    >
                        Delete / Clear DV Lottery
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
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Short description</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.short_description }}</dd>
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
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open from</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.open_from }}</dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open to</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.open_to }}</dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Keep in menu after close</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.show_in_menu_after_close ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="grid gap-2 px-4 py-3 md:grid-cols-[180px_1fr]">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current status</dt>
                        <dd class="text-sm text-slate-900">{{ props.content.status_message || 'Open window active or not yet in closing week.' }}</dd>
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
