import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Search } from 'lucide-react';
import api from '../../lib/api';
import { formatDate } from '../../lib/format';
import { Badge, Input, PageHeader } from '../../components/ui';
import { AdminShowcaseCard } from '../admin/AdminShowcaseCard';

export function BrowseEventsPage() {
  const [events, setEvents] = useState([]);
  const [paidEventIds, setPaidEventIds] = useState(() => new Set());
  const [q, setQ] = useState('');
  const [loading, setLoading] = useState(true);

  const load = async (search = '') => {
    setLoading(true);
    try {
      const [eventsRes, regsRes] = await Promise.all([
        api.get('/events/browse', { params: { q: search || undefined } }),
        api.get('/my-registrations'),
      ]);
      setEvents(eventsRes.data.data);
      const paid = new Set(
        (regsRes.data.data ?? [])
          .filter((r) => r.payment_status === 'paid')
          .map((r) => String(r.event_id ?? r.event?.id)),
      );
      setPaidEventIds(paid);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <div>
      <PageHeader title="Événements" subtitle="Rechercher et s'inscrire" />
      <form
        className="mb-6 flex gap-2 max-md:flex-col"
        onSubmit={(e) => {
          e.preventDefault();
          void load(q);
        }}
      >
        <Input
          placeholder="Rechercher…"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="flex-1"
        />
        <button type="submit" className="btn-glass btn-glass-accent px-4 py-2.5">
          <Search className="h-4 w-4" />
        </button>
      </form>
      {loading ? (
        <p className="text-stone-500">Chargement…</p>
      ) : events.length === 0 ? (
        <p className="glass-panel rounded-xl border border-white/50 px-4 py-8 text-center text-stone-600">
          Aucun événement publié pour le moment.
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {events.map((ev) => {
            const metaParts = [ev.location, formatDate(ev.start_at)].filter(Boolean);
            const isReserved = paidEventIds.has(String(ev.id));
            return (
              <Link key={ev.id} to={`/events/${ev.id}`} className="block h-full">
                <AdminShowcaseCard
                  imageUrl={ev.image_url}
                  imageAlt={ev.title}
                  badge={
                    isReserved ? (
                      <Badge tone="success" size="sm" className="w-fit">
                        D{'\u00e9'}j{'\u00e0'} r{'\u00e9'}serv{'\u00e9'}
                      </Badge>
                    ) : null
                  }
                  title={ev.title}
                  description={ev.description}
                  metaLine={metaParts.length > 0 ? metaParts.join(' · ') : null}
                  ticketPrice={ev.ticket_price}
                  footer={
                    <p className="text-sm font-medium text-brand-800">
                      {isReserved
                        ? "Voir l'événement et mon billet →"
                        : "Voir l'événement et s'inscrire →"}
                    </p>
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
