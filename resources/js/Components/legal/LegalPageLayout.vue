<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import LegalDocument from '@/Components/legal/LegalDocument.vue';
import LegalDocumentNav from '@/Components/legal/LegalDocumentNav.vue';
import SiteLegalFooter from '@/Components/legal/SiteLegalFooter.vue';
import { buildSectionNav } from '@/utils/legalDocument';
import { scrollToLegalSection } from '@/utils/legalScroll';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
});

const activeSectionId = ref('overview');
const mainScrollRef = ref(null);

const navItems = computed(() => [
    { id: 'overview', label: 'Overview' },
    ...buildSectionNav(props.document.sections ?? []),
]);

const scrollToSection = (id) => {
    scrollToLegalSection(id, mainScrollRef.value);
    activeSectionId.value = id;
};

</script>

<template>
    <Head :title="document.title" />

    <div class="flex min-h-screen w-full flex-col bg-slate-100">
        <div class="flex min-h-0 w-full flex-1 flex-col px-3 py-6 sm:px-4 sm:py-8 lg:px-6 lg:py-10">
            <div
                class="relative mb-6 flex shrink-0 min-h-14 items-center sm:mb-8 sm:min-h-16"
            >
                <Link
                    :href="route('home')"
                    class="relative z-10 ml-2 inline-flex shrink-0 transition opacity-90 hover:opacity-100 sm:ml-3 lg:ml-4"
                >
                    <BrandLogo
                        context="site"
                        :show-name="false"
                        container-class="flex items-center justify-center"
                        mark-class="flex h-12 w-auto max-w-[160px] items-center justify-center overflow-hidden sm:h-14 sm:max-w-[200px]"
                        image-class="h-full w-full object-contain"
                        initials-class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-600 text-base font-bold uppercase text-white sm:h-14 sm:w-14"
                    />
                </Link>
                <h1
                    class="legal-page-title pointer-events-none absolute inset-x-0 text-center font-serif text-xl font-bold uppercase tracking-[0.12em] text-slate-900 sm:text-3xl sm:tracking-[0.14em] lg:text-4xl"
                >
                    {{ document.title }}
                </h1>
            </div>

            <div
                class="flex min-h-0 flex-1 flex-col rounded-2xl border border-slate-200/90 bg-white shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)] lg:flex-row lg:gap-4 lg:p-2 xl:gap-5 xl:p-2.5"
            >
                <aside
                    class="hidden w-[260px] shrink-0 bg-slate-50/50 lg:block lg:self-start"
                >
                    <div
                        class="legal-sidebar-nav sticky top-6 z-10 my-2 max-h-[calc(100vh-3rem)] overflow-y-auto rounded-xl border border-slate-200/80 bg-white p-2 shadow-sm lg:my-4 lg:ml-1 lg:mr-0"
                    >
                        <LegalDocumentNav
                            :items="navItems"
                            :active-id="activeSectionId"
                            :scroll-container="mainScrollRef"
                            @update:active-id="activeSectionId = $event"
                        />
                    </div>
                </aside>

                <div class="shrink-0 border-b border-slate-100 px-2 py-4 sm:px-3 lg:hidden">
                    <label
                        for="legal-mobile-nav"
                        class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        Jump to section
                    </label>
                    <select
                        id="legal-mobile-nav"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-800 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        :value="activeSectionId"
                        @change="scrollToSection($event.target.value)"
                    >
                        <option v-for="item in navItems" :key="item.id" :value="item.id">
                            {{ item.label }}
                        </option>
                    </select>
                </div>

                <main
                    ref="mainScrollRef"
                    class="legal-main-content min-h-0 min-w-0 flex-1 lg:max-h-[calc(100vh-10rem)] lg:overflow-y-auto lg:overscroll-contain"
                >
                    <div class="px-2 py-6 sm:px-4 sm:py-8 lg:px-4 lg:py-8 xl:px-5">
                        <LegalDocument :document="document" />
                    </div>
                </main>
            </div>
        </div>

        <SiteLegalFooter variant="light" compact full-width class="mt-4 shrink-0 sm:mt-6" />
    </div>
</template>

<style scoped>
.legal-page-title {
    font-family: 'Times New Roman', Times, Georgia, serif;
}

.legal-sidebar-nav {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

.legal-main-content {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}
</style>
