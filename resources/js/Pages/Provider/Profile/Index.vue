<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import RoleAccountsPanel from '@/Components/Account/RoleAccountsPanel.vue';
import ProviderProfileFeed from '@/Components/marketplace/ProviderProfileFeed.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import {
    PencilSquareIcon,
    MapPinIcon,
    PhoneIcon,
    EnvelopeIcon,
    GlobeAltIcon,
    CheckCircleIcon,
    LinkIcon,
    BookOpenIcon,
    CameraIcon,
    ChatBubbleLeftRightIcon,
    SparklesIcon,
    UserPlusIcon,
    XMarkIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    provider: { type: Object, default: null },
    profileFeed: { type: Array, default: () => [] },
    profileStats: { type: Object, default: () => ({}) },
    languageOptions: { type: Array, default: () => [] },
    countryOptions: { type: Array, default: () => [] },
});

const page = usePage();

const fallbackLanguageOptions = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish' },
];

const languageOpts = computed(() => (
    props.languageOptions?.length ? props.languageOptions : fallbackLanguageOptions
));

const normalizeLanguageValue = (value) => {
    if (!value) return '';
    const raw = String(value).trim();
    const direct = languageOpts.value.find((option) => option.value === raw);
    if (direct) return direct.value;
    const byLabel = languageOpts.value.find((option) => option.label.toLowerCase() === raw.toLowerCase());
    return byLabel?.value || raw;
};

const languageLabel = (code) => (
    languageOpts.value.find((option) => option.value === normalizeLanguageValue(code))?.label
    || String(code || '').toUpperCase()
);

const avatarInput = ref(null);
const avatarTypeError = ref('');
const avatarUploading = ref(false);
const allowedAvatarTypes = ['image/jpeg', 'image/png'];
const avatarServerError = computed(() => page.props.errors?.avatar || '');

const hasAvatar = computed(() => Boolean(props.user?.avatar_url));

const displayName = computed(() => {
    const first = props.user?.first_name?.trim();
    const last = props.user?.last_name?.trim();
    const full = [first, last].filter(Boolean).join(' ').trim();
    return full || 'Your profile';
});

const avatarInitial = computed(() => {
    const first = props.user?.first_name?.trim();
    if (first) return first.charAt(0).toUpperCase();
    const last = props.user?.last_name?.trim();
    if (last) return last.charAt(0).toUpperCase();
    const fromBusiness = props.provider?.business_name?.trim();
    if (fromBusiness) return fromBusiness.charAt(0).toUpperCase();
    return 'P';
});

const locationSummary = computed(() => {
    const fromAccount = [props.user?.city, props.user?.state, props.user?.country].filter(Boolean).join(', ').trim();
    if (fromAccount) return fromAccount;
    const areas = props.provider?.service_areas;
    if (Array.isArray(areas) && areas.length) {
        return areas.join(', ');
    }
    return 'Location not set';
});

const memberSinceLabel = computed(() => {
    const raw = props.user?.created_at;
    if (!raw) return '';
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('en', { month: 'short', year: 'numeric' }).format(d);
});

const completionPercent = computed(() => Number(props.profileStats?.completion || 0));

const insightCards = computed(() => [
    {
        label: 'Profile',
        value: `${completionPercent.value}%`,
        detail: 'Complete',
        icon: CheckCircleIcon,
        tone: 'text-blue-700 bg-blue-50 border-blue-100',
    },
    {
        label: 'Messages',
        value: props.profileStats?.unread_messages || 0,
        detail: 'Unread',
        icon: ChatBubbleLeftRightIcon,
        tone: 'text-emerald-700 bg-emerald-50 border-emerald-100',
    },
    {
        label: 'Library',
        value: props.profileStats?.purchased_products || 0,
        detail: 'Owned',
        icon: BookOpenIcon,
        tone: 'text-slate-700 bg-slate-50 border-slate-200',
    },
    {
        label: 'Matches',
        value: props.profileStats?.matched_providers || 0,
        detail: 'Providers',
        icon: SparklesIcon,
        tone: 'text-rose-700 bg-rose-50 border-rose-100',
    },
]);

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') return 'Not set';
    return `$${Number(value).toFixed(2)}`;
};

const formatDate = (value) => {
    if (!value) return 'No expiration';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString();
};

const healthCertificateFileUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
};

const stateLicenseFileUrl = computed(() => {
    const path = props.provider?.state_license_document_path || '';
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
});

const isImageCertificate = (value) => /\.(jpg|jpeg|png|webp|gif|bmp)$/i.test(String(value || ''));

const healthCertificateThumbnailUrl = (certificate) => {
    const filePath = certificate?.file_path || '';
    const originalName = certificate?.original_name || '';
    if (filePath && isImageCertificate(filePath)) return healthCertificateFileUrl(filePath);
    if (filePath && isImageCertificate(originalName)) return healthCertificateFileUrl(filePath);
    return '';
};

const formatCertificateServiceType = (value) => {
    const normalized = String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    const labels = {
        health_navigator: 'Healthcare Navigator',
        healthcare_navigator: 'Healthcare Navigator',
        healthnavigator: 'Healthcare Navigator',
        pet_sitter: 'Pet Sitter',
        petsitter: 'Pet Sitter',
        babysitter: 'Babysitter',
        baby_sitter: 'Babysitter',
    };
    if (labels[normalized]) return labels[normalized];
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
};

const normalizeServiceType = (value) => String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');

const selectedServiceTypes = computed(() =>
    (props.provider?.service_types || []).map((type) => normalizeServiceType(type))
);

const showsHealthcareNavigatorCredentials = computed(() =>
    selectedServiceTypes.value.some((type) => ['health_navigator', 'healthcare_navigator', 'healthnavigator'].includes(type))
);


const feedAvatarUrl = computed(() => {
    const u = props.user?.avatar_url || props.user?.avatar || '';
    const s = typeof u === 'string' ? u.trim() : '';
    if (!s) return '';
    if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('/')) return s;
    return `/storage/${s}`;
});

const publicListingUrl = computed(() => {
    if (!props.provider?.slug) return '';
    const path = route('marketplace.show', props.provider.slug);
    if (typeof window !== 'undefined') {
        return `${window.location.origin}${path}`;
    }
    return path;
});

const uploadAvatar = (event) => {
    const file = event.target.files?.[0];
    avatarTypeError.value = '';
    if (!file) return;

    if (!allowedAvatarTypes.includes(file.type)) {
        avatarTypeError.value = 'Please choose a JPG or PNG image.';
        event.target.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('avatar', file);

    avatarUploading.value = true;
    router.post(route('provider.profile.avatar'), formData, {
        preserveScroll: true,
        onFinish: () => {
            avatarUploading.value = false;
            event.target.value = '';
        },
        onSuccess: () => {
            avatarTypeError.value = '';
        },
    });
};

const professionalModalOpen = ref(false);

const splitList = (value) => String(value || '')
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean);

const pricingModelOptions = [
    { value: '', label: 'Not set' },
    { value: 'hourly', label: 'Hourly' },
    { value: 'flat_rate', label: 'Flat rate' },
    { value: 'consultation', label: 'Consultation' },
    { value: 'custom', label: 'Custom' },
];

const professionalForm = useForm({
    business_name: props.provider?.business_name ?? '',
    service_types_text: (props.provider?.service_types || []).join(', '),
    specializations_text: (props.provider?.specializations || []).join(', '),
    languages_offered_text: (props.provider?.languages_offered || []).join(', '),
    years_experience: props.provider?.years_experience ?? '',
    serves_remote: Boolean(props.provider?.serves_remote),
    serves_in_person: Boolean(props.provider?.serves_in_person),
    service_radius_miles: props.provider?.service_radius_miles ?? '',

    pricing_model: props.provider?.pricing_model ?? '',
    hourly_rate: props.provider?.hourly_rate ?? '',
    consultation_fee: props.provider?.consultation_fee ?? '',
    free_consultation: Boolean(props.provider?.free_consultation),
    pricing_notes: props.provider?.pricing_notes ?? '',

    linkedin_url: props.provider?.linkedin_url ?? '',
    facebook_url: props.provider?.facebook_url ?? '',
    twitter_url: props.provider?.twitter_url ?? '',
    instagram_url: props.provider?.instagram_url ?? '',
    youtube_url: props.provider?.youtube_url ?? '',
    tiktok_url: props.provider?.tiktok_url ?? '',
    accepting_clients: Boolean(props.provider?.accepting_clients),
});

