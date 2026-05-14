<script setup>
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import RoleAccountsPanel from '@/Components/Account/RoleAccountsPanel.vue';
import ProfileSharePanel from '@/Components/marketplace/ProfileSharePanel.vue';
import Button from '@/Components/ui/Button.vue';
import {
    ArrowLeftIcon,
    CameraIcon,
    BuildingOfficeIcon,
    CurrencyDollarIcon,
    MapPinIcon,
    GlobeAltIcon,
    PlusIcon,
    TrashIcon,
    EyeIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    VideoCameraIcon,
    NewspaperIcon,
    PencilSquareIcon,
    BookOpenIcon,
    ChatBubbleLeftRightIcon,
    SparklesIcon,
    UserPlusIcon,
    LinkIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { ref, computed, reactive } from 'vue';
import LocationCountryStatePick from '@/Components/address/LocationCountryStatePick.vue';

const props = defineProps({
    user: { type: Object, required: true },
    provider: { type: Object, required: true },
    serviceTypes: { type: Array, default: () => [] },
    /** Canonical public listing URL and copy for social shares */
    providerShare: { type: Object, default: null },
    profileFeed: { type: Array, default: () => [] },
    profileStats: { type: Object, default: () => ({}) },
    languageOptions: { type: Array, default: () => [] },
    countryOptions: { type: Array, default: () => [] },
    stateOptions: { type: Array, default: () => [] },
    defaultLocationCountry: { type: String, default: 'US' },
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

const displayName = computed(() => {
    const first = props.user?.first_name?.trim();
    const last = props.user?.last_name?.trim();
    const full = [first, last].filter(Boolean).join(' ').trim();
    return full || 'Your profile';
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

const publicListingUrl = computed(() => {
    if (!props.provider?.slug) return '';
    const path = route('marketplace.show', props.provider.slug);
    if (typeof window !== 'undefined') {
        return `${window.location.origin}${path}`;
    }
    return path;
});

const hasAvatar = computed(() => Boolean(props.user?.avatar_url));

const form = useForm({
    // Business Info
    business_name: props.provider?.business_name || '',
    tagline: props.provider?.tagline || '',
    bio: props.provider?.bio || '',
    description: props.provider?.description || '',
    
    // Contact
    business_email: props.provider?.business_email || '',
    business_phone: props.provider?.business_phone || '',
    website: props.provider?.website || '',
    
    // Services
    service_types: props.provider?.service_types || [],
    specializations: props.provider?.specializations || [],
    languages_offered: props.provider?.languages_offered || [],
    
    // Pricing
    pricing_model: props.provider?.pricing_model || 'hourly',
    hourly_rate: props.provider?.hourly_rate || '',
    consultation_fee: props.provider?.consultation_fee || '',
    free_consultation: props.provider?.free_consultation || false,
    pricing_notes: props.provider?.pricing_notes || '',
    
    // Service Area
    serves_remote: props.provider?.serves_remote || false,
    serves_in_person: props.provider?.serves_in_person || true,
    service_radius_miles: props.provider?.service_radius_miles || '',
    service_areas: props.provider?.service_areas || [],
    
    // Credentials
    license_number: props.provider?.license_number || '',
    license_state: props.provider?.license_state || '',
    license_expiry: props.provider?.license_expiry || '',
    state_license_document: null,
    remove_state_license_document: false,
    state_license_document_path: props.provider?.state_license_document_path || '',
    state_license_document_name: props.provider?.state_license_document_name || '',
    certifications: props.provider?.certifications || [],
    health_certificates: (props.provider?.health_certificates || []).map((certificate) => ({
        name: certificate.name || '',
        service_type: certificate.service_type || '',
        issuing_authority: certificate.issuing_authority || '',
        expiration_date: certificate.expiration_date || '',
        file_path: certificate.file_path || '',
        original_name: certificate.original_name || '',
        document: null,
        preview_url: '',
    })),
    years_experience: props.provider?.years_experience || '',
    
    // Social Links
    linkedin_url: props.provider?.linkedin_url || '',
    facebook_url: props.provider?.facebook_url || '',
    twitter_url: props.provider?.twitter_url || '',
    instagram_url: props.provider?.instagram_url || '',
    youtube_url: props.provider?.youtube_url || '',
    tiktok_url: props.provider?.tiktok_url || '',
    
    // Status
    accepting_clients: props.provider?.accepting_clients ?? true,
});

const avatarInitial = computed(() => {
    const first = props.user?.first_name?.trim();
    if (first) return first.charAt(0).toUpperCase();
    const last = props.user?.last_name?.trim();
    if (last) return last.charAt(0).toUpperCase();
    const source = form.business_name || props.provider?.business_name || 'P';
    return String(source).trim().charAt(0).toUpperCase();
});

const avatarInput = ref(null);
const stateLicenseInput = ref(null);
const newSpecialization = ref('');
const serviceAreaPickError = ref('');

const serviceAreaPick = reactive({
    country: props.defaultLocationCountry || 'US',
    state: '',
    city: '',
    postal_code: '',
    county: '',
    location_label: '',
});

const resetServiceAreaPickFields = () => {
    serviceAreaPick.state = '';
    serviceAreaPick.city = '';
    serviceAreaPick.postal_code = '';
    serviceAreaPick.county = '';
    serviceAreaPick.location_label = '';
};

const languageOptions = [
    { code: 'en', label: 'English' },
    { code: 'es', label: 'Spanish' },
    { code: 'zh', label: 'Chinese (Mandarin)' },
    { code: 'hi', label: 'Hindi' },
    { code: 'ar', label: 'Arabic' },
    { code: 'pt', label: 'Portuguese' },
    { code: 'fr', label: 'French' },
    { code: 'de', label: 'German' },
    { code: 'ja', label: 'Japanese' },
    { code: 'ko', label: 'Korean' },
    { code: 'vi', label: 'Vietnamese' },
    { code: 'tl', label: 'Tagalog' },
    { code: 'ru', label: 'Russian' },
    { code: 'it', label: 'Italian' },
    { code: 'pl', label: 'Polish' },
];

const pricingModels = [
    { value: 'hourly', label: 'Hourly Rate' },
    { value: 'flat_rate', label: 'Flat Rate per Service' },
    { value: 'consultation', label: 'Consultation-Based' },
    { value: 'custom', label: 'Custom Quote' },
];

const normalizeServiceTypeValue = (value) => String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');

const certificateRequiredServiceTypeValues = computed(() =>
    [
        'babysitter',
        'baby_sitter',
        'pet_sitter',
        'petsitter',
        'health_navigator',
        'healthcare_navigator',
        'healthnavigator',
    ]
);

const selectedCertificateRequiredTypes = computed(() => {
    const selected = form.service_types.map((type) => normalizeServiceTypeValue(type));
    return selected.filter((type) => certificateRequiredServiceTypeValues.value.includes(type));
});

const certificateRequiredTypeOptions = computed(() =>
    selectedCertificateRequiredTypes.value.map((value) => {
        const fromProps = (props.serviceTypes || []).find((type) => normalizeServiceTypeValue(type.value) === value);
        const fallbackLabels = {
            health_navigator: 'Healthcare Navigator',
            healthcare_navigator: 'Healthcare Navigator',
            healthnavigator: 'Healthcare Navigator',
            pet_sitter: 'Pet Sitter',
            petsitter: 'Pet Sitter',
            babysitter: 'Babysitter',
            baby_sitter: 'Babysitter',
        };
        return {
            value,
            label: fromProps?.label || fallbackLabels[value] || value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        };
    })
);

const showsHealthCertificates = computed(() => selectedCertificateRequiredTypes.value.length > 0);

const isHealthcareServiceType = (value) => ['health_navigator', 'healthcare_navigator', 'healthnavigator']
    .includes(normalizeServiceTypeValue(value));

const updateProfile = () => {
    form.patch(route('provider.profile.update'), {
        preserveScroll: true,
        forceFormData: true,
    });
};

const stateLicenseFileUrl = computed(() => {
    const path = form.state_license_document_path;
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
});

const onStateLicenseFileChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    form.state_license_document = file;
    form.state_license_document_name = file.name;
    form.remove_state_license_document = false;
};

const removeStateLicenseDocument = () => {
    form.state_license_document = null;
    form.state_license_document_name = '';
    form.state_license_document_path = '';
    form.remove_state_license_document = true;
    if (stateLicenseInput.value) {
        stateLicenseInput.value.value = '';
    }
};

const avatarUploading = ref(false);
const avatarClientError = ref('');

const uploadAvatar = (event) => {
    const input = event.target;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    if (!allowedTypes.includes(file.type)) {
        avatarClientError.value = 'Please choose a JPEG, PNG, GIF, WebP, or BMP image.';
        input.value = '';
        return;
    }
    avatarClientError.value = '';

    const formData = new FormData();
    formData.append('avatar', file);

    avatarUploading.value = true;
    router.post(route('provider.profile.avatar'), formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            avatarClientError.value = '';
        },
        onFinish: () => {
            avatarUploading.value = false;
            input.value = '';
        },
    });
};

const addHealthCertificate = () => {
    form.health_certificates.push({
        name: '',
        service_type: selectedCertificateRequiredTypes.value[0] || '',
        issuing_authority: '',
        expiration_date: '',
        file_path: '',
        original_name: '',
        document: null,
        preview_url: '',
    });
};

const removeHealthCertificate = (index) => {
    const certificate = form.health_certificates[index];
    if (certificate?.preview_url) {
        URL.revokeObjectURL(certificate.preview_url);
    }
    form.health_certificates.splice(index, 1);
};

const onHealthCertificateFileChange = (event, certificate) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    if (certificate.preview_url) {
        URL.revokeObjectURL(certificate.preview_url);
    }
    certificate.document = file;
    certificate.original_name = file.name;
    if (!certificate.service_type && selectedCertificateRequiredTypes.value.length === 1) {
        [certificate.service_type] = selectedCertificateRequiredTypes.value;
    }
    certificate.preview_url = URL.createObjectURL(file);
    if (!certificate.name) {
        certificate.name = file.name.replace(/\.[^/.]+$/, '');
    }
};

const healthCertificateFileUrl = (path) => {
    if (!path) {
        return '';
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    return `/storage/${path}`;
};

const isImageCertificate = (value) => /\.(jpg|jpeg|png|webp|gif|bmp)$/i.test(String(value || ''));

const healthCertificatePreviewUrl = (certificate) => {
    if (certificate.preview_url) {
        return certificate.preview_url;
    }
    if (certificate.file_path && isImageCertificate(certificate.file_path)) {
        return healthCertificateFileUrl(certificate.file_path);
    }
    if (certificate.original_name && isImageCertificate(certificate.original_name) && certificate.file_path) {
        return healthCertificateFileUrl(certificate.file_path);
    }
    return '';
};

const buildServiceAreaLabelFromPick = () => {
    const p = serviceAreaPick;
    let label = (p.location_label || '').trim();
    if (!label) {
        const cityState = [p.city, p.state].filter(Boolean).join(', ');
        label = [cityState, p.postal_code].filter(Boolean).join(' ').trim();
    }
    if (!label) {
        return '';
    }
    if (p.country && p.country !== 'US') {
        label = `${label} (${p.country})`;
    }
    return label.length > 255 ? label.slice(0, 252) + '...' : label;
};

const addServiceAreaFromPick = () => {
    serviceAreaPickError.value = '';
    if (!serviceAreaPick.state?.trim()) {
        serviceAreaPickError.value = 'Select or enter a state / region first.';
        return;
    }
    if (serviceAreaPick.country === 'US') {
        const hasPick =
            (serviceAreaPick.location_label || '').trim() ||
            (serviceAreaPick.postal_code || '').trim() ||
            (serviceAreaPick.city || '').trim();
        if (!hasPick) {
            serviceAreaPickError.value = 'Search and choose a city, ZIP, or county (United States).';
            return;
        }
    } else if (!(serviceAreaPick.city || '').trim()) {
        serviceAreaPickError.value = 'Enter a city or location.';
        return;
    }

    const label = buildServiceAreaLabelFromPick();
    if (!label) {
        serviceAreaPickError.value = 'Could not build a service area label from your selection.';
        return;
    }
    if (form.service_areas.includes(label)) {
        serviceAreaPickError.value = 'This service area is already in your list.';
        return;
    }

    form.service_areas.push(label);
    resetServiceAreaPickFields();
};

const removeServiceArea = (index) => {
    form.service_areas.splice(index, 1);
};

const addSpecialization = () => {
    if (newSpecialization.value && !form.specializations.includes(newSpecialization.value)) {
        form.specializations.push(newSpecialization.value);
        newSpecialization.value = '';
    }
};

const removeSpecialization = (index) => {
    form.specializations.splice(index, 1);
};

const toggleLanguage = (code) => {
    const index = form.languages_offered.indexOf(code);
    if (index > -1) {
        form.languages_offered.splice(index, 1);
    } else {
        form.languages_offered.push(code);
    }
};

const toggleServiceType = (value) => {
    const index = form.service_types.indexOf(value);
    if (index > -1) {
        form.service_types.splice(index, 1);
    } else {
        form.service_types.push(value);
    }

    if (!showsHealthCertificates.value) {
        form.health_certificates = [];
    }
};

const verificationStatusLabel = computed(() => {
    const status = props.provider?.verification_status;
    if (status === 'approved') return { text: 'Verified', class: 'bg-green-100 text-green-700' };
    if (status === 'pending') return { text: 'Pending Verification', class: 'bg-yellow-100 text-yellow-700' };
    if (status === 'rejected') return { text: 'Verification Rejected', class: 'bg-red-100 text-red-700' };
    return { text: 'Not Verified', class: 'bg-slate-100 text-slate-700' };
});

const feedForm = useForm({
    type: 'video',
    url: '',
    title: '',
    caption: '',
});

const feedEditForm = useForm({
    type: 'video',
    url: '',
    title: '',
    caption: '',
});

const editingFeedUuid = ref(null);

const submitFeedPost = () => {
    feedForm.post(route('provider.profile-posts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            feedForm.reset();
            feedForm.type = 'video';
        },
    });
};

const startEditFeedPost = (post) => {
    editingFeedUuid.value = post.uuid;
    feedEditForm.clearErrors();
    feedEditForm.type = post.type;
    feedEditForm.url = post.url;
    feedEditForm.title = post.title || '';
    feedEditForm.caption = post.caption || '';
};

const cancelEditFeedPost = () => {
    editingFeedUuid.value = null;
    feedEditForm.reset();
    feedEditForm.type = 'video';
};

const saveFeedPost = () => {
    if (!editingFeedUuid.value) {
        return;
    }
    feedEditForm.patch(route('provider.profile-posts.update', editingFeedUuid.value), {
        preserveScroll: true,
        onSuccess: () => cancelEditFeedPost(),
    });
};

const deleteFeedPost = (uuid) => {
    if (!window.confirm('Remove this item from your public profile?')) {
        return;
    }
    router.delete(route('provider.profile-posts.destroy', uuid), { preserveScroll: true });
};

const formatFeedDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Edit Profile" />

    <ProviderLayout>
        <div class="bg-slate-100 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1600px] space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="relative z-0 h-36 overflow-hidden rounded-t-lg bg-slate-900 sm:h-40 md:h-44">
                        <img
                            src="/images/immigrationlawyer.jpg"
                            alt=""
                            class="h-full w-full object-cover opacity-70"
                        >
                        <div class="absolute inset-0 bg-slate-950/35" />
                        <Link
                            :href="route('provider.profile.index')"
                            class="absolute left-4 top-3 z-10 inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1.5 text-xs font-semibold text-white backdrop-blur transition hover:bg-white/20 sm:left-6 sm:top-4"
                        >
                            <ArrowLeftIcon class="h-4 w-4" />
                            Overview
                        </Link>
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
                                        :src="user.avatar_url"
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
                                        class="absolute -bottom-2 -right-2 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50"
                                        :disabled="avatarUploading"
                                        @click="avatarInput?.click()"
                                    >
                                        <CameraIcon class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="min-w-0 flex-1 pt-1 sm:max-w-xl sm:pb-1 sm:pt-3 lg:pt-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                            {{ languageLabel(user.preferred_language) }}
                                        </span>
                                        <span class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            {{ completionPercent }}% complete
                                        </span>
                                        <span :class="['rounded-full border px-3 py-1 text-xs font-semibold', verificationStatusLabel.class]">
                                            {{ verificationStatusLabel.text }}
                                        </span>
                                    </div>
                                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                                        Keep your account and listing details current so clients can find you, understand your services, and reach out with confidence.
                                    </p>
                                    <p class="mt-2 text-xs text-slate-500">
                                        JPEG, PNG, GIF, WebP, or BMP. Max 5&nbsp;MB.
                                    </p>
                                    <p v-if="avatarClientError" class="mt-2 text-sm text-red-600">{{ avatarClientError }}</p>
                                    <p v-else-if="page.props.errors?.avatar" class="mt-2 text-sm text-red-600">{{ page.props.errors.avatar }}</p>
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
                            accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,image/jpeg,image/png,image/gif,image/webp,image/bmp"
                            class="hidden"
                            :disabled="avatarUploading"
                            @change="uploadAvatar"
                        >
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
                                        <p class="mt-1 text-sm text-slate-500">Business listing details clients see on your profile.</p>
                                    </div>
                                    <Button size="sm" :loading="form.processing" @click="updateProfile">
                                        <PencilSquareIcon class="h-4 w-4" />
                                        Save profile
                                    </Button>
                                </div>
                            </div>

                            <form id="provider-profile-edit-form" class="space-y-8 p-5" @submit.prevent="updateProfile">
                            <!-- Business Information -->
                    <div class="space-y-4">
                        <h3 class="text-base font-semibold text-slate-950">Business Information</h3>
                        <div class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Business Name *</label>
                                    <input 
                                        v-model="form.business_name" 
                                        type="text" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        required
                                    />
                                    <p v-if="form.errors.business_name" class="mt-1 text-sm text-red-600">{{ form.errors.business_name }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Tagline</label>
                                    <input 
                                        v-model="form.tagline" 
                                        type="text" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="A brief description that appears on your profile card"
                                        maxlength="100"
                                    />
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Bio *</label>
                                <textarea 
                                    v-model="form.bio"
                                    rows="4"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Tell potential clients about yourself, your background, and what makes you unique..."
                                    required
                                ></textarea>
                                <p class="mt-1 text-xs text-slate-400">{{ (form.bio?.length || 0) }}/2000 characters</p>
                                <p v-if="form.errors.bio" class="mt-1 text-sm text-red-600">{{ form.errors.bio }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Detailed Description</label>
                                <textarea 
                                    v-model="form.description"
                                    rows="6"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Provide more details about your services, approach, and expertise..."
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Years of Experience</label>
                                <input 
                                    v-model="form.years_experience" 
                                    type="number" 
                                    min="0" 
                                    max="70"
                                    class="w-32 px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Service Types -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Service Types *</h2>
                        <p class="text-sm text-slate-600 mb-4">Select all services you offer</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="type in serviceTypes"
                                :key="type.value"
                                type="button"
                                @click="toggleServiceType(type.value)"
                                class="px-4 py-2 rounded-xl text-sm font-medium border transition-all"
                                :class="form.service_types.includes(type.value) 
                                    ? 'bg-primary-100 text-primary-700 border-primary-300 ring-2 ring-primary-200' 
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                            >
                                {{ type.label }}
                            </button>
                        </div>
                        <p v-if="form.errors.service_types" class="mt-2 text-sm text-red-600">{{ form.errors.service_types }}</p>
                    </div>

                    <!-- Specializations -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Specializations</h2>
                        <p class="text-sm text-slate-600 mb-4">Add specific areas of expertise within your services</p>
                        <div class="flex gap-2 mb-3">
                            <input 
                                v-model="newSpecialization"
                                type="text"
                                class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                placeholder="e.g., H-1B Visas, Green Card Applications"
                                @keyup.enter.prevent="addSpecialization"
                            />
                            <button type="button" @click="addSpecialization" class="px-4 py-2.5 bg-slate-100 text-slate-700 rounded-xl hover:bg-slate-200 transition-colors">
                                <PlusIcon class="h-5 w-5" />
                            </button>
                        </div>
                        <div v-if="form.specializations.length" class="flex flex-wrap gap-2">
                            <span 
                                v-for="(spec, index) in form.specializations" 
                                :key="index"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-full"
                            >
                                {{ spec }}
                                <button type="button" @click="removeSpecialization(index)" class="hover:text-red-600 transition-colors">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </span>
                        </div>
                    </div>

                    <!-- Languages -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Languages *</h2>
                        <p class="text-sm text-slate-600 mb-4">Select languages you can serve clients in</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="lang in languageOptions"
                                :key="lang.code"
                                type="button"
                                @click="toggleLanguage(lang.code)"
                                class="px-4 py-2 rounded-xl text-sm font-medium border transition-all"
                                :class="form.languages_offered.includes(lang.code) 
                                    ? 'bg-primary-100 text-primary-700 border-primary-300 ring-2 ring-primary-200' 
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                            >
                                {{ lang.label }}
                            </button>
                        </div>
                        <p v-if="form.errors.languages_offered" class="mt-2 text-sm text-red-600">{{ form.errors.languages_offered }}</p>
                    </div>

                    <!-- Pricing -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Pricing</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Pricing Model</label>
                                <select 
                                    v-model="form.pricing_model"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                >
                                    <option v-for="model in pricingModels" :key="model.value" :value="model.value">
                                        {{ model.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Hourly Rate ($)</label>
                                    <input 
                                        v-model="form.hourly_rate" 
                                        type="number" 
                                        min="0" 
                                        step="0.01"
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                        placeholder="0.00" 
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Consultation Fee ($)</label>
                                    <input 
                                        v-model="form.consultation_fee" 
                                        type="number" 
                                        min="0"
                                        step="0.01" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                        placeholder="0.00" 
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input v-model="form.free_consultation" type="checkbox" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                </label>
                                <span class="text-sm text-slate-700">Offer free initial consultation</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Pricing Notes</label>
                                <textarea 
                                    v-model="form.pricing_notes"
                                    rows="2"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Additional pricing information, payment terms, etc."
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Service Area -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Service Area</h2>
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-6">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input v-model="form.serves_remote" type="checkbox" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                    <span class="ml-3 text-sm text-slate-700">Available for remote services</span>
                                </label>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input v-model="form.serves_in_person" type="checkbox" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                    <span class="ml-3 text-sm text-slate-700">Available for in-person services</span>
                                </label>
                            </div>

                            <div v-if="form.serves_in_person">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Service Radius (miles)</label>
                                <input 
                                    v-model="form.service_radius_miles" 
                                    type="number" 
                                    min="1"
                                    class="w-32 px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Specific service areas</label>
                                <p class="text-xs text-slate-500 mb-4">
                                    Same location search as client signup: choose country and state, then for the United States search by city, ZIP, or county. Add each area you serve.
                                </p>
                                <LocationCountryStatePick
                                    v-model:country="serviceAreaPick.country"
                                    v-model:state="serviceAreaPick.state"
                                    v-model:city="serviceAreaPick.city"
                                    v-model:postal-code="serviceAreaPick.postal_code"
                                    v-model:county="serviceAreaPick.county"
                                    v-model:location-label="serviceAreaPick.location_label"
                                    :country-options="countryOptions"
                                    :initial-state-options="stateOptions"
                                />
                                <p v-if="serviceAreaPickError" class="mt-2 text-sm text-red-600">{{ serviceAreaPickError }}</p>
                                <button
                                    type="button"
                                    class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700 transition-colors"
                                    @click="addServiceAreaFromPick"
                                >
                                    <PlusIcon class="h-5 w-5" />
                                    Add to service areas
                                </button>
                                <div v-if="form.service_areas.length" class="mt-4 flex flex-wrap gap-2">
                                    <span
                                        v-for="(area, index) in form.service_areas"
                                        :key="index"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-full text-sm"
                                    >
                                        {{ area }}
                                        <button type="button" @click="removeServiceArea(index)" class="hover:text-red-600 transition-colors">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Contact Information</h2>
                        <div class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Business Email</label>
                                    <input 
                                        v-model="form.business_email" 
                                        type="email" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Business Phone</label>
                                    <input 
                                        v-model="form.business_phone" 
                                        type="tel" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Website</label>
                                <input 
                                    v-model="form.website" 
                                    type="url" 
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="https://"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- State License Document -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">State License (Optional)</h2>
                        <p class="text-sm text-slate-600 mb-4">
                            Upload your professional state license document. This is optional for all provider service types.
                        </p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">License Document</label>
                                <input
                                    ref="stateLicenseInput"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    @change="onStateLicenseFileChange"
                                />
                                <p class="mt-1 text-xs text-slate-500">PDF, JPG, PNG, or WebP up to 10MB.</p>
                                <p v-if="form.errors.state_license_document" class="mt-1 text-sm text-red-600">{{ form.errors.state_license_document }}</p>
                            </div>

                            <div v-if="form.state_license_document_name || form.state_license_document_path" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 text-sm font-medium text-slate-800">
                                            <DocumentTextIcon class="h-4 w-4 text-slate-500" />
                                            <span class="truncate">{{ form.state_license_document_name || 'State license document' }}</span>
                                        </p>
                                        <a
                                            v-if="stateLicenseFileUrl && !form.state_license_document"
                                            :href="stateLicenseFileUrl"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="mt-1 inline-block text-xs font-semibold text-primary-700 underline hover:text-primary-800"
                                        >
                                            View current file
                                        </a>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-red-600 hover:text-red-700"
                                        @click="removeStateLicenseDocument"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Social Links</h2>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">LinkedIn</label>
                                <input 
                                    v-model="form.linkedin_url" 
                                    type="url" 
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="https://linkedin.com/in/"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Facebook</label>
                                <input 
                                    v-model="form.facebook_url" 
                                    type="url" 
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="https://facebook.com/"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Instagram</label>
                                <input 
                                    v-model="form.instagram_url" 
                                    type="url" 
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="https://instagram.com/"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">YouTube</label>
                                <input 
                                    v-model="form.youtube_url" 
                                    type="url" 
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="https://youtube.com/"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-slate-200 pt-8">
                        <h3 class="text-base font-semibold text-slate-950">Availability</h3>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="form.accepting_clients" type="checkbox" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                            <span class="text-sm text-slate-700">Currently accepting new clients</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Turn this off if you're not taking on new clients right now</p>
                    </div>
                </form>
                        </section>

                <section id="public-profile-feed" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm scroll-mt-24">
                    <h2 class="text-lg font-semibold text-slate-950">Public profile feed</h2>
                    <p class="text-sm text-slate-600 mb-4">
                        Share YouTube, TikTok, Vimeo, or Instagram videos/reels, plus article links. Newest posts appear on your public listing in a feed.
                    </p>
                    <p v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                        {{ page.props.flash.success }}
                    </p>
                    <p v-if="page.props.errors?.feed" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {{ Array.isArray(page.props.errors.feed) ? page.props.errors.feed[0] : page.props.errors.feed }}
                    </p>

                    <form class="space-y-4 mb-8 pb-8 border-b border-slate-100" @submit.prevent="submitFeedPost">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                                <select
                                    v-model="feedForm.type"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                >
                                    <option value="video">Video (YouTube, TikTok, Vimeo, Instagram, …)</option>
                                    <option value="article">Article / blog link</option>
                                </select>
                                <p v-if="feedForm.errors.type" class="mt-1 text-sm text-red-600">{{ feedForm.errors.type }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">URL</label>
                                <input
                                    v-model="feedForm.url"
                                    type="url"
                                    required
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="https://"
                                />
                                <p v-if="feedForm.errors.url" class="mt-1 text-sm text-red-600">{{ feedForm.errors.url }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Headline <span class="text-slate-400 font-normal">(optional)</span></label>
                                <input
                                    v-model="feedForm.title"
                                    type="text"
                                    maxlength="255"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                />
                                <p v-if="feedForm.errors.title" class="mt-1 text-sm text-red-600">{{ feedForm.errors.title }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Caption <span class="text-slate-400 font-normal">(optional)</span></label>
                                <textarea
                                    v-model="feedForm.caption"
                                    rows="2"
                                    maxlength="2000"
                                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                />
                                <p v-if="feedForm.errors.caption" class="mt-1 text-sm text-red-600">{{ feedForm.errors.caption }}</p>
                            </div>
                        </div>
                        <button
                            type="submit"
                            class="px-6 py-2.5 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-xl transition-colors disabled:opacity-50"
                            :disabled="feedForm.processing"
                        >
                            {{ feedForm.processing ? 'Adding…' : 'Add to feed' }}
                        </button>
                    </form>

                    <div v-if="!profileFeed.length" class="text-sm text-slate-500 py-4 text-center bg-slate-50 rounded-xl">
                        No feed items yet. Add a video or article above.
                    </div>
                    <ul v-else class="space-y-4">
                        <li
                            v-for="post in profileFeed"
                            :key="post.uuid"
                            class="rounded-xl border border-slate-200 p-4"
                        >
                            <template v-if="editingFeedUuid === post.uuid">
                                <form class="space-y-3" @submit.prevent="saveFeedPost">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                                        <select
                                            v-model="feedEditForm.type"
                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm"
                                        >
                                            <option value="video">Video</option>
                                            <option value="article">Article</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">URL</label>
                                        <input
                                            v-model="feedEditForm.url"
                                            type="url"
                                            required
                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm"
                                        />
                                        <p v-if="feedEditForm.errors.url" class="mt-1 text-xs text-red-600">{{ feedEditForm.errors.url }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Headline</label>
                                        <input v-model="feedEditForm.title" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Caption</label>
                                        <textarea v-model="feedEditForm.caption" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="submit"
                                            class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg disabled:opacity-50"
                                            :disabled="feedEditForm.processing"
                                        >
                                            Save
                                        </button>
                                        <button type="button" class="px-4 py-2 border border-slate-200 text-sm rounded-lg" @click="cancelEditFeedPost">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </template>
                            <template v-else>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mb-1">
                                            <span class="inline-flex items-center gap-1 font-medium text-slate-700">
                                                <VideoCameraIcon v-if="post.type === 'video'" class="h-4 w-4 text-primary-600" />
                                                <NewspaperIcon v-else class="h-4 w-4 text-sky-600" />
                                                {{ post.platform_label || (post.type === 'video' ? 'Video' : 'Article') }}
                                            </span>
                                            <span>· {{ formatFeedDate(post.created_at) }}</span>
                                        </div>
                                        <p v-if="post.title" class="font-medium text-slate-900 truncate">{{ post.title }}</p>
                                        <a
                                            :href="post.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-sm text-primary-600 hover:underline break-all"
                                        >{{ post.url }}</a>
                                        <p v-if="post.caption" class="text-sm text-slate-600 mt-2 line-clamp-2">{{ post.caption }}</p>
                                    </div>
                                    <div class="flex shrink-0 gap-1">
                                        <button
                                            type="button"
                                            class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                            aria-label="Edit feed item"
                                            @click="startEditFeedPost(post)"
                                        >
                                            <PencilSquareIcon class="h-5 w-5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="p-2 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600"
                                            aria-label="Remove feed item"
                                            @click="deleteFeedPost(post.uuid)"
                                        >
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </li>
                    </ul>
                </section>

                    </main>

                    <aside class="space-y-6">
                        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-5 py-4">
                                <h2 class="text-lg font-semibold text-slate-950">Listing actions</h2>
                                <p class="mt-1 text-sm text-slate-500">Share and preview your public profile.</p>
                            </div>
                            <div class="flex flex-col gap-3 p-5">
                                <ProfileSharePanel
                                    v-if="providerShare"
                                    :share="providerShare"
                                    trigger-variant="outline"
                                    menu-align="left"
                                />
                                <Link
                                    :href="route('marketplace.show', provider.slug)"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50"
                                >
                                    <EyeIcon class="h-4 w-4" />
                                    Preview listing
                                </Link>
                            </div>
                        </section>

                        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-5 py-4">
                                <h2 class="text-lg font-semibold text-slate-950">Matching Providers</h2>
                                <p class="mt-1 text-sm text-slate-500">Based on language, location, family, pets, and service needs.</p>
                            </div>
                            <div class="p-6 text-sm text-slate-500">
                                <p>
                                    A complete listing helps the right clients discover you. New inquiries appear in
                                    <Link :href="route('provider.leads.index')" class="font-semibold text-blue-700 hover:text-blue-800">Leads</Link>.
                                </p>
                            </div>
                        </section>

                        <section id="service-certificates" v-if="showsHealthCertificates" class="rounded-lg border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-5 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-950">Certificate Upload</h2>
                                        <p class="mt-1 text-sm text-slate-500">
                                            Add certificate documents for each selected service that requires one.
                                        </p>
                                        <p v-if="certificateRequiredTypeOptions.length" class="mt-1 text-xs text-slate-500">
                                            Required for: {{ certificateRequiredTypeOptions.map((option) => option.label).join(', ') }}
                                        </p>
                                    </div>
                                    <button type="button" @click="addHealthCertificate" class="inline-flex items-center gap-1 rounded-lg border border-primary-200 px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-50">
                                        <PlusIcon class="h-4 w-4" />
                                        Add Certificate
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-3 p-5">
                                <div
                                    v-for="(certificate, index) in form.health_certificates"
                                    :key="index"
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-3"
                                >
                                    <div class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Service Type *</label>
                                            <select
                                                v-model="certificate.service_type"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
                                            >
                                                <option value="" disabled>Select service type</option>
                                                <option v-for="option in certificateRequiredTypeOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div v-if="isHealthcareServiceType(certificate.service_type)" class="sm:col-span-2 rounded-lg border border-slate-200 bg-white p-3">
                                            <p class="mb-2 text-xs font-semibold text-slate-700">Healthcare License Details</p>
                                            <div class="grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-slate-600">License Number</label>
                                                    <input
                                                        v-model="form.license_number"
                                                        type="text"
                                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-slate-600">License State</label>
                                                    <input
                                                        v-model="form.license_state"
                                                        type="text"
                                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-slate-600">License Expiry</label>
                                                    <input
                                                        v-model="form.license_expiry"
                                                        type="date"
                                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Document Name *</label>
                                            <input
                                                v-model="certificate.name"
                                                type="text"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
                                                placeholder="Pet First Aid Training Certificate"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Issuing Authority</label>
                                            <input
                                                v-model="certificate.issuing_authority"
                                                type="text"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
                                                placeholder="American Red Cross"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Document File</label>
                                            <input
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
                                                @change="onHealthCertificateFileChange($event, certificate)"
                                            />
                                            <p class="mt-1 text-xs text-slate-500">PDF, JPG, PNG, or WebP up to 10MB.</p>
                                        </div>
                                    </div>

                                    <div v-if="healthCertificatePreviewUrl(certificate)" class="rounded-lg border border-slate-200 bg-white p-2">
                                        <p class="mb-2 text-xs font-medium text-slate-600">Preview</p>
                                        <img
                                            :src="healthCertificatePreviewUrl(certificate)"
                                            alt="Certificate preview"
                                            class="h-28 w-full rounded object-cover"
                                        >
                                    </div>

                                    <div v-if="certificate.file_path || certificate.original_name" class="flex items-center justify-between gap-3 text-xs text-slate-600">
                                        <a
                                            v-if="certificate.file_path"
                                            :href="healthCertificateFileUrl(certificate.file_path)"
                                            target="_blank"
                                            class="text-primary-600 underline hover:text-primary-700"
                                        >
                                            {{ certificate.original_name || 'View uploaded document' }}
                                        </a>
                                        <span v-else>{{ certificate.original_name }}</span>
                                        <div class="flex items-center gap-3">
                                            <button type="button" @click="removeHealthCertificate(index)" class="text-red-500 transition-colors hover:text-red-700">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="flex justify-end gap-3">
                                        <button type="button" @click="removeHealthCertificate(index)" class="text-sm text-red-500 transition-colors hover:text-red-700">
                                            Remove
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                                        <p v-if="!certificate.document" class="text-xs text-slate-500">
                                            Choose a file to enable Save Certificate.
                                        </p>
                                        <button
                                            type="submit"
                                            form="provider-profile-edit-form"
                                            class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-primary-500 disabled:opacity-50"
                                            :disabled="form.processing || !certificate.document"
                                        >
                                            {{ form.processing ? 'Saving...' : 'Save Certificate' }}
                                        </button>
                                    </div>
                                </div>

                                <p v-if="!form.health_certificates.length" class="rounded-xl bg-slate-50 py-4 text-center text-sm text-slate-500">
                                    No service certificates added yet
                                </p>
                                <p v-if="form.errors.health_certificates" class="text-sm text-red-600">{{ form.errors.health_certificates }}</p>
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
                            <div>
                                <label class="mb-3 block text-base font-medium text-slate-700">Public profile link</label>
                                <input
                                    type="text"
                                    readonly
                                    :value="publicListingUrl || 'Publish your listing to get a link.'"
                                    class="w-full cursor-default rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none"
                                >
                            </div>
                        </section>

                        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <LinkIcon class="h-5 w-5 text-slate-500" />
                                    <h2 class="text-lg font-semibold text-slate-950">Profile snapshot</h2>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">Quick summary.</p>
                            </div>
                            <div class="p-5">
                                <dl class="space-y-3 text-sm">
                                    <div v-if="memberSinceLabel" class="flex justify-between gap-4">
                                        <dt class="text-slate-500">Member since</dt>
                                        <dd class="text-right font-medium text-slate-900">{{ memberSinceLabel }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-slate-500">Business</dt>
                                        <dd class="truncate text-right font-medium text-slate-900">{{ provider?.business_name }}</dd>
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
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
