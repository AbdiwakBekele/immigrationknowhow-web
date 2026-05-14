const STORAGE_KEY = 'ikh_community_guest_id';

function fallbackGuestId() {
    const randomPart = Math.random().toString(36).slice(2, 12);
    return `guest_${Date.now()}_${randomPart}`;
}

export function getCommunityGuestKey() {
    if (typeof window === 'undefined') return '';

    let value = window.localStorage.getItem(STORAGE_KEY);
    if (!value) {
        try {
            if (typeof window.crypto?.randomUUID === 'function') {
                value = window.crypto.randomUUID();
            } else if (typeof window.crypto?.getRandomValues === 'function') {
                const bytes = new Uint8Array(16);
                window.crypto.getRandomValues(bytes);
                value = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
            } else {
                value = fallbackGuestId();
            }
        } catch (error) {
            console.warn('[community][ui] guest key generation fallback used', { error });
            value = fallbackGuestId();
        }
        window.localStorage.setItem(STORAGE_KEY, value);
    }
    return value;
}

export function communityPostPath(id) {
    return `/community/${id}`;
}

export function communityPostShareUrl(id) {
    if (typeof window === 'undefined') {
        return communityPostPath(id);
    }
    return `${window.location.origin}${communityPostPath(id)}`;
}

export function youtubeVideoIdFromUrl(url) {
    if (!url) {
        return null;
    }

    try {
        const parsed = new URL(url);
        if (parsed.hostname === 'youtu.be') {
            return parsed.pathname.replace('/', '').slice(0, 32) || null;
        }
        if (parsed.hostname.includes('youtube.com')) {
            if (parsed.pathname === '/watch') {
                return parsed.searchParams.get('v');
            }
            const embed = parsed.pathname.match(/^\/embed\/([^/]+)/);
            if (embed) {
                return embed[1] || null;
            }
            const shorts = parsed.pathname.match(/^\/shorts\/([^/]+)/);
            if (shorts) {
                return shorts[1] || null;
            }
        }
    } catch {
        return null;
    }

    return null;
}

export async function copyTextToClipboard(text) {
    const value = String(text ?? '');
    if (!value) {
        return false;
    }

    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(value);
            return true;
        } catch {
            // Fall through to the legacy copy path.
        }
    }

    if (typeof document === 'undefined') {
        return false;
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    document.body.removeChild(textarea);
    return copied;
}
