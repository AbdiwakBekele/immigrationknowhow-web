<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';

const props = defineProps({
    items: Object,
    categories: Array,
    types: {
        type: Array,
        default: () => [],
    },
});

const uploadForm = useForm({
    title: '',
    type: props.types?.[0]?.value ?? '',
    category_id: '',
    author: '',
    description: '',
    file: null,
    is_premium: false,
    price: '0',
    currency: 'USD',
    is_active: true,
    is_featured: false,
});

const acceptedFileTypes = computed(() => {
    if (uploadForm.type === 'ebook') {
        return '.pdf,application/pdf';
    }

    if (uploadForm.type === 'audiobook') {
        return '.mp3,.m4a,.aac,.wav,.ogg,audio/*';
    }

    return '*/*';
});

const submitUpload = () => {
    uploadForm.post(route('provider.library.store'), {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset('title', 'author', 'description', 'file', 'price');
            uploadForm.price = '0';
            uploadForm.type = props.types?.[0]?.value ?? '';
            uploadForm.category_id = '';
            uploadForm.is_active = true;
            uploadForm.is_featured = false;
            uploadForm.is_premium = false;
            uploadForm.currency = 'USD';
        },
    });
};

const deleteItem = (slug) => {
    router.delete(route('provider.library.destroy', slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Provider Library" />

    <ProviderLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">My Digital Library</h1>
                <p class="mt-1 text-gray-500">Upload and sell e-books and audiobooks as one-time digital downloads.</p>
            </div>

            <form class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-4" @submit.prevent="submitUpload">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                        <input v-model="uploadForm.title" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
                        <p v-if="uploadForm.errors.title" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Type</label>
                        <select v-model="uploadForm.type" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
                            <option v-for="typeOption in types" :key="typeOption.value" :value="typeOption.value">
                                {{ typeOption.label }}
                            </option>
                        </select>
                        <p v-if="uploadForm.errors.type" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.type }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Category (optional)</label>
                        <select v-model="uploadForm.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                            <option value="">Uncategorized</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <p v-if="uploadForm.errors.category_id" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.category_id }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Author</label>
                        <input v-model="uploadForm.author" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <p v-if="uploadForm.errors.author" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.author }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                    <textarea v-model="uploadForm.description" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                    <p v-if="uploadForm.errors.description" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.description }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">File</label>
                    <input
                        type="file"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        :accept="acceptedFileTypes"
                        @change="uploadForm.file = $event.target.files?.[0] ?? null"
                        required
                    >
                    <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.file }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Price <span class="text-red-500">*</span></label>
                        <input
                            v-model="uploadForm.price"
                            type="number"
                            :min="uploadForm.is_premium ? '0.01' : '0'"
                            step="0.01"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                            placeholder="0 for free"
                            required
                        >
                        <p class="mt-1 text-xs text-gray-500">Required. Use 0 for free titles. If “One-time purchase” is checked, price must be at least 0.01.</p>
                        <p v-if="uploadForm.errors.price" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.price }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Currency <span class="text-red-500">*</span></label>
                        <input
                            v-model="uploadForm.currency"
                            type="text"
                            maxlength="3"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 uppercase"
                            placeholder="USD"
                            required
                        >
                        <p v-if="uploadForm.errors.currency" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.currency }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="uploadForm.is_premium" type="checkbox" class="rounded border-gray-300">
                        One-time purchase required
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="uploadForm.is_active" type="checkbox" class="rounded border-gray-300">
                        Active
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="uploadForm.is_featured" type="checkbox" class="rounded border-gray-300">
                        Featured
                    </label>
                </div>

                <div class="flex items-center justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                        :disabled="uploadForm.processing"
                    >
                        {{ uploadForm.processing ? 'Uploading...' : 'Upload Item' }}
                    </button>
                </div>
            </form>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">My Uploaded Items</h2>
                <div v-if="items?.data?.length" class="mt-4 divide-y divide-gray-100">
                    <div v-for="item in items.data" :key="item.id" class="flex items-center justify-between py-3">
                        <div>
                            <p class="font-medium text-gray-900">{{ item.title }}</p>
                            <p class="text-xs text-gray-500">
                                {{ item.type }} • {{ item.category?.name ?? 'Uncategorized' }}
                                • {{ item.currency ?? 'USD' }} {{ item.price }}
                                <span v-if="item.is_premium"> • One-time purchase</span>
                                <span v-else-if="Number(item.price) === 0"> • Free</span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-sm font-medium text-rose-600 hover:text-rose-500"
                            @click="deleteItem(item.slug)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-gray-500">No uploads yet.</p>
            </div>
        </div>
    </ProviderLayout>
</template>
