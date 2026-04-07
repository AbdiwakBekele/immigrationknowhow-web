<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    UserCircleIcon,
    CameraIcon,
    KeyIcon,
    BellIcon,
    TrashIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const tabs = ['Profile', 'Password', 'Notifications', 'Delete Account'];

const profileForm = useForm({
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    email: props.user.email || '',
    phone: props.user.phone || '',
    city: props.user.city || '',
    state: props.user.state || '',
    country: props.user.country || '',
    preferred_language: props.user.preferred_language || '',
    languages_spoken: props.user.languages_spoken || [],
    immigration_status: props.user.immigration_status || '',
    country_of_origin: props.user.country_of_origin || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    notification_preferences: props.user.notification_preferences || {
        email_new_message: true,
        email_lead_update: true,
        email_review_received: true,
        email_marketing: false,
        push_enabled: true,
    },
});

const deleteForm = useForm({
    password: '',
    confirmation: '',
});

const avatarInput = ref(null);
const showDeleteConfirm = ref(false);

const languageOptions = [
    'English', 'Spanish', 'Mandarin', 'Hindi', 'Arabic', 'Portuguese', 
    'Bengali', 'Russian', 'Japanese', 'French', 'German', 'Korean',
    'Vietnamese', 'Tagalog', 'Italian', 'Polish', 'Ukrainian'
];

const updateProfile = () => {
    profileForm.patch('/profile', {
        preserveScroll: true,
    });
};

const updatePassword = () => {
    passwordForm.patch('/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const updateNotifications = () => {
    notificationForm.patch('/profile/notifications', {
        preserveScroll: true,
    });
};

const uploadAvatar = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);

    router.post('/profile/avatar', formData, {
        preserveScroll: true,
    });
};

const deleteAvatar = () => {
    router.delete('/profile/avatar', {
        preserveScroll: true,
    });
};

const deleteAccount = () => {
    deleteForm.delete('/profile', {
        onSuccess: () => {
            // Redirect handled by controller
        },
    });
};

const toggleLanguage = (lang) => {
    const index = profileForm.languages_spoken.indexOf(lang);
    if (index > -1) {
        profileForm.languages_spoken.splice(index, 1);
    } else {
        profileForm.languages_spoken.push(lang);
    }
};
</script>

