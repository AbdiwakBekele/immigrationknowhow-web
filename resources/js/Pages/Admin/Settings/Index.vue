<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import { Cog6ToothIcon, PhotoIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
});
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    company_name: props.settings.company_name ?? 'ImmigrationKnowHow',
    support_email: props.settings.support_email ?? '',
    support_phone: props.settings.support_phone ?? '',
    support_address: props.settings.support_address ?? '',
    site_tagline: props.settings.site_tagline ?? '',
    footer_tagline: props.settings.footer_tagline ?? '',
    maintenance_mode: props.settings.maintenance_mode ?? false,
    reviews_auto_approve: props.settings.reviews_auto_approve ?? false,
    email_notifications: props.settings.email_notifications ?? true,
    new_provider_alerts: props.settings.new_provider_alerts ?? true,
    site_logo: null,
    admin_logo: null,
    remove_site_logo: false,
    remove_admin_logo: false,
});

const siteLogoPreview = computed(() => form.site_logo ? URL.createObjectURL(form.site_logo) : (form.remove_site_logo ? null : props.settings.site_logo_url));
const adminLogoPreview = computed(() => form.admin_logo ? URL.createObjectURL(form.admin_logo) : (form.remove_admin_logo ? null : props.settings.admin_logo_url));

const updateFile = (field, event) => {
    const file = event.target.files?.[0] ?? null;
    form[field] = file;

    if (field === 'site_logo' && file) {
        form.remove_site_logo = false;
    }

    if (field === 'admin_logo' && file) {
        form.remove_admin_logo = false;
    }
};

const save = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post('/admin/settings', {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <div class="admin-page-container space-y-4">
            <section class="admin-hero-card">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Platform configuration
                </p>
                <h1 class="mt-2 admin-title">General settings</h1>
                <p class="admin-subtitle">
                    Manage global branding and platform behavior from the super-admin console.
                </p>
            </section>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center gap-2">
                    <Cog6ToothIcon class="h-5 w-5 text-sky-600" />
                    <p class="font-semibold text-gray-900">Branding</p>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <Input v-model="form.company_name" label="Company name" :error="form.errors.company_name" required />

                    <div class="grid gap-4 lg:grid-cols-2">
                        <Input v-model="form.support_email" type="email" label="Support email" :error="form.errors.support_email" placeholder="support@example.com" />
                        <Input v-model="form.support_phone" label="Support phone" :error="form.errors.support_phone" placeholder="+1 (555) 123-4567" />
                    </div>

                    <Input v-model="form.support_address" label="Support address" :error="form.errors.support_address" placeholder="123 Main Street, Suite 100, Miami, FL" />
                    <Input v-model="form.site_tagline" label="Site tagline" :error="form.errors.site_tagline" placeholder="Short intro shown on the website and auth screens" />

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-neutral-700">Footer tagline</label>
                        <textarea v-model="form.footer_tagline" rows="3" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm transition-all duration-200 placeholder:text-neutral-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="Footer message shown across the public site"></textarea>
                        <p v-if="form.errors.footer_tagline" class="text-xs text-red-600">{{ form.errors.footer_tagline }}</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-center gap-2">
                                <PhotoIcon class="h-5 w-5 text-sky-600" />
                                <p class="font-semibold text-gray-900">Public site logo</p>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Used on the website, user-facing layouts, auth flows, provider portal, and affiliate portal.</p>
                            <div class="mt-4 flex h-28 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50">
                                <img v-if="siteLogoPreview" :src="siteLogoPreview" alt="Public logo preview" class="max-h-20 max-w-full object-contain" />
                                <span v-else class="text-sm text-gray-400">No public logo uploaded yet</span>
                            </div>
                            <input type="file" accept="image/*,.svg" class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:font-medium file:text-sky-700 hover:file:bg-sky-100" @change="updateFile('site_logo', $event)" />
                            <p class="mt-2 text-xs text-gray-500">PNG, JPG, WEBP, GIF, or SVG up to 2MB.</p>
                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="form.remove_site_logo" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                                Remove current public logo
                            </label>
                            <p v-if="form.errors.site_logo" class="mt-2 text-sm text-rose-600">{{ form.errors.site_logo }}</p>
                        </div>

                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-center gap-2">
                                <PhotoIcon class="h-5 w-5 text-indigo-600" />
                                <p class="font-semibold text-gray-900">Admin panel logo</p>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Used only in admin views, so it can be different from the public-facing brand mark.</p>
                            <div class="mt-4 flex h-28 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50">
                                <img v-if="adminLogoPreview" :src="adminLogoPreview" alt="Admin logo preview" class="max-h-20 max-w-full object-contain" />
                                <span v-else class="text-sm text-gray-400">No admin logo uploaded yet</span>
                            </div>
                            <input type="file" accept="image/*,.svg" class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" @change="updateFile('admin_logo', $event)" />
                            <p class="mt-2 text-xs text-gray-500">PNG, JPG, WEBP, GIF, or SVG up to 2MB.</p>
                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="form.remove_admin_logo" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                                Remove current admin logo
                            </label>
                            <p v-if="form.errors.admin_logo" class="mt-2 text-sm text-rose-600">{{ form.errors.admin_logo }}</p>
                        </div>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center gap-2">
                    <Cog6ToothIcon class="h-5 w-5 text-sky-600" />
                    <p class="font-semibold text-gray-900">Platform configuration</p>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">Maintenance mode</span>
                        <input v-model="form.maintenance_mode" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">Auto-approve reviews</span>
                        <input v-model="form.reviews_auto_approve" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">Email notifications</span>
                        <input v-model="form.email_notifications" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                        <span class="text-sm font-medium text-gray-700">New provider alerts</span>
                        <input v-model="form.new_provider_alerts" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-sky-600" />
                    </label>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-sky-700 disabled:opacity-60"
                        >
                            Save changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
