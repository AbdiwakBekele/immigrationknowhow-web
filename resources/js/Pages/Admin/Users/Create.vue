<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
    form.post('/admin/users');
};
</script>

<template>
    <Head title="Create User" />

    <AdminLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-display font-bold text-gray-900">Create User</h1>
                <p class="text-gray-500 mt-1">Add a new user account and assign a role</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First name</label>
                            <input
                                v-model="form.first_name"
                                type="text"
                                class="input w-full"
                                placeholder="First name"
                            />
                            <p v-if="form.errors.first_name" class="text-sm text-red-600 mt-1">
                                {{ form.errors.first_name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last name</label>
                            <input
                                v-model="form.last_name"
                                type="text"
                                class="input w-full"
                                placeholder="Last name"
                            />
                            <p v-if="form.errors.last_name" class="text-sm text-red-600 mt-1">
                                {{ form.errors.last_name }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="input w-full"
                            placeholder="user@example.com"
                        />
                        <p v-if="form.errors.email" class="text-sm text-red-600 mt-1">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            class="input w-full"
                            placeholder="Set a secure password"
                        />
                        <p v-if="form.errors.password" class="text-sm text-red-600 mt-1">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select v-model="form.role" class="input w-full">
                            <option v-for="role in roles" :key="role.value" :value="role.value">
                                {{ role.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.role" class="text-sm text-red-600 mt-1">
                            {{ form.errors.role }}
                        </p>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input v-model="form.email_verified" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <span class="text-sm text-gray-700">Mark email as verified</span>
                    </label>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Creating...' : 'Create User' }}
                        </button>
                        <Link href="/admin/users" class="btn-secondary">
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
