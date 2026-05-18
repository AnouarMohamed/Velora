import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Card, PageHeader } from '../components/ui';

export function DashboardPage() {
  const { user, hasRole } = useAuth();

  const links = [
    { to: '/events', label: 'Parcourir les événements', show: hasRole('participant') },
    { to: '/my-registrations', label: 'Mes inscriptions', show: hasRole('participant') },
    { to: '/admin/requests', label: 'Demandes à valider', show: hasRole('admin') },
    { to: '/admin/events', label: 'Tous les événements', show: hasRole('admin') },
    { to: '/admin/organizer-events', label: "Gérer l'espace organisateur", show: hasRole('admin') },
    { to: '/admin/my-events', label: 'Gérer mes événements', show: hasRole('admin') },
    { to: '/admin/registrations', label: 'Billets et inscriptions', show: hasRole('admin') },
    { to: '/admin', label: 'Statistiques', show: hasRole('admin') },
    { to: '/admin/users', label: 'Utilisateurs', show: hasRole('admin') },
    { to: '/organizer/events', label: 'Gérer mes événements', show: user?.role === 'organizer' },
    {
      to: '/organizer/registrations',
      label: 'Billets et inscriptions',
      show: user?.role === 'organizer',
    },
    { to: '/client/request-event', label: 'Demander un événement', show: hasRole('client') },
    { to: '/client/stats', label: 'Mes statistiques', show: hasRole('client') },
  ].filter((l) => l.show);

  return (
    <div>
      <PageHeader
        title={`Bonjour, ${user?.name ?? ''}`}
        subtitle="Bienvenue sur votre espace VELORA"
      />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {links.map((l) => (
          <Link key={l.to} to={l.to}>
            <Card className="glass-panel-hover transition duration-300">
              <p className="font-medium">{l.label}</p>
              <p className="mt-1 text-sm text-brand-700">Ouvrir →</p>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
