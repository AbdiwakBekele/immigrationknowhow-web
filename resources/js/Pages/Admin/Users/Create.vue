<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, watch } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    role: props.roles[0]?.value || 'user',
    email_verified: false,
});

const submit = () => {
    if (emailTaken.value) {
        form.setError('email', 'This email is already registered.');
        return;
    }

    form.post('/admin/users');
};

const emailTaken = ref(false);
const checkingEmail = ref(false);
let emailCheckTimeout = null;

watch(
    () => form.email,
    (value) => {
        emailTaken.value = false;
        if (form.errors.email === 'This email is already registered.') {
            form.clearErrors('email');
        }

        const email = (value || '').trim();
        const looksValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        if (!looksValid) {
            checkingEmail.value = false;
            if (emailCheckTimeout) clearTimeout(emailCheckTimeout);
            return;
        }

        if (emailCheckTimeout) clearTimeout(emailCheckTimeout);

        emailCheckTimeout = setTimeout(async () => {
            checkingEmail.value = true;
            try {
                const response = await fetch(`/admin/users/check-email?email=${encodeURIComponent(email)}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json();
                emailTaken.value = data.available === false;

                if (emailTaken.value) {
                    form.setError('email', 'This email is already registered.');
                } else if (form.errors.email === 'This email is already registered.') {
                    form.clearErrors('email');
                }
            } catch {
                // Silent fail: server-side validation still guarantees uniqueness.
            } finally {
                checkingEmail.value = false;
            }
        }, 350);
    }
);
</script>

<template>
    <Head title="Create User" />

    <AdminLayout>
        <div class="mx-auto max-w-3xl space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <Link
                        href="/admin/users"
                        class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        title="Back to users"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 class="text-lg font-display font-bold text-slate-900">Create User</h1>
                        <p class="mt-0.5 text-xs text-slate-500">Add a new user account and assign a role</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">First name</label>
                            <input
                                v-model="form.first_name"
                                type="text"
                                class="input w-full"
                                placeholder="First name"
                            />
                            <p v-if="form.errors.first_name" class="mt-1 text-xs text-red-600">
                                {{ form.errors.first_name }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Last name</label>
                            <input
                                v-model="form.last_name"
                                type="text"
                                class="input w-full"
                                placeholder="Last name"
                            />
                            <p v-if="form.errors.last_name" class="mt-1 text-xs text-red-600">
                                {{ form.errors.last_name }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="input w-full"
                            placeholder="user@example.com"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">
                            {{ form.errors.email }}
                        </p>
                        <p v-else-if="checkingEmail" class="mt-1 text-xs text-slate-500">
                            Checking email availability...
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            class="input w-full"
                            placeholder="Set a secure password"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Role</label>
                        <select v-model="form.role" class="input w-full">
                            <option v-for="role in roles" :key="role.value" :value="role.value">
                                {{ role.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.role" class="mt-1 text-xs text-red-600">
                            {{ form.errors.role }}
                        </p>
                    </div>

                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                        <input v-model="form.email_verified" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <span class="text-xs font-medium text-slate-700">Mark email as verified</span>
                    </label>

                    <div class="flex items-center gap-2 pt-1">
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-sky-700 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Creating...' : 'Create User' }}
                        </button>
                        <Link href="/admin/users" class="inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200">
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
