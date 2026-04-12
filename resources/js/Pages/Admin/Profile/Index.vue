<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { CameraIcon, KeyIcon, UserCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const profileForm = useForm({
    first_name: props.profile.first_name ?? '',
    last_name: props.profile.last_name ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
});

watch(
    () => props.profile,
    (p) => {
        profileForm.first_name = p.first_name ?? '';
        profileForm.last_name = p.last_name ?? '';
        profileForm.email = p.email ?? '';
        profileForm.phone = p.phone ?? '';
    },
    { deep: true },
);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const avatarInput = ref(null);

const formatDateTime = (iso) => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return '—';
    }
};

const formatDate = (iso) => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
    } catch {
        return '—';
    }
};

const submitProfile = () => {
    profileForm.patch('/admin/profile', { preserveScroll: true });
};

const submitPassword = () => {
    passwordForm.patch('/admin/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const onAvatarSelected = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);

    router.post('/admin/profile/avatar', formData, {
        preserveScroll: true,
        onFinish: () => {
            if (avatarInput.value) {
                avatarInput.value.value = '';
            }
        },
    });
};

const removeAvatar = () => {
    router.delete('/admin/profile/avatar', { preserveScroll: true });
};
</script>

<template>
    <Head title="Admin Profile" />

    <AdminLayout>
        <div class="mx-auto max-w-3xl space-y-5">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <h1 class="text-xl font-display font-bold text-slate-900">Admin Profile</h1>
                <p class="mt-1 text-sm text-slate-500">Your administrator account details and sign-in settings.</p>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>

            <!-- Photo + read-only account meta -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Profile photo</h2>
                <p class="mt-0.5 text-xs text-slate-500">Optional. Shown in the admin sidebar when set.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div
                        class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-slate-400"
                    >
                        <img
                            v-if="profile.avatar_url"
                            :src="profile.avatar_url"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                        <UserCircleIcon v-else class="h-14 w-14" />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            ref="avatarInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onAvatarSelected"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                            @click="avatarInput?.click()"
                        >
                            <CameraIcon class="h-4 w-4 text-slate-500" />
                            Upload photo
                        </button>
                        <button
                            v-if="profile.avatar_url"
                            type="button"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                            @click="removeAvatar"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                <dl class="mt-6 grid gap-3 border-t border-slate-100 pt-5 sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ (profile.role_labels || []).join(', ') || '—' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email verified</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ profile.email_verified_at ? formatDateTime(profile.email_verified_at) : 'Not verified' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member since</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ formatDate(profile.created_at) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last sign-in</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ formatDateTime(profile.last_login_at) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Editable profile -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Account details</h2>
                <p class="mt-0.5 text-xs text-slate-500">Name, email, and phone are stored on your user record.</p>

                <form class="mt-4 space-y-4" @submit.prevent="submitProfile">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                First name
                            </label>
                            <input
                                v-model="profileForm.first_name"
                                type="text"
                                class="input w-full"
                                autocomplete="given-name"
                            />
                            <p v-if="profileForm.errors.first_name" class="mt-1 text-xs text-red-600">
                                {{ profileForm.errors.first_name }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Last name
                            </label>
                            <input
                                v-model="profileForm.last_name"
                                type="text"
                                class="input w-full"
                                autocomplete="family-name"
                            />
                            <p v-if="profileForm.errors.last_name" class="mt-1 text-xs text-red-600">
                                {{ profileForm.errors.last_name }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Email
                        </label>
                        <input
                            v-model="profileForm.email"
                            type="email"
                            class="input w-full"
                            autocomplete="email"
                        />
                        <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-600">
                            {{ profileForm.errors.email }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Changing your email clears verification until you confirm the new address.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Phone
                        </label>
                        <input
                            v-model="profileForm.phone"
                            type="tel"
                            class="input w-full"
                            autocomplete="tel"
                            placeholder="Optional"
                        />
                        <p v-if="profileForm.errors.phone" class="mt-1 text-xs text-red-600">
                            {{ profileForm.errors.phone }}
                        </p>
                    </div>

                    <div class="flex justify-end border-t border-slate-100 pt-4">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 disabled:opacity-50"
                            :disabled="profileForm.processing"
                        >
                            Save changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2">
                    <KeyIcon class="h-5 w-5 text-sky-600" />
                    <h2 class="text-sm font-semibold text-slate-900">Password</h2>
                </div>
                <p class="mt-1 text-xs text-slate-500">Use a strong password unique to this account.</p>

                <form class="mt-4 space-y-4" @submit.prevent="submitPassword">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Current password
                        </label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            class="input w-full"
                            autocomplete="current-password"
                        />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                New password
                            </label>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                class="input w-full"
                                autocomplete="new-password"
                            />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">
                                {{ passwordForm.errors.password }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Confirm new password
                            </label>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                class="input w-full"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-100 pt-4">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-900 disabled:opacity-50"
                            :disabled="passwordForm.processing"
                        >
                            Update password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
