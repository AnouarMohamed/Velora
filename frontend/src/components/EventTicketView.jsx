import { EventImage } from './EventImage';
import { VeloraLogo } from './VeloraLogo';
import { getEventImageUrl } from '../lib/eventImage';
import { formatDate, formatPrice } from '../lib/format';

const EM_DASH = '\u2014';

function formatTicketStubDate(iso) {
  if (!iso) return EM_DASH;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return EM_DASH;
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function EventTicketView({ registration, participantName, ticketRef, compact = false }) {
  const ev = registration?.event;
  if (!ev) return null;

  const imageUrl = getEventImageUrl(ev);
  const hasImage = Boolean(imageUrl);

  const pad = compact ? 'p-3 sm:p-4' : 'p-5 sm:p-6';
  const titleClass = compact
    ? 'font-display text-lg font-bold leading-snug text-stone-950 sm:text-xl'
    : 'font-display text-2xl font-bold leading-tight text-stone-950 sm:text-3xl';
  const descClass = compact
    ? 'mt-1 line-clamp-3 text-sm leading-snug text-stone-600'
    : 'mt-2 text-base leading-relaxed text-stone-600';
  const imageClass = compact
    ? 'h-24 w-full shrink-0 object-cover sm:h-28'
    : 'aspect-[21/9] w-full shrink-0 object-cover';
  const descClassWithImage =
    compact && hasImage
      ? 'mt-1 line-clamp-2 text-sm leading-snug text-stone-600'
      : descClass;
  const headerPad = compact ? 'px-3 py-2.5 sm:px-4' : 'px-5 py-4';
  const gridGap = compact ? 'gap-2' : 'gap-3';
  const cellPad = compact ? 'px-2.5 py-2' : 'px-3 py-2.5';
  const stubWidth = compact ? 'w-12 sm:w-14' : 'w-14 sm:w-16';

  const stubId = registration.ticket_code
    ? String(registration.ticket_code).slice(0, 8).toUpperCase()
    : String(registration.id).padStart(6, '0');

  return (
    <article
      ref={ticketRef}
      className="overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-stone-200"
    >
      <div className="flex min-h-0">
        <aside
          className={`relative flex shrink-0 flex-col items-center justify-between border-r border-dashed border-stone-300 bg-gradient-to-b from-brand-100 via-brand-50/80 to-brand-100 py-4 ${stubWidth}`}
          aria-label="Coupon du billet"
        >
          <div
            className="pointer-events-none absolute -right-2 top-[18%] h-3.5 w-3.5 rounded-full bg-white ring-1 ring-stone-200"
            aria-hidden
          />
          <div
            className="pointer-events-none absolute -right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 rounded-full bg-white ring-1 ring-stone-200"
            aria-hidden
          />
          <div
            className="pointer-events-none absolute -right-2 bottom-[18%] h-3.5 w-3.5 rounded-full bg-white ring-1 ring-stone-200"
            aria-hidden
          />

          <span
            className="mt-1 text-[9px] font-bold uppercase tracking-[0.22em] text-brand-800"
            style={{ writingMode: 'vertical-rl', transform: 'rotate(180deg)' }}
          >
            Velora
          </span>

          <div className="flex flex-1 flex-col items-center justify-center gap-2 py-3">
            <span
              className="text-center text-[10px] font-semibold leading-tight text-brand-900"
              style={{ writingMode: 'vertical-rl' }}
            >
              {formatTicketStubDate(ev.start_at)}
            </span>
            <span className="h-px w-6 rotate-90 bg-stone-300" aria-hidden />
            <span
              className="text-[9px] font-medium uppercase tracking-widest text-stone-500"
              style={{ writingMode: 'vertical-rl' }}
            >
              Entr{'\u00e9'}e
            </span>
          </div>

          <span
            className="mb-1 font-mono text-[9px] font-bold tracking-wider text-stone-600"
            style={{ writingMode: 'vertical-rl' }}
          >
            {stubId}
          </span>
        </aside>

        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
          <div
            className={`flex shrink-0 items-center justify-between gap-2 border-b border-stone-200 bg-gradient-to-r from-brand-50 to-white ${headerPad}`}
          >
            <VeloraLogo size="ticket" showText linkTo={false} />
            <span className="rounded-full border border-brand-300/60 bg-brand-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-brand-800 max-md:px-2 max-md:py-0.5 max-md:text-[9px] sm:px-3 sm:py-1 sm:text-xs">
              Billet officiel
            </span>
          </div>

          {hasImage && <EventImage src={imageUrl} alt={ev.title} className={imageClass} />}

          <div className={`min-h-0 space-y-3 ${pad}`}>
            <div>
              <h2 className={titleClass}>{ev.title}</h2>
              {ev.description && <p className={descClassWithImage}>{ev.description}</p>}
            </div>

            <dl className={`grid text-sm sm:grid-cols-2 ${gridGap}`}>
              <div className={`rounded-lg bg-stone-50 ${cellPad}`}>
                <dt className="text-[10px] font-semibold uppercase tracking-wide text-stone-500 sm:text-xs">
                  D{'\u00e9'}but
                </dt>
                <dd className="mt-0.5 text-xs font-medium text-stone-900 sm:text-sm">
                  {formatDate(ev.start_at)}
                </dd>
              </div>
              <div className={`rounded-lg bg-stone-50 ${cellPad}`}>
                <dt className="text-[10px] font-semibold uppercase tracking-wide text-stone-500 sm:text-xs">
                  Fin
                </dt>
                <dd className="mt-0.5 text-xs font-medium text-stone-900 sm:text-sm">
                  {formatDate(ev.end_at)}
                </dd>
              </div>
              <div className={`rounded-lg bg-stone-50 ${cellPad}`}>
                <dt className="text-[10px] font-semibold uppercase tracking-wide text-stone-500 sm:text-xs">
                  Lieu
                </dt>
                <dd className="mt-0.5 text-xs font-medium text-stone-900 sm:text-sm">
                  {ev.location || EM_DASH}
                </dd>
              </div>
              <div className={`rounded-lg bg-stone-50 ${cellPad}`}>
                <dt className="text-[10px] font-semibold uppercase tracking-wide text-stone-500 sm:text-xs">
                  Salle
                </dt>
                <dd className="mt-0.5 text-xs font-medium text-stone-900 sm:text-sm">
                  {ev.room || EM_DASH}
                </dd>
              </div>
            </dl>

            <div className="space-y-3 border-t border-dashed border-stone-300 pb-1 pt-3">
              <div className="grid grid-cols-2 gap-3">
                <div className="min-w-0">
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-stone-500 sm:text-xs">
                    Participant
                  </p>
                  <p className="mt-0.5 truncate text-sm font-medium text-stone-900">{participantName}</p>
                </div>
                <div className="min-w-0 text-right sm:text-left">
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-stone-500 sm:text-xs">
                    Prix pay{'\u00e9'}
                  </p>
                  <p className={`mt-0.5 font-bold text-brand-900 ${compact ? 'text-base' : 'text-lg'}`}>
                    {formatPrice(registration.amount ?? ev.ticket_price)}
                  </p>
                </div>
              </div>

              <div className="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5">
                <p className="text-[10px] font-semibold uppercase tracking-widest text-stone-500">
                  Code billet
                </p>
                <p className="mt-1 break-all font-mono text-[11px] font-semibold leading-snug text-stone-900 sm:text-xs">
                  {registration.ticket_code}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </article>
  );
}
