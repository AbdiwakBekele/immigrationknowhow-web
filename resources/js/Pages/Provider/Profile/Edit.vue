<script setup>
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    UserCircleIcon,
    CameraIcon,
    BuildingOfficeIcon,
    CurrencyDollarIcon,
    MapPinIcon,
    GlobeAltIcon,
    AcademicCapIcon,
    PlusIcon,
    TrashIcon,
    EyeIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    provider: { type: Object, required: true },
    serviceTypes: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

// Flash messages
const flash = computed(() => page.props.flash || {});

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
    certifications: props.provider?.certifications || [],
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

const avatarInput = ref(null);
const newServiceArea = ref('');
const newSpecialization = ref('');

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

const updateProfile = () => {
    form.patch(route('provider.profile.update'), {
        preserveScroll: true,
    });
};

const uploadAvatar = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);

    router.post(route('provider.profile.avatar'), formData, {
        preserveScroll: true,
    });
};

const addCertification = () => {
    form.certifications.push({
        name: '',
        issuer: '',
        year: new Date().getFullYear(),
    });
};

const removeCertification = (index) => {
    form.certifications.splice(index, 1);
};

const addServiceArea = () => {
    if (newServiceArea.value && !form.service_areas.includes(newServiceArea.value)) {
        form.service_areas.push(newServiceArea.value);
        newServiceArea.value = '';
    }
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
};

const verificationStatusLabel = computed(() => {
    const status = props.provider?.verification_status;
    if (status === 'approved') return { text: 'Verified', class: 'bg-green-100 text-green-700' };
    if (status === 'pending') return { text: 'Pending Verification', class: 'bg-yellow-100 text-yellow-700' };
    if (status === 'rejected') return { text: 'Verification Rejected', class: 'bg-red-100 text-red-700' };
    return { text: 'Not Verified', class: 'bg-slate-100 text-slate-700' };
});
</script>

<template>
    <Head title="Edit Profile" />

    <AppLayout>
        <div class="min-h-screen bg-slate-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-display font-bold text-slate-900">Edit Profile</h1>
                        <p class="text-slate-500 mt-1">Update your business information and settings</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span :class="['px-3 py-1 rounded-full text-sm font-medium', verificationStatusLabel.class]">
                            {{ verificationStatusLabel.text }}
                        </span>
                        <Link :href="route('marketplace.show', provider.slug)" class="flex items-center gap-2 px-4 py-2 border border-slate-200 text-slate-700 rounded-xl hover:bg-slate-50 transition-colors">
                            <EyeIcon class="h-4 w-4" />
                            Preview
                        </Link>
                    </div>
                </div>

                <!-- Flash Messages -->
                <div v-if="flash.success" class="mb-6 flex items-center gap-2 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700">
                    <CheckCircleIcon class="h-5 w-5 flex-shrink-0" />
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="mb-6 flex items-center gap-2 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">
                    <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
                    {{ flash.error }}
                </div>

                <form @submit.prevent="updateProfile" class="space-y-6">
                    <!-- Profile Photo -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Profile Photo</h2>
                        <div class="flex items-center gap-6">
                            <div class="relative">
                                <img 
                                    :src="user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(form.business_name || 'P')}&background=3B95F3&color=fff&size=96`" 
                                    class="h-24 w-24 rounded-2xl object-cover bg-slate-100"
                                />
                                <button 
                                    type="button"
                                    @click="avatarInput?.click()"
                                    class="absolute bottom-0 right-0 p-2 bg-white rounded-full shadow-lg border border-slate-200 hover:bg-slate-50 transition-colors"
                                >
                                    <CameraIcon class="h-4 w-4 text-slate-600" />
                                </button>
                                <input 
                                    ref="avatarInput"
                                    type="file" 
                                    accept="image/*" 
                                    class="hidden" 
                                    @change="uploadAvatar"
                                />
                            </div>
                            <div>
                                <p class="text-sm text-slate-600">Upload a professional photo</p>
                                <p class="text-xs text-slate-400 mt-1">JPG, PNG or GIF. Max 2MB.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Business Information</h2>
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
                                <label class="block text-sm font-medium text-slate-700 mb-2">Specific Service Areas</label>
                                <div class="flex gap-2 mb-2">
                                    <input 
                                        v-model="newServiceArea"
                                        type="text"
                                        class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="Add a city, county, or state"
                                        @keyup.enter.prevent="addServiceArea"
                                    />
                                    <button type="button" @click="addServiceArea" class="px-4 py-2.5 bg-slate-100 text-slate-700 rounded-xl hover:bg-slate-200 transition-colors">
                                        <PlusIcon class="h-5 w-5" />
                                    </button>
                                </div>
                                <div v-if="form.service_areas.length" class="flex flex-wrap gap-2">
                                    <span 
                                        v-for="(area, index) in form.service_areas" 
                                        :key="index"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-full"
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

                    <!-- Credentials & Licenses -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Credentials & Licenses</h2>
                        <div class="space-y-4">
                            <div class="grid sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">License Number</label>
                                    <input 
                                        v-model="form.license_number" 
                                        type="text" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">License State</label>
                                    <input 
                                        v-model="form.license_state" 
                                        type="text" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">License Expiry</label>
                                    <input 
                                        v-model="form.license_expiry" 
                                        type="date" 
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-700">Certifications</label>
                                    <button type="button" @click="addCertification" class="flex items-center gap-1 text-primary-600 hover:text-primary-700 text-sm font-medium">
                                        <PlusIcon class="h-4 w-4" />
                                        Add Certification
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <div 
                                        v-for="(cert, index) in form.certifications" 
                                        :key="index"
                                        class="flex items-start gap-3 p-4 bg-slate-50 rounded-xl"
                                    >
                                        <AcademicCapIcon class="h-6 w-6 text-primary-600 flex-shrink-0 mt-1" />
                                        <div class="flex-1 grid sm:grid-cols-3 gap-3">
                                            <input 
                                                v-model="cert.name"
                                                type="text"
                                                class="px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="Certification Name"
                                                required
                                            />
                                            <input 
                                                v-model="cert.issuer"
                                                type="text"
                                                class="px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="Issuing Organization"
                                            />
                                            <input 
                                                v-model="cert.year"
                                                type="number"
                                                class="px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="Year"
                                                min="1900"
                                                :max="new Date().getFullYear()"
                                            />
                                        </div>
                                        <button type="button" @click="removeCertification(index)" class="text-red-500 hover:text-red-700 transition-colors">
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                                <p v-if="!form.certifications.length" class="text-slate-500 text-center py-4 bg-slate-50 rounded-xl">
                                    No certifications added yet
                                </p>
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

                    <!-- Availability -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-display font-bold text-slate-900 mb-4">Availability</h2>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="form.accepting_clients" type="checkbox" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                            <span class="text-sm text-slate-700">Currently accepting new clients</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Turn this off if you're not taking on new clients right now</p>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end gap-3 pb-8">
                        <Link :href="route('provider.dashboard')" class="px-6 py-3 border border-slate-200 text-slate-700 font-medium rounded-xl hover:bg-slate-50 transition-colors">
                            Cancel
                        </Link>
                        <button 
                            type="submit" 
                            class="px-8 py-3 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-xl transition-colors disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
