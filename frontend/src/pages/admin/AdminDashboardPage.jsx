import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
import { eventPublicDetailPath } from '../../lib/eventPaths';
import { Card, PageHeader } from '../../components/ui';
import { formatDate, formatPrice } from '../../lib/format';
import { EventFeedbacksSection } from '../../components/EventFeedbacksSection';
import { AdminShowcaseCard } from './AdminShowcaseCard';

function HistoryEventCard({ ev }) {
  const request = ev.event_request ?? ev.eventRequest;
  const metaParts = [ev.location, formatDate(ev.end_at ?? ev.start_at)].filter(Boolean);

  return (
    <Link
      to={eventPublicDetailPath(ev.id)}
      state={{ from: '/admin' }}
      className="block h-full transition hover:opacity-95"
    >
      <AdminShowcaseCard
        imageUrl={ev.image_url}
        imageAlt={ev.title}
        badge={null}
        title={ev.title}
        description={ev.description}
        clientName={request?.contact_name}
        clientEmail={request?.contact_email}
        metaLine={metaParts.length > 0 ? metaParts.join(' · ') : null}
        organizerName={ev.organizer?.name}
        ticketPrice={ev.ticket_price}
        footer={<EventFeedbacksSection eventId={ev.id} className="mt-2 border-t-0 pt-0" />}
      />
    </Link>
  );
}

export function AdminDashboardPage() {
  const [stats, setStats] = useState(null);

  useEffect(() => {
    api.get('/admin/stats').then((r) => setStats(r.data));
  }, []);

  if (!stats) {
    return <p className="text-stone-500">Chargement…</p>;
  }

  const items = [
    { label: 'Utilisateurs', value: stats.users_total },
    { label: 'Événements', value: stats.events_total },
    { label: 'Publiés', value: stats.events_published },
    { label: 'Inscriptions', value: stats.registrations_total },
    { label: 'Revenus', value: formatPrice(stats.revenue) },
    { label: 'Demandes en attente', value: stats.pending_requests },
    { label: 'Publications à valider', value: stats.pending_publications ?? 0 },
  ];

  const pastEvents = stats.past_events ?? [];

  return (
    <div>
      <PageHeader title="Statistiques globales" />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((i) => (
          <Card key={i.label}>
            <p className="text-sm text-stone-500">{i.label}</p>
            <p className="mt-1 text-3xl font-bold text-brand-700">{i.value}</p>
          </Card>
        ))}
      </div>

      <section className="mt-10">
        <h2 className="font-display mb-2 text-2xl font-medium text-stone-900">
          Historique des événements
        </h2>
        <p className="mb-6 text-base text-stone-600">Événements publiés et terminés</p>

        {pastEvents.length === 0 ? (
          <Card>
            <p className="text-lg text-stone-600">Aucun événement terminé pour le moment.</p>
          </Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {pastEvents.map((ev) => (
              <HistoryEventCard key={ev.id} ev={ev} />
            ))}
          </div>
        )}
      </section>
    </div>
  );
}

