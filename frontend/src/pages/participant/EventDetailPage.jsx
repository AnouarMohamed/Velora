import { useCallback, useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { ArrowLeft, CreditCard, Settings, Ticket, X } from 'lucide-react';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import {
  eventDetailBackPath,
  eventManagePath,
} from '../../lib/eventPaths';
import { formatDate, formatEventStatus, formatPaymentStatus, formatPrice } from '../../lib/format';
import { EventImage } from '../../components/EventImage';
import { EventTicketModal } from '../../components/EventTicketModal';
import { PaymentCheckoutModal } from '../../components/PaymentCheckoutModal';
import { EventFeedbacksSection } from '../../components/EventFeedbacksSection';
import { RegistrationChoiceModal } from '../../components/RegistrationChoiceModal';
import { Badge, Button, Card, PageHeader, Textarea } from '../../components/ui';

function ChipList({ items }) {
  if (!items?.length) return null;
  return (
    <div className="flex flex-wrap gap-2">
      {items.map((item) => (
        <span
          key={item.id}
          className="rounded-lg border border-white/50 bg-white/45 px-3 py-2 text-sm text-stone-800 shadow-sm"
        >
          {item.title}
        </span>
      ))}
    </div>
  );
}

export function EventDetailPage() {
  const { id } = useParams();
  const location = useLocation();
  const { hasRole, user } = useAuth();
  const [event, setEvent] = useState(null);
  const [registration, setRegistration] = useState(null);
  const [msg, setMsg] = useState('');
  const [feedbackMsg, setFeedbackMsg] = useState('');
  const [feedbackError, setFeedbackError] = useState(false);
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [payOpen, setPayOpen] = useState(false);
  const [ticketOpen, setTicketOpen] = useState(false);
  const [paying, setPaying] = useState(false);
  const [choiceOpen, setChoiceOpen] = useState(false);
  const [cancelling, setCancelling] = useState(false);

  const isParticipant = hasRole('participant');
  const isStaff = hasRole('admin') || hasRole('organizer') || hasRole('client');

  const loadEvent = useCallback(
    () => api.get(`/events/${id}`).then((r) => setEvent(r.data)),
    [id],
  );

  const loadRegistration = useCallback(() => {
    if (!isParticipant) {
      setRegistration(null);
      return Promise.resolve();
    }
    return api.get(`/events/${id}/my-registration`).then((r) => {
      setRegistration(r.data.registration ?? null);
    });
  }, [id, isParticipant]);

  const load = useCallback(
    () => Promise.all([loadEvent(), loadRegistration()]),
    [loadEvent, loadRegistration],
  );

  const mergeRegistrationEvent = (reg) =>
    reg ? { ...reg, event: reg.event ?? event } : reg;

  useEffect(() => {
    void load();
  }, [load]);

  const register = async () => {
    try {
      await api.post(`/events/${id}/register`);
      setMsg('Inscription réussie !');
      await load();
    } catch (e) {
      const err = e;
      setMsg(err.response?.data?.message || 'Erreur inscription');
    }
  };

  const confirmCancel = async () => {
    if (!registration) return;
    setCancelling(true);
    try {
      await api.delete(`/registrations/${registration.id}`);
      setChoiceOpen(false);
      setMsg('Inscription annulée.');
      await load();
    } catch (e) {
      const err = e;
      setMsg(err.response?.data?.message || "Impossible d'annuler l'inscription.");
    } finally {
      setCancelling(false);
    }
  };

  const confirmPayment = async () => {
    if (!registration) return;
    setPaying(true);
    try {
      const { data } = await api.post(`/registrations/${registration.id}/pay`);
      setPayOpen(false);
      setRegistration(mergeRegistrationEvent(data));
      setMsg('Paiement confirmé.');
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Le paiement a échoué.', { cause: error });
    } finally {
      setPaying(false);
    }
  };

  const submitFeedback = async (e) => {
    e.preventDefault();
    setFeedbackMsg('');
    setFeedbackError(false);
    try {
      const { data } = await api.post(`/events/${id}/feedback`, {
        rating,
        comment: comment.trim() || null,
      });
      setFeedbackMsg(data.message ?? 'Votre avis a bien été envoyé.');
      setFeedbackError(false);
      setComment('');
    } catch (err) {
      const error = err;
      setFeedbackMsg(
        error.response?.data?.message ||
          "Impossible d'envoyer le commentaire. Réessayez plus tard.",
      );
      setFeedbackError(true);
    }
  };

  if (!event) {
    return <p className="text-stone-500">Chargement…</p>;
  }

  const subtitleParts = [event.location, event.room].filter(Boolean);
  const fromRegistrations = Boolean(registration);
  const backTo =
    location.state?.from ??
    (fromRegistrations ? '/my-registrations' : eventDetailBackPath(user?.role));
  const backLabel = fromRegistrations
    ? 'Retour aux inscriptions'
    : 'Retour';

  const showManageLink =
    event.status !== 'published' &&
    (hasRole('admin') || (hasRole('organizer') && eventManagePath(event, user) !== '#'));

  return (
    <div>
      <Link
        to={backTo}
        className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 transition hover:text-brand-800"
      >
        <ArrowLeft className="h-4 w-4" />
        {backLabel}
      </Link>

      <PageHeader
        title={event.title}
        subtitle={subtitleParts.length > 0 ? subtitleParts.join(' · ') : undefined}
      />

      {event.image_url && (
        <EventImage
          src={event.image_url}
          alt={event.title}
          className="mb-6 aspect-[21/9] max-h-72 w-full rounded-2xl border border-white/50 object-cover shadow-lg max-md:aspect-[16/9] max-md:max-h-48"
        />
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="space-y-6 lg:col-span-2 max-md:order-2">
          <div>
            <p className="text-stone-600">{event.description}</p>
          </div>

          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-stone-500">Lieu</dt>
              <dd className="font-medium text-stone-800">{event.location || '—'}</dd>
            </div>
            <div>
              <dt className="text-stone-500">Salle</dt>
              <dd className="font-medium text-stone-800">{event.room || '—'}</dd>
            </div>
            <div>
              <dt className="text-stone-500">Début</dt>
              <dd>{formatDate(event.start_at)}</dd>
            </div>
            <div>
              <dt className="text-stone-500">Fin</dt>
              <dd>{formatDate(event.end_at)}</dd>
            </div>
            <div>
              <dt className="text-stone-500">Prix du billet</dt>
              <dd>{formatPrice(event.ticket_price)}</dd>
            </div>
            <div>
              <dt className="text-stone-500">Places</dt>
              <dd>
                {event.registered_count} / {event.capacity}
              </dd>
            </div>
          </dl>

          {event.tasks?.length > 0 && (
            <section className="space-y-2 border-t border-stone-300/50 pt-5">
              <h2 className="font-display text-xl font-medium text-stone-900">Nos services</h2>
              <ChipList items={event.tasks} />
            </section>
          )}

          {event.activities?.length > 0 && (
            <section className="space-y-2 border-t border-stone-300/50 pt-5">
              <h2 className="font-display text-xl font-medium text-stone-900">Activités</h2>
              <ChipList items={event.activities} />
            </section>
          )}

          {event.status === 'published' && <EventFeedbacksSection eventId={id} />}
        </Card>

        <Card className="space-y-4 max-md:order-1">
          {isStaff && (
            <div className="space-y-3">
              <p className="text-sm font-medium text-stone-700">Statut</p>
              <Badge
                tone={
                  event.status === 'published'
                    ? 'success'
                    : event.status === 'pending_publication' || event.status === 'draft'
                      ? 'warning'
                      : 'default'
                }
              >
                {formatEventStatus(event.status)}
              </Badge>
              {event.organizer?.name && (
                <p className="text-sm text-stone-600">
                  Organisateur : <span className="font-medium">{event.organizer.name}</span>
                </p>
              )}
              {showManageLink && (
                <Link
                  to={eventManagePath(event, user)}
                  className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/50 bg-white/30 px-5 py-3 text-lg font-medium tracking-wide text-stone-800 backdrop-blur-md transition-all duration-300 hover:border-brand-400/50 hover:bg-white/50"
                >
                  <Settings className="h-4 w-4" />
                  Gérer l&apos;événement
                </Link>
              )}
            </div>
          )}

          {isParticipant && registration && (
            <div className="space-y-3">
              <p className="text-sm font-medium text-stone-700">Votre inscription</p>
              <Badge tone={registration.payment_status === 'paid' ? 'success' : 'warning'}>
                {formatPaymentStatus(registration.payment_status)}
              </Badge>
              <p className="text-sm text-stone-600">
                Montant : {formatPrice(registration.amount ?? event.ticket_price)}
              </p>
              {registration.payment_status !== 'paid' && (
                <>
                  <Button className="w-full" onClick={() => setPayOpen(true)}>
                    <CreditCard className="h-4 w-4" /> Payer
                  </Button>
                  <Button
                    className="w-full"
                    variant="danger"
                    onClick={() => setChoiceOpen(true)}
                  >
                    <X className="h-4 w-4" /> Annuler l&apos;inscription
                  </Button>
                </>
              )}
              {registration.payment_status === 'paid' && (
                <Button className="w-full" variant="secondary" onClick={() => setTicketOpen(true)}>
                  <Ticket className="h-4 w-4" /> Billet
                </Button>
              )}
            </div>
          )}

          {isParticipant && !registration && event.status === 'published' && (
            <Button className="w-full" onClick={register}>
              S&apos;inscrire
            </Button>
          )}

          {msg && <p className="text-sm text-brand-700">{msg}</p>}

          {isParticipant && registration?.payment_status === 'paid' && (
            <form
              onSubmit={submitFeedback}
              className="space-y-3 border-t border-stone-300/50 pt-4"
            >
              <p className="text-sm font-medium">Laisser un avis</p>
              <label className="block text-sm">
                Note
                <input
                  type="range"
                  min={1}
                  max={5}
                  value={rating}
                  onChange={(e) => setRating(Number(e.target.value))}
                  className="w-full"
                />
                <span className="text-brand-700">{rating}/5</span>
              </label>
              <Textarea
                label="Commentaire"
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                rows={3}
              />
              <Button type="submit" variant="secondary" className="w-full">
                Envoyer
              </Button>
              {feedbackMsg && (
                <p
                  className={`text-sm ${feedbackError ? 'text-red-700' : 'text-emerald-800'}`}
                  role="status"
                >
                  {feedbackMsg}
                </p>
              )}
            </form>
          )}
        </Card>
      </div>

      {isParticipant && (
        <>
      <RegistrationChoiceModal
        open={choiceOpen}
        registration={mergeRegistrationEvent(registration)}
            busy={cancelling}
            onClose={() => !cancelling && setChoiceOpen(false)}
            onPay={() => {
              setChoiceOpen(false);
              setPayOpen(true);
            }}
            onCancelRegistration={() => void confirmCancel()}
          />

      <PaymentCheckoutModal
        open={payOpen}
        registration={mergeRegistrationEvent(registration)}
            userEmail={user?.email}
            busy={paying}
            onClose={() => !paying && setPayOpen(false)}
            onConfirm={confirmPayment}
          />

          <EventTicketModal
            open={ticketOpen}
            registration={registration}
            participantName={user?.name ?? 'Participant'}
            onClose={() => setTicketOpen(false)}
          />
        </>
      )}
    </div>
  );
}
