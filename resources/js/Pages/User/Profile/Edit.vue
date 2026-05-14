<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import RoleAccountsPanel from '@/Components/Account/RoleAccountsPanel.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import {
    BellIcon,
    BookOpenIcon,
    CameraIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    EnvelopeIcon,
    ExclamationTriangleIcon,
    HeartIcon,
    KeyIcon,
    LinkIcon,
    MapPinIcon,
    PencilSquareIcon,
    ShieldCheckIcon,
    SparklesIcon,
    StarIcon,
    TrashIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    user: { type: Object, required: true },
    countryOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, default: () => [] },
    serviceTypeOptions: { type: Array, default: () => [] },
    profileStats: { type: Object, default: () => ({}) },
    recentMessages: { type: Array, default: () => [] },
    purchasedProducts: { type: Array, default: () => [] },
    matchedProviders: { type: Array, default: () => [] },
});

const page = usePage();

const fallbackLanguageOptions = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish' },
    { value: 'zh', label: 'Chinese (Mandarin)' },
    { value: 'hi', label: 'Hindi' },
    { value: 'ar', label: 'Arabic' },
    { value: 'pt', label: 'Portuguese' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
    { value: 'ja', label: 'Japanese' },
    { value: 'ko', label: 'Korean' },
    { value: 'vi', label: 'Vietnamese' },
    { value: 'tl', label: 'Tagalog' },
    { value: 'ru', label: 'Russian' },
];

const serviceLocationOptions = [
    { value: 'usa', label: 'USA' },
    { value: 'uk', label: 'UK' },
    { value: 'europe', label: 'Europe' },
    { value: 'canada', label: 'Canada' },
    { value: 'other', label: 'Other' },
];

const notificationDefaults = {
    email_new_message: true,
    email_lead_update: true,
    email_review_received: true,
    email_marketing: false,
    push_enabled: true,
};

const languageOptions = computed(() => (
    props.languageOptions?.length ? props.languageOptions : fallbackLanguageOptions
));

