<script setup>
import { sectionIdFromHeading } from '@/utils/legalDocument';

defineProps({
    document: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <article class="legal-document">
        <div
            id="overview"
            data-legal-section
            class="legal-doc-section mb-8 scroll-mt-28"
        >
            <div
                v-for="(paragraph, index) in document.intro"
                :key="`intro-${index}`"
                class="legal-doc-prose mb-4 text-[15px] leading-relaxed text-slate-700"
            >
                {{ paragraph }}
            </div>
        </div>

        <section
            v-for="(section, sectionIndex) in document.sections"
            :id="sectionIdFromHeading(section.heading, sectionIndex)"
            :key="`section-${sectionIndex}`"
            data-legal-section
            class="legal-doc-section scroll-mt-28 border-t border-slate-100 pt-8 first:mb-0 first:border-t-0 first:pt-0"
        >
            <h2
                class="legal-doc-heading mb-4 font-serif text-xl font-bold tracking-tight text-slate-900 sm:text-[1.35rem]"
            >
                {{ section.heading }}
            </h2>

            <p
                v-for="(paragraph, index) in section.paragraphs ?? []"
                :key="`p-${sectionIndex}-${index}`"
                class="legal-doc-prose mb-3 text-[15px] leading-relaxed text-slate-700"
            >
                {{ paragraph }}
            </p>

            <div
                v-for="(subsection, subIndex) in section.subsections ?? []"
                :key="`sub-${sectionIndex}-${subIndex}`"
                class="mb-5 w-full rounded-xl border border-slate-100 bg-slate-50/80 px-2 py-4 sm:px-2.5"
            >
                <h3
                    v-if="subsection.title"
                    class="legal-doc-subheading mb-3 font-serif text-base font-bold text-slate-900"
                >
                    {{ subsection.title }}
                </h3>
                <ul
                    v-if="subsection.list?.length"
                    class="legal-doc-list mb-3 list-disc space-y-1.5 pl-5 text-[15px] leading-relaxed text-slate-700"
                >
                    <li v-for="(item, itemIndex) in subsection.list" :key="`li-${itemIndex}`">
                        {{ item }}
                    </li>
                </ul>
                <p
                    v-for="(paragraph, index) in subsection.paragraphs ?? []"
                    :key="`subp-${sectionIndex}-${subIndex}-${index}`"
                    class="legal-doc-prose mb-3 text-[15px] leading-relaxed text-slate-700 last:mb-0"
                >
                    {{ paragraph }}
                </p>
            </div>

            <ul
                v-if="section.list?.length"
                class="legal-doc-list mb-3 list-disc space-y-1.5 pl-5 text-[15px] leading-relaxed text-slate-700"
            >
                <li v-for="(item, itemIndex) in section.list" :key="`li-${sectionIndex}-${itemIndex}`">
                    {{ item }}
                </li>
            </ul>
        </section>
    </article>
</template>

<style scoped>
.legal-document .legal-doc-prose,
.legal-document .legal-doc-heading,
.legal-document .legal-doc-subheading,
.legal-document .legal-doc-list {
    font-family: 'Times New Roman', Times, Georgia, serif;
}
</style>
