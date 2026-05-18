import { renderSafeMarkdown } from '@/utils/markdown';

const NOISE_LINE_PATTERNS = [
    /^see this story on our app/i,
    /^advertisement$/i,
    /^click here to share on social media/i,
    /^cookie preferences/i,
    /^follow al jazeera/i,
    /^©\s*\d{4}/i,
    /^source:\s*https?:\/\//i,
    /^open the menu/i,
    /^end of list$/i,
    /^list of \d+ item/i,
    /^\*{3,}\s*$/,
    /^!\[\]\(data:image/i,
    /^!\[logo\]/i,
    /^published on\b/i,
    /^share$/i,
    /^more from news/i,
    /^most popular/i,
    /^recommended stories/i,
    /^about$/i,
    /^connect$/i,
    /^our channels$/i,
    /^our network$/i,
    /^explainer$/i,
    /^sign up$/i,
    /^live$/i,
    /^by\s*\[/i,
    /^\[!\[/i,
];

const NOISE_INLINE_PATTERNS = [
    /\[view in app\]\([^)]+\)/gi,
    /al jazeera\s+see this story on our app/gi,
];

const NAV_ONLY_LINK_LINE = /^(\[[^\]]+\]\([^)]+\)\s*[|·•]\s*)*(\[[^\]]+\]\([^)]+\)\s*)+$/;

/**
 * Strip scraped article chrome (nav, ads, footers) while keeping article markdown.
 */
export function cleanCommunityMarkdown(source) {
    let text = typeof source === 'string' ? source : '';
    if (!text.trim()) {
        return '';
    }

    text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

    for (const pattern of NOISE_INLINE_PATTERNS) {
        text = text.replace(pattern, '');
    }

    const kept = [];

    for (const line of text.split('\n')) {
        const trimmed = line.trim();

        if (trimmed === '') {
            kept.push('');
            continue;
        }

        if (/^!\[[^\]]*\]\(data:image/i.test(trimmed)) {
            continue;
        }

        if (NOISE_LINE_PATTERNS.some((pattern) => pattern.test(trimmed))) {
            continue;
        }

        if (NAV_ONLY_LINK_LINE.test(trimmed) && trimmed.length < 320) {
            continue;
        }

        if (/^!\[[^\]]*\]\(https?:\/\/[^)]+\)\s*$/.test(trimmed) && /related_podcast|footer-logo|placeholder/i.test(trimmed)) {
            continue;
        }

        kept.push(line);
    }

    return kept
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

/**
 * Plain-text excerpt for cards, search, and previews.
 */
export function communityDescriptionPlainText(source, maxLength = 240) {
    const cleaned = cleanCommunityMarkdown(source);
    if (!cleaned) {
        return '';
    }

    let plain = cleaned
        .replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1')
        .replace(/^#{1,6}\s+/gm, '')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/__(.+?)__/g, '$1')
        .replace(/\*([^*\n]+)\*/g, '$1')
        .replace(/_([^_\n]+)_/g, '$1')
        .replace(/^>\s?/gm, '')
        .replace(/`([^`]+)`/g, '$1')
        .replace(/\n+/g, ' ')
        .replace(/\s{2,}/g, ' ')
        .trim();

    if (maxLength > 0 && plain.length > maxLength) {
        return `${plain.slice(0, maxLength).trimEnd()}…`;
    }

    return plain;
}

/**
 * Sanitized HTML for post body (headings, bold, italic, links, lists, blockquotes).
 */
export function renderCommunityDescription(source) {
    const cleaned = cleanCommunityMarkdown(source);
    if (!cleaned) {
        return '';
    }

    return renderSafeMarkdown(cleaned);
}