const worldCountryOptions = [
    { value: 'AF', label: 'Afghanistan' },
    { value: 'AL', label: 'Albania' },
    { value: 'DZ', label: 'Algeria' },
    { value: 'AD', label: 'Andorra' },
    { value: 'AO', label: 'Angola' },
    { value: 'AG', label: 'Antigua and Barbuda' },
    { value: 'AR', label: 'Argentina' },
    { value: 'AM', label: 'Armenia' },
    { value: 'AU', label: 'Australia' },
    { value: 'AT', label: 'Austria' },
    { value: 'AZ', label: 'Azerbaijan' },
    { value: 'BS', label: 'Bahamas' },
    { value: 'BH', label: 'Bahrain' },
    { value: 'BD', label: 'Bangladesh' },
    { value: 'BB', label: 'Barbados' },
    { value: 'BY', label: 'Belarus' },
    { value: 'BE', label: 'Belgium' },
    { value: 'BZ', label: 'Belize' },
    { value: 'BJ', label: 'Benin' },
    { value: 'BT', label: 'Bhutan' },
    { value: 'BO', label: 'Bolivia' },
    { value: 'BA', label: 'Bosnia and Herzegovina' },
    { value: 'BW', label: 'Botswana' },
    { value: 'BR', label: 'Brazil' },
    { value: 'BN', label: 'Brunei' },
    { value: 'BG', label: 'Bulgaria' },
    { value: 'BF', label: 'Burkina Faso' },
    { value: 'BI', label: 'Burundi' },
    { value: 'CV', label: 'Cabo Verde' },
    { value: 'KH', label: 'Cambodia' },
    { value: 'CM', label: 'Cameroon' },
    { value: 'CA', label: 'Canada' },
    { value: 'CF', label: 'Central African Republic' },
    { value: 'TD', label: 'Chad' },
    { value: 'CL', label: 'Chile' },
    { value: 'CN', label: 'China' },
    { value: 'CO', label: 'Colombia' },
    { value: 'KM', label: 'Comoros' },
    { value: 'CG', label: 'Congo' },
    { value: 'CR', label: 'Costa Rica' },
    { value: 'HR', label: 'Croatia' },
    { value: 'CU', label: 'Cuba' },
    { value: 'CY', label: 'Cyprus' },
    { value: 'CZ', label: 'Czechia' },
    { value: 'CD', label: 'Democratic Republic of the Congo' },
    { value: 'DK', label: 'Denmark' },
    { value: 'DJ', label: 'Djibouti' },
    { value: 'DM', label: 'Dominica' },
    { value: 'DO', label: 'Dominican Republic' },
    { value: 'EC', label: 'Ecuador' },
    { value: 'EG', label: 'Egypt' },
    { value: 'SV', label: 'El Salvador' },
    { value: 'GQ', label: 'Equatorial Guinea' },
    { value: 'ER', label: 'Eritrea' },
    { value: 'EE', label: 'Estonia' },
    { value: 'SZ', label: 'Eswatini' },
    { value: 'ET', label: 'Ethiopia' },
    { value: 'FJ', label: 'Fiji' },
    { value: 'FI', label: 'Finland' },
    { value: 'FR', label: 'France' },
    { value: 'GA', label: 'Gabon' },
    { value: 'GM', label: 'Gambia' },
    { value: 'GE', label: 'Georgia' },
    { value: 'DE', label: 'Germany' },
    { value: 'GH', label: 'Ghana' },
    { value: 'GR', label: 'Greece' },
    { value: 'GD', label: 'Grenada' },
    { value: 'GT', label: 'Guatemala' },
    { value: 'GN', label: 'Guinea' },
    { value: 'GW', label: 'Guinea-Bissau' },
    { value: 'GY', label: 'Guyana' },
    { value: 'HT', label: 'Haiti' },
    { value: 'HN', label: 'Honduras' },
    { value: 'HU', label: 'Hungary' },
    { value: 'IS', label: 'Iceland' },
    { value: 'IN', label: 'India' },
    { value: 'ID', label: 'Indonesia' },
    { value: 'IR', label: 'Iran' },
    { value: 'IQ', label: 'Iraq' },
    { value: 'IE', label: 'Ireland' },
    { value: 'IL', label: 'Israel' },
    { value: 'IT', label: 'Italy' },
    { value: 'JM', label: 'Jamaica' },
    { value: 'JP', label: 'Japan' },
    { value: 'JO', label: 'Jordan' },
    { value: 'KZ', label: 'Kazakhstan' },
    { value: 'KE', label: 'Kenya' },
    { value: 'KI', label: 'Kiribati' },
    { value: 'KW', label: 'Kuwait' },
    { value: 'KG', label: 'Kyrgyzstan' },
    { value: 'LA', label: 'Laos' },
    { value: 'LV', label: 'Latvia' },
    { value: 'LB', label: 'Lebanon' },
    { value: 'LS', label: 'Lesotho' },
    { value: 'LR', label: 'Liberia' },
    { value: 'LY', label: 'Libya' },
    { value: 'LI', label: 'Liechtenstein' },
    { value: 'LT', label: 'Lithuania' },
    { value: 'LU', label: 'Luxembourg' },
    { value: 'MG', label: 'Madagascar' },
    { value: 'MW', label: 'Malawi' },
    { value: 'MY', label: 'Malaysia' },
    { value: 'MV', label: 'Maldives' },
    { value: 'ML', label: 'Mali' },
    { value: 'MT', label: 'Malta' },
    { value: 'MH', label: 'Marshall Islands' },
    { value: 'MR', label: 'Mauritania' },
    { value: 'MU', label: 'Mauritius' },
    { value: 'MX', label: 'Mexico' },
    { value: 'FM', label: 'Micronesia' },
    { value: 'MD', label: 'Moldova' },
    { value: 'MC', label: 'Monaco' },
    { value: 'MN', label: 'Mongolia' },
    { value: 'ME', label: 'Montenegro' },
    { value: 'MA', label: 'Morocco' },
    { value: 'MZ', label: 'Mozambique' },
    { value: 'MM', label: 'Myanmar' },
    { value: 'NA', label: 'Namibia' },
    { value: 'NR', label: 'Nauru' },
    { value: 'NP', label: 'Nepal' },
    { value: 'NL', label: 'Netherlands' },
    { value: 'NZ', label: 'New Zealand' },
    { value: 'NI', label: 'Nicaragua' },
    { value: 'NE', label: 'Niger' },
    { value: 'NG', label: 'Nigeria' },
    { value: 'KP', label: 'North Korea' },
    { value: 'MK', label: 'North Macedonia' },
    { value: 'NO', label: 'Norway' },
    { value: 'OM', label: 'Oman' },
    { value: 'PK', label: 'Pakistan' },
    { value: 'PW', label: 'Palau' },
    { value: 'PA', label: 'Panama' },
    { value: 'PG', label: 'Papua New Guinea' },
    { value: 'PY', label: 'Paraguay' },
    { value: 'PE', label: 'Peru' },
    { value: 'PH', label: 'Philippines' },
    { value: 'PL', label: 'Poland' },
    { value: 'PT', label: 'Portugal' },
    { value: 'QA', label: 'Qatar' },
    { value: 'RO', label: 'Romania' },
    { value: 'RU', label: 'Russia' },
    { value: 'RW', label: 'Rwanda' },
    { value: 'KN', label: 'Saint Kitts and Nevis' },
    { value: 'LC', label: 'Saint Lucia' },
    { value: 'VC', label: 'Saint Vincent and the Grenadines' },
    { value: 'WS', label: 'Samoa' },
    { value: 'SM', label: 'San Marino' },
    { value: 'ST', label: 'Sao Tome and Principe' },
    { value: 'SA', label: 'Saudi Arabia' },
    { value: 'SN', label: 'Senegal' },
    { value: 'RS', label: 'Serbia' },
    { value: 'SC', label: 'Seychelles' },
    { value: 'SL', label: 'Sierra Leone' },
    { value: 'SG', label: 'Singapore' },
    { value: 'SK', label: 'Slovakia' },
    { value: 'SI', label: 'Slovenia' },
    { value: 'SB', label: 'Solomon Islands' },
    { value: 'SO', label: 'Somalia' },
    { value: 'ZA', label: 'South Africa' },
    { value: 'KR', label: 'South Korea' },
    { value: 'SS', label: 'South Sudan' },
    { value: 'ES', label: 'Spain' },
    { value: 'LK', label: 'Sri Lanka' },
    { value: 'SD', label: 'Sudan' },
    { value: 'SR', label: 'Suriname' },
    { value: 'SE', label: 'Sweden' },
    { value: 'CH', label: 'Switzerland' },
    { value: 'SY', label: 'Syria' },
    { value: 'TJ', label: 'Tajikistan' },
    { value: 'TZ', label: 'Tanzania' },
    { value: 'TH', label: 'Thailand' },
    { value: 'TL', label: 'Timor-Leste' },
    { value: 'TG', label: 'Togo' },
    { value: 'TO', label: 'Tonga' },
    { value: 'TT', label: 'Trinidad and Tobago' },
    { value: 'TN', label: 'Tunisia' },
    { value: 'TR', label: 'Turkey' },
    { value: 'TM', label: 'Turkmenistan' },
    { value: 'TV', label: 'Tuvalu' },
    { value: 'UG', label: 'Uganda' },
    { value: 'UA', label: 'Ukraine' },
    { value: 'AE', label: 'United Arab Emirates' },
    { value: 'GB', label: 'United Kingdom' },
    { value: 'US', label: 'United States' },
    { value: 'UY', label: 'Uruguay' },
    { value: 'UZ', label: 'Uzbekistan' },
    { value: 'VU', label: 'Vanuatu' },
    { value: 'VA', label: 'Vatican City' },
    { value: 'VE', label: 'Venezuela' },
    { value: 'VN', label: 'Vietnam' },
    { value: 'YE', label: 'Yemen' },
    { value: 'ZM', label: 'Zambia' },
    { value: 'ZW', label: 'Zimbabwe' },
];

