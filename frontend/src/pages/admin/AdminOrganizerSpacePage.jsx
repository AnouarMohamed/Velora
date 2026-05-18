import { useCallback, useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { eventCardLinkPath } from '../../lib/eventPaths';
import { formatDate, formatEventStatus } from '../../lib/format';
import { Badge, PageHeader } from '../../components/ui';
import { AdminShowcaseCard } from './AdminShowcaseCard';

function parseEventsPayload(payload) {
  if (Array.isArray(payload)) return payload;
  return payload.data ?? [];
}

function creatorOrganizerName(ev) {
  if (ev.creator?.role === 'organizer' && ev.creator.name) return ev.creator.name;
  return null;
}

export function AdminOrganizerSpacePage() {
  const location = useLocation();
  const { user } = useAuth();
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await api.get('/admin/organizer-events');
      setEvents(parseEventsPayload(data));
    } catch {
      setError('Impossible de charger les événements des organisateurs.');
      setEvents([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load, location.key]);

  return (
    <div>
      <PageHeader
        title="Gérer l'espace organisateur"
        subtitle="Événements assignés à un organisateur ou créés par un organisateur"
      />
      {error && <p className="text-center text-sm text-red-600">{error}</p>}
      {loading ? (
        <p className="text-stone-500">Chargement…</p>
      ) : events.length === 0 ? (
        <p className="glass-panel rounded-xl border border-white/50 px-4 py-8 text-center text-stone-600">
          Aucun événement dans l&apos;espace organisateur pour le moment.
        </p>
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
            const metaLine = metaParts.length > 0 ? metaParts.join(' · ') : null;

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
                  metaLine={metaLine}
                  organizerName={creatorOrganizerName(ev)}
                  organizerNamePrefix="Créé par : "
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
