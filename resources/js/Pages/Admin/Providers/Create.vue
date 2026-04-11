<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    serviceTypes: { type: Array, default: () => [] },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    email_verified: false,

    business_name: '',
    primary_service_type: props.serviceTypes[0]?.value || '',
    tagline: '',
    bio: '',
    description: '',
    business_email: '',
    business_phone: '',
    website: '',

    pricing_model: '',
    hourly_rate: '',
    consultation_fee: '',
    free_consultation: false,
    pricing_notes: '',

    serves_remote: false,
    serves_in_person: true,
    service_radius_miles: '',

    license_number: '',
    license_state: '',
    years_experience: '',

    linkedin_url: '',

    is_active: true,
    is_featured: false,
    accepting_clients: true,
    is_verified: false,
});

const submit = () => {
    form.post('/admin/providers');
};

const sectionClass = 'rounded-xl border border-slate-200 bg-white p-4 shadow-sm';
const labelClass = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600';
const errorClass = 'mt-1 text-xs text-red-600';
</script>

<template>
    <Head title="Create Service Provider" />

    <AdminLayout>
        <div class="mx-auto max-w-4xl space-y-4">
            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <Link
                    href="/admin/providers"
                    class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                >
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">Create Service Provider</h1>
                    <p class="text-xs text-slate-500">Owner account, business profile, and service details</p>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Owner account -->
                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Owner account</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass">First name</label>
                            <input v-model="form.first_name" type="text" class="input w-full" />
                            <p v-if="form.errors.first_name" :class="errorClass">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Last name</label>
                            <input v-model="form.last_name" type="text" class="input w-full" />
                            <p v-if="form.errors.last_name" :class="errorClass">{{ form.errors.last_name }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Email</label>
                            <input v-model="form.email" type="email" class="input w-full" autocomplete="off" />
                            <p v-if="form.errors.email" :class="errorClass">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Phone</label>
                            <input v-model="form.phone" type="text" class="input w-full" />
                            <p v-if="form.errors.phone" :class="errorClass">{{ form.errors.phone }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Password</label>
                            <input v-model="form.password" type="password" class="input w-full" autocomplete="new-password" />
                            <p v-if="form.errors.password" :class="errorClass">{{ form.errors.password }}</p>
                        </div>
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                        <input v-model="form.email_verified" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                        <span class="text-xs font-medium text-slate-700">Mark email as verified</span>
                    </label>
                </div>

                <!-- Business -->
                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Business</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Business name</label>
                            <input v-model="form.business_name" type="text" class="input w-full" />
                            <p v-if="form.errors.business_name" :class="errorClass">{{ form.errors.business_name }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Primary service type</label>
                            <select v-model="form.primary_service_type" class="input w-full">
                                <option v-for="t in serviceTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <p v-if="form.errors.primary_service_type" :class="errorClass">{{ form.errors.primary_service_type }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Tagline</label>
                            <input v-model="form.tagline" type="text" class="input w-full" />
                            <p v-if="form.errors.tagline" :class="errorClass">{{ form.errors.tagline }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Bio</label>
                            <textarea v-model="form.bio" class="input min-h-24 w-full" />
                            <p v-if="form.errors.bio" :class="errorClass">{{ form.errors.bio }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Description</label>
                            <textarea v-model="form.description" class="input min-h-28 w-full" />
                            <p v-if="form.errors.description" :class="errorClass">{{ form.errors.description }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Business email</label>
                            <input v-model="form.business_email" type="email" class="input w-full" />
                            <p v-if="form.errors.business_email" :class="errorClass">{{ form.errors.business_email }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Business phone</label>
                            <input v-model="form.business_phone" type="text" class="input w-full" />
                            <p v-if="form.errors.business_phone" :class="errorClass">{{ form.errors.business_phone }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Website</label>
                            <input v-model="form.website" type="text" class="input w-full" placeholder="https://..." />
                            <p v-if="form.errors.website" :class="errorClass">{{ form.errors.website }}</p>
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Pricing</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass">Pricing model</label>
                            <select v-model="form.pricing_model" class="input w-full">
                                <option value="">— Select —</option>
                                <option value="hourly">Hourly</option>
                                <option value="flat_rate">Flat rate</option>
                                <option value="consultation">Consultation</option>
                                <option value="custom">Custom</option>
                            </select>
                            <p v-if="form.errors.pricing_model" :class="errorClass">{{ form.errors.pricing_model }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Hourly rate</label>
                            <input v-model="form.hourly_rate" type="number" step="0.01" min="0" class="input w-full" />
                            <p v-if="form.errors.hourly_rate" :class="errorClass">{{ form.errors.hourly_rate }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Consultation fee</label>
                            <input v-model="form.consultation_fee" type="number" step="0.01" min="0" class="input w-full" />
                            <p v-if="form.errors.consultation_fee" :class="errorClass">{{ form.errors.consultation_fee }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">Pricing notes</label>
                            <textarea v-model="form.pricing_notes" class="input min-h-20 w-full" />
                            <p v-if="form.errors.pricing_notes" :class="errorClass">{{ form.errors.pricing_notes }}</p>
                        </div>
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                        <input v-model="form.free_consultation" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                        <span class="text-xs font-medium text-slate-700">Free consultation</span>
                    </label>
                </div>

                <!-- Service area -->
                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Service area</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass">Service radius (miles)</label>
                            <input v-model="form.service_radius_miles" type="number" min="0" class="input w-full" />
                            <p v-if="form.errors.service_radius_miles" :class="errorClass">{{ form.errors.service_radius_miles }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.serves_remote" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Serves remote</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.serves_in_person" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Serves in person</span>
                        </label>
                    </div>
                </div>

                <!-- Credentials & social -->
                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Credentials &amp; social</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass">License number</label>
                            <input v-model="form.license_number" type="text" class="input w-full" />
                            <p v-if="form.errors.license_number" :class="errorClass">{{ form.errors.license_number }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">License state</label>
                            <input v-model="form.license_state" type="text" class="input w-full" maxlength="10" />
                            <p v-if="form.errors.license_state" :class="errorClass">{{ form.errors.license_state }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Years experience</label>
                            <input v-model="form.years_experience" type="number" min="0" max="80" class="input w-full" />
                            <p v-if="form.errors.years_experience" :class="errorClass">{{ form.errors.years_experience }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass">LinkedIn URL</label>
                            <input v-model="form.linkedin_url" type="text" class="input w-full" />
                            <p v-if="form.errors.linkedin_url" :class="errorClass">{{ form.errors.linkedin_url }}</p>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div :class="sectionClass">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900">Status</h2>
                    <div class="flex flex-wrap gap-2">
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Active</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Featured</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.accepting_clients" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Accepting clients</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input v-model="form.is_verified" type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                            <span class="text-xs font-medium text-slate-700">Verified provider</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-sky-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Creating…' : 'Create provider' }}
                    </button>
                    <Link href="/admin/providers" class="inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
