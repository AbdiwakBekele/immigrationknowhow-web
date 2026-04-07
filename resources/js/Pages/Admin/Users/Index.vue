<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    MagnifyingGlassIcon,
    FunnelIcon,
    PlusIcon,
    EyeIcon,
    PencilIcon,
    TrashIcon,
    ShieldCheckIcon,
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
</script>

<template>
    <Head title="Manage Users" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-display font-bold text-gray-900">Users</h1>
                    <p class="text-gray-500 mt-1">Manage platform users and their roles</p>
                </div>
                <Link href="/admin/users/create" class="btn-primary">
                    <PlusIcon class="h-5 w-5 mr-2" />
                    Add User
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1 relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                        <input 
                            v-model="search"
                            type="text"
                            class="input w-full pl-10"
                            placeholder="Search by name or email..."
                        />
                    </div>
                    <select v-model="roleFilter" class="input w-full sm:w-40">
                        <option value="">All Roles</option>
                        <option v-for="role in roles" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                    <select v-model="statusFilter" class="input w-full sm:w-40">
                        <option value="">All Status</option>
                        <option value="verified">Verified</option>
                        <option value="unverified">Unverified</option>
                    </select>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">User</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Role</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Status</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Joined</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Activity</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <img 
                                            :src="user.avatar || '/images/default-avatar.png'" 
                                            class="h-10 w-10 rounded-full"
                                        />
                                        <div>
                                            <div class="font-medium text-gray-900">{{ user.first_name }} {{ user.last_name }}</div>
                                            <div class="text-sm text-gray-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span 
                                        class="px-2 py-1 text-xs font-medium rounded-full"
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
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <span v-if="user.banned_at" class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded-full">
                                            Banned
                                        </span>
                                        <span v-else-if="user.email_verified_at" class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded-full">
                                            Verified
                                        </span>
                                        <span v-else class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded-full">
                                            Unverified
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-500">
                                    {{ formatDate(user.created_at) }}
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-500">
                                    <div>{{ user.leads_count || 0 }} leads</div>
                                    <div>{{ user.reviews_count || 0 }} reviews</div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <Menu as="div" class="relative inline-block">
                                        <MenuButton class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                            </svg>
                                        </MenuButton>
                                        <MenuItems class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-10">
                                            <MenuItem v-slot="{ active }">
                                                <Link 
                                                    :href="`/admin/users/${user.id}`"
                                                    class="flex items-center gap-2 px-4 py-2 text-sm"
                                                    :class="active ? 'bg-gray-50' : ''"
                                                >
                                                    <EyeIcon class="h-4 w-4" />
                                                    View
                                                </Link>
                                            </MenuItem>
                                            <MenuItem v-slot="{ active }">
                                                <Link 
                                                    :href="`/admin/users/${user.id}/edit`"
                                                    class="flex items-center gap-2 px-4 py-2 text-sm"
                                                    :class="active ? 'bg-gray-50' : ''"
                                                >
                                                    <PencilIcon class="h-4 w-4" />
                                                    Edit
                                                </Link>
                                            </MenuItem>
                                            <MenuItem v-if="!user.banned_at" v-slot="{ active }">
                                                <button 
                                                    @click="banUser(user)"
                                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-orange-600"
                                                    :class="active ? 'bg-gray-50' : ''"
                                                >
                                                    <NoSymbolIcon class="h-4 w-4" />
                                                    Ban User
                                                </button>
                                            </MenuItem>
                                            <MenuItem v-else v-slot="{ active }">
                                                <button 
                                                    @click="unbanUser(user)"
                                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-green-600"
                                                    :class="active ? 'bg-gray-50' : ''"
                                                >
                                                    <ArrowPathIcon class="h-4 w-4" />
                                                    Unban User
                                                </button>
                                            </MenuItem>
                                            <MenuItem v-slot="{ active }">
                                                <button 
                                                    @click="deleteUser(user)"
                                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600"
                                                    :class="active ? 'bg-gray-50' : ''"
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
                <div v-if="users.links?.length > 3" class="px-4 py-3 border-t border-gray-100">
                    <nav class="flex justify-center gap-1">
                        <Link 
                            v-for="link in users.links" 
                            :key="link.label"
                            :href="link.url"
                            class="px-3 py-2 text-sm rounded-lg"
                            :class="link.active ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
