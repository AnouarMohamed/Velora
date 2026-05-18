import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
import { eventPublicDetailPath } from '../../lib/eventPaths';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { EventImage } from '../../components/EventImage';
import { Badge, Button, Card, PageHeader } from '../../components/ui';
import { EventFeedbacksSection } from '../../components/EventFeedbacksSection';
import { PriceText } from '../../components/PriceText';
import { formatDate, formatPriceAmount } from '../../lib/format';

function eventEndsAt(ev) {
  return ev.end_at ?? ev.start_at ?? null;
}

function parseApiDateTime(value) {
  if (!value) return null;
  const s = String(value).trim();
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
  if (m) {
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]));
  }
  const d = new Date(s);
  return Number.isNaN(d.getTime()) ? null : d;
}

function isEventFinished(ev) {
  const endsAt = parseApiDateTime(eventEndsAt(ev));
  if (!endsAt) return false;
  return endsAt <= new Date();
}

function EventCard({ ev, badge, showFeedbacks }) {
  return (
    <li>
      <Link
        to={eventPublicDetailPath(ev.id)}
        state={{ from: '/client/stats' }}
        className="glass-panel flex flex-col overflow-hidden rounded-xl border border-white/50 transition hover:border-brand-400/40 hover:shadow-md"
      >
      {ev.image_url && (
        <EventImage
          src={ev.image_url}
          alt={ev.title}
          className="aspect-[16/10] w-full max-h-44 object-cover"
        />
      )}

      <div className="flex flex-col gap-2 p-4">
        {badge && (
          <Badge tone={badge.tone} className="w-fit self-start">
            {badge.label}
          </Badge>
        )}
        <p className="text-2xl font-bold leading-snug text-stone-950">{ev.title}</p>
        <p className="text-base text-stone-600">
          {ev.location ? `${ev.location} · ` : ''}
          {formatDate(badge ? ev.start_at : (ev.end_at ?? ev.start_at))}
        </p>
        {ev.organizer && (
          <p className="text-base text-brand-700">Organisateur : {ev.organizer.name}</p>
        )}
        <p className="text-lg font-bold text-stone-950">
          Billet : <PriceText value={ev.ticket_price} size="md" />
        </p>
        <p className="mt-1 border-t border-stone-400/50 pt-3 text-base font-medium text-stone-800">
          {ev.tickets_count} billet{ev.tickets_count > 1 ? 's' : ''} · {ev.registered_count}/
          {ev.capacity} inscr.
        </p>
        {showFeedbacks && <EventFeedbacksSection eventId={ev.id} className="mt-4 border-t-0 pt-0" />}
      </div>
      </Link>
    </li>
  );
}

function RequestColumn({ title, items, empty, accent, onDelete, deletingId }) {
  const accentBorder = {
    warning: 'border-brand-400/50',
    success: 'border-emerald-400/50',
    danger: 'border-red-300/60',
  }[accent];

  return (
    <div className="flex min-h-[300px] flex-col">
      <h2 className="font-display mb-3 text-center text-2xl font-medium text-stone-900">{title}</h2>
      <div
        className={`flex flex-1 flex-col gap-3 rounded-2xl border bg-white/25 p-3 backdrop-blur-sm ${accentBorder}`}
      >
        {items.length === 0 ? (
          <p className="flex flex-1 items-center justify-center px-2 text-center text-lg text-stone-600">
            {empty}
          </p>
        ) : (
          items.map((req) => (
            <article
              key={req.id}
              className="glass-panel flex flex-col overflow-hidden rounded-xl border border-white/50"
            >
              {req.image_url && (
                <EventImage
                  src={req.image_url}
                  alt={req.title}
                  className="aspect-[16/10] w-full max-h-40 object-cover"
                />
              )}
              <div className="flex flex-col gap-2 p-4">
                <h3 className="text-2xl font-bold leading-snug text-stone-950">{req.title}</h3>
                {req.description && (
                  <p className="text-base leading-relaxed text-stone-700">{req.description}</p>
                )}
                <p className="text-sm text-stone-600">
                  {req.location ? `${req.location} · ` : ''}
                  {formatDate(req.preferred_start)}
                </p>
                {req.status === 'rejected' && req.rejection_reason && (
                  <p className="text-base text-red-700">Motif : {req.rejection_reason}</p>
                )}
                <p className="text-lg font-bold text-stone-950">
                  Billet :{' '}
                  <PriceText value={req.ticket_price ?? req.event?.ticket_price ?? 0} />
                </p>
                <p className="mt-auto border-t border-stone-400/50 pt-3 text-lg font-bold text-stone-950">
                  Inscriptions :{' '}
                  <span className="text-2xl font-bold text-brand-900">
                    {req.registrations_count ?? req.event?.registrations_count ?? 0}
                  </span>
                </p>
                {onDelete && (
                  <Button
                    type="button"
                    variant="danger"
                    className="mt-2 w-full"
                    onClick={() => onDelete(req)}
                    disabled={deletingId === req.id}
                  >
                    Supprimer la demande
                  </Button>
                )}
              </div>
            </article>
          ))
        )}
      </div>
    </div>
  );
}

const emptyStats = {
  total_revenue: 0,
  featured_events: [],
  past_events: [],
  can_submit_new_request: true,
  requests: { pending: [], approved: [], rejected: [] },
};

