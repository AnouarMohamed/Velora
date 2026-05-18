import { useCallback, useEffect, useState } from 'react';
import { Star, Trash2 } from 'lucide-react';
import api from '../lib/api';
import { useAuth } from '../context/AuthContext';
import { formatDate } from '../lib/format';
import { Badge, Button } from './ui';

function StarRating({ value, size = 'sm' }) {
  const sizeClass = size === 'lg' ? 'h-5 w-5' : 'h-4 w-4';
  return (
    <div className="flex items-center gap-0.5" aria-label={`Note ${value} sur 5`}>
      {[1, 2, 3, 4, 5].map((n) => (
        <Star
          key={n}
          className={`${sizeClass} ${n <= value ? 'fill-amber-400 text-amber-400' : 'text-stone-300'}`}
        />
      ))}
    </div>
  );
}

function FeedbackCard({ item, children }) {
  return (
    <article className="rounded-xl border border-white/50 bg-white/40 px-4 py-3 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="font-medium text-stone-900">{item.user?.name ?? 'Participant'}</p>
          <p className="text-xs text-stone-500">{formatDate(item.created_at)}</p>
        </div>
        <StarRating value={item.rating} />
      </div>
      {item.comment && (
        <p className="mt-2 text-sm leading-relaxed text-stone-700">{item.comment}</p>
      )}
      {children}
    </article>
  );
}

export function EventFeedbacksSection({ eventId, className = '' }) {
  const { user } = useAuth();
  const isAdmin = user?.role === 'admin';
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState(null);

  const load = useCallback(async () => {
    if (!eventId) return;
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get(`/events/${eventId}/feedbacks`);
      setItems(data.data ?? []);
    } catch {
      setError('Impossible de charger les avis.');
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [eventId]);

  useEffect(() => {
    void load();
  }, [load]);

  const approve = async (id) => {
    setBusyId(id);
    try {
      await api.post(`/admin/feedbacks/${id}/approve`);
      await load();
    } finally {
      setBusyId(null);
    }
  };

  const remove = async (id) => {
    setBusyId(id);
    try {
      await api.delete(`/admin/feedbacks/${id}`);
      await load();
    } finally {
      setBusyId(null);
    }
  };

  const approved = items.filter((f) => f.status === 'approved');
  const pending = items.filter((f) => f.status === 'pending');

  return (
    <section className={`space-y-4 border-t border-stone-300/50 pt-5 ${className}`}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="font-display text-xl font-medium text-stone-900">Avis des participants</h2>
        {isAdmin && pending.length > 0 && (
          <Badge tone="warning" size="sm">
            {pending.length} en attente de validation
          </Badge>
        )}
      </div>

      {loading && <p className="text-sm text-stone-500">Chargement des avis…</p>}
      {error && <p className="text-sm text-red-600">{error}</p>}

      {!loading && !error && isAdmin && pending.length > 0 && (
        <div className="space-y-3">
          <p className="text-sm font-medium text-stone-600">À valider</p>
          {pending.map((item) => (
            <FeedbackCard key={item.id} item={item}>
              <div className="mt-3 flex flex-wrap gap-2">
                <Button
                  type="button"
                  className="px-3 py-1.5 text-sm"
                  disabled={busyId === item.id}
                  onClick={() => void approve(item.id)}
                >
                  Publier l&apos;avis
                </Button>
                <Button
                  type="button"
                  variant="danger"
                  className="px-3 py-1.5 text-sm"
                  disabled={busyId === item.id}
                  onClick={() => void remove(item.id)}
                >
                  <Trash2 className="h-4 w-4" /> Supprimer
                </Button>
              </div>
            </FeedbackCard>
          ))}
        </div>
      )}

      {!loading && !error && approved.length > 0 && (
        <div className="space-y-3">
          {isAdmin && pending.length > 0 && (
            <p className="text-sm font-medium text-stone-600">Publiés</p>
          )}
          <div className="space-y-3">
            {approved.map((item) => (
              <FeedbackCard key={item.id} item={item}>
                {isAdmin && (
                  <div className="mt-3">
                    <Button
                      type="button"
                      variant="danger"
                      className="px-3 py-1.5 text-sm"
                      disabled={busyId === item.id}
                      onClick={() => void remove(item.id)}
                    >
                      <Trash2 className="h-4 w-4" /> Supprimer
                    </Button>
                  </div>
                )}
              </FeedbackCard>
            ))}
          </div>
        </div>
      )}

      {!loading && !error && approved.length === 0 && pending.length === 0 && (
        <p className="rounded-xl border border-dashed border-stone-300/60 bg-white/25 px-4 py-6 text-center text-sm text-stone-500">
          Aucun avis publié pour le moment.
        </p>
      )}
    </section>
  );
}
