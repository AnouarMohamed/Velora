import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft, CreditCard, X } from 'lucide-react';
import { formatDate, formatPrice } from '../lib/format';
import { VeloraLogo } from './VeloraLogo';
import { Button } from './ui';

export function RegistrationChoiceModal({
  open,
  registration,
  busy = false,
  onClose,
  onPay,
  onCancelRegistration,
}) {
  useEffect(() => {
    if (!open) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKeyDown = (e) => {
      if (e.key === 'Escape' && !busy) onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener('keydown', onKeyDown);
    };
  }, [open, busy, onClose]);

  if (!open || !registration) return null;

  const eventTitle = registration.event?.title ?? 'Événement';
  const eventDate = registration.event?.start_at
    ? formatDate(registration.event.start_at)
    : null;

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="registration-choice-title"
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      <button
        type="button"
        className="absolute inset-0 bg-stone-900/20 backdrop-blur-[3px]"
        aria-label="Fermer"
        onClick={busy ? undefined : onClose}
        disabled={busy}
      />

      <div
        className="glass-panel relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-white/55 shadow-velora-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="border-b border-white/40 bg-gradient-to-br from-brand-50/80 via-white/50 to-white/30 px-6 py-5">
          <div className="flex items-start gap-3">
            <div className="shrink-0 rounded-xl border border-brand-400/35 bg-white/70 p-1 shadow-sm">
              <VeloraLogo size="sm" showText={false} linkTo={false} />
            </div>
            <div className="min-w-0">
              <h2
                id="registration-choice-title"
                className="font-display text-xl font-medium tracking-wide text-stone-900"
              >
                Que souhaitez-vous faire ?
              </h2>
              <p className="mt-1 text-sm leading-relaxed text-stone-600">
                Votre inscription est en attente de paiement.
              </p>
            </div>
          </div>
        </div>

        <div className="space-y-4 px-6 py-5">
          <div className="rounded-xl border border-white/50 bg-white/40 px-4 py-3">
            <p className="font-medium text-stone-900">{eventTitle}</p>
            {eventDate && <p className="mt-0.5 text-sm text-stone-600">{eventDate}</p>}
            <p className="mt-2 text-sm text-stone-700">
              Montant :{' '}
              <span className="font-semibold text-brand-800">
                {formatPrice(registration.amount)}
              </span>
            </p>
          </div>

          <p className="text-center text-sm leading-relaxed text-stone-600">
            Vous pouvez finaliser le paiement, annuler votre inscription pour libérer votre place,
            ou revenir sans rien changer.
          </p>

          <div className="flex flex-col gap-2.5">
            <Button
              type="button"
              className="w-full justify-center py-3 text-base"
              disabled={busy}
              onClick={onPay}
            >
              <CreditCard className="h-4 w-4" />
              Passer au paiement
            </Button>

            <Button
              type="button"
              variant="danger"
              className="w-full justify-center py-3 text-base"
              disabled={busy}
              onClick={onCancelRegistration}
            >
              <X className="h-4 w-4" />
              {busy ? 'Annulation…' : "Annuler l'inscription"}
            </Button>

            <Button
              type="button"
              variant="ghost"
              className="w-full justify-center py-2.5 text-base text-stone-700"
              disabled={busy}
              onClick={onClose}
            >
              <ArrowLeft className="h-4 w-4" />
              Retour
            </Button>
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
}
