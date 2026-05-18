import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { apiToDatetimeLocal, datetimeLocalToApi, parseDatetimeLocal } from '../../lib/datetime';
import { formatEventStatus } from '../../lib/format';
import { eventLocationSuggestionSet, eventLocationSuggestions } from '../../data/eventLocationSuggestions';
import { eventRoomVenueSet, eventRoomVenues } from '../../data/eventRoomVenues';
import { OrganizerEventDetailView } from './OrganizerEventDetailView';
/** Aligne les libellés BDD / navigateur (apostrophe courbe vs droite) pour le cocheur. */
function taskTitleKey(s) {
    return s
        .normalize('NFC')
        .replace(/\u2019/g, "'")
        .replace(/\u2018/g, "'")
        .trim();
}
/** Laravel renvoie souvent un tableau ; certaines enveloppes utilisent { data: [...] }. */
function asJsonArray(payload) {
    if (Array.isArray(payload))
        return payload;
    if (payload !== null && typeof payload === 'object' && 'data' in payload) {
        const inner = payload.data;
        if (Array.isArray(inner))
            return inner;
    }
    return null;
}
function extractCreatedTask(raw) {
    if (!raw || typeof raw !== 'object')
        return null;
    const o = raw;
    if ('id' in o && (typeof o.id === 'number' || typeof o.id === 'string')) {
        return raw;
    }
    if ('data' in o &&
        o.data &&
        typeof o.data === 'object' &&
        o.data !== null &&
        'id' in o.data) {
        return o.data;
    }
    return null;
}
function extractCreatedActivity(raw) {
    if (!raw || typeof raw !== 'object')
        return null;
    const o = raw;
    if ('id' in o && (typeof o.id === 'number' || typeof o.id === 'string')) {
        return raw;
    }
    if ('data' in o &&
        o.data &&
        typeof o.data === 'object' &&
        o.data !== null &&
        'id' in o.data) {
        return o.data;
    }
    return null;
}
function formatApiError(e) {
    const err = e;
    const data = err.response?.data;
    if (data?.errors) {
        const parts = Object.values(data.errors).flatMap((v) => (Array.isArray(v) ? v : [String(v)]));
        if (parts.length)
            return parts.join(' ');
    }
    return data?.message ?? 'Une erreur est survenue. Réessayez.';
}
function formSnapshotFromEvent(ev) {
    return {
        editLocation: typeof ev.location === 'string' ? ev.location : '',
        room: typeof ev.room === 'string' ? ev.room.trim() : '',
        startAtLocal: apiToDatetimeLocal(ev.start_at),
        endAtLocal: apiToDatetimeLocal(ev.end_at),
        capacity: ev.capacity,
        ticketPrice: String(ev.ticket_price ?? 0),
    };
}
function formSnapshotsEqual(a, b) {
    if (!a || !b)
        return a === b;
    return (a.editLocation === b.editLocation &&
        a.room === b.room &&
        a.startAtLocal === b.startAtLocal &&
        a.endAtLocal === b.endAtLocal &&
        a.capacity === b.capacity &&
        a.ticketPrice === b.ticketPrice);
}
const taskPickerClass = 'relative z-10 glass-panel max-h-[min(70vh,48rem)] overflow-y-auto overflow-x-hidden rounded-xl border border-white/40 p-3';
const readOnlyFieldClass = 'glass-panel w-full px-4 py-3 text-lg text-stone-900 outline-none';
const inlineInputClass = 'glass-panel min-w-0 flex-1 px-4 py-3 text-lg text-stone-900 outline-none placeholder:text-stone-500 focus:border-brand-500/50';
const blockInputClass = `${inlineInputClass} w-full max-w-none shrink-0`;
/** Liste déroulante : flèche à droite, même style que les champs inline. */
const inlineSelectClass = `${inlineInputClass} cursor-pointer appearance-none bg-[length:1.25rem] bg-[right_0.65rem_center] bg-no-repeat pr-10 bg-[url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%23787169' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E")]`;
const blockSelectClass = `${inlineSelectClass} w-full max-w-none shrink-0`;
export function OrganizerEventDetailPage() {
    const { id } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const { user } = useAuth();
    const [event, setEvent] = useState(null);
    const [tasks, setTasks] = useState([]);
    const [activities, setActivities] = useState([]);
    const [capacity, setCapacity] = useState(0);
    const [ticketPrice, setTicketPrice] = useState('');
    const [room, setRoom] = useState('');
    const [taskPickerOpen, setTaskPickerOpen] = useState(false);
    const [activityPickerOpen, setActivityPickerOpen] = useState(false);
    const [togglingTask, setTogglingTask] = useState(null);
    const [togglingActivity, setTogglingActivity] = useState(null);
    const [savingForm, setSavingForm] = useState(false);
    const [savedForm, setSavedForm] = useState(null);
    const [leaveDialogOpen, setLeaveDialogOpen] = useState(false);
    const [editLocation, setEditLocation] = useState('');
    const [startAtLocal, setStartAtLocal] = useState('');
    const [endAtLocal, setEndAtLocal] = useState('');
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);
    const tasksRef = useRef([]);
    tasksRef.current = tasks;
    const activitiesRef = useRef([]);
    activitiesRef.current = activities;
    const allowLeaveRef = useRef(false);
    const isAdminMyEventsDetailRoute = location.pathname.startsWith('/admin/my-events/');
    const isAdminOrganizerSpaceDetailRoute = location.pathname.startsWith('/admin/organizer-events/');
    const backToPath = isAdminMyEventsDetailRoute
        ? '/admin/my-events'
        : isAdminOrganizerSpaceDetailRoute
          ? '/admin/organizer-events'
          : user?.role === 'admin'
            ? '/admin/events'
            : '/organizer/events';
    const currentForm = useMemo(() => ({
        editLocation,
        room,
        startAtLocal,
        endAtLocal,
        capacity,
        ticketPrice,
    }), [editLocation, room, startAtLocal, endAtLocal, capacity, ticketPrice]);
    const isDirty = useMemo(() => savedForm !== null && !formSnapshotsEqual(savedForm, currentForm), [savedForm, currentForm]);
    useEffect(() => {
        const onBeforeUnload = (e) => {
            if (isDirty && !allowLeaveRef.current) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', onBeforeUnload);
        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [isDirty]);
    const requestBack = () => {
        if (isDirty) {
            setLeaveDialogOpen(true);
            return;
        }
        navigate(backToPath);
    };
    const confirmLeave = () => {
        allowLeaveRef.current = true;
        setLeaveDialogOpen(false);
        navigate(backToPath);
    };
    const cancelLeave = () => {
        setLeaveDialogOpen(false);
    };
    const backTo = (
        <button
            type="button"
            onClick={requestBack}
            className="mb-4 inline-block text-sm font-medium text-brand-700 hover:underline"
        >
            ← Retour
        </button>
    );
    /** Sur les pages /admin/..., appels API réservés admin (évite /organizer/... avant chargement du user). */
    const manageApiBase = useMemo(() => {
        if (!id)
            return '';
        if (location.pathname.startsWith('/admin/')) {
            return `/admin/events/${id}`;
        }
        return `/organizer/events/${id}`;
    }, [id, location.pathname]);
    const roomOrphan = useMemo(() => {
        const t = room.trim();
        if (!t)
            return null;
        return eventRoomVenueSet.has(t) ? null : t;
    }, [room]);
    const locationOrphan = useMemo(() => {
        const t = editLocation.trim();
        if (!t)
            return null;
        return eventLocationSuggestionSet.has(t) ? null : t;
    }, [editLocation]);
    const load = useCallback(async (opts) => {
        if (!id || !manageApiBase)
            return;
        const quiet = opts?.quiet === true;
        if (!quiet) {
            setLoading(true);
            setLoadError(null);
        }
        try {
            const { data: ev } = await api.get(`/events/${id}`);
            setEvent(ev);
            setCapacity(ev.capacity);
            setTicketPrice(String(ev.ticket_price ?? 0));
            setRoom(typeof ev.room === 'string' ? ev.room.trim() : '');
            setEditLocation(typeof ev.location === 'string' ? ev.location : '');
            setStartAtLocal(apiToDatetimeLocal(ev.start_at));
            setEndAtLocal(apiToDatetimeLocal(ev.end_at));
            setSavedForm(formSnapshotFromEvent(ev));
            const [tasksRes, activitiesRes] = await Promise.allSettled([
                api.get(`${manageApiBase}/tasks`),
                api.get(`${manageApiBase}/activities`),
            ]);
            setTasks((prev) => {
                if (tasksRes.status !== 'fulfilled')
                    return prev;
                const parsed = asJsonArray(tasksRes.value.data);
                return parsed ?? prev;
            });
            setActivities((prev) => {
                if (activitiesRes.status !== 'fulfilled')
                    return prev;
                const parsed = asJsonArray(activitiesRes.value.data);
                return parsed ?? prev;
            });
        }
        catch {
            if (!quiet) {
                setEvent(null);
                setLoadError("Impossible de charger cet événement. Vérifiez qu'il vous est bien assigné.");
            }
        }
        finally {
            if (!quiet) {
                setLoading(false);
            }
        }
    }, [id, manageApiBase]);
    const refreshTasksFromApi = useCallback(async () => {
        if (!id || !manageApiBase)
            return;
        try {
            const { data } = await api.get(`${manageApiBase}/tasks`);
            const parsed = asJsonArray(data);
            if (parsed)
                setTasks(parsed);
        }
        catch {
            /* ignore */
        }
    }, [id, manageApiBase]);
    const refreshActivitiesFromApi = useCallback(async () => {
        if (!id || !manageApiBase)
            return;
        try {
            const { data } = await api.get(`${manageApiBase}/activities`);
            const parsed = asJsonArray(data);
            if (parsed)
                setActivities(parsed);
        }
        catch {
            /* ignore */
        }
    }, [id, manageApiBase]);
    useEffect(() => {
        void load();
    }, [load]);
    const saveAllChanges = async () => {
        if (!event)
            return false;
        setSavingForm(true);
        setMessage('');
        const start = parseDatetimeLocal(startAtLocal);
        const end = parseDatetimeLocal(endAtLocal);
        if (!startAtLocal || !endAtLocal || !start || !end) {
            setMessage('Veuillez renseigner des dates de début et de fin valides.');
            setSavingForm(false);
            return false;
        }
        if (end <= start) {
            setMessage('La date de fin doit être après la date de début.');
            setSavingForm(false);
            return false;
        }
        const price = Number(ticketPrice);
        const safePrice = Number.isFinite(price) && price >= 0 ? price : 0;
        try {
            await api.patch(manageApiBase, {
                location: editLocation.trim() || null,
                room: room.trim() || null,
                start_at: datetimeLocalToApi(startAtLocal),
                end_at: datetimeLocalToApi(endAtLocal),
                capacity,
                ticket_price: safePrice,
            });
            setMessage('Modifications enregistrées.');
            await load({ quiet: true });
            setSavedForm(currentForm);
            return true;
        }
        catch (e) {
            setMessage(formatApiError(e));
            return false;
        }
        finally {
            setSavingForm(false);
        }
    };
    const publish = async () => {
        if (isDirty) {
            const ok = await saveAllChanges();
            if (!ok)
                return;
        }
        setSavingForm(true);
        setMessage('');
        try {
            if (user?.role === 'admin') {
                await api.patch(manageApiBase, { status: 'published' });
                setMessage('Événement publié.');
            }
            else {
                await api.post(`${manageApiBase}/request-publication`);
                setMessage('Demande de publication envoyée. Un administrateur validera votre événement.');
            }
            allowLeaveRef.current = true;
            await load({ quiet: true });
        }
        catch (e) {
            setMessage(formatApiError(e));
        }
        finally {
            setSavingForm(false);
        }
    };
    const approvePublication = async () => {
        if (!id)
            return;
        setSavingForm(true);
        setMessage('');
        try {
            await api.post(`/admin/events/${id}/approve-publication`);
            setMessage('Événement publié.');
            await load({ quiet: true });
        }
        catch (e) {
            setMessage(formatApiError(e));
        }
        finally {
            setSavingForm(false);
        }
    };
    const taskByTitle = useMemo(() => {
        const map = new Map();
        for (const t of tasks) {
            map.set(taskTitleKey(t.title), t);
        }
        return map;
    }, [tasks]);
    const activityByTitle = useMemo(() => {
        const map = new Map();
        for (const a of activities) {
            map.set(taskTitleKey(a.title), a);
        }
        return map;
    }, [activities]);
    const locationOptions = useMemo(() => {
        const opts = eventLocationSuggestions.map((loc) => ({ value: loc, label: loc }));
        if (locationOrphan) {
            opts.unshift({ value: locationOrphan, label: `${locationOrphan} (non listé)` });
        }
        return opts;
    }, [locationOrphan]);
    const roomOptions = useMemo(() => {
        const opts = eventRoomVenues.map((v) => ({ value: v, label: v }));
        if (roomOrphan) {
            opts.unshift({ value: roomOrphan, label: `${roomOrphan} (non listé)` });
        }
        return opts;
    }, [roomOrphan]);
    const toggleTaskSuggestion = async (title) => {
        if (!id || togglingTask)
            return;
        const key = taskTitleKey(title);
        const existing = tasksRef.current.find((t) => taskTitleKey(t.title) === key);
        setTogglingTask(title);
        setMessage('');
        try {
            if (existing) {
                await api.delete(`${manageApiBase}/tasks/${existing.id}`);
                setTasks((prev) => prev.filter((t) => t.id !== existing.id));
            }
            else {
                const res = await api.post(`${manageApiBase}/tasks`, { title });
                const created = extractCreatedTask(res.data);
                if (!created || created.id == null) {
                    setMessage('Réponse inattendue du serveur pour la tâche.');
                    await refreshTasksFromApi();
                    return;
                }
                setTasks((prev) => {
                    if (prev.some((t) => t.id === created.id))
                        return prev;
                    const sameTitle = prev.some((t) => taskTitleKey(t.title) === taskTitleKey(created.title));
                    if (sameTitle)
                        return prev;
                    return [...prev, created];
                });
            }
        }
        catch (e) {
            setMessage(formatApiError(e));
            await refreshTasksFromApi();
        }
        finally {
            setTogglingTask(null);
        }
    };
    const toggleActivitySuggestion = async (title) => {
        if (!id || togglingActivity)
            return;
        const key = taskTitleKey(title);
        const existing = activitiesRef.current.find((a) => taskTitleKey(a.title) === key);
        setTogglingActivity(title);
        setMessage('');
        try {
            if (existing) {
                await api.delete(`${manageApiBase}/activities/${existing.id}`);
                setActivities((prev) => prev.filter((a) => a.id !== existing.id));
            }
            else {
                const res = await api.post(`${manageApiBase}/activities`, { title });
                const created = extractCreatedActivity(res.data);
                if (!created || created.id == null) {
                    setMessage('Réponse inattendue du serveur pour l’activité.');
                    await refreshActivitiesFromApi();
                    return;
                }
                setActivities((prev) => {
                    if (prev.some((a) => a.id === created.id))
                        return prev;
                    const sameTitle = prev.some((a) => taskTitleKey(a.title) === taskTitleKey(created.title));
                    if (sameTitle)
                        return prev;
                    return [...prev, created];
                });
            }
        }
        catch (e) {
            setMessage(formatApiError(e));
            await refreshActivitiesFromApi();
        }
        finally {
            setTogglingActivity(null);
        }
    };
    if (loading) {
        return <p className="mx-auto max-w-3xl text-stone-500">Chargement…</p>;
    }
    if (loadError || !event) {
        return (
            <div className="mx-auto max-w-3xl">
                {backTo}
                <p className="glass-panel rounded-xl border border-red-200/60 bg-red-50/50 px-4 py-6 text-center text-red-700">
                    {loadError ?? 'Événement introuvable.'}
                </p>
            </div>
        );
    }
    return (<OrganizerEventDetailView
        backTo={backTo}
        event={event}
        readOnlyFieldClass={readOnlyFieldClass}
        editLocation={editLocation}
        setEditLocation={setEditLocation}
        locationOptions={locationOptions}
        locationOrphan={locationOrphan}
        room={room}
        setRoom={setRoom}
        roomOptions={roomOptions}
        roomOrphan={roomOrphan}
        startAtLocal={startAtLocal}
        setStartAtLocal={setStartAtLocal}
        endAtLocal={endAtLocal}
        setEndAtLocal={setEndAtLocal}
        blockSelectClass={blockSelectClass}
        blockInputClass={blockInputClass}
        inlineInputClass={inlineInputClass}
        capacity={capacity}
        setCapacity={setCapacity}
        ticketPrice={ticketPrice}
        setTicketPrice={setTicketPrice}
        tasks={tasks}
        activities={activities}
        taskPickerOpen={taskPickerOpen}
        setTaskPickerOpen={setTaskPickerOpen}
        activityPickerOpen={activityPickerOpen}
        setActivityPickerOpen={setActivityPickerOpen}
        taskPickerClass={taskPickerClass}
        taskByTitle={taskByTitle}
        activityByTitle={activityByTitle}
        togglingTask={togglingTask}
        togglingActivity={togglingActivity}
        toggleTaskSuggestion={toggleTaskSuggestion}
        toggleActivitySuggestion={toggleActivitySuggestion}
        message={message}
        savingForm={savingForm}
        isDirty={isDirty}
        saveAllChanges={saveAllChanges}
        publish={publish}
        approvePublication={approvePublication}
        user={user}
        id={id}
        leaveDialogOpen={leaveDialogOpen}
        confirmLeave={confirmLeave}
        cancelLeave={cancelLeave}
        formatEventStatus={formatEventStatus}
    />);
}
