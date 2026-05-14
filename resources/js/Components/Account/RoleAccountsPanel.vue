<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Button from '@/Components/ui/Button.vue';

const page = usePage();

const roleAccounts = computed(() => page.props.auth?.role_accounts ?? null);
const activePortal = computed(() => page.props.auth?.active_portal ?? 'user');

const showPanel = computed(() => {
    const meta = roleAccounts.value;
    if (!meta) {
        return false;
    }

    return meta.can_switch || meta.can_add_seeker || meta.can_add_provider;
});

const switchPortal = (portal) => {
    router.post(route('account-roles.switch'), { portal }, { preserveScroll: true });
};

const startProvider = () => {
    router.post(route('account-roles.provider.start'), {}, { preserveScroll: true });
};
</script>

<template>
    <section
        v-if="showPanel"
        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
    >
        <h2 class="text-lg font-semibold text-slate-950">Account roles</h2>
        <p class="mt-1 text-sm text-slate-500">
            Use one login for both service seeker and provider experiences. Switch anytime without signing out.
        </p>

        <div v-if="roleAccounts?.can_switch" class="mt-4 flex flex-wrap gap-3">
            <Button
                size="sm"
                :variant="activePortal === 'user' ? 'primary' : 'secondary'"
                @click="switchPortal('user')"
            >
                Service seeker
            </Button>
            <Button
                size="sm"
                :variant="activePortal === 'provider' ? 'primary' : 'secondary'"
                @click="switchPortal('provider')"
            >
                Service provider
            </Button>
        </div>

        <div v-if="roleAccounts?.can_add_seeker" class="mt-4">
            <Link :href="route('account-roles.seeker.create')">
                <Button variant="secondary" size="sm">Create service seeker account</Button>
            </Link>
        </div>

        <div v-if="roleAccounts?.can_add_provider" class="mt-4">
            <Button variant="secondary" size="sm" @click="startProvider">
                Create service provider account
            </Button>
        </div>
    </section>
</template>
