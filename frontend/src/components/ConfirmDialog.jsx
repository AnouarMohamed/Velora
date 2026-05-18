import { useEffect } from 'react';
import { Button } from './ui';

export function ConfirmDialog({
  open,
  title,
  message,
  children,
  confirmLabel = 'Confirmer',
  cancelLabel = 'Annuler',
  confirmVariant = 'danger',
  onConfirm,
  onCancel,
  busy = false,
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e) => {
      if (e.key === 'Escape' && !busy) onCancel();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, busy, onCancel]);

  if (!open) return null;

  const body = children ?? (message ? <p>{message}</p> : null);

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center p-4 max-md:items-end max-md:p-3"
      role="dialog"
      aria-modal="true"
      aria-labelledby="confirm-dialog-title"
    >
      <button
        type="button"
        className="absolute inset-0 bg-stone-800/30 backdrop-blur-[2px]"
        aria-label="Fermer la boîte de dialogue"
        onClick={busy ? undefined : onCancel}
        disabled={busy}
      />
      <div
        className="glass-panel relative z-10 w-full max-w-lg rounded-2xl border border-white/50 p-6 shadow-velora-lg max-md:p-5 sm:p-8"
        onClick={(e) => e.stopPropagation()}
        onKeyDown={(e) => e.stopPropagation()}
      >
        <h2
          id="confirm-dialog-title"
          className="text-2xl font-medium tracking-wide text-stone-900 max-md:text-xl"
        >
          {title}
        </h2>
        {body && (
          <div className="mt-4 text-lg leading-relaxed text-stone-700 max-md:text-base">{body}</div>
        )}
        <div className="mt-8 flex flex-wrap justify-end gap-3 max-md:mt-6 max-md:flex-col-reverse max-md:gap-2">
          <Button type="button" variant="secondary" className="max-md:w-full" onClick={onCancel} disabled={busy}>
            {cancelLabel}
          </Button>
          <Button
            type="button"
            variant={confirmVariant}
            className="max-md:w-full"
            onClick={onConfirm}
            disabled={busy}
          >
            {busy ? 'Suppression…' : confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
