/**
 * Build share metadata for the public site or the current page.
 */
export function siteShareMeta(overrides = {}) {
    const title =
        overrides.title
        ?? (typeof document !== 'undefined' ? document.title : 'ImmigrationKnowHow');
    const description =
        overrides.description
        ?? 'Connect with trusted immigration and settlement service providers.';
    const url =
        overrides.url
        ?? (typeof window !== 'undefined' ? window.location.href : '/');

    return { title, description, url };
}
