import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { VeloraLogo } from './VeloraLogo';
import { Button } from './ui';

export function DeleteParticipantModal({
  open,
  registration,
  busy = false,
  onClose,
  onConfirm,
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

  const participantName = registration.user?.name ?? 'ce participant';
  const eventTitle = registration.event?.title ?? 'cet événement';

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-participant-title"
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
        <div className="border-b border-white/40 bg-gradient-to-br from-red-50/70 via-white/50 to-white/30 px-6 py-5">
          <div className="flex items-start gap-3">
            <div className="shrink-0 rounded-xl border border-red-200/60 bg-white/80 p-1 shadow-sm">
              <VeloraLogo size="sm" showText={false} linkTo={false} />
            </div>
            <div className="min-w-0">
              <h2
                id="delete-participant-title"
                className="font-display text-xl font-medium tracking-wide text-stone-900"
              >
                Supprimer l&apos;inscription ?
              </h2>
              <p className="mt-1 text-sm leading-relaxed text-stone-600">
                Cette action est irréversible.
              </p>
            </div>
          </div>
        </div>

        <div className="space-y-4 px-6 py-5">
          <p className="text-center text-base leading-relaxed text-stone-700">
            Êtes-vous sûr de vouloir supprimer{' '}
            <strong className="font-medium text-stone-900">{participantName}</strong>, participant
            inscrit à{' '}
            <strong className="font-medium text-stone-900">{eventTitle}</strong> ?
          </p>

          <p className="rounded-xl border border-amber-200/60 bg-amber-50/70 px-4 py-3 text-center text-sm text-amber-900">
            La place sera libérée. L&apos;inscription en attente de paiement sera définitivement
            retirée.
          </p>

          <div className="flex flex-col gap-2.5">
            <Button
              type="button"
              variant="danger"
              className="w-full justify-center py-3 text-base"
              disabled={busy}
              onClick={onConfirm}
            >
              <Trash2 className="h-4 w-4" />
              {busy ? 'Suppression…' : 'Oui, supprimer'}
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
