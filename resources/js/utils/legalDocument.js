/** @typedef {{ heading: string, navLabel?: string, paragraphs?: string[], list?: string[], subsections?: { title: string, list?: string[], paragraphs?: string[] }[] }} LegalSection */

const CONTINUED_HEADING = /\s*\(continued[^)]*\)/i;

/**
 * @param {string} heading
 */
export function isContinuedSection(heading) {
    return CONTINUED_HEADING.test(String(heading));
}

/**
 * @param {string} heading
 */
export function normalizeSectionHeading(heading) {
    return String(heading).replace(CONTINUED_HEADING, '').replace(/\s+/g, ' ').trim();
}

/**
 * @param {LegalSection[]} sections
 * @returns {LegalSection[]}
 */
export function mergeContinuedLegalSections(sections) {
    /** @type {LegalSection[]} */
    const merged = [];

    for (const section of sections) {
        const copy = {
            heading: section.heading,
            navLabel: section.navLabel,
            paragraphs: section.paragraphs ? [...section.paragraphs] : [],
            list: section.list ? [...section.list] : undefined,
            subsections: section.subsections
                ? section.subsections.map((sub) => ({
                      ...sub,
                      list: sub.list ? [...sub.list] : undefined,
                      paragraphs: sub.paragraphs ? [...sub.paragraphs] : undefined,
                  }))
                : undefined,
        };

        if (isContinuedSection(section.heading) && merged.length > 0) {
            const prev = merged[merged.length - 1];

            if (copy.paragraphs.length) {
                prev.paragraphs = [...(prev.paragraphs ?? []), ...copy.paragraphs];
            }

            if (copy.list?.length) {
                prev.list = [...(prev.list ?? []), ...copy.list];
            }

            if (copy.subsections?.length) {
                prev.subsections = [...(prev.subsections ?? []), ...copy.subsections];
            }

            continue;
        }

        copy.heading = normalizeSectionHeading(copy.heading);
        merged.push(copy);
    }

    return merged;
}

/**
 * @param {string} heading
 * @param {number} index
 */
export function sectionIdFromHeading(heading, index) {
    const slug = normalizeSectionHeading(heading)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const base = slug || 'section';

    return `legal-${index}-${base}`;
}

/**
 * @param {string} heading
 * @param {string | undefined} navLabel
 */
export function sectionNavLabel(heading, navLabel) {
    if (navLabel) {
        return navLabel;
    }

    return normalizeSectionHeading(heading);
}

/**
 * @param {LegalSection[]} sections
 */
export function buildSectionNav(sections) {
    const seen = new Set();

    return sections.map((section, index) => {
        const label = sectionNavLabel(section.heading, section.navLabel);
        const id = sectionIdFromHeading(section.heading, index);

        if (seen.has(label)) {
            return {
                id,
                label: `${label} (${index + 1})`,
            };
        }

        seen.add(label);

        return { id, label };
    });
}
