import { EventImage } from '../../components/EventImage';
import { PriceText } from '../../components/PriceText';

export function AdminShowcaseCard({
  imageUrl,
  imageAlt,
  badge,
  title,
  description,
  clientName,
  clientEmail,
  metaLine,
  organizerName,
  organizerNamePrefix = 'Organisateur : ',
  ticketPrice,
  footer,
}) {
  return (
    <article className="glass-panel flex h-full flex-col overflow-hidden rounded-xl border border-white/50 max-md:h-auto max-md:overflow-visible">
      {imageUrl && (
        <EventImage
          src={imageUrl}
          alt={imageAlt}
          className="aspect-[16/10] w-full shrink-0 object-cover max-md:max-h-44 max-md:min-h-0 max-md:w-full"
        />
      )}
      <div className="flex min-h-0 flex-1 flex-col gap-2 p-4 max-md:flex-none max-md:gap-2.5 max-md:pb-5">
        {badge}
        <h3 className="font-display text-2xl font-bold leading-snug text-stone-950 max-md:text-xl">
          {title}
        </h3>
        {description && (
          <p className="text-base leading-relaxed text-stone-700 max-md:text-sm">{description}</p>
        )}
        {clientName && (
          <p className="text-sm text-stone-600">
            <span className="font-medium text-stone-800">Client :</span> {clientName}
          </p>
        )}
        {clientEmail && <p className="break-all text-sm text-stone-600">{clientEmail}</p>}
        {metaLine && <p className="break-words text-sm text-stone-600">{metaLine}</p>}
        {organizerName && (
          <p className="break-words text-sm text-stone-500">
            {organizerNamePrefix}
            {organizerName}
          </p>
        )}
        <p className="text-lg font-bold text-stone-950 max-md:text-base">
          Billet : <PriceText value={ticketPrice ?? 0} size="md" />
        </p>
        {footer && (
          <div className="mt-auto shrink-0 border-t border-stone-400/50 pt-3 max-md:mt-3">
            {footer}
          </div>
        )}
      </div>
    </article>
  );
}