const originCountryOptions = computed(() => {
    if (typeof Intl === 'undefined' || typeof Intl.DisplayNames !== 'function') {
        return worldCountryOptions;
    }

    try {
        const displayNames = new Intl.DisplayNames(['en'], { type: 'region' });
        const regionCodes = typeof Intl.supportedValuesOf === 'function'
            ? Intl.supportedValuesOf('region')
            : [];

        if (!Array.isArray(regionCodes) || regionCodes.length === 0) {
            return worldCountryOptions;
        }

        return regionCodes
            .filter((code) => typeof code === 'string' && code.length === 2)
            .map((code) => ({
                value: code.toUpperCase(),
                label: displayNames.of(code.toUpperCase()) || code.toUpperCase(),
            }))
            .sort((a, b) => a.label.localeCompare(b.label, 'en'));
    } catch {
        return worldCountryOptions;
    }
});

const profileCountryOptions = computed(() => {
    const optionsMap = new Map();

    for (const option of worldCountryOptions) {
        optionsMap.set(String(option.value).toUpperCase(), {
            value: String(option.value).toUpperCase(),
            label: option.label,
        });
    }

    for (const option of props.countryOptions || []) {
        if (!option || typeof option !== 'object') continue;
        const code = String(option.value || '').toUpperCase();
        const label = String(option.label || '').trim();
        if (!code || !label) continue;
        if (!optionsMap.has(code)) {
            optionsMap.set(code, { value: code, label });
        }
    }

    if (profileForm.country) {
        const currentCode = String(profileForm.country).toUpperCase();
        if (currentCode && !optionsMap.has(currentCode)) {
            optionsMap.set(currentCode, { value: currentCode, label: currentCode });
        }
    }

    if (!optionsMap.has('OTHER')) {
        optionsMap.set('OTHER', { value: 'OTHER', label: 'Other' });
    }

    return Array.from(optionsMap.values()).sort((a, b) => a.label.localeCompare(b.label, 'en'));
});

const normalizeLanguageValue = (value) => {
    if (!value) return '';
    const raw = String(value).trim();
    const direct = languageOptions.value.find((option) => option.value === raw);
    if (direct) return direct.value;

    const byLabel = languageOptions.value.find((option) => option.label.toLowerCase() === raw.toLowerCase());
    return byLabel?.value || raw;
};

const languageLabel = (code) => (
    languageOptions.value.find((option) => option.value === code)?.label || String(code || '').toUpperCase()
);

const userLanguages = computed(() => {
    const value = props.user.languages_spoken;
    return Array.isArray(value) ? value.map(normalizeLanguageValue).filter(Boolean) : [];
});

const profileForm = useForm({
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    email: props.user.email || '',
    phone: props.user.phone || '',
    city: props.user.city || '',
    state: props.user.state || '',
    country: props.user.country || '',
    preferred_language: normalizeLanguageValue(props.user.preferred_language) || 'en',
    languages_spoken: [...userLanguages.value],
    service_location: props.user.service_location || '',
    has_children: Boolean(props.user.has_children),
    children_ages: Array.isArray(props.user.children_ages) ? [...props.user.children_ages] : [],
    has_pets: Boolean(props.user.has_pets),
    pet_types: Array.isArray(props.user.pet_types) ? [...props.user.pet_types] : [],
    social_links: Array.isArray(props.user.social_links) ? [...props.user.social_links] : [],
    hobbies: Array.isArray(props.user.hobbies) ? [...props.user.hobbies] : [],
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    notification_preferences: {
        ...notificationDefaults,
        ...(props.user.notification_preferences || {}),
    },
});

