export function formatDate(value) {
    if (!value)
        return '—';
    const s = String(value).trim();
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
    const d = m
        ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]))
        : new Date(s);
    if (Number.isNaN(d.getTime()))
        return '—';
    return d.toLocaleString('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
function formatAmount(value) {
    return new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value));
}
const EVENT_STATUS_LABELS = {
    draft: 'Brouillon',
    pending_publication: 'En attente de validation',
    published: 'Publié',
    cancelled: 'Annulé',
    completed: 'Terminé',
    pending: 'En attente',
    approved: 'Accepté',
    rejected: 'Refusé',
};
export function formatEventStatus(status) {
    return EVENT_STATUS_LABELS[status] ?? status;
}
const PAYMENT_STATUS_LABELS = {
    pending: 'En attente',
    paid: 'Payé',
    failed: 'Échoué',
};
export function formatPaymentStatus(status) {
    return PAYMENT_STATUS_LABELS[status] ?? status;
}
export function formatPrice(value) {
    const n = Number(value);
    if (n <= 0)
        return 'Gratuit';
    return `${formatAmount(n)} MAD`;
}
/** Montant seul (afficher « MAD » à part, en plus petit). */
export function formatPriceAmount(value) {
    const n = Number(value);
    if (n <= 0)
        return '0';
    return formatAmount(n);
}
