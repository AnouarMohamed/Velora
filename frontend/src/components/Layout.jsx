import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import {
  CalendarDays,
  LayoutDashboard,
  LogOut,
  Menu,
  PlusCircle,
  Search,
  Ticket,
  Users,
  X,
} from 'lucide-react';
import { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { VeloraLogo } from './VeloraLogo';
import { NotificationBell } from './NotificationBell';
import { ConfirmDialog } from './ConfirmDialog';

const logoutBtnClass =
  'inline-flex shrink-0 items-center gap-2 rounded-lg border border-white/50 bg-white/40 px-3 py-2 text-sm font-medium text-stone-700 shadow-sm backdrop-blur-md transition hover:border-brand-400/45 hover:bg-white/55 hover:text-brand-800';

function defaultNavClass(isActive) {
  return `rounded-lg px-3 py-2 text-lg font-medium transition max-md:text-base ${
    isActive
      ? 'border border-brand-400/40 bg-white/90 text-brand-800 shadow-sm'
      : 'text-stone-600 hover:bg-white/70 hover:text-brand-700'
  }`;
}

function adminNavClass(isActive, iconOnly = false) {
  const size = iconOnly ? 'px-2.5 py-2' : 'px-2 py-1.5 text-xs font-medium sm:px-2.5 sm:text-[13px]';
  return `inline-flex shrink-0 items-center whitespace-nowrap rounded-lg border font-medium leading-none transition ${size} ${
    isActive
      ? 'border-brand-400/50 bg-white/95 text-brand-800 shadow-sm'
      : 'border-white/40 bg-white/40 text-stone-700 hover:border-brand-300/50 hover:bg-white/70 hover:text-brand-800'
  }`;
}

function NavItem({ to, end, admin, iconOnly, title, onClick, children }) {
  return (
    <NavLink
      to={to}
      end={end}
      title={title}
      aria-label={iconOnly ? title : undefined}
      onClick={onClick}
      className={({ isActive }) =>
        admin ? adminNavClass(isActive, iconOnly) : defaultNavClass(isActive)
      }
    >
      {children}
    </NavLink>
  );
}

export function Layout() {
  const { user, logout, hasRole } = useAuth();
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);
  const [logoutOpen, setLogoutOpen] = useState(false);

  const isAdminNav = user?.role === 'admin';

  const confirmLogout = async () => {
    setLogoutOpen(false);
    navigate('/', { replace: true });
    await logout();
  };

  const closeMenu = () => setMenuOpen(false);

  const adminLinks = (
    <>
      <NavItem to="/dashboard" admin onClick={closeMenu}>
        Dashboard
      </NavItem>
      <NavItem to="/admin/requests" admin onClick={closeMenu}>
        Demandes à valider
      </NavItem>
      <NavItem to="/admin/events" admin onClick={closeMenu}>
        Tous les événements
      </NavItem>
      <NavItem to="/admin/organizer-events" admin onClick={closeMenu}>
        Espace organisateur
      </NavItem>
      <NavItem to="/admin/my-events" admin onClick={closeMenu}>
        Mes événements
      </NavItem>
      <NavItem to="/admin/registrations" admin onClick={closeMenu}>
        Billets et inscriptions
      </NavItem>
      <NavItem to="/admin" end admin onClick={closeMenu}>
        Statistiques
      </NavItem>
      <NavItem to="/admin/users" admin iconOnly title="Utilisateurs" onClick={closeMenu}>
        <Users className="h-4 w-4 shrink-0" />
      </NavItem>
    </>
  );

  const adminMobileLinks = (
    <>
      <NavItem to="/dashboard" admin onClick={closeMenu}>
        Dashboard
      </NavItem>
      <NavItem to="/admin/requests" admin onClick={closeMenu}>
        Demandes à valider
      </NavItem>
      <NavItem to="/admin/events" admin onClick={closeMenu}>
        Tous les événements
      </NavItem>
      <NavItem to="/admin/organizer-events" admin onClick={closeMenu}>
        Espace organisateur
      </NavItem>
      <NavItem to="/admin/my-events" admin onClick={closeMenu}>
        Mes événements
      </NavItem>
      <NavItem to="/admin/registrations" admin onClick={closeMenu}>
        Billets et inscriptions
      </NavItem>
      <NavItem to="/admin" end admin onClick={closeMenu}>
        Statistiques
      </NavItem>
      <NavItem to="/admin/users" admin onClick={closeMenu}>
        <Users className="h-4 w-4 shrink-0" />
        Utilisateurs
      </NavItem>
    </>
  );

  const standardLinks = (
    <>
      <NavItem to="/dashboard" onClick={closeMenu}>
        <span className="flex items-center gap-2">
          <LayoutDashboard className="h-4 w-4" />
          Tableau de bord
        </span>
      </NavItem>

      {(hasRole('participant') || !user) && (
        <NavItem to="/events" onClick={closeMenu}>
          <span className="flex items-center gap-2">
            <Search className="h-4 w-4" />
            Événements
          </span>
        </NavItem>
      )}

      {hasRole('participant') && (
        <NavItem to="/my-registrations" onClick={closeMenu}>
          <span className="flex items-center gap-2">
            <CalendarDays className="h-4 w-4" />
            Mes inscriptions
          </span>
        </NavItem>
      )}

      {user?.role === 'organizer' && (
        <>
          <NavItem to="/organizer/events" onClick={closeMenu}>
            <span className="flex items-center gap-2">
              <CalendarDays className="h-4 w-4" />
              Mes événements
            </span>
          </NavItem>
          <NavItem to="/organizer/events/new" onClick={closeMenu}>
            <span className="flex items-center gap-2">
              <PlusCircle className="h-4 w-4" />
              Créer
            </span>
          </NavItem>
          <NavItem to="/organizer/registrations" onClick={closeMenu}>
            <span className="flex items-center gap-2">
              <Ticket className="h-4 w-4" />
              Billets et inscriptions
            </span>
          </NavItem>
        </>
      )}

      {hasRole('client') && (
        <>
          <NavItem to="/client/request-event" onClick={closeMenu}>
            <span className="flex items-center gap-2">
              <PlusCircle className="h-4 w-4" />
              Demander un événement
            </span>
          </NavItem>
          <NavItem to="/client/stats" onClick={closeMenu}>
            Statistiques
          </NavItem>
        </>
      )}
    </>
  );

  const mobileLinks = isAdminNav ? adminMobileLinks : standardLinks;

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-50 border-b border-white/40 bg-white/50 shadow-velora backdrop-blur-xl">
        {isAdminNav ? (
          <div className="mx-auto flex min-h-16 w-full flex-wrap items-center gap-x-1 gap-y-1.5 px-3 py-2 sm:gap-x-2 sm:px-5">
            <div className="shrink-0">
              <VeloraLogo size="sm" showText={false} linkTo="/" />
            </div>

            <nav className="hidden flex flex-1 flex-wrap items-center justify-center gap-1 lg:flex">
              {adminLinks}
            </nav>

            <div className="ml-auto flex shrink-0 items-center gap-2 overflow-visible">
              <NotificationBell />
              <button
                type="button"
                onClick={() => setLogoutOpen(true)}
                className={`${logoutBtnClass} hidden sm:inline-flex`}
              >
                <LogOut className="h-4 w-4" />
                Déconnexion
              </button>
              <button
                type="button"
                className="rounded-lg border border-white/50 bg-white/40 p-2 text-stone-600 transition hover:bg-white/60 lg:hidden"
                onClick={() => setMenuOpen(!menuOpen)}
                aria-expanded={menuOpen}
                aria-label="Menu"
              >
                {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
              </button>
            </div>
          </div>
        ) : (
          <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 sm:py-4 max-md:gap-2 max-md:px-3 max-md:py-2.5">
            <div className="min-w-0 shrink">
              {!user ? (
                <VeloraLogo size="md" showText linkTo="/" />
              ) : (
                <>
                  <div className="md:hidden">
                    <VeloraLogo size="sm" showText={false} linkTo="/" />
                  </div>
                  <div className="hidden md:block">
                    <VeloraLogo size="md" showText linkTo="/" />
                  </div>
                </>
              )}
            </div>

            {user && (
              <nav className="hidden min-w-0 flex-1 items-center justify-center gap-1 md:flex">
                {standardLinks}
              </nav>
            )}

            <div className="flex shrink-0 items-center gap-2 overflow-visible sm:gap-3">
              {!user ? (
                <div className="flex flex-col gap-1.5 min-[400px]:flex-row min-[400px]:gap-2">
                  <NavLink to="/login" className="btn-glass whitespace-nowrap px-3 py-2 text-sm">
                    Connexion
                  </NavLink>
                  <NavLink to="/register" className="btn-glass btn-glass-accent whitespace-nowrap px-3 py-2 text-sm">
                    Inscription
                  </NavLink>
                </div>
              ) : (
                <>
                  <NotificationBell />
                  <button
                    type="button"
                    onClick={() => setLogoutOpen(true)}
                    className={`${logoutBtnClass} hidden md:inline-flex`}
                  >
                    <LogOut className="h-4 w-4" />
                    Déconnexion
                  </button>
                  <button
                    type="button"
                    className="rounded-lg p-2 text-stone-600 transition hover:bg-stone-100 md:hidden"
                    onClick={() => setMenuOpen(!menuOpen)}
                    aria-expanded={menuOpen}
                    aria-label="Menu"
                  >
                    {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                  </button>
                </>
              )}
            </div>
          </div>
        )}

        {menuOpen && user && (
          <nav
            className={`flex max-h-[70vh] flex-col gap-2 overflow-y-auto border-t border-white/40 bg-white/30 px-4 py-4 ${isAdminNav ? 'lg:hidden' : 'md:hidden'}`}
          >
            <div className={`flex flex-col gap-2 ${isAdminNav ? '' : 'gap-1'}`}>
              {mobileLinks}
            </div>
            <button
              type="button"
              onClick={() => {
                setMenuOpen(false);
                setLogoutOpen(true);
              }}
              className={`${logoutBtnClass} mt-2 w-full justify-center`}
            >
              <LogOut className="h-4 w-4" />
              Déconnexion
            </button>
          </nav>
        )}
      </header>

      <main className="mx-auto max-w-7xl px-4 py-8 max-md:px-3 max-md:py-5 sm:px-6">
        <Outlet />
      </main>

      <ConfirmDialog
        open={logoutOpen}
        title="Déconnexion"
        message="Êtes-vous sûr de vouloir vous déconnecter ?"
        confirmLabel="Se déconnecter"
        cancelLabel="Annuler"
        confirmVariant="primary"
        onConfirm={() => void confirmLogout()}
        onCancel={() => setLogoutOpen(false)}
      />
    </div>
  );
}
