import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bell, CheckCheck } from 'lucide-react';
import api from '../lib/api';
import { useAuth } from '../context/AuthContext';

function formatRelativeTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const diff = Date.now() - d.getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "À l'instant";
  if (mins < 60) return `Il y a ${mins} min`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `Il y a ${hours} h`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `Il y a ${days} j`;
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

export function NotificationBell() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState([]);
  const [unread, setUnread] = useState(0);
  const [loading, setLoading] = useState(false);
  const panelRef = useRef(null);

  const loadUnread = useCallback(async () => {
    if (!user) {
      setUnread(0);
      return;
    }
    try {
      const { data } = await api.get('/notifications/unread-count');
      const count = Number(data?.count ?? data?.unread_count ?? 0);
      setUnread(Number.isFinite(count) ? count : 0);
    } catch {
      /* ignore */
    }
  }, [user]);

  const loadList = useCallback(async () => {
    if (!user) return;
    setLoading(true);
    try {
      const { data } = await api.get('/notifications', { params: { per_page: 15 } });
      setItems(data.data ?? []);
      const unreadFromList = (data.data ?? []).filter((n) => !n.read_at).length;
      if (typeof data.unread_count === 'number') {
        setUnread(data.unread_count);
      } else {
        setUnread(unreadFromList);
      }
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    void loadUnread();
    const intervalId = window.setInterval(() => void loadUnread(), 20000);
    const onFocus = () => void loadUnread();
    window.addEventListener('focus', onFocus);
    return () => {
      window.clearInterval(intervalId);
      window.removeEventListener('focus', onFocus);
    };
  }, [loadUnread]);

  useEffect(() => {
    if (open) void loadList();
  }, [open, loadList]);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e) => {
      if (panelRef.current && !panelRef.current.contains(e.target)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);

  const markRead = async (n) => {
    if (!n.read_at) {
      try {
        await api.post(`/notifications/${n.id}/read`);
        setUnread((c) => Math.max(0, c - 1));
        setItems((list) =>
          list.map((item) =>
            item.id === n.id ? { ...item, read_at: new Date().toISOString() } : item,
          ),
        );
      } catch {
        /* ignore */
      }
    }
    const link = n.data?.link;
    setOpen(false);
    if (link) navigate(link);
  };

  const markAllRead = async () => {
    try {
      await api.post('/notifications/read-all');
      setUnread(0);
      setItems((list) =>
        list.map((item) => ({ ...item, read_at: item.read_at ?? new Date().toISOString() })),
      );
    } catch {
      /* ignore */
    }
  };

  const hasUnread = unread > 0;
  const ariaLabel = hasUnread
    ? `Notifications (${unread} non lue${unread > 1 ? 's' : ''})`
    : 'Notifications';

  return (
    <div className="relative overflow-visible" ref={panelRef}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={`relative inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm backdrop-blur-md transition ${
          hasUnread
            ? 'border-red-400/80 bg-red-50/90 text-red-700 hover:border-red-500 hover:bg-red-100/90 hover:text-red-800'
            : 'border-white/50 bg-white/40 text-stone-700 hover:border-brand-400/45 hover:bg-white/60 hover:text-brand-800'
        }`}
        aria-label={ariaLabel}
        aria-expanded={open}
      >
        <Bell className={`h-5 w-5 ${hasUnread ? 'fill-red-100' : ''}`} />
        {hasUnread && (
          <>
            <span
              className="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full bg-red-600 motion-safe:animate-ping"
              aria-hidden
            />
            <span className="absolute -right-1 -top-1 z-[1] flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white shadow-md ring-2 ring-white">
              {unread > 9 ? '9+' : unread}
            </span>
          </>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full z-[60] mt-2 w-96 overflow-hidden rounded-2xl border border-white/55 bg-white/95 shadow-velora-lg backdrop-blur-xl max-md:fixed max-md:left-3 max-md:right-3 max-md:top-16 max-md:mt-0 max-md:w-auto">
          <div className="flex items-center justify-between border-b border-stone-200/60 px-4 py-3">
            <p className="font-display text-lg font-medium text-stone-900">
              Notifications
              {hasUnread && (
                <span className="ml-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-xs font-bold text-white">
                  {unread > 9 ? '9+' : unread}
                </span>
              )}
            </p>
            {hasUnread && (
              <button
                type="button"
                onClick={() => void markAllRead()}
                className="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-900"
              >
                <CheckCheck className="h-4 w-4" />
                Tout lire
              </button>
            )}
          </div>

          <div className="max-h-[min(70vh,24rem)] overflow-y-auto">
            {loading ? (
              <p className="px-4 py-6 text-center text-sm text-stone-500">Chargement…</p>
            ) : items.length === 0 ? (
              <p className="px-4 py-6 text-center text-sm text-stone-500">Aucune notification.</p>
            ) : (
              <ul className="divide-y divide-stone-200/50">
                {items.map((n) => (
                  <li key={n.id}>
                    <button
                      type="button"
                      onClick={() => void markRead(n)}
                      className={`w-full px-4 py-3 text-left transition hover:bg-brand-50/50 ${
                        !n.read_at ? 'border-l-4 border-red-500 bg-red-50/70' : ''
                      }`}
                    >
                      <div className="flex items-start gap-2">
                        {!n.read_at && (
                          <span
                            className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-red-600"
                            aria-hidden
                          />
                        )}
                        <div className="min-w-0 flex-1">
                          <p
                            className={`text-sm font-semibold ${!n.read_at ? 'text-red-900' : 'text-stone-900'}`}
                          >
                            {n.title}
                          </p>
                          <p
                            className={`mt-0.5 text-sm leading-snug ${!n.read_at ? 'text-red-800/90' : 'text-stone-600'}`}
                          >
                            {n.message}
                          </p>
                          <p className="mt-1 text-xs text-stone-400">
                            {formatRelativeTime(n.created_at)}
                          </p>
                        </div>
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
