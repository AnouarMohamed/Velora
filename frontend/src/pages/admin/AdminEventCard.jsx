import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Badge, Button } from '../../components/ui';
import { eventPublicDetailPath } from '../../lib/eventPaths';
import { formatDate, formatEventStatus } from '../../lib/format';
import { AdminShowcaseCard } from './AdminShowcaseCard';

const btnCompact = 'w-full px-3 py-2 text-sm';

function canShowManageLink(ev) {
  return ev.status === 'draft' || ev.status === 'pending_publication';
}

function getLinkedRequest(ev) {
  return ev.event_request ?? ev.eventRequest;
}

function statusBadge(statusDisplay, ev) {
  if (ev.status === 'pending_publication') {
    return (
      <Badge tone="warning" size="sm" className="w-fit">
        {formatEventStatus('pending_publication')}
      </Badge>
    );
  }
  if (statusDisplay === 'assign') {
    return (
      <Badge tone="warning" size="sm" className="w-fit">
        {formatEventStatus('draft')}
      </Badge>
    );
  }
  if (statusDisplay === 'online') {
    return (
      <Badge tone="success" size="sm" className="w-fit">
        Publié
      </Badge>
    );
  }
  if (statusDisplay === 'organizerCreated') {
    const tone =
      ev.status === 'published' ? 'success' : ev.status === 'draft' ? 'warning' : 'default';
    return (
      <Badge tone={tone} size="sm" className="w-fit">
        {formatEventStatus(ev.status)}
      </Badge>
    );
  }
  return (
    <Badge tone="warning" size="sm" className="w-fit">
      À venir
    </Badge>
  );
}

export function AdminEventCard({
  ev,
  statusDisplay,
  organizers = [],
  onAssign,
  onAssignToMe,
  onDeleteClick,
  onManageCapacity,
  onApprovePublication,
}) {
  const [selectedOrganizerId, setSelectedOrganizerId] = useState('');
  const [isAssigning, setIsAssigning] = useState(false);
  const request = getLinkedRequest(ev);
  const metaParts = [ev.location, formatDate(ev.start_at)].filter(Boolean);
  const showManage = Boolean(onManageCapacity && canShowManageLink(ev));
  const showDelete = statusDisplay !== 'online' && onDeleteClick;

  let footer = null;

  if (statusDisplay === 'assign') {
    footer = (
      <div className="space-y-2">
        <select
          className="select-field glass-panel w-full px-3 py-2 text-sm text-stone-800"
          value={selectedOrganizerId}
          onChange={(e) => setSelectedOrganizerId(e.target.value)}
        >
          <option value="" disabled>
            Choisir un organisateur ou administrateur
          </option>
          {organizers.map((o) => (
            <option key={o.id} value={o.id}>
              {o.name} {o.role === 'admin' ? '(Admin)' : ''}
            </option>
          ))}
        </select>
        <div className="grid grid-cols-2 gap-2">
          <Button
            variant="primary"
            className={btnCompact}
            disabled={!selectedOrganizerId || isAssigning}
            onClick={async () => {
              setIsAssigning(true);
              try {
                await onAssign(ev.id, selectedOrganizerId);
              } finally {
                setIsAssigning(false);
              }
            }}
          >
            {isAssigning ? 'Confirmation...' : 'Confirmer'}
          </Button>
          {showDelete && (
            <Button
              variant="danger"
              className={btnCompact}
              onClick={() => onDeleteClick(ev)}
            >
              Supprimer
            </Button>
          )}
        </div>
      </div>
    );
  } else if (statusDisplay === 'online') {
    footer = (
      <Link
        to={eventPublicDetailPath(ev.id)}
        state={{ from: '/admin/events' }}
        className="block text-sm font-medium text-brand-800 hover:text-brand-900"
      >
        Voir l&apos;événement →
      </Link>
    );
  } else if (showManage || showDelete || (ev.status === 'pending_publication' && onApprovePublication)) {
    footer = (
      <div className="space-y-2">
        {ev.status === 'pending_publication' && onApprovePublication && (
          <Button className="w-full px-3 py-2 text-sm" onClick={() => onApprovePublication(ev)}>
            Approuver la publication
          </Button>
        )}
        {showManage && (
          <button
            type="button"
            className="w-full text-left text-sm font-medium text-brand-800 hover:text-brand-900"
            onClick={() => onManageCapacity(ev)}
          >
            Gérer capacité, tâches et activités →
          </button>
        )}
        {showDelete && (
          <Button
            variant="danger"
            className="w-full px-3 py-2 text-sm"
            onClick={() => onDeleteClick(ev)}
          >
            Supprimer
          </Button>
        )}
      </div>
    );
  }

  return (
    <AdminShowcaseCard
      imageUrl={ev.image_url}
      imageAlt={ev.title}
      badge={statusBadge(statusDisplay, ev)}
      title={ev.title}
      description={ev.description}
      clientName={request?.contact_name}
      clientEmail={request?.contact_email}
      metaLine={metaParts.length > 0 ? metaParts.join(' · ') : null}
      organizerName={
        statusDisplay === 'organizerCreated'
          ? ev.creator?.name
          : statusDisplay !== 'assign'
            ? ev.organizer?.name
            : undefined
      }
      ticketPrice={ev.ticket_price}
      footer={footer}
    />
  );
}
