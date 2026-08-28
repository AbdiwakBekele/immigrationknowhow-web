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

export async function redeemEbookCoupon(slug: string, code?: string): Promise<any> {
    const response = await fetch(`/library/${encodeURIComponent(slug)}/redeem-coupon`, {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify(code?.trim() ? { code: code.trim() } : {}),
    });

    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload?.message || 'Unable to redeem coupon.');
    }

    return payload;
}