const openProfessionalModal = () => {
    professionalForm.defaults({
        business_name: props.provider?.business_name ?? '',
        service_types_text: (props.provider?.service_types || []).join(', '),
        specializations_text: (props.provider?.specializations || []).join(', '),
        languages_offered_text: (props.provider?.languages_offered || []).join(', '),
        years_experience: props.provider?.years_experience ?? '',
        serves_remote: Boolean(props.provider?.serves_remote),
        serves_in_person: Boolean(props.provider?.serves_in_person),
        service_radius_miles: props.provider?.service_radius_miles ?? '',
        pricing_model: props.provider?.pricing_model ?? '',
        hourly_rate: props.provider?.hourly_rate ?? '',
        consultation_fee: props.provider?.consultation_fee ?? '',
        free_consultation: Boolean(props.provider?.free_consultation),
        pricing_notes: props.provider?.pricing_notes ?? '',
        linkedin_url: props.provider?.linkedin_url ?? '',
        facebook_url: props.provider?.facebook_url ?? '',
        twitter_url: props.provider?.twitter_url ?? '',
        instagram_url: props.provider?.instagram_url ?? '',
        youtube_url: props.provider?.youtube_url ?? '',
        tiktok_url: props.provider?.tiktok_url ?? '',
        accepting_clients: Boolean(props.provider?.accepting_clients),
    });
    professionalForm.reset();
    professionalForm.clearErrors();
    professionalModalOpen.value = true;
};

const saveProfessionalDetails = () => {
    professionalForm
        .transform((data) => ({
            business_name: data.business_name,
            service_types: splitList(data.service_types_text),
            specializations: splitList(data.specializations_text),
            languages_offered: splitList(data.languages_offered_text),
            years_experience: data.years_experience === '' ? null : Number(data.years_experience),
            serves_remote: Boolean(data.serves_remote),
            serves_in_person: Boolean(data.serves_in_person),
            service_radius_miles: data.service_radius_miles === '' ? null : Number(data.service_radius_miles),
            pricing_model: data.pricing_model || null,
            hourly_rate: data.hourly_rate === '' ? null : Number(data.hourly_rate),
            consultation_fee: data.consultation_fee === '' ? null : Number(data.consultation_fee),
            free_consultation: Boolean(data.free_consultation),
            pricing_notes: data.pricing_notes || null,
            linkedin_url: data.linkedin_url || null,
            facebook_url: data.facebook_url || null,
            twitter_url: data.twitter_url || null,
            instagram_url: data.instagram_url || null,
            youtube_url: data.youtube_url || null,
            tiktok_url: data.tiktok_url || null,
            accepting_clients: Boolean(data.accepting_clients),
        }))
        .patch(route('provider.profile.update'), {
            preserveScroll: true,
            onSuccess: () => {
                professionalModalOpen.value = false;
            },
        });
};
</script>

