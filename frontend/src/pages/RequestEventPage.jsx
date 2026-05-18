import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import { datetimeLocalToApi } from '../lib/datetime';
import { compressImageForUpload, MAX_IMAGE_MB } from '../lib/compressImage';
import { fileToDataUrl } from '../lib/fileToDataUrl';
import { EventImage } from '../components/EventImage';
import { Button, Card, Input, PageHeader } from '../components/ui';
import { MobileSelect } from '../components/MobileSelect';
import { DateTimeField } from '../components/DateTimeField';
import { eventDescriptionSuggestions } from '../data/eventDescriptionSuggestions';
import { eventLocationSuggestions } from '../data/eventLocationSuggestions';
import { eventTitleSuggestions } from '../data/eventTitleSuggestions';

const MAX_IMAGE_BYTES = MAX_IMAGE_MB * 1024 * 1024;

const chipClass = (active) =>
  `rounded-full border px-3 py-1 text-sm font-medium transition ${
    active
      ? 'border-brand-500/60 bg-white/80 text-brand-800 shadow-sm'
      : 'border-white/50 bg-white/40 text-stone-700 hover:border-brand-400/50 hover:bg-white/65'
  }`;

const suggestionBoxClass =
  'glass-panel max-h-52 overflow-y-auto overflow-x-hidden rounded-xl border border-white/40 p-3';

const descriptionOptions = eventDescriptionSuggestions.map((s) => ({
  value: s,
  label: s,
}));

const locationOptions = eventLocationSuggestions.map((loc) => ({
  value: loc,
  label: loc,
}));

