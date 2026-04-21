/**
 * Human-readable sentence-case label for ad status (not snake_case).
 */
const LABELS = {
    draft: 'Draft',
    pending: 'Pending',
    pending_payment: 'Pending payment',
    pending_approval: 'Pending approval',
    published: 'Published',
    rejected: 'Rejected',
};

function sentenceCaseFromSnake(key) {
    const spaced = String(key).replace(/_/g, ' ').toLowerCase();
    if (!spaced) return '—';
    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

export function formatAdStatus(status) {
    if (status == null || status === '') return '—';
    const key = String(status).trim();
    if (Object.prototype.hasOwnProperty.call(LABELS, key)) {
        return LABELS[key];
    }
    return sentenceCaseFromSnake(key);
}
