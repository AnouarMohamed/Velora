import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../lib/api';
import { Badge, PageHeader } from '../../components/ui';
import { formatDate, formatEventStatus } from '../../lib/format';
import { AdminShowcaseCard } from './AdminShowcaseCard';
import { RequestReviewCard } from './RequestReviewCard';
export function AdminRequestsPage() {
    const navigate = useNavigate();
    const [requests, setRequests] = useState([]);
    const [rejectReason, setRejectReason] = useState({});
    const load = () => api.get('/admin/event-requests').then((r) => setRequests(r.data.data));
    useEffect(() => {
        load();
    }, []);
    const pending = useMemo(() => requests.filter((r) => r.status === 'pending'), [requests]);
    const reviewed = useMemo(() => requests.filter((r) => r.status !== 'pending'), [requests]);
    const review = async (req, decision) => {
        await api.post(`/admin/event-requests/${req.id}/review`, {
            decision,
            rejection_reason: decision === 'rejected' ? rejectReason[req.id] || 'Non conforme' : undefined,
        });
        if (decision === 'approved') {
            navigate('/admin/events');
            return;
        }
        load();
    };
    return (_jsxs("div", { children: [_jsx(PageHeader, { title: "Demandes d'\u00E9v\u00E9nements", subtitle: "Valider ou rejeter" }), _jsxs("section", { className: "mb-8", children: [_jsx("h2", { className: "font-display mb-4 text-xl font-medium text-stone-900", children: "\u00C0 valider" }), pending.length === 0 ? (_jsx("p", { className: "glass-panel rounded-xl border border-white/50 px-4 py-6 text-center text-sm text-stone-600", children: "Aucune demande en attente." })) : (_jsx("div", { className: "grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3", children: pending.map((req) => (_jsx(RequestReviewCard, { req: req, rejectReason: rejectReason[req.id] || '', onRejectReasonChange: (value) => setRejectReason((prev) => ({ ...prev, [req.id]: value })), onApprove: () => void review(req, 'approved'), onReject: () => void review(req, 'rejected') }, req.id))) }))] }), reviewed.length > 0 && (_jsxs("section", { children: [_jsx("h2", { className: "font-display mb-4 text-xl font-medium text-stone-900", children: "Historique" }), _jsx("div", { className: "grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3", children: reviewed.map((req) => {
                            const metaParts = [req.location, formatDate(req.preferred_start)].filter(Boolean);
                            return (_jsx(AdminShowcaseCard, { imageUrl: req.image_url, imageAlt: req.title, badge: _jsx(Badge, { tone: req.status === 'approved' ? 'success' : 'danger', size: "sm", className: "w-fit", children: formatEventStatus(req.status) }), title: req.title, description: req.description, clientName: req.contact_name, clientEmail: req.contact_email, metaLine: metaParts.length > 0 ? metaParts.join(' · ') : null, ticketPrice: req.ticket_price, footer: req.status === 'rejected' && req.rejection_reason ? (_jsxs("p", { className: "text-sm text-red-700", children: ["Motif : ", req.rejection_reason] })) : undefined }, req.id));
                        }) })] }))] }));
}
