/** @param {number} [width] */
export function useLegalColumnScroll(width = 1024) {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia(`(min-width: ${width}px)`).matches;
}

/**
 * @param {string} id
 * @param {HTMLElement | null | undefined} scrollContainer
 * @param {number} [offset]
 */
export function scrollToLegalSection(id, scrollContainer, offset = 24) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }

    if (scrollContainer && useLegalColumnScroll()) {
        const top =
            scrollContainer.scrollTop +
            el.getBoundingClientRect().top -
            scrollContainer.getBoundingClientRect().top -
            offset;
        scrollContainer.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        return;
    }

    const top = el.getBoundingClientRect().top + window.scrollY - 88;
    window.scrollTo({ top, behavior: 'smooth' });
}

/**
 * @param {HTMLElement | null | undefined} scrollContainer
 */
export function getLegalScrollRoot(scrollContainer) {
    return scrollContainer && useLegalColumnScroll() ? scrollContainer : null;
}