export function RequestEventPage() {
  const { user } = useAuth();
  const location = useLocation();
  const [form, setForm] = useState({
    title: '',
    description: '',
    preferred_start: '',
    preferred_end: '',
    location: '',
    ticket_price: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
  });

  useEffect(() => {
    if (user) {
      setForm((f) => ({
        ...f,
        contact_name: user.name,
        contact_email: user.email,
      }));
    }
  }, [user]);

  const [imageFile, setImageFile] = useState(null);
  const [imagePreview, setImagePreview] = useState(null);
  const [imageProcessing, setImageProcessing] = useState(false);
  const [done, setDone] = useState(false);
  const [submitted, setSubmitted] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [canSubmit, setCanSubmit] = useState(null);
  const [blockReason, setBlockReason] = useState(null);

  useEffect(() => {
    if (user?.role !== 'client') return;
    api
      .get('/client/stats')
      .then((r) => {
        setCanSubmit(r.data.can_submit_new_request !== false);
        setBlockReason(r.data.block_reason ?? null);
      })
      .catch(() => {
        setCanSubmit(true);
        setBlockReason(null);
      });
  }, [user, location.key]);

  const onImageChange = async (file) => {
    setError('');
    if (imagePreview) URL.revokeObjectURL(imagePreview);
    if (!file) {
      setImageFile(null);
      setImagePreview(null);
      return;
    }
    setImageProcessing(true);
    try {
      const processed =
        file.size <= MAX_IMAGE_BYTES ? file : await compressImageForUpload(file);
      setImageFile(processed);
      setImagePreview(URL.createObjectURL(processed));
    } catch {
      setImageFile(null);
      setImagePreview(null);
      setError(
        `Image trop volumineuse (max. ${MAX_IMAGE_MB} Mo). Choisissez une photo plus légère ou une image plus petite.`,
      );
    } finally {
      setImageProcessing(false);
    }
  };

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const payload = {
        title: form.title,
        description: form.description,
        preferred_start: datetimeLocalToApi(form.preferred_start) || undefined,
        preferred_end: datetimeLocalToApi(form.preferred_end) || undefined,
        location: form.location,
        ticket_price: Number(form.ticket_price) || 0,
        contact_name: form.contact_name,
        contact_email: form.contact_email,
        contact_phone: form.contact_phone || undefined,
      };
      if (imageFile) {
        payload.image_data = await fileToDataUrl(imageFile);
        payload.image_mime = imageFile.type || 'image/jpeg';
      }
      const { data } = await api.post('/event-requests', payload);
      setSubmitted(data);
      setDone(true);
    } catch (err) {
      const ax = err;
      const data = ax.response?.data;
      if (data?.errors) {
        const first = Object.values(data.errors).flat()[0];
        setError(first || 'Envoi impossible.');
      } else {
        setError(data?.message || 'Envoi impossible. Réessayez.');
      }
    } finally {
      setLoading(false);
    }
  };

  if (done && submitted) {
    return (
      <Card className="mx-auto max-w-lg overflow-hidden p-0 text-center">
        {submitted.image_url && (
          <EventImage
            src={submitted.image_url}
            alt={submitted.title}
            className="aspect-[16/10] w-full max-h-56 object-cover"
          />
        )}
        <div className="p-6">
          <h2 className="text-xl font-bold text-emerald-600">Demande envoyée</h2>
          <p className="mt-2 text-stone-600">{submitted.title}</p>
          <p className="mt-4 text-stone-500">
            Un administrateur examinera votre demande sous peu.
          </p>
          <Link to="/" className="mt-6 inline-block font-medium text-brand-700 hover:underline">
            Retour à l&apos;accueil
          </Link>
        </div>
      </Card>
    );
  }

  if (user?.role === 'client' && canSubmit === false) {
    const blockMessage =
      blockReason === 'pending'
        ? 'Vous avez une demande en attente. Supprimez-la depuis vos statistiques pour en envoyer une nouvelle.'
        : 'Votre événement est encore en cours. Attendez sa fin pour envoyer une nouvelle demande.';
    return (
      <div className="mx-auto max-w-3xl min-w-0">
        <PageHeader title="Demander un événement" subtitle="Une seule demande active à la fois" />
        <Card className="space-y-4 text-center">
          <p className="text-lg text-stone-700">{blockMessage}</p>
          <p className="text-base text-stone-600">
            Si votre demande a été refusée par l&apos;administrateur, vous pouvez en soumettre une
            nouvelle tout de suite.
          </p>
          <Link to="/client/stats" className="inline-block font-medium text-brand-700 hover:underline">
            Voir mes statistiques
          </Link>
        </Card>
      </div>
    );
  }

  if (user?.role === 'client' && canSubmit === null) {
    return <p className="text-lg text-stone-600">Chargement…</p>;
  }

  return (
    <div className="mx-auto max-w-3xl min-w-0">
      <PageHeader
        title="Demander un événement"
        subtitle="Décrivez votre projet — un administrateur examinera votre demande"
      />
      <Card className="max-md:overflow-hidden">
        <form onSubmit={submit} className="min-w-0 space-y-4">
          <label className="block min-w-0 space-y-1.5">
            <span className="text-base font-medium tracking-wide text-stone-700 sm:text-lg">
              Titre
            </span>
            <input
              list="event-title-suggestions"
              className="glass-panel w-full min-w-0 max-w-full px-3 py-2.5 text-base text-stone-900 outline-none placeholder:text-stone-500 focus:border-brand-500/50 sm:px-4 sm:py-3 sm:text-lg"
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
              placeholder="Choisissez ou saisissez un titre"
              required
            />
            <datalist id="event-title-suggestions">
              {eventTitleSuggestions.map((s) => (
                <option key={s} value={s} />
              ))}
            </datalist>
          </label>

          <div className="min-w-0">
            <p className="mb-2 text-base font-medium text-stone-700">Suggestions de titre</p>
            <div className={suggestionBoxClass}>
              <div className="flex flex-wrap gap-2">
                {eventTitleSuggestions.map((s) => (
                  <button
                    key={s}
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, title: s }))}
                    className={chipClass(form.title === s)}
                  >
                    {s}
                  </button>
                ))}
              </div>
            </div>
          </div>

          <div className="md:hidden">
            <MobileSelect
              label="Description"
              value={form.description}
              onChange={(description) => setForm((f) => ({ ...f, description }))}
              options={descriptionOptions}
              placeholder="Choisir une description"
              required
            />
          </div>
          <label className="hidden space-y-1.5 md:block">
            <span className="text-lg font-medium tracking-wide text-stone-700">Description</span>
            <select
              className="select-field glass-panel w-full px-4 py-3 text-lg text-stone-800"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              required
            >
              <option value="">Choisir une description</option>
              {eventDescriptionSuggestions.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
          </label>

          <label className="block min-w-0 space-y-1.5">
            <span className="text-base font-medium tracking-wide text-stone-700 sm:text-lg">
              Image <span className="font-normal text-stone-500">(optionnel)</span>
            </span>
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              disabled={imageProcessing}
              className="glass-panel w-full min-w-0 max-w-full px-3 py-2.5 text-base text-stone-800 file:mr-3 file:rounded-lg file:border-0 file:bg-white/70 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-stone-700 disabled:opacity-60"
              onChange={(e) => void onImageChange(e.target.files?.[0] ?? null)}
            />
            {imageProcessing && (
              <p className="text-sm text-stone-500">Optimisation de l&apos;image…</p>
            )}
            <p className="text-sm text-stone-500">
              JPG, PNG, WebP ou GIF — max. {MAX_IMAGE_MB} Mo (redimensionné automatiquement si
              besoin).
            </p>
            {imagePreview && (
              <img
                src={imagePreview}
                alt="Aperçu"
                className="mt-2 max-h-48 w-full rounded-xl border border-white/50 object-cover"
              />
            )}
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="md:hidden">
              <DateTimeField
                label="Début souhaité"
                value={form.preferred_start}
                onChange={(preferred_start) => setForm((f) => ({ ...f, preferred_start }))}
              />
            </div>
            <div className="md:hidden">
              <DateTimeField
                label="Fin souhaitée"
                value={form.preferred_end}
                onChange={(preferred_end) => setForm((f) => ({ ...f, preferred_end }))}
              />
            </div>
            <div className="hidden md:contents">
              <Input
                label="Début souhaité"
                type="datetime-local"
                value={form.preferred_start}
                onChange={(e) => setForm({ ...form, preferred_start: e.target.value })}
              />
              <Input
                label="Fin souhaitée"
                type="datetime-local"
                value={form.preferred_end}
                onChange={(e) => setForm({ ...form, preferred_end: e.target.value })}
              />
            </div>
          </div>

          <div className="md:hidden">
            <MobileSelect
              label="Lieu"
              value={form.location}
              onChange={(loc) => setForm((f) => ({ ...f, location: loc }))}
              options={locationOptions}
              placeholder="Choisir lieu"
            />
          </div>
          <label className="hidden space-y-1.5 md:block">
            <span className="text-lg font-medium tracking-wide text-stone-700">Lieu</span>
            <select
              className="select-field glass-panel w-full px-4 py-3 text-lg text-stone-800"
              value={form.location}
              onChange={(e) => setForm({ ...form, location: e.target.value })}
            >
              <option value="">Choisir lieu</option>
              {eventLocationSuggestions.map((loc) => (
                <option key={loc} value={loc}>
                  {loc}
                </option>
              ))}
            </select>
          </label>

          <Input
            label="Prix du billet par personne (MAD)"
            type="number"
            min={0}
            step={0.01}
            value={form.ticket_price}
            onChange={(e) => setForm({ ...form, ticket_price: e.target.value })}
            required
          />
          <Input
            label="Nom contact"
            value={form.contact_name}
            onChange={(e) => setForm({ ...form, contact_name: e.target.value })}
            required
          />
          <Input
            label="Email contact"
            type="email"
            value={form.contact_email}
            onChange={(e) => setForm({ ...form, contact_email: e.target.value })}
            required
          />
          <Input
            label="Téléphone"
            value={form.contact_phone}
            onChange={(e) => setForm({ ...form, contact_phone: e.target.value })}
          />

          {error && <p className="text-sm text-red-600">{error}</p>}
          <Button
            type="submit"
            className="max-md:w-full"
            disabled={loading || imageProcessing}
          >
            {loading ? 'Envoi…' : 'Envoyer la demande'}
          </Button>
        </form>
      </Card>
    </div>
  );
}
