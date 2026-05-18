/** URL d'image événement (événement ou demande liée). */
export function getEventImageUrl(event) {
  if (!event) return null;
  if (event.image_url) return event.image_url;
  const request = event.event_request ?? event.eventRequest;
  if (request?.image_url) return request.image_url;
  return null;
}
