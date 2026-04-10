<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    MagnifyingGlassIcon,
    PlusIcon,
    EyeIcon,
    PencilIcon,
    TrashIcon,
    NoSymbolIcon,
    ArrowPathIcon
} from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');
const statusFilter = ref(props.filters.status || '');

let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 300);
});

watch([roleFilter, statusFilter], () => applyFilters());

const applyFilters = () => {
    router.get('/admin/users', {
        search: search.value || undefined,
        role: roleFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const deleteUser = (user) => {
    if (!confirm(`Are you sure you want to delete ${user.full_name}?`)) return;
    router.delete(`/admin/users/${user.id}`);
};

const banUser = (user) => {
    if (!confirm(`Ban ${user.full_name}?`)) return;
    router.post(`/admin/users/${user.id}/ban`);
};

const unbanUser = (user) => {
    router.post(`/admin/users/${user.id}/unban`);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const getInitial = (user) => {
    const value = (user?.first_name || '?').trim();
    return value ? value.charAt(0).toUpperCase() : '?';
};
</script>

<template>
    <Head title="Manage Users" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4">
            <!-- Header -->
            <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-display font-bold text-slate-900">Users</h1>
                    <p class="mt-0.5 text-xs text-slate-500">Manage platform users and their roles</p>
                </div>
                <Link href="/admin/users/create" class="inline-flex items-center rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-sky-700">
                    <PlusIcon class="mr-1.5 h-4 w-4" />
                    Add User
                </Link>
            </div>

            <!-- Filters -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex-1 relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input 
                            v-model="search"
                            type="text"
                            class="input w-full pl-10"
                            placeholder="Search by name or email..."
                        />
                    </div>
                    <select v-model="roleFilter" class="input w-full sm:w-36">
                        <option value="">All Roles</option>
                        <option v-for="role in roles" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                    <select v-model="statusFilter" class="input w-full sm:w-36">
                        <option value="">All Status</option>
                        <option value="verified">Verified</option>
                        <option value="unverified">Unverified</option>
                    </select>
                </div>
            </div>

            <!-- Users Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200 bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">User</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Role</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Joined</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Activity</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-700">
                                            {{ getInitial(user) }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-slate-900">{{ user.first_name }} {{ user.last_name }}</div>
                                            <div class="text-xs text-slate-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span 
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-purple-100 text-purple-700': user.roles?.[0]?.name === 'super_admin',
                                            'bg-red-100 text-red-700': user.roles?.[0]?.name === 'admin',
                                            'bg-green-100 text-green-700': user.roles?.[0]?.name === 'provider',
                                            'bg-blue-100 text-blue-700': user.roles?.[0]?.name === 'user' || !user.roles?.[0],
                                        }"
                                    >
                                        {{ user.roles?.[0]?.name || 'user' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <span v-if="user.banned_at" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                            Banned
                                        </span>
                                        <span v-else-if="user.email_verified_at" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                            Verified
                                        </span>
                                        <span v-else class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700">
                                            Unverified
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-500">
                                    {{ formatDate(user.created_at) }}
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-500">
                                    <div>{{ user.leads_count || 0 }} leads</div>
                                    <div>{{ user.reviews_count || 0 }} reviews</div>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <Menu as="div" class="relative inline-block">
                                        <MenuButton class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                            </svg>
                                        </MenuButton>
                                        <MenuItems class="absolute right-0 z-10 mt-2 w-48 rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                            <MenuItem v-slot="{ active }">
                                                <Link 
                                                    :href="`/admin/users/${user.id}`"
                                                    class="flex items-center gap-2 px-4 py-2 text-sm"
                                                    :class="active ? 'bg-slate-50' : ''"
                                                >
                                                    <EyeIcon class="h-4 w-4" />
                                                    View
                                                </Link>
                                            </MenuItem>
                                            <MenuItem v-slot="{ active }">
                                                <Link 
                                                    :href="`/admin/users/${user.id}/edit`"
                                                    class="flex items-center gap-2 px-4 py-2 text-sm"
                                                    :class="active ? 'bg-slate-50' : ''"
                                                >
                                                    <PencilIcon class="h-4 w-4" />
                                                    Edit
                                                </Link>
                                            </MenuItem>
                                            <MenuItem v-if="!user.banned_at" v-slot="{ active }">
                                                <button 
                                                    @click="banUser(user)"
                                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-orange-600"
                                                    :class="active ? 'bg-slate-50' : ''"
                                                >
                                                    <NoSymbolIcon class="h-4 w-4" />
                                                    Ban User
                                                </button>
                                            </MenuItem>
                                            <MenuItem v-else v-slot="{ active }">
                                                <button 
                                                    @click="unbanUser(user)"
                                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-green-600"
                                                    :class="active ? 'bg-slate-50' : ''"
                                                >
                                                    <ArrowPathIcon class="h-4 w-4" />
                                                    Unban User
                                                </button>
                                            </MenuItem>
                                            <MenuItem v-slot="{ active }">
                                                <button 
                                                    @click="deleteUser(user)"
                                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600"
                                                    :class="active ? 'bg-slate-50' : ''"
                                                >
                                                    <TrashIcon class="h-4 w-4" />
                                                    Delete
                                                </button>
                                            </MenuItem>
                                        </MenuItems>
                                    </Menu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="users.links?.length > 3" class="border-t border-slate-100 px-3 py-2.5">
                    <nav class="flex justify-center gap-1">
                        <Link 
                            v-for="link in users.links" 
                            :key="link.label"
                            :href="link.url"
                            class="rounded-lg px-2.5 py-1.5 text-xs"
                            :class="link.active ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
