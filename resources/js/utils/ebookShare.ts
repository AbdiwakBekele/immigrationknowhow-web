export function toAbsoluteShareUrl(shareUrl: string): string {
    if (!shareUrl) {
        return '';
    }

    if (/^https?:\/\//i.test(shareUrl)) {
        return shareUrl;
    }

    if (typeof window !== 'undefined') {
        const path = shareUrl.startsWith('/') ? shareUrl : `/${shareUrl}`;
        return `${window.location.origin}${path}`;
    }

    return shareUrl;
}

export function buildEbookShareTargets(shareUrl: string, title: string) {
    const absoluteUrl = toAbsoluteShareUrl(shareUrl);
    const encodedUrl = encodeURIComponent(absoluteUrl);
    const plainTitle = String(title || '').replace(/^"|"$/g, '').trim();
    const message = encodeURIComponent(`Check out "${plainTitle}" on IKH Library`);

    return {
        shareUrl: absoluteUrl,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}&quote=${message}`,
        x: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${message}`,
    };
}

function csrfHeaders(): Record<string, string> {
    const rawToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const token = rawToken ? decodeURIComponent(rawToken) : '';

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}

export async function recordEbookShareIntent(slug: string, platform: 'facebook' | 'x' | 'other'): Promise<any> {
    const response = await fetch(`/library/share/items/${encodeURIComponent(slug)}/intent`, {
        method: 'POST',
        headers: csrfHeaders(),
        body: JSON.stringify({ platform }),
        credentials: 'same-origin',
    });

    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload?.message || 'Unable to record share.');
    }

    return payload;
}

export async function startEbookShare(slug: string): Promise<any> {
    const response = await fetch(`/library/share/items/${encodeURIComponent(slug)}/start`, {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
    });

    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload?.message || 'Unable to start share.');
    }

    return payload;
}
