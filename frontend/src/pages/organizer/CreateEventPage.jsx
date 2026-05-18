import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { compressImageForUpload, MAX_IMAGE_MB } from '../../lib/compressImage';
import { fileToDataUrl } from '../../lib/fileToDataUrl';
import { datetimeLocalToApi, parseDatetimeLocal } from '../../lib/datetime';
import { MobileSelect } from '../../components/MobileSelect';
import { DateTimeField } from '../../components/DateTimeField';
import { Button, Card, Input, PageHeader } from '../../components/ui';
import { eventActivitySuggestions } from '../../data/eventActivitySuggestions';
import { eventDescriptionSuggestions } from '../../data/eventDescriptionSuggestions';
import { eventLocationSuggestions } from '../../data/eventLocationSuggestions';
import { eventRoomVenues } from '../../data/eventRoomVenues';
import { eventTaskSuggestions } from '../../data/eventTaskSuggestions';
import { eventTitleSuggestions } from '../../data/eventTitleSuggestions';

const MAX_IMAGE_BYTES = MAX_IMAGE_MB * 1024 * 1024;

function taskTitleKey(s) {
    return s
        .normalize('NFC')
        .replace(/\u2019/g, "'")
        .replace(/\u2018/g, "'")
        .trim();
}

function formatApiError(e) {
    const err = e;
    const data = err?.response?.data;
    if (data?.errors) {
        const parts = Object.values(data.errors).flatMap((v) => (Array.isArray(v) ? v : [String(v)]));
        if (parts.length)
            return parts.join(' ');
    }
    return data?.message ?? 'Une erreur est survenue. Réessayez.';
}

const chipClass = (active) =>
    `rounded-full border px-3 py-1 text-sm font-medium transition ${
        active
            ? 'border-brand-500/60 bg-white/80 text-brand-800 shadow-sm'
            : 'border-white/50 bg-white/40 text-stone-700 hover:border-brand-400/50 hover:bg-white/65'
    }`;

const suggestionBoxClass = 'glass-panel max-h-52 overflow-y-auto rounded-xl border border-white/40 p-3';

const taskPickerClass =
    'relative z-10 glass-panel max-h-[min(70vh,48rem)] overflow-y-auto overflow-x-hidden rounded-xl border border-white/40 p-3';

const inlineInputClass =
    'glass-panel min-w-0 flex-1 px-4 py-3 text-lg text-stone-900 outline-none placeholder:text-stone-500 focus:border-brand-500/50';

const inlineSelectClass = `${inlineInputClass} cursor-pointer appearance-none bg-[length:1.25rem] bg-[right_0.65rem_center] bg-no-repeat pr-10 bg-[url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%23787169' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E")]`;

const blockSelectClass = `${inlineSelectClass} w-full max-w-none shrink-0`;

const descriptionOptions = eventDescriptionSuggestions.map((s) => ({ value: s, label: s }));
const locationOptions = eventLocationSuggestions.map((loc) => ({ value: loc, label: loc }));
const roomOptions = eventRoomVenues.map((v) => ({ value: v, label: v }));
const statusOptions = [
    { value: 'draft', label: 'Brouillon' },
    { value: 'published', label: 'Publié' },
];

