<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { ArrowLeftIcon, InboxIcon } from '@heroicons/vue/24/outline';

defineProps({
    conversations: { type: Object, required: true },
});

const formatTime = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
    <Head title="Archived messages" />

    <ProviderLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center gap-3 mb-6">
                <Link
                    :href="route('provider.messages.index')"
                    class="inline-flex p-2 rounded-lg text-slate-600 hover:bg-slate-100"
                >
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-display font-bold text-slate-900">Archived</h1>
                    <p class="text-slate-500 text-sm">Conversations you archived as a provider</p>
                </div>
            </div>

            <div v-if="conversations.data?.length" class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
                <Link
                    v-for="conversation in conversations.data"
                    :key="conversation.id"
                    :href="route('provider.messages.show', conversation.uuid)"
                    class="flex items-center gap-4 p-4 hover:bg-slate-50"
                >
                    <img
                        :src="conversation.user?.avatar || '/images/default-avatar.png'"
                        class="h-10 w-10 rounded-full object-cover"
                    />
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 truncate">{{ conversation.user?.full_name || 'Client' }}</p>
                        <p class="text-sm text-slate-500 truncate">{{ conversation.latest_message?.body || 'No preview' }}</p>
                    </div>
                    <span class="text-xs text-slate-400">{{ formatTime(conversation.last_message_at) }}</span>
                </Link>
            </div>

            <div v-else class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <InboxIcon class="h-12 w-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-600">No archived conversations</p>
            </div>

            <div v-if="conversations.links?.length > 3" class="mt-6 flex justify-center">
                <nav class="flex gap-1">
                    <Link
                        v-for="link in conversations.links"
                        :key="link.label"
                        :href="link.url"
                        class="px-3 py-2 text-sm rounded-lg"
                        :class="link.active ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </ProviderLayout>
</template>
