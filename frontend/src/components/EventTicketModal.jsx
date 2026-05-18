import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Download, X } from 'lucide-react';
import { EventTicketView } from './EventTicketView';
import { downloadTicketHtml } from '../lib/ticketDownload';

export function EventTicketModal({ open, registration, participantName, onClose }) {
  const ticketRef = useRef(null);

  useEffect(() => {
    if (!open) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKeyDown = (e) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener('keydown', onKeyDown);
    };
  }, [open, onClose]);

  if (!open || !registration) return null;

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) onClose();
  };

  const handleDownload = () => {
    downloadTicketHtml(registration, participantName);
  };

  return createPortal(
    <div
      className="fixed inset-0 z-[9999] overflow-y-auto overscroll-y-contain p-4 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="ticket-modal-title"
      onClick={handleBackdropClick}
    >
      <p id="ticket-modal-title" className="sr-only">
        Votre billet — {registration.event?.title}
      </p>

      <div
        className="mx-auto flex min-h-[min(100%,100dvh)] w-full max-w-md flex-col items-stretch justify-center gap-3 py-2"
        onClick={(e) => e.stopPropagation()}
      >
        <EventTicketView
          compact
          ticketRef={ticketRef}
          registration={registration}
          participantName={participantName}
        />

        <button
          type="button"
          onClick={handleDownload}
          className="btn-glass btn-glass-accent flex shrink-0 items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-base font-semibold tracking-wide shadow-velora-lg transition hover:scale-[1.01]"
        >
          <Download className="h-5 w-5" />
          Télécharger le billet
        </button>

        <button
          type="button"
          onClick={onClose}
          className="flex shrink-0 items-center justify-center gap-1 py-1.5 text-sm font-medium text-stone-500 transition hover:text-stone-800"
        >
          <X className="h-4 w-4" />
          Fermer
        </button>
      </div>
    </div>,
    document.body,
  );
}
