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
