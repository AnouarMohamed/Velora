import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { CreditCard, Search, Ticket, Trash2, Users } from 'lucide-react';
import api from '../../lib/api';
import { eventPublicDetailPath } from '../../lib/eventPaths';
import { formatDate, formatEventStatus, formatPaymentStatus, formatPrice } from '../../lib/format';
import { DeleteParticipantModal } from '../../components/DeleteParticipantModal';
import { EventTicketModal } from '../../components/EventTicketModal';
import { Badge, Button, Card, PageHeader } from '../../components/ui';

function StatCard({ label, value, tone }) {
  const tones = {
    default: 'text-stone-900',
    success: 'text-emerald-800',
    warning: 'text-brand-800',
  };
  return (
    <Card className="text-center">
      <p className="text-sm text-stone-500">{label}</p>
      <p className={`mt-1 text-3xl font-bold ${tones[tone] ?? tones.default}`}>{value}</p>
    </Card>
  );
}

export function ManageRegistrationsPage() {
  const location = useLocation();
  const isAdminScope = location.pathname.startsWith('/admin/registrations');
  const apiPrefix = isAdminScope ? '/admin' : '/organizer';

  const [events, setEvents] = useState([]);
  const [regs, setRegs] = useState([]);
  const [summary, setSummary] = useState({ total: 0, paid: 0, pending: 0 });
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [eventId, setEventId] = useState('');
  const [paymentStatus, setPaymentStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [page, setPage] = useState(1);

  const [ticketTarget, setTicketTarget] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const loadEvents = useCallback(async () => {
    const { data } = await api.get(`${apiPrefix}/registrations/events`);
    setEvents(Array.isArray(data) ? data : []);
  }, [apiPrefix]);

  const loadRegistrations = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = { page };
      if (eventId) params.event_id = eventId;
      if (paymentStatus !== 'all') params.payment_status = paymentStatus;
      if (search) params.q = search;

      const { data } = await api.get(`${apiPrefix}/registrations`, { params });
      setRegs(data.data ?? []);
      setSummary(data.summary ?? { total: 0, paid: 0, pending: 0 });
      setMeta(data.meta ?? { current_page: 1, last_page: 1, total: 0 });
    } catch {
      setError('Impossible de charger les inscriptions.');
      setRegs([]);
    } finally {
      setLoading(false);
    }
  }, [apiPrefix, eventId, paymentStatus, search, page]);

  useEffect(() => {
    void loadEvents();
  }, [loadEvents]);

  useEffect(() => {
    void loadRegistrations();
  }, [loadRegistrations]);

  const subtitle = useMemo(() => {
    if (isAdminScope) {
      return 'Événements qui vous sont assignés, créés par vous ou gérés dans l’espace organisateur.';
    }
    return 'Événements publiés qui vous sont assignés ou que vous avez créés.';
  }, [isAdminScope]);

  const publishedEvents = useMemo(
    () => events.filter((ev) => ev.status === 'published'),
    [events],
  );

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setSearch(searchInput.trim());
    setPage(1);
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    setError('');
    try {
      await api.delete(`${apiPrefix}/registrations/${deleteTarget.id}`);
      setDeleteTarget(null);
      await loadRegistrations();
      await loadEvents();
    } catch (e) {
      const err = e;
      setError(err.response?.data?.message || "Impossible de supprimer l'inscription.");
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div>
      <PageHeader title="Billets et inscriptions" subtitle={subtitle} />

      {error && <p className="mb-4 text-center text-sm text-red-600">{error}</p>}

      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <StatCard label="Inscriptions" value={summary.total} tone="default" />
        <StatCard label="Payées (billets)" value={summary.paid} tone="success" />
        <StatCard label="En attente" value={summary.pending} tone="warning" />
      </div>

      <Card className="mb-6 space-y-4">
        <form
          onSubmit={handleSearchSubmit}
          className="flex flex-col gap-3 lg:flex-row lg:items-end lg:flex-wrap"
        >
          <label className="block min-w-[200px] flex-1 space-y-1.5 max-md:w-full max-md:min-w-0">
            <span className="text-sm font-medium text-stone-700">Événement</span>
            <select
              className="select-field glass-panel w-full px-3 py-2.5 text-base text-stone-800"
              value={eventId}
              onChange={(e) => {
                setEventId(e.target.value);
                setPage(1);
              }}
            >
              <option value="">Tous les événements</option>
              {events.map((ev) => (
                <option key={ev.id} value={ev.id}>
                  {ev.title}
                  {ev.status !== 'published' ? ` (${formatEventStatus(ev.status)})` : ''}
                </option>
              ))}
            </select>
          </label>

          <label className="block min-w-[160px] space-y-1.5 max-md:w-full max-md:min-w-0">
            <span className="text-sm font-medium text-stone-700">Paiement</span>
            <select
              className="select-field glass-panel w-full px-3 py-2.5 text-base text-stone-800"
              value={paymentStatus}
              onChange={(e) => {
                setPaymentStatus(e.target.value);
                setPage(1);
              }}
            >
              <option value="all">Tous</option>
              <option value="paid">Payé</option>
              <option value="pending">En attente</option>
            </select>
          </label>

          <label className="block min-w-[200px] flex-[2] space-y-1.5 max-md:w-full max-md:min-w-0">
            <span className="text-sm font-medium text-stone-700">Recherche</span>
            <div className="flex gap-2 max-md:flex-col">
              <input
                type="search"
                placeholder="Participant, e-mail, événement, code billet…"
                className="glass-panel w-full px-3 py-2.5 text-base text-stone-900 outline-none placeholder:text-stone-500"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
              />
              <Button type="submit" variant="secondary" className="shrink-0 px-4 max-md:w-full">
                <Search className="h-4 w-4" />
              </Button>
            </div>
          </label>
        </form>

        {!isAdminScope && publishedEvents.length === 0 && !loading && (
          <p className="text-sm text-stone-600">
            Aucun événement publié pour le moment. Publiez un événement pour voir les inscriptions ici.
          </p>
        )}
      </Card>

      {loading ? (
        <p className="text-stone-500">Chargement…</p>
      ) : regs.length === 0 ? (
        <Card>
          <p className="flex items-center gap-2 text-stone-600">
            <Users className="h-5 w-5 shrink-0" />
            Aucune inscription pour ces critères.
          </p>
        </Card>
      ) : (
        <div className="space-y-3">
          {regs.map((r) => (
            <Card
              key={r.id}
              className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-semibold text-stone-900">{r.user?.name ?? 'Participant'}</p>
                  <Badge tone={r.payment_status === 'paid' ? 'success' : 'warning'}>
                    {formatPaymentStatus(r.payment_status)}
                  </Badge>
                </div>
                <p className="text-sm text-stone-600">{r.user?.email}</p>
                <p className="text-sm font-medium text-brand-800">
                  <Link
                    to={eventPublicDetailPath(r.event_id)}
                    state={{ from: location.pathname }}
                    className="hover:underline"
                  >
                    {r.event?.title ?? 'Événement'}
                  </Link>
                  {r.event?.start_at && (
                    <span className="font-normal text-stone-500">
                      {' '}
                      · {formatDate(r.event.start_at)}
                    </span>
                  )}
                </p>
                <p className="text-sm text-stone-700">
                  {formatPrice(r.amount)}
                  {r.payment_status === 'paid' && r.ticket_code && (
                    <span className="ml-2 text-stone-500">
                      · Code :{' '}
                      <span className="font-mono text-xs">{String(r.ticket_code).slice(0, 8)}…</span>
                    </span>
                  )}
                </p>
              </div>

              <div className="flex shrink-0 flex-wrap gap-2 max-md:w-full max-md:flex-col">
                {r.payment_status === 'paid' ? (
                  <Button className="max-md:w-full" variant="secondary" onClick={() => setTicketTarget(r)}>
                    <Ticket className="h-4 w-4" /> Voir le billet
                  </Button>
                ) : (
                  <>
                    <span className="inline-flex items-center gap-1.5 rounded-xl border border-brand-400/40 bg-brand-50/80 px-3 py-2 text-sm text-brand-800">
                      <CreditCard className="h-4 w-4" />
                      En attente
                    </span>
                    <Button className="max-md:w-full" variant="danger" onClick={() => setDeleteTarget(r)}>
                      <Trash2 className="h-4 w-4" /> Supprimer
                    </Button>
                  </>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}

      {meta.last_page > 1 && (
        <div className="mt-6 flex items-center justify-center gap-3">
          <Button
            variant="secondary"
            disabled={page <= 1 || loading}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            Précédent
          </Button>
          <span className="text-sm text-stone-600">
            Page {meta.current_page} / {meta.last_page}
          </span>
          <Button
            variant="secondary"
            disabled={page >= meta.last_page || loading}
            onClick={() => setPage((p) => p + 1)}
          >
            Suivant
          </Button>
        </div>
      )}

      <DeleteParticipantModal
        open={deleteTarget !== null}
        registration={deleteTarget}
        busy={deleting}
        onClose={() => !deleting && setDeleteTarget(null)}
        onConfirm={() => void confirmDelete()}
      />

      <EventTicketModal
        open={ticketTarget !== null}
        registration={ticketTarget}
        participantName={ticketTarget?.user?.name ?? 'Participant'}
        onClose={() => setTicketTarget(null)}
      />
    </div>
  );
}