export function CreateEventPage() {
    const navigate = useNavigate();
    const { user } = useAuth();
    const [form, setForm] = useState({
        title: '',
        description: '',
        location: '',
        room: '',
        start_at: '',
        end_at: '',
        capacity: 50,
        ticket_price: '',
        status: 'draft',
    });
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [imageProcessing, setImageProcessing] = useState(false);
    const [selectedTasks, setSelectedTasks] = useState([]);
    const [selectedActivities, setSelectedActivities] = useState([]);
    const [taskPickerOpen, setTaskPickerOpen] = useState(false);
    const [activityPickerOpen, setActivityPickerOpen] = useState(false);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    /** Si l’événement existe mais l’ajout des tâches / activités a échoué. */
    const [createdEventId, setCreatedEventId] = useState(null);

    const onImageChange = async (file) => {
        setError('');
        if (imagePreview)
            URL.revokeObjectURL(imagePreview);
        if (!file) {
            setImageFile(null);
            setImagePreview(null);
            return;
        }
        setImageProcessing(true);
        try {
            const processed = file.size <= MAX_IMAGE_BYTES ? file : await compressImageForUpload(file);
            setImageFile(processed);
            setImagePreview(URL.createObjectURL(processed));
        }
        catch {
            setImageFile(null);
            setImagePreview(null);
            setError(`Image trop volumineuse (max. ${MAX_IMAGE_MB} Mo). Choisissez une photo plus légère ou une image plus petite.`);
        }
        finally {
            setImageProcessing(false);
        }
    };

    const toggleTaskSuggestion = (title) => {
        setSelectedTasks((prev) => {
            const k = taskTitleKey(title);
            const has = prev.some((t) => taskTitleKey(t) === k);
            if (has)
                return prev.filter((t) => taskTitleKey(t) !== k);
            return [...prev, title];
        });
    };

    const toggleActivitySuggestion = (title) => {
        setSelectedActivities((prev) => {
            const k = taskTitleKey(title);
            const has = prev.some((t) => taskTitleKey(t) === k);
            if (has)
                return prev.filter((t) => taskTitleKey(t) !== k);
            return [...prev, title];
        });
    };

    const submit = async (e) => {
        e.preventDefault();
        setError('');
        const start = parseDatetimeLocal(form.start_at);
        const end = parseDatetimeLocal(form.end_at);
        if (!form.description?.trim()) {
            setError('Choisissez une description.');
            return;
        }
        if (!form.start_at || !form.end_at || !start || !end) {
            setError('Veuillez renseigner des dates de début et de fin valides.');
            return;
        }
        if (end <= start) {
            setError('La date de fin doit être après la date de début.');
            return;
        }
        const cap = Number(form.capacity);
        if (!Number.isFinite(cap) || cap < 1) {
            setError('Capacité invalide (minimum 1).');
            return;
        }
        setLoading(true);
        setCreatedEventId(null);
        try {
            const payload = {
                title: form.title.trim(),
                description: form.description,
                location: form.location.trim() || null,
                room: form.room.trim() || null,
                start_at: datetimeLocalToApi(form.start_at),
                end_at: datetimeLocalToApi(form.end_at),
                capacity: cap,
                ticket_price: Number(form.ticket_price) || 0,
                status: form.status,
            };
            if (imageFile) {
                payload.image_data = await fileToDataUrl(imageFile);
                payload.image_mime = imageFile.type || 'image/jpeg';
            }
            const { data: ev } = await api.post('/organizer/events', payload);
            const manageBase =
                user?.role === 'admin' ? `/admin/events/${ev.id}` : `/organizer/events/${ev.id}`;
            try {
                await Promise.all([
                    ...selectedTasks.map((title) => api.post(`${manageBase}/tasks`, { title })),
                    ...selectedActivities.map((title) => api.post(`${manageBase}/activities`, { title })),
                ]);
            }
            catch (inner) {
                setCreatedEventId(ev.id);
                setError(
                    `Événement enregistré, mais l’ajout des tâches ou activités a échoué : ${formatApiError(inner)}. Complétez depuis la fiche.`,
                );
                return;
            }
            const listPath = user?.role === 'admin' ? '/admin/my-events' : '/organizer/events';
            navigate(listPath, { replace: true });
        }
        catch (err) {
            setError(formatApiError(err));
        }
        finally {
            setLoading(false);
        }
    };

    const isAdmin = user?.role === 'admin';

    return (
        <div className="mx-auto max-w-3xl min-w-0">
            <PageHeader title="Créer un événement" />
            <Card className="max-md:overflow-hidden">
                <form onSubmit={submit} className="min-w-0 space-y-4">
                    <label className="block space-y-1.5">
                        <span className="text-lg font-medium tracking-wide text-stone-700">Titre</span>
                        <input
                            list="create-event-title-suggestions"
                            className="glass-panel w-full px-4 py-3 text-lg text-stone-900 outline-none placeholder:text-stone-500 focus:border-brand-500/50"
                            value={form.title}
                            onChange={(e) => setForm({ ...form, title: e.target.value })}
                            placeholder="Choisissez ou saisissez un titre"
                            required
                        />
                        <datalist id="create-event-title-suggestions">
                            {eventTitleSuggestions.map((s) => (
                                <option key={s} value={s} />
                            ))}
                        </datalist>
                    </label>

                    <div>
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

                    <label className="block space-y-1.5">
                        <span className="text-lg font-medium tracking-wide text-stone-700">
                            Ajouter une image{' '}
                            <span className="font-normal text-stone-500">(optionnel)</span>
                        </span>
                        <p className="text-sm text-stone-600">
                            Illustration ou affiche de l’événement — choisissez un fichier sur votre appareil.
                        </p>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            disabled={imageProcessing}
                            aria-label="Ajouter une image pour l’événement"
                            className="glass-panel w-full px-4 py-3 text-base text-stone-800 file:mr-3 file:rounded-lg file:border-0 file:bg-white/70 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-stone-700 disabled:opacity-60"
                            onChange={(e) => void onImageChange(e.target.files?.[0] ?? null)}
                        />
                        {imageProcessing && <p className="text-sm text-stone-500">Optimisation de l’image…</p>}
                        <p className="text-sm text-stone-500">
                            Formats : JPG, PNG, WebP ou GIF — max. {MAX_IMAGE_MB} Mo (redimensionné automatiquement si
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
                                label="Début"
                                value={form.start_at}
                                onChange={(start_at) => setForm((f) => ({ ...f, start_at }))}
                            />
                        </div>
                        <div className="md:hidden">
                            <DateTimeField
                                label="Fin"
                                value={form.end_at}
                                onChange={(end_at) => setForm((f) => ({ ...f, end_at }))}
                            />
                        </div>
                        <div className="hidden md:contents">
                            <Input
                                label="Début"
                                type="datetime-local"
                                value={form.start_at}
                                onChange={(e) => setForm({ ...form, start_at: e.target.value })}
                                required
                            />
                            <Input
                                label="Fin"
                                type="datetime-local"
                                value={form.end_at}
                                onChange={(e) => setForm({ ...form, end_at: e.target.value })}
                                required
                            />
                        </div>
                    </div>

                    <div className="md:hidden">
                        <MobileSelect
                            label="Lieu"
                            value={form.location}
                            onChange={(location) => setForm((f) => ({ ...f, location }))}
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

                    <div className="md:hidden">
                        <MobileSelect
                            label="Salle"
                            value={form.room}
                            onChange={(room) => setForm((f) => ({ ...f, room }))}
                            options={roomOptions}
                            placeholder="— Choisir un espace —"
                        />
                    </div>
                    <label className="hidden space-y-1.5 md:block">
                        <span className="text-lg font-medium tracking-wide text-stone-700">Salle</span>
                        <p className="text-sm text-stone-500">Espace ou salle précise.</p>
                        <select
                            value={form.room}
                            onChange={(e) => setForm({ ...form, room: e.target.value })}
                            className={blockSelectClass}
                            aria-label="Salle"
                        >
                            <option value="">— Choisir un espace —</option>
                            {eventRoomVenues.map((v) => (
                                <option key={v} value={v}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label="Capacité (places)"
                            type="number"
                            min={1}
                            value={form.capacity}
                            onChange={(e) => setForm({ ...form, capacity: Number(e.target.value) })}
                            required
                        />
                        <Input
                            label="Prix du billet (MAD)"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.ticket_price}
                            onChange={(e) => setForm({ ...form, ticket_price: e.target.value })}
                        />
                    </div>

                    {isAdmin && (
                        <>
                            <div className="md:hidden">
                                <MobileSelect
                                    label="Statut"
                                    value={form.status}
                                    onChange={(status) => setForm((f) => ({ ...f, status }))}
                                    options={statusOptions}
                                    placeholder="Choisir un statut"
                                    required
                                />
                            </div>
                            <label className="hidden space-y-1.5 text-sm md:block">
                                <span className="text-lg font-medium tracking-wide text-stone-700">Statut</span>
                                <select
                                    className="glass-panel w-full px-4 py-2.5 text-stone-800"
                                    value={form.status}
                                    onChange={(e) => setForm({ ...form, status: e.target.value })}
                                >
                                    <option value="draft">Brouillon</option>
                                    <option value="published">Publié</option>
                                </select>
                            </label>
                        </>
                    )}

                    <div className="block space-y-1.5">
                        <div>
                            <span className="text-sm font-medium text-stone-600">Tâches à l’avance</span>
                            <div className="glass-panel mt-1.5 overflow-x-auto rounded-xl border border-white/40 px-3 py-3">
                                {selectedTasks.length === 0 ? (
                                    <p className="text-sm text-stone-500">
                                        Aucune tâche. Ouvrez la liste pour en ajouter depuis les suggestions.
                                    </p>
                                ) : (
                                    <div className="flex flex-nowrap gap-2">
                                        {selectedTasks.map((t) => (
                                            <div
                                                key={taskTitleKey(t)}
                                                className="flex min-w-0 max-w-[min(100%,20rem)] shrink-0 rounded-lg border border-white/50 bg-white/45 px-3 py-2 shadow-sm"
                                            >
                                                <span
                                                    className="overflow-x-auto whitespace-nowrap text-sm leading-tight text-stone-800"
                                                    title={t}
                                                >
                                                    {t}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                        <Button type="button" variant="secondary" onClick={() => setTaskPickerOpen((o) => !o)}>
                            {taskPickerOpen ? 'Fermer la liste' : 'Choisir des tâches'}
                        </Button>
                        {taskPickerOpen && (
                            <div className={taskPickerClass}>
                                <p className="mb-2 text-xs text-stone-500">
                                    Cochez les tâches à créer automatiquement sur la fiche (vous pourrez les modifier
                                    ensuite).
                                </p>
                                <ul className="space-y-0.5">
                                    {eventTaskSuggestions.map((title) => {
                                        const added = selectedTasks.some(
                                            (t) => taskTitleKey(t) === taskTitleKey(title),
                                        );
                                        return (
                                            <li key={title}>
                                                <button
                                                    type="button"
                                                    aria-pressed={added}
                                                    onClick={() => toggleTaskSuggestion(title)}
                                                    className="flex min-w-0 w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-white/40"
                                                >
                                                    <span
                                                        className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition ${
                                                            added
                                                                ? 'border-brand-600 bg-brand-600'
                                                                : 'border-stone-400 bg-white/80'
                                                        }`}
                                                        aria-hidden
                                                    >
                                                        {added && (
                                                            <svg
                                                                className="h-3 w-3 text-white"
                                                                viewBox="0 0 12 12"
                                                                fill="none"
                                                            >
                                                                <path
                                                                    d="M2.5 6.5L5 9l4.5-5"
                                                                    stroke="currentColor"
                                                                    strokeWidth="1.8"
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                />
                                                            </svg>
                                                        )}
                                                    </span>
                                                    <span className="min-w-0 flex-1 overflow-x-auto whitespace-nowrap text-base text-stone-800">
                                                        {title}
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}
                    </div>

                    <div className="block space-y-1.5">
                        <div>
                            <span className="text-sm font-medium text-stone-600">Activités à l’avance</span>
                            <div className="glass-panel mt-1.5 overflow-x-auto rounded-xl border border-white/40 px-3 py-3">
                                {selectedActivities.length === 0 ? (
                                    <p className="text-sm text-stone-500">
                                        Aucune activité. Ouvrez la liste pour en ajouter depuis les suggestions.
                                    </p>
                                ) : (
                                    <div className="flex flex-nowrap gap-2">
                                        {selectedActivities.map((t) => (
                                            <div
                                                key={taskTitleKey(t)}
                                                className="flex min-w-0 max-w-[min(100%,20rem)] shrink-0 rounded-lg border border-white/50 bg-white/45 px-3 py-2 shadow-sm"
                                            >
                                                <span
                                                    className="overflow-x-auto whitespace-nowrap text-sm leading-tight text-stone-800"
                                                    title={t}
                                                >
                                                    {t}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setActivityPickerOpen((o) => !o)}
                        >
                            {activityPickerOpen ? 'Fermer la liste' : 'Choisir des activités'}
                        </Button>
                        {activityPickerOpen && (
                            <div className={taskPickerClass}>
                                <p className="mb-2 text-xs text-stone-500">
                                    Cochez les activités à créer automatiquement sur la fiche.
                                </p>
                                <ul className="space-y-0.5">
                                    {eventActivitySuggestions.map((title) => {
                                        const added = selectedActivities.some(
                                            (t) => taskTitleKey(t) === taskTitleKey(title),
                                        );
                                        return (
                                            <li key={title}>
                                                <button
                                                    type="button"
                                                    aria-pressed={added}
                                                    onClick={() => toggleActivitySuggestion(title)}
                                                    className="flex min-w-0 w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-white/40"
                                                >
                                                    <span
                                                        className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition ${
                                                            added
                                                                ? 'border-brand-600 bg-brand-600'
                                                                : 'border-stone-400 bg-white/80'
                                                        }`}
                                                        aria-hidden
                                                    >
                                                        {added && (
                                                            <svg
                                                                className="h-3 w-3 text-white"
                                                                viewBox="0 0 12 12"
                                                                fill="none"
                                                            >
                                                                <path
                                                                    d="M2.5 6.5L5 9l4.5-5"
                                                                    stroke="currentColor"
                                                                    strokeWidth="1.8"
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                />
                                                            </svg>
                                                        )}
                                                    </span>
                                                    <span className="min-w-0 flex-1 overflow-x-auto whitespace-nowrap text-base text-stone-800">
                                                        {title}
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}
                    </div>

                    {error && (
                        <p className="text-sm text-red-600">
                            {error}
                        </p>
                    )}

                    {createdEventId && (
                        <div className="rounded-xl border border-amber-200/80 bg-amber-50/60 px-4 py-3 text-stone-800">
                            <p className="text-sm font-medium">L’événement est déjà en base.</p>
                            <Link
                                to={
                                    isAdmin
                                        ? `/organizer/events/${createdEventId}`
                                        : `/organizer/events/${createdEventId}`
                                }
                                className="mt-2 inline-block font-medium text-brand-700 hover:underline"
                            >
                                Ouvrir la fiche pour ajouter les tâches et activités
                            </Link>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={loading || imageProcessing}>
                            {loading ? 'Enregistrement…' : 'Créer l’événement'}
                        </Button>
                        <Link
                            to={isAdmin ? '/admin/my-events' : '/organizer/events'}
                            className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/50 bg-white/30 px-5 py-3 text-lg font-medium tracking-wide text-stone-800 backdrop-blur-md transition-all duration-300 hover:border-brand-400/50 hover:bg-white/50"
                        >
                            Annuler
                        </Link>
                    </div>
                </form>
            </Card>
        </div>
    );
}
