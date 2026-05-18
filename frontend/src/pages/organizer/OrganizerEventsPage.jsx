import { useCallback, useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { eventCardLinkPath } from '../../lib/eventPaths';
import { formatDate, formatEventStatus } from '../../lib/format';
import { Badge, Button, PageHeader } from '../../components/ui';
import { AdminShowcaseCard } from '../admin/AdminShowcaseCard';

function parseEventsPayload(payload) {
    if (Array.isArray(payload))
        return payload;
    return payload.data ?? [];
}

export function OrganizerEventsPage() {
    const location = useLocation();
    const navigate = useNavigate();
    const { user } = useAuth();
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await api.get('/organizer/events');
            setEvents(parseEventsPayload(data));
        }
        catch {
            setError('Impossible de charger vos événements.');
            setEvents([]);
        }
        finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void load();
    }, [load, location.key]);

    const createBtn = (
        <Button type="button" onClick={() => navigate('/organizer/events/new')}>
            Créer un événement
        </Button>
    );

    return (
        <div>
            <PageHeader
                title="Mes événements"
                subtitle="Événements dont vous êtes l’organisateur"
                action={createBtn}
            />
            {error && <p className="mb-4 text-center text-sm text-red-600">{error}</p>}
            {loading ? (
                <p className="text-stone-500">Chargement…</p>
            ) : events.length === 0 ? (
                <div className="space-y-4">
                    <p className="glass-panel rounded-xl border border-white/50 px-4 py-8 text-center text-stone-600">
                        Aucun événement pour le moment. Créez votre premier événement pour le voir apparaître ici.
                    </p>
                    <div className="flex justify-center">{createBtn}</div>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {events.map((ev) => {
                        const request = ev.event_request ?? ev.eventRequest;
                        const metaParts = [ev.location, formatDate(ev.start_at)].filter(Boolean);
                        const badgeTone =
                            ev.status === 'published'
                                ? 'success'
                                : ev.status === 'pending_publication' || ev.status === 'draft'
                                  ? 'warning'
                                  : 'default';
                        return (
                            <Link
                                key={ev.id}
                                to={eventCardLinkPath(ev, user)}
                                state={{ from: location.pathname }}
                                className="block h-full"
                            >
                                <AdminShowcaseCard
                                    imageUrl={ev.image_url}
                                    imageAlt={ev.title}
                                    badge={
                                        <Badge tone={badgeTone} size="sm" className="w-fit">
                                            {formatEventStatus(ev.status)}
                                        </Badge>
                                    }
                                    title={ev.title}
                                    description={ev.description}
                                    clientName={request?.contact_name}
                                    clientEmail={request?.contact_email}
                                    metaLine={metaParts.length > 0 ? metaParts.join(' · ') : null}
                                    ticketPrice={ev.ticket_price}
                                    footer={
                                        ev.status === 'draft' || ev.status === 'pending_publication' ? (
                                            <p className="text-sm font-medium text-brand-800">
                                                Gérer capacité, tâches et activités →
                                            </p>
                                        ) : undefined
                                    }
                                />
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
