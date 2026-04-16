<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SlideOver from '@/Components/ui/SlideOver.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import {
    MagnifyingGlassIcon,
    PlusIcon,
    EyeIcon,
    PencilSquareIcon,
    TrashIcon,
    CheckBadgeIcon,
    SparklesIcon,
    FunnelIcon,
    XMarkIcon,
    UsersIcon,
    ShieldExclamationIcon,
    EnvelopeIcon,
    ClockIcon,
    UserCircleIcon,
    PhoneIcon,
    MapPinIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    users: { type: Object, required: true },
    selectedUser: { type: Object, default: null },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    backgroundCheckStatuses: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');
const statusFilter = ref(props.filters.status || '');
const backgroundCheckFilter = ref(props.filters.background_check || '');
const sort = ref(props.filters.sort || 'created_at');
const dir = ref(props.filters.dir || 'desc');
const isEditMode = ref(false);
const avatarPreviewUrl = ref(null);
const avatarInput = ref(null);
const selectedUserAvatarLoadFailed = ref(false);

let searchTimeout;

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 350);
});

watch([roleFilter, statusFilter, backgroundCheckFilter, sort, dir], () => {
    applyFilters();
});

watch(
    () => props.selectedUser,
    (user) => {
        if (user) {
            syncFormFromUser(user);
            isEditMode.value = false;
        }
    },
    { immediate: true }
);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    preferred_language: '',
    timezone: '',
    role: '',
    email_verified: false,
    is_active: true,
    avatar: null,
});

function syncFormFromUser(user) {
    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
        avatarPreviewUrl.value = null;
    }

    form.defaults({
        first_name: user?.first_name || '',
        last_name: user?.last_name || '',
        email: user?.email || '',
        phone: user?.phone || '',
        address: user?.address || '',
        city: user?.city || '',
        state: user?.state || '',
        postal_code: user?.postal_code || '',
        country: user?.country || '',
        preferred_language: user?.preferred_language || 'en',
        timezone: user?.timezone || 'America/New_York',
        role: user?.role_name || user?.roles?.[0]?.name || 'user',
        email_verified: !!user?.email_verified_at,
        is_active: user?.is_active ?? true,
        avatar: null,
    });

    form.reset();
    form.clearErrors();
    selectedUserAvatarLoadFailed.value = false;
}

const applyFilters = () => {
    router.get(
        '/admin/users',
        {
            search: search.value || undefined,
            role: roleFilter.value || undefined,
            status: statusFilter.value || undefined,
            background_check: backgroundCheckFilter.value || undefined,
            sort: sort.value || undefined,
            dir: dir.value || undefined,
            view: props.filters.view || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const resetFilters = () => {
    search.value = '';
    roleFilter.value = '';
    statusFilter.value = '';
    backgroundCheckFilter.value = '';
    sort.value = 'created_at';
    dir.value = 'desc';
};

const openDrawer = (userId, edit = false) => {
    router.get(
        '/admin/users',
        {
            search: search.value || undefined,
            role: roleFilter.value || undefined,
            status: statusFilter.value || undefined,
            sort: sort.value || undefined,
            dir: dir.value || undefined,
            view: userId,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'selectedUser', 'stats', 'filters', 'roles'],
            onSuccess: () => {
                isEditMode.value = edit;
            },
        }
    );
};

const closeDrawer = () => {
    isEditMode.value = false;

    router.get(
        '/admin/users',
        {
            search: search.value || undefined,
            role: roleFilter.value || undefined,
            status: statusFilter.value || undefined,
            sort: sort.value || undefined,
            dir: dir.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'selectedUser', 'stats', 'filters', 'roles'],
        }
    );
};

const deleteUser = (user) => {
    if (!confirm(`Are you sure you want to delete ${fullName(user)}?`)) return;

    router.delete(`/admin/users/${user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeDrawer();
        },
    });
};

const submitEdit = () => {
    if (!props.selectedUser) return;

    form.patch(`/admin/users/${props.selectedUser.id}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isEditMode.value = false;
            openDrawer(props.selectedUser.id, false);
        },
    });
};

const onAvatarSelected = (event) => {
    const [file] = event.target.files || [];
    form.avatar = file ?? null;

    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
        avatarPreviewUrl.value = null;
    }

    if (file) {
        avatarPreviewUrl.value = URL.createObjectURL(file);
    }
};