<template>
    <Head title="Profile" />

    <ProviderLayout>
        <div class="bg-slate-100 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1600px] space-y-6">
                <div
                    v-if="!provider"
                    class="rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm"
                >
                    <h1 class="text-lg font-semibold text-slate-950">Provider profile not found</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Complete onboarding or contact support if you believe this is an error.
                    </p>
                </div>

                <template v-else>
                    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="relative z-0 h-36 overflow-hidden rounded-t-lg bg-slate-900 sm:h-40 md:h-44">
                            <img
                                src="/images/immigrationlawyer.jpg"
                                alt=""
                                class="h-full w-full object-cover opacity-70"
                            >
                            <div class="absolute inset-0 bg-slate-950/35" />
                            <div class="absolute bottom-3 left-4 right-4 flex flex-wrap items-end justify-between gap-3 text-white sm:bottom-4 sm:left-6 sm:right-6">
                                <div class="min-w-0 pr-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-100 sm:text-sm">My profile</p>
                                    <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:mt-2 sm:text-3xl md:text-4xl">{{ displayName }}</h1>
                                    <p class="mt-1 flex items-center gap-2 text-xs text-slate-100 sm:mt-2 sm:text-sm">
                                        <MapPinIcon class="h-4 w-4 shrink-0" />
                                        {{ locationSummary }}
                                    </p>
                                </div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    :disabled="avatarUploading"
                                    @click="avatarInput?.click()"
                                >
                                    <CameraIcon class="h-4 w-4" />
                                    Change photo
                                </Button>
                            </div>
                        </div>

                        <div class="relative z-10 rounded-b-lg bg-white px-4 pb-5 pt-8 sm:px-6 sm:pt-10">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <div class="relative -mt-6 h-24 w-24 shrink-0 rounded-lg border-4 border-white bg-blue-600 shadow-lg sm:-mt-8 sm:h-28 sm:w-28">
                                        <img
                                            v-if="hasAvatar"
                                            :src="user?.avatar_url"
                                            :alt="displayName"
                                            class="h-full w-full rounded-md object-cover"
                                        >
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center rounded-md text-4xl font-bold text-white"
                                        >
                                            {{ avatarInitial }}
                                        </div>
                                        <button
                                            type="button"
                                            class="absolute -bottom-2 -right-2 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
                                            :disabled="avatarUploading"
                                            @click="avatarInput?.click()"
                                        >
                                            <CameraIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1 sm:max-w-xl sm:pb-1 sm:pt-3 lg:pt-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                                {{ languageLabel(user?.preferred_language) }}
                                            </span>
                                            <span class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                {{ completionPercent }}% complete
                                            </span>
                                        </div>
                                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                                        Keep your account and listing details current so clients can find you, understand your services, and reach out with confidence.
                                    </p>
                                    </div>
                                </div>

                                <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-4 lg:w-auto">
                                    <div
                                        v-for="card in insightCards"
                                        :key="card.label"
                                        class="rounded-lg border bg-white p-4 shadow-sm"
                                    >
                                        <div :class="['mb-3 inline-flex rounded-lg border p-2', card.tone]">
                                            <component :is="card.icon" class="h-4 w-4" />
                                        </div>
                                        <p class="text-2xl font-semibold text-slate-950">{{ card.value }}</p>
                                        <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">{{ card.detail }}</p>
                                    </div>
                                </div>
                            </div>

                            <input
                                ref="avatarInput"
                                type="file"
                                accept="image/jpeg,image/png"
                                class="hidden"
                                @change="uploadAvatar"
                            >
                            <div
                                v-if="avatarTypeError || avatarServerError"
                                class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
                            >
                                {{ avatarTypeError || avatarServerError }}
                            </div>
                        </div>
                    </section>

                    <RoleAccountsPanel />

                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_420px]">
                        <main class="space-y-6">
                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h2 class="text-lg font-semibold text-slate-950">Profile Details</h2>
                                            <p class="mt-1 text-sm text-slate-500">Visible details and match preferences.</p>
                                        </div>
                                        <Link
                                            :href="route('provider.profile.edit')"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-blue-600 bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 hover:border-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" />
                                            Save profile
                                        </Link>
                                    </div>
                                </div>

                                <div class="space-y-8 p-5">
                                    <div class="grid gap-5 md:grid-cols-2">
                                        <Input
                                            :model-value="user?.first_name || ''"
                                            label="First name"
                                            readonly
                                        />
                                        <Input
                                            :model-value="user?.last_name || ''"
                                            label="Last name"
                                            readonly
                                        />
                                        <Input
                                            :model-value="user?.email || ''"
                                            type="email"
                                            label="Email"
                                            readonly
                                        />
                                        <Input
                                            :model-value="user?.phone || ''"
                                            label="Phone"
                                            readonly
                                        />
                                    </div>

                                    <div class="grid gap-5 md:grid-cols-2">
                                        <Input
                                            :model-value="provider?.business_name || ''"
                                            label="Business name"
                                            readonly
                                        />
                                        <Input
                                            :model-value="provider?.tagline || ''"
                                            label="Tagline"
                                            readonly
                                        />
                                    </div>

                                    <div class="grid gap-5 md:grid-cols-3">
                                        <Input
                                            :model-value="user?.city || ''"
                                            label="City"
                                            readonly
                                        />
                                        <Input
                                            :model-value="user?.state || ''"
                                            label="State"
                                            readonly
                                        />
                                        <Select
                                            :model-value="user?.country || ''"
                                            :options="countryOptions"
                                            label="Country"
                                            placeholder="—"
                                            size="auth"
                                            disabled
                                        />
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <h2 class="text-lg font-semibold text-slate-950">About you</h2>
                                    <p class="mt-1 text-sm text-slate-500">Your story on your public listing.</p>
                                </div>
                                <div class="space-y-4 p-5">
                                    <p class="whitespace-pre-line text-sm leading-6 text-slate-700">
                                        {{ provider?.bio || 'No bio added yet.' }}
                                    </p>
                                    <p class="whitespace-pre-line text-sm leading-6 text-slate-600">
                                        {{ provider?.description || 'No detailed description yet.' }}
                                    </p>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-950">Posts</h2>
                                        <p class="mt-1 text-sm text-slate-500">
                                            Videos and articles on your public profile.
                                        </p>
                                    </div>
                                    <Link
                                        :href="`${route('provider.profile.edit')}#public-profile-feed`"
                                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                        Manage feed
                                    </Link>
                                </div>
                                <div class="p-5">
                                    <ProviderProfileFeed
                                        v-if="profileFeed.length"
                                        hide-intro
                                        :posts="profileFeed"
                                        :business-name="provider?.business_name || ''"
                                        :avatar-url="feedAvatarUrl"
                                    />
                                    <p v-else class="text-sm leading-6 text-slate-600">
                                        No posts yet. Open
                                        <Link
                                            :href="`${route('provider.profile.edit')}#public-profile-feed`"
                                            class="font-semibold text-blue-700 hover:text-blue-800"
                                        >
                                            Edit profile
                                        </Link>
                                        to add content.
                                    </p>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <h2 class="text-lg font-semibold text-slate-950">Contact</h2>
                                    <p class="mt-1 text-sm text-slate-500">How clients can reach you.</p>
                                </div>
                                <div class="grid gap-4 p-5 text-sm sm:grid-cols-2">
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <EnvelopeIcon class="h-4 w-4 shrink-0 text-slate-500" />
                                        <span class="min-w-0 break-words">{{ provider?.business_email || 'No email' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <PhoneIcon class="h-4 w-4 shrink-0 text-slate-500" />
                                        <span>{{ provider?.business_phone || 'No phone' }}</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-slate-700 sm:col-span-2">
                                        <MapPinIcon class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                                        <span>{{ provider?.service_areas?.join(', ') || 'No specific service areas set' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-700 sm:col-span-2">
                                        <GlobeAltIcon class="h-4 w-4 shrink-0 text-slate-500" />
                                        <span class="min-w-0 break-all">{{ provider?.website || 'No website' }}</span>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h2 class="text-lg font-semibold text-slate-950">Professional details</h2>
                                            <p class="mt-1 text-sm text-slate-500">Services, pricing, credentials, and social links.</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100"
                                            @click="openProfessionalModal"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" />
                                            Edit
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-6 p-5">
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Services</p>
                                            <div class="mt-3 space-y-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">Service types</p>
                                                    <p class="mt-1 text-sm text-slate-700">{{ provider?.service_types?.join(', ') || 'Not specified' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">Specializations</p>
                                                    <p class="mt-1 text-sm text-slate-700">{{ provider?.specializations?.join(', ') || 'Not specified' }}</p>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <p class="text-xs font-semibold text-slate-500">Experience</p>
                                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ provider?.years_experience ?? '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs font-semibold text-slate-500">Remote</p>
                                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ provider?.serves_remote ? 'Yes' : 'No' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs font-semibold text-slate-500">In person</p>
                                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ provider?.serves_in_person ? 'Yes' : 'No' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Pricing</p>
                                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-500">Model</p>
                                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ provider?.pricing_model || '—' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-500">Hourly</p>
                                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatCurrency(provider?.hourly_rate) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-500">Consultation</p>
                                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatCurrency(provider?.consultation_fee) }}</p>
                                                </div>
                                            </div>
                                            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                                                <span
                                                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                                                    :class="provider?.free_consultation ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-700'"
                                                >
                                                    {{ provider?.free_consultation ? 'Free consultation' : 'Paid consultation' }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                                                    :class="provider?.accepting_clients ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-50 text-slate-700'"
                                                >
                                                    {{ provider?.accepting_clients ? 'Accepting clients' : 'Not accepting clients' }}
                                                </span>
                                            </div>
                                            <p v-if="provider?.pricing_notes" class="mt-4 text-sm text-slate-700">
                                                {{ provider.pricing_notes }}
                                            </p>
                                            <p v-else class="mt-4 text-sm text-slate-500">
                                                No pricing notes.
                                            </p>
                                        </div>
                                    </div>

                                    <div v-if="showsHealthcareNavigatorCredentials" class="border-t border-slate-100 pt-8">
                                        <h3 class="text-sm font-semibold text-slate-950">Credentials</h3>
                                        <div class="mt-3 space-y-6 text-sm text-slate-700">
                                            <div class="space-y-2">
                                                <p><span class="font-semibold text-slate-900">License number:</span> {{ provider?.license_number || 'Not set' }}</p>
                                                <p><span class="font-semibold text-slate-900">License state:</span> {{ provider?.license_state || 'Not set' }}</p>
                                                <p><span class="font-semibold text-slate-900">License expiry:</span> {{ provider?.license_expiry || 'Not set' }}</p>
                                                <p class="flex items-center gap-2">
                                                    <span class="font-semibold text-slate-900">State license document:</span>
                                                    <a
                                                        v-if="stateLicenseFileUrl"
                                                        :href="stateLicenseFileUrl"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="inline-flex items-center gap-1 font-semibold text-blue-700 underline hover:text-blue-800"
                                                    >
                                                        <DocumentTextIcon class="h-4 w-4" />
                                                        {{ provider?.state_license_document_name || 'View file' }}
                                                    </a>
                                                    <span v-else>Not uploaded</span>
                                                </p>
                                            </div>

                                            <div>
                                                <p class="font-semibold text-slate-900">Certifications</p>
                                                <div v-if="provider?.certifications?.length" class="mt-2 space-y-2">
                                                    <div
                                                        v-for="(cert, index) in provider.certifications"
                                                        :key="index"
                                                        class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700"
                                                    >
                                                        <span class="font-medium text-slate-900">{{ cert.name || 'Untitled certification' }}</span>
                                                        <span v-if="cert.issuer"> — {{ cert.issuer }}</span>
                                                        <span v-if="cert.year"> ({{ cert.year }})</span>
                                                    </div>
                                                </div>
                                                <p v-else class="mt-2 text-sm text-slate-500">No certifications added.</p>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Social</p>
                                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-slate-500">LinkedIn</dt>
                                                <dd class="truncate font-medium text-slate-900">{{ provider?.linkedin_url || '—' }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-slate-500">Facebook</dt>
                                                <dd class="truncate font-medium text-slate-900">{{ provider?.facebook_url || '—' }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-slate-500">Twitter</dt>
                                                <dd class="truncate font-medium text-slate-900">{{ provider?.twitter_url || '—' }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-slate-500">Instagram</dt>
                                                <dd class="truncate font-medium text-slate-900">{{ provider?.instagram_url || '—' }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-slate-500">YouTube</dt>
                                                <dd class="truncate font-medium text-slate-900">{{ provider?.youtube_url || '—' }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-slate-500">TikTok</dt>
                                                <dd class="truncate font-medium text-slate-900">{{ provider?.tiktok_url || '—' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </section>
                        </main>

                        <aside class="space-y-6">
                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <h2 class="text-lg font-semibold text-slate-950">Matching Providers</h2>
                                    <p class="mt-1 text-sm text-slate-500">Based on language, location, family, pets, and service needs.</p>
                                </div>
                                <div class="p-6 text-sm text-slate-500">
                                    <p>
                                        Complete your listing, service areas, and languages so the right clients can discover you.
                                        New inquiries appear in
                                        <Link :href="route('provider.leads.index')" class="font-semibold text-blue-700 hover:text-blue-800">Leads</Link>.
                                    </p>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h2 class="text-lg font-semibold text-slate-950">Service certificates</h2>
                                            <p class="mt-1 text-sm text-slate-500">Uploaded certificates visible on your account.</p>
                                        </div>
                                        <Link
                                            :href="`${route('provider.profile.edit')}#service-certificates`"
                                            class="inline-flex items-center justify-center rounded-lg border border-primary-200 px-3 py-1.5 text-sm font-semibold text-primary-700 transition hover:bg-primary-50"
                                        >
                                            Add
                                        </Link>
                                    </div>
                                </div>
                                <div class="space-y-2 p-5">
                                    <div v-if="provider?.health_certificates?.length" class="space-y-2">
                                        <div
                                            v-for="(certificate, index) in provider.health_certificates"
                                            :key="`health-cert-sidebar-${index}`"
                                            class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700"
                                        >
                                            <img
                                                v-if="healthCertificateThumbnailUrl(certificate)"
                                                :src="healthCertificateThumbnailUrl(certificate)"
                                                :alt="certificate.name || 'Certificate thumbnail'"
                                                class="mb-2 h-24 w-24 rounded-lg border border-slate-200 object-cover bg-white"
                                            >
                                            <p class="font-medium text-slate-900">{{ certificate.name || 'Untitled certificate' }}</p>
                                            <p v-if="certificate.service_type" class="mt-0.5 text-slate-600">
                                                Service: {{ formatCertificateServiceType(certificate.service_type) }}
                                            </p>
                                            <p v-if="certificate.issuing_authority" class="mt-0.5 text-slate-600">
                                                Issued by {{ certificate.issuing_authority }}
                                            </p>
                                            <p class="mt-0.5 text-slate-600">Expiration: {{ formatDate(certificate.expiration_date) }}</p>
                                            <a
                                                v-if="certificate.file_path"
                                                :href="healthCertificateFileUrl(certificate.file_path)"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="mt-1 inline-block text-sm font-semibold text-blue-700 underline hover:text-blue-800"
                                            >
                                                {{ certificate.original_name || 'View document' }}
                                            </a>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-slate-500">No service certificates added.</p>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="mb-5 flex items-start gap-3">
                                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-2 text-emerald-700">
                                        <UserPlusIcon class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-950">Invite A Provider</h2>
                                        <p class="mt-1 text-sm text-slate-500">Recommend a service provider you trust.</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-3 block text-base font-medium text-slate-700">Public profile link</label>
                                        <input
                                            type="text"
                                            readonly
                                            :value="publicListingUrl || 'Publish your listing to get a link.'"
                                            class="w-full cursor-default rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none"
                                        >
                                    </div>
                                    <Link
                                        v-if="provider?.slug"
                                        :href="route('marketplace.show', provider.slug)"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-blue-600 bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                                    >
                                        View public listing
                                    </Link>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <LinkIcon class="h-5 w-5 text-slate-500" />
                                        <h2 class="text-lg font-semibold text-slate-950">Profile snapshot</h2>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">Account and listing at a glance.</p>
                                </div>
                                <div class="p-5">
                                    <dl class="space-y-3 text-sm">
                                        <div v-if="memberSinceLabel" class="flex justify-between gap-4">
                                            <dt class="text-slate-500">Member since</dt>
                                            <dd class="text-right font-medium text-slate-900">{{ memberSinceLabel }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-slate-500">Account email</dt>
                                            <dd class="truncate text-right font-medium text-slate-900">{{ user?.email || 'Not set' }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-slate-500">Business</dt>
                                            <dd class="truncate text-right font-medium text-slate-900">{{ provider?.business_name }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-slate-500">Public listing</dt>
                                            <dd class="text-right font-medium text-slate-900">
                                                <Link
                                                    v-if="provider?.slug"
                                                    :href="route('marketplace.show', provider.slug)"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    View on marketplace
                                                </Link>
                                                <span v-else class="text-slate-500">Not available</span>
                                            </dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-slate-500">Listing email</dt>
                                            <dd class="truncate text-right font-medium text-slate-900">{{ provider?.business_email || 'Not set' }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-slate-500">Phone</dt>
                                            <dd class="text-right font-medium text-slate-900">{{ provider?.business_phone || 'Not set' }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-slate-500">Rating</dt>
                                            <dd class="flex items-center justify-end gap-1 font-medium text-slate-900">
                                                <StarSolid class="h-4 w-4 text-amber-500" />
                                                {{ Number(provider?.average_rating || 0).toFixed(1) }}
                                                <span class="text-slate-500">({{ provider?.total_reviews || 0 }})</span>
                                            </dd>
                                        </div>
                                    </dl>
                                    <Link
                                        :href="route('provider.profile.edit')"
                                        class="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl border border-blue-600 bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                        Edit profile
                                    </Link>
                                </div>
                            </section>
                        </aside>
                    </div>
                </template>
            </div>
        </div>
    </ProviderLayout>

    <TransitionRoot as="template" :show="professionalModalOpen">
        <Dialog as="div" class="relative z-50" @close="professionalModalOpen = false">
            <TransitionChild
                as="template"
                enter="ease-out duration-200"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-150"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-slate-900/50" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6">
                <div class="flex min-h-full items-center justify-center">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
                            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                                <div>
                                    <DialogTitle class="text-lg font-semibold text-slate-950">Edit professional details</DialogTitle>
                                    <p class="mt-1 text-sm text-slate-600">Updates your public listing details.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100"
                                    @click="professionalModalOpen = false"
                                >
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>

                            <form class="space-y-6 px-6 py-6" @submit.prevent="saveProfessionalDetails">
                                <div class="grid gap-5 md:grid-cols-2">
                                    <Input v-model="professionalForm.business_name" label="Business name" required :error="professionalForm.errors.business_name" />
                                    <Input v-model="professionalForm.years_experience" label="Years of experience" type="number" min="0" :error="professionalForm.errors.years_experience" />
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <Input
                                        v-model="professionalForm.service_types_text"
                                        label="Service types (comma-separated)"
                                        helper="Example: immigration_attorney, translator"
                                        :error="professionalForm.errors.service_types"
                                        required
                                    />
                                    <Input
                                        v-model="professionalForm.languages_offered_text"
                                        label="Languages offered (comma-separated)"
                                        helper="Example: en, es"
                                        :error="professionalForm.errors.languages_offered"
                                        required
                                    />
                                </div>

                                <Input
                                    v-model="professionalForm.specializations_text"
                                    label="Specializations (comma-separated)"
                                    :error="professionalForm.errors.specializations"
                                />

                                <div class="grid gap-5 md:grid-cols-2">
                                    <Select
                                        v-model="professionalForm.pricing_model"
                                        :options="pricingModelOptions"
                                        label="Pricing model"
                                        placeholder="Not set"
                                        size="auth"
                                        :error="professionalForm.errors.pricing_model"
                                    />
                                    <Input v-model="professionalForm.hourly_rate" label="Hourly rate" type="number" min="0" step="0.01" :error="professionalForm.errors.hourly_rate" />
                                    <Input v-model="professionalForm.consultation_fee" label="Consultation fee" type="number" min="0" step="0.01" :error="professionalForm.errors.consultation_fee" />
                                    <Input v-model="professionalForm.service_radius_miles" label="Service radius (miles)" type="number" min="1" max="500" :error="professionalForm.errors.service_radius_miles" />
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
                                        <input v-model="professionalForm.serves_remote" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                                        Serves remote
                                    </label>
                                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
                                        <input v-model="professionalForm.serves_in_person" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                                        Serves in person
                                    </label>
                                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
                                        <input v-model="professionalForm.free_consultation" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                                        Free consultation
                                    </label>
                                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
                                        <input v-model="professionalForm.accepting_clients" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                                        Accepting clients
                                    </label>
                                </div>

                                <div>
                                    <label class="mb-3 block text-base font-medium text-slate-700">Pricing notes</label>
                                    <textarea
                                        v-model="professionalForm.pricing_notes"
                                        rows="3"
                                        class="w-full resize-none rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-base text-slate-900 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                        placeholder="Optional notes about your pricing..."
                                    />
                                    <p v-if="professionalForm.errors.pricing_notes" class="mt-2 text-sm font-medium text-red-600">
                                        {{ professionalForm.errors.pricing_notes }}
                                    </p>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <Input v-model="professionalForm.linkedin_url" label="LinkedIn URL" :error="professionalForm.errors.linkedin_url" />
                                    <Input v-model="professionalForm.facebook_url" label="Facebook URL" :error="professionalForm.errors.facebook_url" />
                                    <Input v-model="professionalForm.twitter_url" label="Twitter/X URL" :error="professionalForm.errors.twitter_url" />
                                    <Input v-model="professionalForm.instagram_url" label="Instagram URL" :error="professionalForm.errors.instagram_url" />
                                    <Input v-model="professionalForm.youtube_url" label="YouTube URL" :error="professionalForm.errors.youtube_url" />
                                    <Input v-model="professionalForm.tiktok_url" label="TikTok URL" :error="professionalForm.errors.tiktok_url" />
                                </div>

                                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50"
                                        @click="professionalModalOpen = false"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="professionalForm.processing"
                                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Save changes
                                    </button>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