export function ClientStatsPage() {
  const [stats, setStats] = useState(null);
  const [error, setError] = useState('');
  const [deletingId, setDeletingId] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);

  const load = useCallback(() => {
    return api
      .get('/client/stats')
      .then((r) => setStats(r.data))
      .catch(() => {
        setError('Impossible de charger vos statistiques.');
        setStats(emptyStats);
      });
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const confirmDelete = async () => {
    if (!pendingDelete) return;
    const { id } = pendingDelete;
    setDeletingId(id);
    setError('');
    try {
      await api.delete(`/event-requests/${id}`);
      setPendingDelete(null);
      await load();
    } catch (err) {
      const msg = err.response?.data?.message || 'Suppression impossible.';
      setError(msg);
    } finally {
      setDeletingId(null);
    }
  };

  const { momentEvents, historyEvents } = useMemo(() => {
    if (!stats) {
      return { momentEvents: [], historyEvents: [] };
    }
    const byId = new Map();
    for (const ev of [...stats.featured_events, ...stats.past_events]) {
      byId.set(ev.id, ev);
    }
    const moment = [];
    const history = [];
    for (const ev of byId.values()) {
      if (isEventFinished(ev)) {
        history.push(ev);
      } else {
        moment.push(ev);
      }
    }
    moment.sort(
      (a, b) =>
        (parseApiDateTime(eventEndsAt(a))?.getTime() ?? 0) -
        (parseApiDateTime(eventEndsAt(b))?.getTime() ?? 0),
    );
    history.sort(
      (a, b) =>
        (parseApiDateTime(eventEndsAt(b))?.getTime() ?? 0) -
        (parseApiDateTime(eventEndsAt(a))?.getTime() ?? 0),
    );
    return { momentEvents: moment, historyEvents: history };
  }, [stats]);

  if (!stats) {
    return <p className="text-lg text-stone-600">Chargement…</p>;
  }

  return (
    <div>
      <ConfirmDialog
        open={pendingDelete !== null}
        title="Supprimer cette demande ?"
        cancelLabel="Annuler"
        confirmLabel="Supprimer"
        busy={deletingId !== null}
        onCancel={() => !deletingId && setPendingDelete(null)}
        onConfirm={() => void confirmDelete()}
      >
        {pendingDelete && (
          <>
            <p>
              Voulez-vous supprimer la demande{' '}
              <strong className="font-medium text-stone-900">{pendingDelete.title}</strong> ?
            </p>
            <p className="mt-3 text-stone-600">
              Cette action est irréversible. Vous pourrez ensuite envoyer une nouvelle demande
              d&apos;événement.
            </p>
          </>
        )}
      </ConfirmDialog>
      <PageHeader title="Statistiques" subtitle="Revenus et suivi de vos demandes" />
      {error && <p className="mb-4 text-center text-base text-red-600">{error}</p>}

      <div className="mb-8 grid gap-4 lg:grid-cols-[1fr_280px] lg:items-stretch">
        <div className="grid gap-4 md:grid-cols-2 max-md:order-2">
          <Card>
            <h2 className="font-display mb-4 text-2xl font-medium text-stone-900 max-md:text-xl">
              Événements du moment
            </h2>
            <p className="mb-4 text-base text-stone-600">Événements en cours</p>
            {momentEvents.length === 0 ? (
              <p className="text-lg text-stone-600">
                Aucun événement en cours pour le moment. Les événements apparaîtront ici tant
                qu&apos;ils ne sont pas terminés.
              </p>
            ) : (
              <ul className="space-y-3">
                {momentEvents.map((ev) => (
                  <EventCard key={ev.id} ev={ev} badge={{ tone: 'success', label: 'En ligne' }} />
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <h2 className="font-display mb-4 text-2xl font-medium text-stone-900 max-md:text-xl">
              Historique des événements
            </h2>
            <p className="mb-4 text-base text-stone-600">Événements terminés</p>
            {historyEvents.length === 0 ? (
              <p className="text-lg text-stone-600">
                Aucun événement terminé pour le moment.
              </p>
            ) : (
              <ul className="space-y-3">
                {historyEvents.map((ev) => (
                  <EventCard key={ev.id} ev={ev} showFeedbacks />
                ))}
              </ul>
            )}
          </Card>
        </div>

        <Card className="flex flex-col items-center justify-center text-center max-md:order-1">
          <p className="text-lg text-stone-600">Revenus</p>
          <p className="mt-2 flex flex-wrap items-baseline justify-center gap-1 text-stone-950">
            <span className="text-5xl font-bold max-md:text-3xl">
              {formatPriceAmount(stats.total_revenue)}
            </span>
            <span className="text-lg font-semibold text-stone-700">MAD</span>
          </p>
        </Card>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <RequestColumn
          title="En attente"
          items={stats.requests.pending}
          empty="Aucune demande en attente."
          accent="warning"
          deletingId={deletingId}
          onDelete={(req) => !deletingId && setPendingDelete({ id: req.id, title: req.title })}
        />
        <RequestColumn
          title="Acceptées"
          items={stats.requests.approved}
          empty="Aucune demande acceptée."
          accent="success"
        />
        <RequestColumn
          title="Refusées"
          items={stats.requests.rejected}
          empty="Aucune demande refusée."
          accent="danger"
        />
      </div>
    </div>
  );
}