const inviteForm = useForm({
    name: '',
    email: '',
    phone: '',
    service_type: '',
    website: '',
    note: '',
});

const deleteForm = useForm({
    password: '',
    confirmation: '',
});

const avatarInput = ref(null);
const avatarTypeError = ref('');
const avatarUploading = ref(false);
const childAgeInput = ref('');
const petTypeInput = ref('');
const hobbyInput = ref('');
const socialLabelInput = ref('');
const socialUrlInput = ref('');
const showDeleteConfirm = ref(false);

const allowedAvatarTypes = ['image/jpeg', 'image/png'];

const displayName = computed(() => {
    const name = [profileForm.first_name, profileForm.last_name].filter(Boolean).join(' ').trim();
    return name || 'Your profile';
});

const hasAvatar = computed(() => Boolean(props.user?.avatar_url));
const avatarInitial = computed(() => {
    const first = props.user?.first_name?.trim();
    const last = props.user?.last_name?.trim();
    if (first) return first.charAt(0).toUpperCase();
    if (last) return last.charAt(0).toUpperCase();
    return 'U';
});

const locationSummary = computed(() => (
    [profileForm.city, profileForm.state, profileForm.country].filter(Boolean).join(', ') || 'Location not set'
));

const completionPercent = computed(() => Number(props.profileStats?.completion || 0));
const avatarServerError = computed(() => page.props.errors?.avatar || '');
const invitedProviders = computed(() => (
    Array.isArray(props.user.provider_invites) ? [...props.user.provider_invites].reverse() : []
));

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

const updateProfile = () => {
    const payload = {
        ...profileForm.data(),
        children_ages: profileForm.has_children ? profileForm.children_ages : [],
        pet_types: profileForm.has_pets ? profileForm.pet_types : [],
    };

    profileForm.transform(() => payload).patch(route('profile.update'), {
        preserveScroll: true,
        onFinish: () => profileForm.transform((data) => data),
    });
};