<template>
    <Head title="Edit Profile" />

    <AppLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-2xl font-display font-bold text-gray-900 mb-6">Account Settings</h1>

            <TabGroup>
                <TabList class="flex space-x-1 bg-gray-100 rounded-xl p-1 mb-6">
                    <Tab 
                        v-for="tab in tabs" 
                        :key="tab" 
                        v-slot="{ selected }"
                        class="w-full"
                    >
                        <button
                            class="w-full py-2.5 text-sm font-medium rounded-lg transition-colors"
                            :class="selected ? 'bg-white text-primary-700 shadow' : 'text-gray-600 hover:text-gray-900'"
                        >
                            {{ tab }}
                        </button>
                    </Tab>
                </TabList>

                <TabPanels>
                    <!-- Profile Tab -->
                    <TabPanel>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <!-- Avatar Section -->
                            <div class="flex items-center gap-6 mb-8 pb-8 border-b border-gray-100">
                                <div class="relative">
                                    <img 
                                        :src="user.avatar || '/images/default-avatar.png'" 
                                        class="h-24 w-24 rounded-full object-cover"
                                    />
                                    <button 
                                        @click="avatarInput?.click()"
                                        class="absolute bottom-0 right-0 p-2 bg-white rounded-full shadow-lg border border-gray-200 hover:bg-gray-50"
                                    >
                                        <CameraIcon class="h-4 w-4 text-gray-600" />
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
                                    <h3 class="font-medium text-gray-900">Profile Photo</h3>
                                    <p class="text-sm text-gray-500 mb-2">JPG, PNG or GIF. Max 2MB.</p>
                                    <div class="flex gap-2">
                                        <button @click="avatarInput?.click()" class="btn-secondary btn-sm">
                                            Upload
                                        </button>
                                        <button v-if="user.avatar" @click="deleteAvatar" class="btn-ghost btn-sm text-red-600">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <form @submit.prevent="updateProfile" class="space-y-6">
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                        <input v-model="profileForm.first_name" type="text" class="input w-full" required />
                                        <p v-if="profileForm.errors.first_name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.first_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                        <input v-model="profileForm.last_name" type="text" class="input w-full" required />
                                        <p v-if="profileForm.errors.last_name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.last_name }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input v-model="profileForm.email" type="email" class="input w-full" required />
                                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                    <input v-model="profileForm.phone" type="tel" class="input w-full" />
                                </div>

                                <div class="grid sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                        <input v-model="profileForm.city" type="text" class="input w-full" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                        <input v-model="profileForm.state" type="text" class="input w-full" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                        <input v-model="profileForm.country" type="text" class="input w-full" />
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Language</label>
                                    <select v-model="profileForm.preferred_language" class="input w-full">
                                        <option value="">Select language</option>
                                        <option v-for="lang in languageOptions" :key="lang" :value="lang">{{ lang }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Languages Spoken</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="lang in languageOptions"
                                            :key="lang"
                                            type="button"
                                            @click="toggleLanguage(lang)"
                                            class="px-3 py-1 rounded-full text-sm border transition-colors"
                                            :class="profileForm.languages_spoken.includes(lang) 
                                                ? 'bg-primary-100 text-primary-700 border-primary-300' 
                                                : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-gray-300'"
                                        >
                                            {{ lang }}
                                        </button>
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Immigration Status</label>
                                        <select v-model="profileForm.immigration_status" class="input w-full">
                                            <option value="">Prefer not to say</option>
                                            <option value="citizen">U.S. Citizen</option>
                                            <option value="permanent_resident">Permanent Resident</option>
                                            <option value="visa_holder">Visa Holder</option>
                                            <option value="asylum_seeker">Asylum Seeker</option>
                                            <option value="daca">DACA Recipient</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Country of Origin</label>
                                        <input v-model="profileForm.country_of_origin" type="text" class="input w-full" />
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button 
                                        type="submit" 
                                        class="btn-primary"
                                        :disabled="profileForm.processing"
                                    >
                                        {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </TabPanel>

                    <!-- Password Tab -->
                    <TabPanel>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-primary-100 rounded-lg">
                                    <KeyIcon class="h-5 w-5 text-primary-600" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
                                    <p class="text-sm text-gray-500">Update your password to keep your account secure</p>
                                </div>
                            </div>

                            <form @submit.prevent="updatePassword" class="space-y-4 max-w-md">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                    <input v-model="passwordForm.current_password" type="password" class="input w-full" required />
                                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                    <input v-model="passwordForm.password" type="password" class="input w-full" required />
                                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                    <input v-model="passwordForm.password_confirmation" type="password" class="input w-full" required />
                                </div>
                                <button 
                                    type="submit" 
                                    class="btn-primary"
                                    :disabled="passwordForm.processing"
                                >
                                    {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                                </button>
                            </form>
                        </div>
                    </TabPanel>

                    <!-- Notifications Tab -->
                    <TabPanel>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-primary-100 rounded-lg">
                                    <BellIcon class="h-5 w-5 text-primary-600" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Notification Preferences</h2>
                                    <p class="text-sm text-gray-500">Choose how you want to be notified</p>
                                </div>
                            </div>

                            <form @submit.prevent="updateNotifications" class="space-y-4">
                                <div class="space-y-4">
                                    <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100">
                                        <div>
                                            <div class="font-medium text-gray-900">New Messages</div>
                                            <div class="text-sm text-gray-500">Get notified when you receive a new message</div>
                                        </div>
                                        <input 
                                            v-model="notificationForm.notification_preferences.email_new_message" 
                                            type="checkbox" 
                                            class="toggle"
                                        />
                                    </label>

                                    <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100">
                                        <div>
                                            <div class="font-medium text-gray-900">Lead Updates</div>
                                            <div class="text-sm text-gray-500">Get notified about updates to your inquiries</div>
                                        </div>
                                        <input 
                                            v-model="notificationForm.notification_preferences.email_lead_update" 
                                            type="checkbox" 
                                            class="toggle"
                                        />
                                    </label>

                                    <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100">
                                        <div>
                                            <div class="font-medium text-gray-900">Marketing Emails</div>
                                            <div class="text-sm text-gray-500">Receive tips, updates, and promotional content</div>
                                        </div>
                                        <input 
                                            v-model="notificationForm.notification_preferences.email_marketing" 
                                            type="checkbox" 
                                            class="toggle"
                                        />
                                    </label>
                                </div>

                                <button 
                                    type="submit" 
                                    class="btn-primary"
                                    :disabled="notificationForm.processing"
                                >
                                    {{ notificationForm.processing ? 'Saving...' : 'Save Preferences' }}
                                </button>
                            </form>
                        </div>
                    </TabPanel>

                    <!-- Delete Account Tab -->
                    <TabPanel>
                        <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-red-100 rounded-lg">
                                    <ExclamationTriangleIcon class="h-5 w-5 text-red-600" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Delete Account</h2>
                                    <p class="text-sm text-gray-500">Permanently delete your account and all data</p>
                                </div>
                            </div>

                            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                                <p class="text-red-800 text-sm">
                                    <strong>Warning:</strong> This action cannot be undone. All your data, including messages, 
                                    reviews, and profile information will be permanently deleted.
                                </p>
                            </div>

                            <button 
                                @click="showDeleteConfirm = true"
                                class="btn-danger"
                            >
                                <TrashIcon class="h-4 w-4 mr-2" />
                                Delete My Account
                            </button>
                        </div>
                    </TabPanel>
                </TabPanels>
            </TabGroup>
        </div>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showDeleteConfirm = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Account Deletion</h3>
                        <p class="text-gray-600 mb-6">
                            This will permanently delete your account. Please enter your password and type 
                            <strong>DELETE</strong> to confirm.
                        </p>
                        <form @submit.prevent="deleteAccount" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input v-model="deleteForm.password" type="password" class="input w-full" required />
                                <p v-if="deleteForm.errors.password" class="mt-1 text-sm text-red-600">{{ deleteForm.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type DELETE to confirm</label>
                                <input v-model="deleteForm.confirmation" type="text" class="input w-full" placeholder="DELETE" required />
                            </div>
                            <div class="flex gap-3">
                                <button type="button" @click="showDeleteConfirm = false" class="btn-secondary flex-1">
                                    Cancel
                                </button>
                                <button 
                                    type="submit" 
                                    class="btn-danger flex-1"
                                    :disabled="deleteForm.processing || deleteForm.confirmation !== 'DELETE'"
                                >
                                    {{ deleteForm.processing ? 'Deleting...' : 'Delete Account' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