const triggerAvatarPicker = () => {
    avatarInput.value?.click();
};

const onSelectedUserAvatarError = () => {
    selectedUserAvatarLoadFailed.value = true;
};

const formatDate = (date) => {
    if (!date) return '—';

    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatDateTime = (date) => {
    if (!date) return 'Never';

    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const getInitial = (user) => {
    const first = (user?.first_name || '').trim();
    const last = (user?.last_name || '').trim();
    const initials = `${first.charAt(0)}${last.charAt(0)}`.trim().toUpperCase();

    return initials || first.charAt(0).toUpperCase() || '?';
};

const fullName = (user) => {
    const name = `${user?.first_name || ''} ${user?.last_name || ''}`.trim();
    return name || user?.full_name || 'User';
};

const roleMeta = (user) => {
    const role = user?.role_name || user?.roles?.[0]?.name || 'user';

    if (role === 'super_admin') {
        return { label: 'Super Admin', classes: 'bg-violet-100 text-violet-700' };
    }

    if (role === 'admin') {
        return { label: 'Admin', classes: 'bg-rose-100 text-rose-700' };
    }

    if (role === 'provider') {
        return { label: 'Provider', classes: 'bg-emerald-100 text-emerald-700' };
    }

    return { label: 'User', classes: 'bg-blue-100 text-blue-700' };
};

const statusMeta = (user) => {
    if (user?.email_verified_at) {
        return { label: 'Verified', classes: 'bg-emerald-100 text-emerald-700' };
    }

    return { label: 'Unverified', classes: 'bg-amber-100 text-amber-700' };
};

const activeMeta = (user) => {
    return user?.is_active
        ? { label: 'Active', classes: 'bg-emerald-100 text-emerald-700' }
        : { label: 'Inactive', classes: 'bg-slate-200 text-slate-700' };
};

const backgroundCheckMeta = (user) => {
    const status = user?.service_provider?.background_check_status;
    if (!status) {
        return { label: 'No check', classes: 'bg-slate-100 text-slate-600' };
    }

    switch (status) {
        case 'clear':
            return { label: 'Cleared', classes: 'bg-emerald-100 text-emerald-800' };
        case 'pending':
            return { label: 'Pending', classes: 'bg-slate-100 text-slate-700' };
        case 'invited':
            return { label: 'Invited', classes: 'bg-blue-100 text-blue-800' };
        case 'completed':
            return { label: 'In review', classes: 'bg-amber-100 text-amber-800' };
        case 'consider':
            return { label: 'Review required', classes: 'bg-orange-100 text-orange-800' };
        case 'suspended':
            return { label: 'Suspended', classes: 'bg-red-100 text-red-800' };
        case 'dispute':
            return { label: 'Dispute', classes: 'bg-purple-100 text-purple-800' };
        case 'expired':
            return { label: 'Expired', classes: 'bg-slate-100 text-slate-500' };
        default:
            return { label: status, classes: 'bg-slate-100 text-slate-700' };
    }
};

const hasActiveFilters = computed(() => {
    return Boolean(
        search.value ||
        roleFilter.value ||
        statusFilter.value ||
        backgroundCheckFilter.value ||
        sort.value !== 'created_at' ||
        dir.value !== 'desc'
    );
});

const summaryCards = computed(() => [
    {
        title: 'Total Users',
        value: props.stats.total || 0,
        subtitle: 'All user accounts',
        icon: UsersIcon,
        box: 'bg-blue-50 text-blue-700',
        chip: 'Overview',
        chipClass: 'bg-blue-100 text-blue-700',
    },
    {
        title: 'Verified Users',
        value: props.stats.verified || 0,
        subtitle: 'Email verified users',
        icon: CheckBadgeIcon,
        box: 'bg-emerald-50 text-emerald-700',
        chip: 'Trusted',
        chipClass: 'bg-emerald-100 text-emerald-700',
    },
    {
        title: 'Unverified Users',
        value: props.stats.unverified || 0,
        subtitle: 'Need email verification',
        icon: ShieldExclamationIcon,
        box: 'bg-amber-50 text-amber-700',
        chip: 'Attention',
        chipClass: 'bg-amber-100 text-amber-700',
    },
    {
        title: 'New Today',
        value: props.stats.new_today || 0,
        subtitle: 'New signups in the last 24 hours',
        icon: SparklesIcon,
        box: 'bg-cyan-50 text-cyan-700',
        chip: 'Daily',
        chipClass: 'bg-cyan-100 text-cyan-700',
    },
]);

const drawerTitle = computed(() => {
    if (!props.selectedUser) return 'User';
    return isEditMode.value ? `Edit ${fullName(props.selectedUser)}` : fullName(props.selectedUser);
});

const drawerDescription = computed(() => {
    if (!props.selectedUser) return '';

    return isEditMode.value
        ? 'Update account details, role, status, and contact information.'
        : 'Review the full user profile and manage this account without leaving the page.';
});

const parsedLanguages = computed(() => {
    const raw = props.selectedUser?.languages;

    if (!raw) return [];

    if (Array.isArray(raw)) return raw;

    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
});

const fullAddress = computed(() => {
    const user = props.selectedUser;
    if (!user) return '—';

    const parts = [
        user.address,
        user.city,
        user.state,
        user.postal_code,
        user.country,
    ].filter(Boolean);

    return parts.length ? parts.join(', ') : '—';
});
</script>

<template>
    <Head title="Manage Users" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            User management
                        </p>
                        <h1 class="mt-2 admin-title">
                            Users
                        </h1>
                        <p class="admin-subtitle">
                            Search, review, and manage platform users, roles, verification status, and account activity from one place.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            href="/admin/users/create"
                            class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-[0_14px_30px_-18px_rgba(37,99,235,0.75)] transition hover:bg-blue-700"
                        >
                            <PlusIcon class="h-4 w-4" />
                            Add User
                        </Link>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in summaryCards"
                    :key="card.title"
                    class="rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg"
                            :class="card.box"
                        >
                            <component :is="card.icon" class="h-4 w-4" />
                        </div>
                    </div>

                    <div class="mt-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.title }}</p>
                        <p class="mt-1 text-xl font-semibold tracking-tight text-slate-900">{{ card.value }}</p>
                        <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ card.subtitle }}</p>
                    </div>
                </article>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">All Users</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Filter by role, status, or search by name and email.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                            <FunnelIcon class="h-5 w-5" />
                        </div>
                        <span class="text-sm text-slate-500">
                            {{ users.total || 0 }} total results
                        </span>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-end gap-4">
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            class="admin-input !w-[280px] pl-11"
                            placeholder="Search by name, email, or phone..."
                        />
                    </div>

                    <select
                        v-model="roleFilter"
                        class="admin-select !w-auto min-w-[160px]"
                    >
                        <option value="">All Roles</option>
                        <option
                            v-for="role in roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </option>
                    </select>

                    <select
                        v-model="statusFilter"
                        class="admin-select !w-auto min-w-[160px]"
                    >
                        <option value="">All Status</option>
                        <option value="verified">Verified</option>
                        <option value="unverified">Unverified</option>
                    </select>

                    <select
                        v-model="backgroundCheckFilter"
                        class="admin-select !w-auto min-w-[200px]"
                    >
                        <option value="">All background checks</option>
                        <option
                            v-for="bc in backgroundCheckStatuses"
                            :key="bc.value"
                            :value="bc.value"
                        >
                            {{ bc.label }}
                        </option>
                        <option value="none">No background check</option>
                    </select>

                    <select
                        v-model="sort"
                        class="admin-select !w-auto min-w-[160px]"
                    >
                        <option value="created_at">Newest</option>
                        <option value="first_name">First name</option>
                        <option value="email">Email</option>
                        <option value="last_login_at">Last login</option>
                    </select>

                    <select
                        v-model="dir"
                        class="admin-select !w-auto min-w-[140px]"
                    >
                        <option value="desc">Descending</option>
                        <option value="asc">Ascending</option>
                    </select>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        <XMarkIcon class="h-4 w-4" />
                        Clear filters
                    </button>

                    <span class="text-sm text-slate-500">
                        Showing {{ users.from || 0 }}–{{ users.to || 0 }} of {{ users.total || 0 }} users
                    </span>
                </div>
            </section>

            <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="border-b border-slate-200 bg-slate-50/80">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">User</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Role</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Joined</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Last Login</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Background Check</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Activity</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="transition hover:bg-slate-50/80"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-sm font-semibold text-blue-700">
                                            {{ getInitial(user) }}
                                        </div>

                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ fullName(user) }}
                                            </p>
                                            <p class="truncate text-sm text-slate-500">
                                                {{ user.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="roleMeta(user).classes"
                                    >
                                        {{ roleMeta(user).label }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="statusMeta(user).classes"
                                    >
                                        {{ statusMeta(user).label }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ formatDate(user.created_at) }}
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ formatDateTime(user.last_login_at) }}
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="backgroundCheckMeta(user).classes"
                                    >
                                        {{ backgroundCheckMeta(user).label }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="space-y-1 text-sm text-slate-600">
                                        <p>{{ user.leads_count || 0 }} leads</p>
                                        <p>{{ user.reviews_count || 0 }} reviews</p>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                            title="View user"
                                            aria-label="View user"
                                            @click="openDrawer(user.id)"
                                        >
                                            <EyeIcon class="h-5 w-5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                            title="Edit user"
                                            aria-label="Edit user"
                                            @click="openDrawer(user.id, true)"
                                        >
                                            <PencilSquareIcon class="h-5 w-5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-rose-600 transition hover:bg-rose-50 hover:text-rose-700"
                                            title="Delete user"
                                            aria-label="Delete user"
                                            @click="deleteUser(user)"
                                        >
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!users.data.length">
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-md">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                            <UsersIcon class="h-7 w-7" />
                                        </div>
                                        <h3 class="mt-4 text-lg font-semibold text-slate-900">No users found</h3>
                                        <p class="mt-2 text-sm leading-6 text-slate-500">
                                            Try adjusting your search or filters to find the users you’re looking for.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4 p-4 lg:hidden">
                    <article
                        v-for="user in users.data"
                        :key="user.id"
                        class="rounded-[1.5rem] border border-slate-200 p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-sm font-semibold text-blue-700">
                                    {{ getInitial(user) }}
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">
                                        {{ fullName(user) }}
                                    </p>
                                    <p class="truncate text-sm text-slate-500">
                                        {{ user.email }}
                                    </p>
                                </div>
                            </div>

                            <div class="inline-flex items-center gap-1">
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                    title="View user"
                                    aria-label="View user"
                                    @click="openDrawer(user.id)"
                                >
                                    <EyeIcon class="h-5 w-5" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                    title="Edit user"
                                    aria-label="Edit user"
                                    @click="openDrawer(user.id, true)"
                                >
                                    <PencilSquareIcon class="h-5 w-5" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-rose-600 transition hover:bg-rose-50 hover:text-rose-700"
                                    title="Delete user"
                                    aria-label="Delete user"
                                    @click="deleteUser(user)"
                                >
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="roleMeta(user).classes"
                            >
                                {{ roleMeta(user).label }}
                            </span>

                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="statusMeta(user).classes"
                            >
                                {{ statusMeta(user).label }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                <p class="text-slate-500">Joined</p>
                                <p class="mt-1 font-medium text-slate-900">{{ formatDate(user.created_at) }}</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                <p class="text-slate-500">Last login</p>
                                <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(user.last_login_at) }}</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                <p class="text-slate-500">Leads</p>
                                <p class="mt-1 font-medium text-slate-900">{{ user.leads_count || 0 }}</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                <p class="text-slate-500">Reviews</p>
                                <p class="mt-1 font-medium text-slate-900">{{ user.reviews_count || 0 }}</p>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!users.data.length"
                        class="rounded-[1.5rem] border border-dashed border-slate-200 px-6 py-12 text-center"
                    >
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <UsersIcon class="h-7 w-7" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">No users found</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Try adjusting your search or filters to find the users you’re looking for.
                        </p>
                    </div>
                </div>

                <div
                    v-if="users.links?.length > 3"
                    class="border-t border-slate-200 px-4 py-4 sm:px-6"
                >
                    <nav class="flex flex-wrap justify-center gap-2">
                        <Link
                            v-for="link in users.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url || '#'"
                            class="inline-flex min-w-[2.5rem] items-center justify-center rounded-2xl px-3 py-2 text-sm font-medium transition"
                            :class="[
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : link.url
                                        ? 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                        : 'cursor-not-allowed bg-slate-100 text-slate-400'
                            ]"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </section>
        </div>

        <SlideOver
            :open="!!selectedUser"
            :title="drawerTitle"
            :description="drawerDescription"
            width-class="max-w-[40vw]"
            @close="closeDrawer"
        >
            <div v-if="selectedUser" class="space-y-4 px-5 py-5 sm:px-6">
                <template v-if="!isEditMode">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-4">
                            <img
                                v-if="selectedUser.avatar_url && !selectedUserAvatarLoadFailed"
                                :src="selectedUser.avatar_url"
                                alt=""
                                class="h-16 w-16 rounded-[1.5rem] object-cover"
                                @error="onSelectedUserAvatarError"
                            />
                            <div
                                v-else
                                class="flex h-16 w-16 items-center justify-center rounded-[1.5rem] bg-blue-50 text-lg font-semibold text-blue-700"
                            >
                                {{ getInitial(selectedUser) }}
                            </div>

                            <div class="min-w-0">
                                <h3 class="text-xl font-semibold text-slate-900">
                                    {{ fullName(selectedUser) }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ selectedUser.email }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap justify-end gap-2">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="roleMeta(selectedUser).classes"
                            >
                                {{ roleMeta(selectedUser).label }}
                            </span>

                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="statusMeta(selectedUser).classes"
                            >
                                {{ statusMeta(selectedUser).label }}
                            </span>

                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="activeMeta(selectedUser).classes"
                            >
                                {{ activeMeta(selectedUser).label }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-1.5 md:grid-cols-2">
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div class="flex items-center gap-3">
                                <UserCircleIcon class="h-5 w-5 text-slate-400" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">First name</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ selectedUser.first_name || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div class="flex items-center gap-3">
                                <UserCircleIcon class="h-5 w-5 text-slate-400" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Last name</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ selectedUser.last_name || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div class="flex items-center gap-3">
                                <EnvelopeIcon class="h-5 w-5 text-slate-400" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Email</p>
                                    <p class="mt-1 break-all font-medium text-slate-900">{{ selectedUser.email }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div class="flex items-center gap-3">
                                <PhoneIcon class="h-5 w-5 text-slate-400" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ selectedUser.phone || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div class="flex items-center gap-3">
                                <GlobeAltIcon class="h-5 w-5 text-slate-400" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Preferred language</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ selectedUser.preferred_language || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div class="flex items-center gap-3">
                                <ClockIcon class="h-5 w-5 text-slate-400" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Timezone</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ selectedUser.timezone || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Joined</p>
                                <p class="mt-1 font-medium text-slate-900">{{ formatDate(selectedUser.created_at) }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Last login</p>
                                <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(selectedUser.last_login_at) }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Email verified</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.email_verified_at ? formatDateTime(selectedUser.email_verified_at) : 'No' }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Phone verified</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.phone_verified_at ? formatDateTime(selectedUser.phone_verified_at) : 'No' }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Onboarding completed</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.onboarding_completed ? 'Yes' : 'No' }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Onboarding completed at</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.onboarding_completed_at ? formatDateTime(selectedUser.onboarding_completed_at) : '—' }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Leads</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.leads_count || 0 }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Reviews</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.reviews_count || 0 }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 md:col-span-2">
                            <div class="flex items-start gap-3">
                                <MapPinIcon class="mt-0.5 h-5 w-5 text-slate-400" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Address</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ fullAddress }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Latitude</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.latitude ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Longitude</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.longitude ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 md:col-span-2">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Languages</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        v-for="language in parsedLanguages"
                                        :key="language"
                                        class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700"
                                    >
                                        {{ language }}
                                    </span>
                                    <span v-if="!parsedLanguages.length" class="font-medium text-slate-900">—</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Referred by affiliate ID</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.referred_by_affiliate_id ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Affiliate referral ID</p>
                                <p class="mt-1 font-medium text-slate-900">{{ selectedUser.affiliate_referral_id ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedUser.service_provider"
                        class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5"
                    >
                        <h4 class="text-base font-semibold text-slate-900">Provider profile</h4>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Business name</p>
                                <p class="mt-1 font-medium text-slate-900">
                                    {{ selectedUser.service_provider.business_name || '—' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                                <p class="mt-1 font-medium text-slate-900">
                                    {{ selectedUser.service_provider.phone || '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <form class="space-y-5" @submit.prevent="submitEdit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-slate-700">Profile photo</label>
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <img
                                            v-if="avatarPreviewUrl"
                                            :src="avatarPreviewUrl"
                                            alt=""
                                            class="h-14 w-14 rounded-2xl object-cover"
                                        />
                                        <img
                                            v-else-if="selectedUser.avatar_url && !selectedUserAvatarLoadFailed"
                                            :src="selectedUser.avatar_url"
                                            alt=""
                                            class="h-14 w-14 rounded-2xl object-cover"
                                            @error="onSelectedUserAvatarError"
                                        />
                                        <div
                                            v-else
                                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-sm font-semibold text-blue-700"
                                        >
                                            {{ getInitial(selectedUser) }}
                                        </div>

                                        <button
                                            type="button"
                                            class="absolute -bottom-1 -right-1 inline-flex h-7 w-7 items-center justify-center rounded-full border border-white bg-blue-600 text-white shadow-sm transition hover:bg-blue-700"
                                            aria-label="Change profile photo"
                                            @click="triggerAvatarPicker"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <input
                                        ref="avatarInput"
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="onAvatarSelected"
                                    />
                                </div>
                                <p v-if="form.errors.avatar" class="mt-2 text-sm font-medium text-red-600">
                                    {{ form.errors.avatar }}
                                </p>
                            </div>

                            <Input
                                v-model="form.first_name"
                                label="First name"
                                :error="form.errors.first_name"
                                required
                            />

                            <Input
                                v-model="form.last_name"
                                label="Last name"
                                :error="form.errors.last_name"
                                required
                            />

                            <Input
                                v-model="form.email"
                                type="email"
                                label="Email"
                                :error="form.errors.email"
                                required
                            />

                            <Input
                                v-model="form.phone"
                                label="Phone"
                                :error="form.errors.phone"
                            />

                            <Input
                                v-model="form.address"
                                label="Address"
                                :error="form.errors.address"
                            />

                            <Input
                                v-model="form.city"
                                label="City"
                                :error="form.errors.city"
                            />

                            <Input
                                v-model="form.state"
                                label="State"
                                :error="form.errors.state"
                            />

                            <Input
                                v-model="form.postal_code"
                                label="Postal code"
                                :error="form.errors.postal_code"
                            />

                            <Input
                                v-model="form.country"
                                label="Country"
                                :error="form.errors.country"
                            />

                            <Input
                                v-model="form.preferred_language"
                                label="Preferred language"
                                :error="form.errors.preferred_language"
                            />

                            <Input
                                v-model="form.timezone"
                                label="Timezone"
                                :error="form.errors.timezone"
                            />

                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700">
                                    Role
                                </label>

                                <select
                                    v-model="form.role"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                >
                                    <option
                                        v-for="role in roles"
                                        :key="role.value"
                                        :value="role.value"
                                    >
                                        {{ role.label }}
                                    </option>
                                </select>

                                <p v-if="form.errors.role" class="mt-2 text-sm font-medium text-red-600">
                                    {{ form.errors.role }}
                                </p>
                            </div>

                            <div class="grid gap-4">
                                <label class="flex min-h-[48px] items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <input
                                        v-model="form.email_verified"
                                        type="checkbox"
                                        class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-slate-700">Email is verified</span>
                                </label>

                                <label class="flex min-h-[48px] items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-slate-700">Account is active</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </template>
            </div>

            <template #footer>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <Button
                            v-if="!isEditMode"
                            type="button"
                            variant="secondary"
                            @click="isEditMode = true"
                        >
                            <PencilSquareIcon class="h-4 w-4" />
                            Edit user
                        </Button>

                        <Button
                            v-else
                            type="button"
                            variant="secondary"
                            @click="
                                isEditMode = false;
                                syncFormFromUser(selectedUser);
                            "
                        >
                            Cancel
                        </Button>

                        <Button
                            type="button"
                            variant="secondary"
                            class="!border-rose-200 !bg-white !text-rose-700 hover:!bg-rose-50"
                            @click="deleteUser(selectedUser)"
                        >
                            <TrashIcon class="h-4 w-4" />
                            Delete user
                        </Button>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="primary"
                            :loading="form.processing"
                            @click="submitEdit"
                        >
                            Save changes
                        </Button>
                    </div>
                </div>
            </template>
        </SlideOver>
    </AdminLayout>
</template>