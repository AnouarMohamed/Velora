import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { Badge, Button, Textarea } from '../../components/ui';
import { formatDate } from '../../lib/format';
import { AdminShowcaseCard } from './AdminShowcaseCard';
export function RequestReviewCard({ req, rejectReason, onRejectReasonChange, onApprove, onReject, }) {
    const metaParts = [
        req.location,
        formatDate(req.preferred_start),
    ].filter(Boolean);
    return (_jsx(AdminShowcaseCard, { imageUrl: req.image_url, imageAlt: req.title, badge: _jsx(Badge, { tone: "warning", size: "sm", className: "w-fit", children: "\u00C0 valider" }), title: req.title, description: req.description, clientName: req.contact_name, clientEmail: req.contact_email, metaLine: metaParts.length > 0 ? metaParts.join(' · ') : null, ticketPrice: req.ticket_price, footer: _jsxs("div", { className: "space-y-2", children: [_jsx(Textarea, { placeholder: "Motif de rejet", rows: 2, value: rejectReason, onChange: (e) => onRejectReasonChange(e.target.value) }), _jsxs("div", { className: "grid grid-cols-2 gap-2", children: [_jsx(Button, { className: "px-3 py-2 text-sm", onClick: onApprove, children: "Valider" }), _jsx(Button, { variant: "danger", className: "px-3 py-2 text-sm", onClick: onReject, children: "Rejeter" })] })] }) }));
}
