import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight, CreditCard, Ticket, X } from 'lucide-react';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { RegistrationChoiceModal } from '../../components/RegistrationChoiceModal';
import { formatDate, formatPaymentStatus, formatPrice } from '../../lib/format';
import { PaymentCheckoutModal } from '../../components/PaymentCheckoutModal';
import { EventTicketModal } from '../../components/EventTicketModal';
import { Badge, Button, Card, PageHeader } from '../../components/ui';

const FILTERS = [
  { id: 'all', label: 'Toutes' },
  { id: 'paid', label: 'Payé' },
  { id: 'pending', label: 'En attente' },
];

export function MyRegistrationsPage() {
  const { user } = useAuth();
  const [regs, setRegs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [payTarget, setPayTarget] = useState(null);
  const [ticketTarget, setTicketTarget] = useState(null);
  const [cancelTarget, setCancelTarget] = useState(null);
  const [paying, setPaying] = useState(false);
  const [cancelling, setCancelling] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await api.get('/my-registrations');
      setRegs(r.data.data ?? []);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const filtered = useMemo(() => {
    if (filter === 'paid') {
      return regs.filter((r) => r.payment_status === 'paid');
    }
    if (filter === 'pending') {
      return regs.filter((r) => r.payment_status === 'pending');
    }
    return regs;
  }, [regs, filter]);

  const counts = useMemo(
    () => ({
      all: regs.length,
      paid: regs.filter((r) => r.payment_status === 'paid').length,
      pending: regs.filter((r) => r.payment_status === 'pending').length,
    }),
    [regs],
  );

  const openPayment = (e, registration) => {
    e.preventDefault();
    e.stopPropagation();
    setPayTarget(registration);
  };

  const openTicket = async (e, registration) => {
    e.preventDefault();
    e.stopPropagation();
    const eventId = registration.event?.id ?? registration.event_id;
    if (eventId) {
      try {
        const { data } = await api.get(`/events/${eventId}/my-registration`);
        if (data.registration) {
          setTicketTarget(data.registration);
          return;
        }
      } catch {
        /* garde les données en cache */
      }
    }
    setTicketTarget(registration);
  };

  const confirmCancel = async () => {
    if (!cancelTarget) return;
    setCancelling(true);
    try {
      await api.delete(`/registrations/${cancelTarget.id}`);
      setRegs((prev) => prev.filter((r) => r.id !== cancelTarget.id));
      setCancelTarget(null);
    } catch (e) {
      const err = e;
      alert(err.response?.data?.message || "Impossible d'annuler l'inscription.");
    } finally {
      setCancelling(false);
    }
  };

  const goToPaymentFromChoice = () => {
    if (!cancelTarget) return;
    setPayTarget(cancelTarget);
    setCancelTarget(null);
  };

  const confirmPayment = async () => {
    if (!payTarget) return;
    setPaying(true);
    try {
      const { data } = await api.post(`/registrations/${payTarget.id}/pay`);
      setRegs((prev) =>
        prev.map((r) =>
          r.id === payTarget.id ? { ...data, event: data.event ?? r.event } : r,
        ),
      );
      setPayTarget(null);
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Le paiement a échoué.', { cause: error });
    } finally {
      setPaying(false);
    }
  };

  return (
    <div>
      <PageHeader title="Mes inscriptions" />

      <div className="mb-6 flex flex-wrap gap-2">
        {FILTERS.map(({ id, label }) => (
          <button
            key={id}
            type="button"
            onClick={() => setFilter(id)}
            className={`rounded-full px-4 py-2 text-sm font-medium transition-colors ${
              filter === id
                ? 'border border-brand-500/50 bg-white/70 text-brand-900 shadow-sm'
                : 'border border-white/40 bg-white/30 text-stone-600 hover:bg-white/50'
            }`}
          >
            {label}
            <span className="ml-1.5 tabular-nums text-stone-500">({counts[id]})</span>
          </button>
        ))}
      </div>

      <div className="space-y-4">
        {loading &&
          [1, 2].map((n) => (
            <Card key={n} className="animate-pulse space-y-3 p-5">
              <div className="h-5 w-2/3 rounded bg-stone-200/80" />
              <div className="h-4 w-1/3 rounded bg-stone-200/60" />
            </Card>
          ))}

        {!loading &&
          filtered.map((r) => {
            const eventId = r.event?.id ?? r.event_id;
            if (!eventId) return null;

            return (
              <Card
                key={r.id}
                className="flex flex-col gap-4 p-0 sm:flex-row sm:items-stretch sm:justify-between"
              >
                <Link
                  to={`/events/${eventId}`}
                  className="group flex min-w-0 flex-1 flex-col gap-2 rounded-xl p-4 transition hover:bg-white/30 sm:p-5"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                      <h3 className="font-semibold text-stone-900 group-hover:text-brand-800">
                        {r.event?.title}
                      </h3>
                      <Badge
                        size="sm"
                        tone={r.payment_status === 'paid' ? 'success' : 'warning'}
                      >
                        {formatPaymentStatus(r.payment_status)}
                      </Badge>
                    </div>
                    <ChevronRight className="h-5 w-5 shrink-0 text-stone-400 transition group-hover:translate-x-0.5 group-hover:text-brand-700" />
                  </div>
                  <p className="text-sm text-stone-500">{formatDate(r.event?.start_at)}</p>
                  <p className="text-sm">{formatPrice(r.amount)}</p>
                </Link>

                <div className="flex flex-wrap gap-2 border-t border-white/40 p-4 sm:flex-col sm:justify-center sm:border-l sm:border-t-0 sm:pl-4 max-md:flex-col max-md:border-l-0 max-md:pl-4">
                  {r.payment_status !== 'paid' && (
                    <>
                      <Button className="max-md:w-full" variant="secondary" onClick={(e) => openPayment(e, r)}>
                        <CreditCard className="h-4 w-4" /> Payer
                      </Button>
                      <Button
                        className="max-md:w-full"
                        variant="danger"
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          setCancelTarget(r);
                        }}
                      >
                        <X className="h-4 w-4" /> Annuler
                      </Button>
                    </>
                  )}
                  {r.payment_status === 'paid' && (
                    <Button className="max-md:w-full" variant="secondary" onClick={(e) => openTicket(e, r)}>
                      <Ticket className="h-4 w-4" /> Billet
                    </Button>
                  )}
                </div>
              </Card>
            );
          })}

        {!loading && !filtered.length && (
          <p className="text-stone-500">
            {filter === 'all'
              ? 'Aucune inscription.'
              : filter === 'paid'
                ? 'Aucune inscription payée.'
                : 'Aucune inscription en attente.'}
          </p>
        )}
      </div>

      <RegistrationChoiceModal
        open={cancelTarget !== null}
        registration={cancelTarget}
        busy={cancelling}
        onClose={() => !cancelling && setCancelTarget(null)}
        onPay={goToPaymentFromChoice}
        onCancelRegistration={() => void confirmCancel()}
      />

      <PaymentCheckoutModal
        open={payTarget !== null}
        registration={payTarget}
        userEmail={user?.email}
        busy={paying}
        onClose={() => !paying && setPayTarget(null)}
        onConfirm={confirmPayment}
      />

      <EventTicketModal
        open={ticketTarget !== null}
        registration={ticketTarget}
        participantName={user?.name ?? 'Participant'}
        onClose={() => setTicketTarget(null)}
      />
    </div>
  );
}