const updatePassword = () => {
    passwordForm.patch(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const updateNotifications = () => {
    notificationForm.patch(route('profile.notifications'), {
        preserveScroll: true,
    });
};

const sendProviderInvite = () => {
    inviteForm.post(route('profile.provider-invites.store'), {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
    });
};

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
    router.post(route('profile.avatar'), formData, {
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

const deleteAvatar = () => {
    router.delete(route('profile.avatar.delete'), {
        preserveScroll: true,
    });
};

const deleteAccount = () => {
    deleteForm.delete(route('profile.destroy'));
};

const toggleLanguage = (code) => {
    const index = profileForm.languages_spoken.indexOf(code);
    if (index > -1) {
        profileForm.languages_spoken.splice(index, 1);
        return;
    }

    profileForm.languages_spoken.push(code);
};

const addChildAge = () => {
    const age = Number(childAgeInput.value);
    if (!Number.isInteger(age) || age < 0 || age > 25) return;
    if (!profileForm.children_ages.includes(age)) {
        profileForm.children_ages.push(age);
    }
    childAgeInput.value = '';
};

const removeChildAge = (age) => {
    profileForm.children_ages = profileForm.children_ages.filter((item) => item !== age);
};

const addPetType = () => {
    const pet = petTypeInput.value.trim();
    if (!pet) return;
    if (!profileForm.pet_types.includes(pet)) {
        profileForm.pet_types.push(pet);
    }
    petTypeInput.value = '';
};

const removePetType = (pet) => {
    profileForm.pet_types = profileForm.pet_types.filter((item) => item !== pet);
};

const addHobby = () => {
    const hobby = hobbyInput.value.trim();
    if (!hobby) return;
    if (!profileForm.hobbies.includes(hobby)) {
        profileForm.hobbies.push(hobby);
    }
    hobbyInput.value = '';
};

const removeHobby = (hobby) => {
    profileForm.hobbies = profileForm.hobbies.filter((item) => item !== hobby);
};

const addSocialLink = () => {
    const url = socialUrlInput.value.trim();
    if (!url) return;

    profileForm.social_links.push({
        label: socialLabelInput.value.trim() || 'Social profile',
        url,
    });

    socialLabelInput.value = '';
    socialUrlInput.value = '';
};

const removeSocialLink = (index) => {
    profileForm.social_links.splice(index, 1);
};

const formatDate = (value) => {
    if (!value) return 'Not available';
    return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value));
};

const formatTimeAgo = (value) => {
    if (!value) return '';
    const date = new Date(value);
    const diff = Date.now() - date.getTime();
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
    return date.toLocaleDateString();
};

const formatCurrency = (amount, currency = 'USD') => {
    if (amount === null || amount === undefined || Number.isNaN(Number(amount))) return '';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(Number(amount));
};

const initialFor = (...values) => {
    for (const value of values) {
        const text = (value || '').trim();
        if (text) return text.charAt(0).toUpperCase();
    }
    return 'U';
};
</script>

<template>
    <Head title="Profile" />

    <AppLayout>
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
                        <div class="absolute bottom-3 left-4 right-4 flex flex-wrap items-end justify-between gap-3 text-white sm:bottom-4 sm:left-6 sm:right-6">
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-100 sm:text-sm">User Profile</p>
                                <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:mt-2 sm:text-3xl md:text-4xl">{{ displayName }}</h1>
                                <p class="mt-1 flex items-center gap-2 text-xs text-slate-100 sm:mt-2 sm:text-sm">
                                    <MapPinIcon class="h-4 w-4" />
                                    {{ locationSummary }}
                                </p>
                            </div>
                            <Button variant="secondary" size="sm" @click="avatarInput?.click()">
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
                                    <div v-else class="flex h-full w-full items-center justify-center rounded-md text-4xl font-bold text-white">
                                        {{ avatarInitial }}
                                    </div>
                                    <button
                                        type="button"
                                        class="absolute -bottom-2 -right-2 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
                                        @click="avatarInput?.click()"
                                    >
                                        <CameraIcon class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="min-w-0 flex-1 pt-1 sm:max-w-xl sm:pb-1 sm:pt-3 lg:pt-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                            {{ languageLabel(profileForm.preferred_language) }}
                                        </span>
                                        <span class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            {{ completionPercent }}% complete
                                        </span>
                                    </div>
                                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                                        Keep your details current so providers can match by language, location, family needs, pets, interests, and services.
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

                        <input ref="avatarInput" type="file" accept="image/jpeg,image/png" class="hidden" @change="uploadAvatar">
                        <div v-if="avatarTypeError || avatarServerError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
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
                                    <Button size="sm" :loading="profileForm.processing" @click="updateProfile">
                                        <PencilSquareIcon class="h-4 w-4" />
                                        Save profile
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-8 p-5">
                                <div class="grid gap-5 md:grid-cols-2">
                                    <Input v-model="profileForm.first_name" label="First name" :error="profileForm.errors.first_name" />
                                    <Input v-model="profileForm.last_name" label="Last name" :error="profileForm.errors.last_name" />
                                    <Input v-model="profileForm.email" type="email" label="Email" :error="profileForm.errors.email" />
                                    <Input v-model="profileForm.phone" label="Phone" :error="profileForm.errors.phone" />
                                </div>

                                <div class="grid gap-5 md:grid-cols-3">
                                    <Input v-model="profileForm.city" label="City" :error="profileForm.errors.city" />
                                    <Input v-model="profileForm.state" label="State" :error="profileForm.errors.state" />
                                    <Select
                                        v-model="profileForm.country"
                                        :options="profileCountryOptions"
                                        label="Country"
                                        placeholder="Select country"
                                        size="auth"
                                        :error="profileForm.errors.country"
                                    />
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <Select
                                        v-model="profileForm.preferred_language"
                                        :options="languageOptions"
                                        label="Preferred language"
                                        placeholder="Select language"
                                        size="auth"
                                        :error="profileForm.errors.preferred_language"
                                    />
                                    <Select
                                        v-model="profileForm.service_location"
                                        :options="serviceLocationOptions"
                                        label="Service location"
                                        placeholder="Select service location"
                                        size="auth"
                                        :error="profileForm.errors.service_location"
                                    />
                                </div>

                                <div>
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-semibold text-slate-950">Spoken Languages</h3>
                                            <p class="text-sm text-slate-500">Used to match providers who can serve you comfortably.</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="language in languageOptions"
                                            :key="language.value"
                                            type="button"
                                            :class="[
                                                'rounded-full border px-3 py-2 text-sm font-semibold transition',
                                                profileForm.languages_spoken.includes(language.value)
                                                    ? 'border-blue-200 bg-blue-50 text-blue-700'
                                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                                            ]"
                                            @click="toggleLanguage(language.value)"
                                        >
                                            {{ language.label }}
                                        </button>
                                    </div>
                                    <p v-if="profileForm.errors.languages_spoken" class="mt-2 text-sm font-medium text-red-600">
                                        {{ profileForm.errors.languages_spoken }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-6 lg:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                                <div class="flex items-start gap-3">
                                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-2 text-emerald-700">
                                        <UsersIcon class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-950">Family And Pets</h2>
                                        <p class="mt-1 text-sm text-slate-500">These details improve matches for family care, tutors, pet care, and nearby support.</p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-5">
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-4">
                                        <input
                                            v-model="profileForm.has_children"
                                            type="checkbox"
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-900">I have children</span>
                                            <span class="block text-sm text-slate-500">Add ages so we can match child care and age-specific services.</span>
                                        </span>
                                    </label>
                                    <div v-if="profileForm.has_children" class="space-y-3">
                                        <div class="flex gap-2">
                                            <input
                                                v-model="childAgeInput"
                                                type="number"
                                                min="0"
                                                max="25"
                                                class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                                placeholder="Child age"
                                                @keydown.enter.prevent="addChildAge"
                                            >
                                            <button type="button" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="addChildAge">
                                                Add
                                            </button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="age in profileForm.children_ages"
                                                :key="`profile-age-${age}`"
                                                type="button"
                                                class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700"
                                                @click="removeChildAge(age)"
                                            >
                                                Age {{ age }} x
                                            </button>
                                        </div>
                                    </div>

                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-4">
                                        <input
                                            v-model="profileForm.has_pets"
                                            type="checkbox"
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-900">I have pets</span>
                                            <span class="block text-sm text-slate-500">Pet details can surface pet sitters and pet care providers.</span>
                                        </span>
                                    </label>
                                    <div v-if="profileForm.has_pets" class="space-y-3">
                                        <div class="flex gap-2">
                                            <input
                                                v-model="petTypeInput"
                                                type="text"
                                                class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                                placeholder="Dog, cat, bird..."
                                                @keydown.enter.prevent="addPetType"
                                            >
                                            <button type="button" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="addPetType">
                                                Add
                                            </button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="pet in profileForm.pet_types"
                                                :key="`profile-pet-${pet}`"
                                                type="button"
                                                class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700"
                                                @click="removePetType(pet)"
                                            >
                                                {{ pet }} x
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                                <div class="flex items-start gap-3">
                                    <div class="rounded-lg border border-rose-100 bg-rose-50 p-2 text-rose-700">
                                        <HeartIcon class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-950">Social Links And Hobbies</h2>
                                        <p class="mt-1 text-sm text-slate-500">Add the details you want available from your profile.</p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-5">
                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-slate-700">Social profile links</label>
                                        <div class="grid gap-2 sm:grid-cols-[0.8fr_1fr_auto]">
                                            <input v-model="socialLabelInput" type="text" class="rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="Instagram">
                                            <input v-model="socialUrlInput" type="url" class="rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="https://...">
                                            <button type="button" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="addSocialLink">
                                                Add
                                            </button>
                                        </div>
                                        <div v-if="profileForm.social_links.length" class="mt-3 space-y-2">
                                            <div
                                                v-for="(link, index) in profileForm.social_links"
                                                :key="`${link.url}-${index}`"
                                                class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2"
                                            >
                                                <a :href="link.url || '#'" target="_blank" rel="noreferrer" class="min-w-0 truncate text-sm font-semibold text-blue-700">
                                                    {{ link.label || link.url }}
                                                </a>
                                                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-red-600" @click="removeSocialLink(index)">
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                        <p v-if="profileForm.errors.social_links" class="mt-2 text-sm font-medium text-red-600">
                                            {{ profileForm.errors.social_links }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-slate-700">Hobbies</label>
                                        <div class="flex gap-2">
                                            <input
                                                v-model="hobbyInput"
                                                type="text"
                                                class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                                placeholder="Gardening, soccer, cooking..."
                                                @keydown.enter.prevent="addHobby"
                                            >
                                            <button type="button" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="addHobby">
                                                Add
                                            </button>
                                        </div>
                                        <div v-if="profileForm.hobbies.length" class="mt-3 flex flex-wrap gap-2">
                                            <button
                                                v-for="hobby in profileForm.hobbies"
                                                :key="`hobby-${hobby}`"
                                                type="button"
                                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700"
                                                @click="removeHobby(hobby)"
                                            >
                                                {{ hobby }} x
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-2">
                                <div class="border-b border-slate-200 px-5 py-4">
                                    <h2 class="text-lg font-semibold text-slate-950">Matching Providers</h2>
                                    <p class="mt-1 text-sm text-slate-500">Based on language, location, family, pets, and service needs.</p>
                                </div>
                                <div v-if="matchedProviders.length" class="divide-y divide-slate-100">
                                    <Link
                                        v-for="provider in matchedProviders"
                                        :key="provider.id"
                                        :href="route('marketplace.show', provider.slug)"
                                        class="block p-4 transition hover:bg-slate-50"
                                    >
                                        <div class="flex gap-3">
                                            <img
                                                v-if="provider.avatar_url"
                                                :src="provider.avatar_url"
                                                :alt="provider.business_name"
                                                class="h-12 w-12 rounded-lg object-cover"
                                            >
                                            <div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white">
                                                {{ initialFor(provider.business_name) }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start justify-between gap-3">
                                                    <p class="line-clamp-1 text-sm font-semibold text-slate-950">{{ provider.business_name }}</p>
                                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600">
                                                        <StarIcon class="h-3.5 w-3.5" />
                                                        {{ provider.average_rating ? Number(provider.average_rating).toFixed(1) : 'New' }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-xs text-slate-500">{{ provider.location }}</p>
                                                <div class="mt-3 flex flex-wrap gap-1.5">
                                                    <span
                                                        v-for="reason in provider.match_reasons"
                                                        :key="`${provider.id}-${reason}`"
                                                        class="rounded-full border border-blue-100 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700"
                                                    >
                                                        {{ reason }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                                <div v-else class="p-6 text-sm text-slate-500">
                                    Complete your location, languages, children, pets, and service preferences to improve matches.
                                </div>
                            </section>

                            <div class="rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-2">
                                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-950">Digital Products</h2>
                                        <p class="mt-1 text-sm text-slate-500">Ebooks and audio products you purchased.</p>
                                    </div>
                                    <Link :href="route('library.my')" class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                                        Library
                                    </Link>
                                </div>
                                <div v-if="purchasedProducts.length" class="divide-y divide-slate-100">
                                    <Link
                                        v-for="product in purchasedProducts"
                                        :key="product.access_id"
                                        :href="route('library.read', product.slug)"
                                        class="flex gap-3 p-4 transition hover:bg-slate-50"
                                    >
                                        <img
                                            v-if="product.cover_image_url"
                                            :src="product.cover_image_url"
                                            :alt="product.title"
                                            class="h-16 w-12 rounded-lg object-cover shadow-sm"
                                        >
                                        <div v-else class="flex h-16 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                            <BookOpenIcon class="h-5 w-5" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="line-clamp-2 text-sm font-semibold leading-5 text-slate-950">{{ product.title }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ product.author || 'Author not listed' }}</p>
                                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                                <div class="h-2 rounded-full bg-slate-100">
                                                    <div class="h-2 rounded-full bg-blue-600" :style="{ width: `${product.reading_progress || 0}%` }" />
                                                </div>
                                                <div v-if="product.has_audio" class="h-2 rounded-full bg-slate-100">
                                                    <div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${product.audio_progress || 0}%` }" />
                                                </div>
                                            </div>
                                            <p class="mt-2 text-xs text-slate-500">
                                                Purchased {{ formatDate(product.purchased_at) }}
                                                <span v-if="product.price"> - {{ formatCurrency(product.price, product.currency) }}</span>
                                            </p>
                                        </div>
                                    </Link>
                                </div>
                                <div v-else class="p-8 text-center">
                                    <BookOpenIcon class="mx-auto h-8 w-8 text-slate-300" />
                                    <p class="mt-3 text-sm font-medium text-slate-700">No purchased ebooks yet</p>
                                    <Link :href="route('library.my')" class="mt-2 inline-flex text-sm font-semibold text-blue-700">
                                        Browse the library
                                    </Link>
                                </div>
                            </div>
                        </section>

                    </main>

                    <aside class="space-y-6">
                        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="mb-4 flex items-center gap-3">
                                <LinkIcon class="h-5 w-5 text-slate-500" />
                                <h2 class="text-lg font-semibold text-slate-950">Profile Snapshot</h2>
                            </div>
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-500">Email</dt>
                                    <dd class="truncate font-medium text-slate-900">{{ profileForm.email }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-500">Phone</dt>
                                    <dd class="font-medium text-slate-900">{{ profileForm.phone || 'Not set' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-500">Children</dt>
                                    <dd class="font-medium text-slate-900">
                                        {{ profileForm.has_children ? (profileForm.children_ages.length ? profileForm.children_ages.join(', ') : 'Yes') : 'No' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-500">Pets</dt>
                                    <dd class="font-medium text-slate-900">
                                        {{ profileForm.has_pets ? (profileForm.pet_types.join(', ') || 'Yes') : 'No' }}
                                    </dd>
                                </div>
                            </dl>
                            <div v-if="profileForm.social_links.length" class="mt-5 border-t border-slate-200 pt-4">
                                <h3 class="text-sm font-semibold text-slate-950">Social links</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a
                                        v-for="(link, index) in profileForm.social_links"
                                        :key="`snapshot-social-${index}`"
                                        :href="link.url || '#'"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        {{ link.label }}
                                    </a>
                                </div>
                            </div>
                            <div v-if="profileForm.hobbies.length" class="mt-5 border-t border-slate-200 pt-4">
                                <h3 class="text-sm font-semibold text-slate-950">Hobbies</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="hobby in profileForm.hobbies"
                                        :key="`snapshot-hobby-${hobby}`"
                                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700"
                                    >
                                        {{ hobby }}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">Recent Messages</h2>
                                    <p class="mt-1 text-sm text-slate-500">Messages received from service providers.</p>
                                </div>
                                <Link :href="route('messages.index')" class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                                    View all
                                </Link>
                            </div>
                            <div v-if="recentMessages.length" class="divide-y divide-slate-100">
                                <Link
                                    v-for="message in recentMessages"
                                    :key="message.uuid"
                                    :href="message.conversation_uuid ? route('messages.show', message.conversation_uuid) : route('messages.index')"
                                    class="flex gap-3 p-4 transition hover:bg-slate-50"
                                >
                                    <img
                                        v-if="message.sender_avatar_url"
                                        :src="message.sender_avatar_url"
                                        :alt="message.sender_name"
                                        class="h-11 w-11 rounded-lg object-cover"
                                    >
                                    <div v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white">
                                        {{ initialFor(message.sender_name) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate text-sm font-semibold text-slate-950">{{ message.sender_name }}</p>
                                            <span class="shrink-0 text-xs text-slate-500">{{ formatTimeAgo(message.created_at) }}</span>
                                        </div>
                                        <p class="mt-1 line-clamp-2 text-sm leading-5 text-slate-600">{{ message.body }}</p>
                                        <span v-if="message.is_unread" class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                            New
                                        </span>
                                    </div>
                                </Link>
                            </div>
                            <div v-else class="p-8 text-center">
                                <ChatBubbleLeftRightIcon class="mx-auto h-8 w-8 text-slate-300" />
                                <p class="mt-3 text-sm font-medium text-slate-700">No messages yet</p>
                                <p class="mt-1 text-sm text-slate-500">Provider conversations will appear here.</p>
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

                            <form class="space-y-4" @submit.prevent="sendProviderInvite">
                                <Input v-model="inviteForm.name" label="Provider name" :error="inviteForm.errors.name" required />
                                <Input v-model="inviteForm.email" type="email" label="Email" :error="inviteForm.errors.email" required />
                                <Input v-model="inviteForm.phone" label="Phone" :error="inviteForm.errors.phone" />
                                <Select
                                    v-model="inviteForm.service_type"
                                    :options="serviceTypeOptions"
                                    label="Service type"
                                    placeholder="Select service"
                                    size="auth"
                                    :error="inviteForm.errors.service_type"
                                />
                                <Input v-model="inviteForm.website" type="url" label="Website or profile" :error="inviteForm.errors.website" />
                                <div>
                                    <label class="mb-3 block text-base font-medium text-slate-700">Note</label>
                                    <textarea
                                        v-model="inviteForm.note"
                                        rows="3"
                                        class="w-full rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                        placeholder="Why do you recommend them?"
                                    />
                                    <p v-if="inviteForm.errors.note" class="mt-2 text-sm font-medium text-red-600">{{ inviteForm.errors.note }}</p>
                                </div>
                                <Button type="submit" class="w-full" :loading="inviteForm.processing">
                                    <EnvelopeIcon class="h-4 w-4" />
                                    Send invite
                                </Button>
                            </form>

                            <div v-if="invitedProviders.length" class="mt-5 border-t border-slate-200 pt-5">
                                <h3 class="text-sm font-semibold text-slate-950">Invited providers</h3>
                                <div class="mt-3 space-y-2">
                                    <div
                                        v-for="invite in invitedProviders.slice(0, 4)"
                                        :key="invite.id"
                                        class="rounded-lg border border-slate-200 px-3 py-2"
                                    >
                                        <p class="text-sm font-semibold text-slate-900">{{ invite.name }}</p>
                                        <p class="text-xs text-slate-500">{{ invite.email }} - {{ formatDate(invite.created_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <form class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="updatePassword">
                            <div class="mb-5 flex items-start gap-3">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-slate-700">
                                    <KeyIcon class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">Password</h2>
                                    <p class="mt-1 text-sm text-slate-500">Update your account password.</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <Input v-model="passwordForm.current_password" type="password" label="Current password" :error="passwordForm.errors.current_password" />
                                <Input v-model="passwordForm.password" type="password" label="New password" :error="passwordForm.errors.password" />
                                <Input v-model="passwordForm.password_confirmation" type="password" label="Confirm password" :error="passwordForm.errors.password_confirmation" />
                                <Button type="submit" :loading="passwordForm.processing">
                                    <ShieldCheckIcon class="h-4 w-4" />
                                    Save password
                                </Button>
                            </div>
                        </form>

                        <section class="rounded-lg border border-red-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg border border-red-100 bg-red-50 p-2 text-red-700">
                                    <ExclamationTriangleIcon class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">Delete Account</h2>
                                    <p class="mt-1 text-sm text-slate-500">This permanently removes your account access.</p>
                                </div>
                            </div>
                            <button
                                v-if="!showDeleteConfirm"
                                type="button"
                                class="mt-5 rounded-lg border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 hover:bg-red-50"
                                @click="showDeleteConfirm = true"
                            >
                                Delete account
                            </button>
                            <form v-else class="mt-5 space-y-4" @submit.prevent="deleteAccount">
                                <Input v-model="deleteForm.password" type="password" label="Password" :error="deleteForm.errors.password" />
                                <Input v-model="deleteForm.confirmation" label="Type DELETE" :error="deleteForm.errors.confirmation" />
                                <div class="flex flex-wrap gap-2">
                                    <Button type="submit" :loading="deleteForm.processing">
                                        <TrashIcon class="h-4 w-4" />
                                        Delete account
                                    </Button>
                                    <Button type="button" variant="secondary" @click="showDeleteConfirm = false">
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </section>

                        <form class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="updateNotifications">
                            <div class="mb-5 flex items-start gap-3">
                                <div class="rounded-lg border border-blue-100 bg-blue-50 p-2 text-blue-700">
                                    <BellIcon class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">Notifications</h2>
                                    <p class="mt-1 text-sm text-slate-500">Choose the updates you want to receive.</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <label
                                    v-for="(enabled, key) in notificationForm.notification_preferences"
                                    :key="key"
                                    class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3"
                                >
                                    <span class="text-sm font-medium text-slate-700">
                                        {{
                                            {
                                                email_new_message: 'New messages',
                                                email_lead_update: 'Inquiry updates',
                                                email_review_received: 'Review updates',
                                                email_marketing: 'Immigrant Knowhow news',
                                                push_enabled: 'Browser notifications',
                                            }[key]
                                        }}
                                    </span>
                                    <input
                                        v-model="notificationForm.notification_preferences[key]"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    >
                                </label>
                                <Button type="submit" :loading="notificationForm.processing">
                                    Save notifications
                                </Button>
                            </div>
                        </form>
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
