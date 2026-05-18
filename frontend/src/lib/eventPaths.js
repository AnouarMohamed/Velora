/** Page publique de consultation (même vue que le participant). */
export function eventPublicDetailPath(eventId) {
  return `/events/${eventId}`;
}

/** Fiche de gestion (capacité, tâches, publication). */
export function eventManagePath(ev, user) {
  if (!ev?.id || !user) return '#';

  if (user.role === 'admin') {
    const isOrganizerSpace =
      ev.organizer?.role === 'organizer' || ev.creator?.role === 'organizer';
    return isOrganizerSpace
      ? `/admin/organizer-events/${ev.id}`
      : `/admin/my-events/${ev.id}`;
  }

  return `/organizer/events/${ev.id}`;
}

/** Lien carte liste : publié → vue participant ; sinon → gestion. */
export function eventCardLinkPath(ev, user) {
  if (!ev?.id) return '#';
  if (ev.status === 'published') {
    return eventPublicDetailPath(ev.id);
  }
  return eventManagePath(ev, user);
}

export function eventDetailBackPath(role) {
  switch (role) {
    case 'client':
      return '/client/stats';
    case 'admin':
      return '/admin/events';
    case 'organizer':
      return '/organizer/events';
    case 'participant':
      return '/events';
    default:
      return '/dashboard';
  }
}
