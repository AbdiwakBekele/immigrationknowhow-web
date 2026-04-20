import DOMPurify from 'dompurify';
import { marked } from 'marked';

marked.setOptions({
    gfm: true,
    breaks: true,
});

export const renderSafeMarkdown = (value) => {
    const source = typeof value === 'string' ? value : '';
    if (!source.trim()) return '';

    const rawHtml = marked.parse(source);

    return DOMPurify.sanitize(rawHtml, {
        USE_PROFILES: { html: true },
    });
};
