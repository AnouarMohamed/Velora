import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { PageHeader } from '../../components/ui';
import { AdminEventCard } from './AdminEventCard';

function EventSection({
  title,
  empty,
  events,
  statusDisplay,
  organizers,
  onAssign,
  onDeleteClick,
  onManageCapacity,
  onApprovePublication,
}) {
  return (
    <section className="flex min-h-[280px] flex-col max-md:min-h-0">
      <h2 className="font-display mb-3 text-center text-xl font-medium text-stone-900">{title}</h2>
      {events.length === 0 ? (
        <p className="glass-panel flex flex-1 items-center justify-center rounded-xl border border-white/50 px-4 py-6 text-center text-sm text-stone-600">
          {empty}
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {events.map((ev) => (
            <AdminEventCard
              key={ev.id}
              ev={ev}
              statusDisplay={statusDisplay}
              organizers={organizers}
              onAssign={onAssign}
              onDeleteClick={onDeleteClick}
              onManageCapacity={onManageCapacity}
              onApprovePublication={onApprovePublication}
            />
          ))}
        </div>
      )}
    </section>
  );
}

export function AdminEventsPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [events, setEvents] = useState([]);
  const [organizers, setOrganizers] = useState([]);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const load = async () => {
    const [ev, org] = await Promise.all([api.get('/admin/events'), api.get('/admin/organizers')]);
    setEvents(ev.data.data);
    
    // Sort so current user is at the top and add (Moi) label
    const allOrgs = org.data
      .filter((o) => o.role === 'organizer' || o.role === 'admin')
      .map((o) => ({
        ...o,
        name: o.id === user?.id ? `${o.name} (Moi)` : o.name,
      }))
      .sort((a, b) => {
        if (a.id === user?.id) return -1;
        if (b.id === user?.id) return 1;
        return a.name.localeCompare(b.name);
      });

    setOrganizers(allOrgs);
  };

  useEffect(() => {
    if (user) load();
  }, [user]);

  const { toAssign, assignedUpcoming, online } = useMemo(() => {
    const pending = events.filter((e) => !e.organizer_id);
    const withOrganizer = events.filter((e) => e.organizer_id);
    return {
      toAssign: pending,
      assignedUpcoming: withOrganizer.filter((e) => e.status !== 'published'),
      online: withOrganizer.filter((e) => e.status === 'published'),
    };
  }, [events]);

  const assign = async (eventId, organizerId) => {
    try {
      await api.patch(`/admin/events/${eventId}/assign-organizer`, { organizer_id: organizerId });
      if (organizerId === user?.id) {
        navigate('/admin/my-events', { replace: true, state: { refreshAt: Date.now() } });
      } else {
        await load();
      }
    } catch (error) {
      console.error('Assignment failed:', error);
      alert('L’assignation a échoué. Veuillez réessayer.');
    }
  };

  const manageCapacity = (ev) => {
    const isOrganizerSpace =
      ev.organizer?.role === 'organizer' || ev.creator?.role === 'organizer';
    navigate(
      isOrganizerSpace ? `/admin/organizer-events/${ev.id}` : `/admin/my-events/${ev.id}`,
    );
  };

  const approvePublication = async (ev) => {
    await api.post(`/admin/events/${ev.id}/approve-publication`);
    load();
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    await api.delete(`/admin/events/${deleteTarget.id}`);
    setDeleteTarget(null);
    load();
  };

  return (
    <div>
      <PageHeader title="Tous les événements" />
      <div className="grid grid-cols-1 gap-6 lg:items-start xl:grid-cols-3">
        <EventSection
          title="Événements à assigner"
          empty="Aucun événement en attente d'assignation."
          events={toAssign}
          statusDisplay="assign"
          organizers={organizers}
          onAssign={assign}
          onDeleteClick={setDeleteTarget}
        />
        <EventSection
          title="Événements déjà assignés"
          empty="Aucun événement assigné en préparation."
          events={assignedUpcoming}
          statusDisplay="upcoming"
          organizers={organizers}
          onAssign={assign}
          onDeleteClick={setDeleteTarget}
          onManageCapacity={manageCapacity}
          onApprovePublication={approvePublication}
        />
        <EventSection
          title="Événements en ligne"
          empty="Aucun événement publié pour le moment."
          events={online}
          statusDisplay="online"
          organizers={organizers}
          onAssign={assign}
        />
      </div>
      <ConfirmDialog
        open={deleteTarget !== null}
        title="Supprimer l'événement"
        message={
          deleteTarget
            ? `Êtes-vous sûr de vouloir supprimer « ${deleteTarget.title} » ? Cet événement a déjà été validé et cette action est irréversible.`
            : ''
        }
        confirmLabel="Supprimer"
        cancelLabel="Annuler"
        confirmVariant="danger"
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}
