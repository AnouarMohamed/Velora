import { EventImage } from '../../components/EventImage';
import { EventFeedbacksSection } from '../../components/EventFeedbacksSection';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { MobileSelect } from '../../components/MobileSelect';
import { DateTimeField } from '../../components/DateTimeField';
import { Badge, Button, Card, PageHeader } from '../../components/ui';
import { eventActivitySuggestions } from '../../data/eventActivitySuggestions';
import { eventLocationSuggestions } from '../../data/eventLocationSuggestions';
import { eventRoomVenues } from '../../data/eventRoomVenues';
import { eventTaskSuggestions } from '../../data/eventTaskSuggestions';

function taskTitleKey(s) {
  return s
    .normalize('NFC')
    .replace(/\u2019/g, "'")
    .replace(/\u2018/g, "'")
    .trim();
}

export function OrganizerEventDetailView({
  backTo,
  event,
  readOnlyFieldClass,
  editLocation,
  setEditLocation,
  locationOptions,
  locationOrphan,
  room,
  setRoom,
  roomOptions,
  roomOrphan,
  startAtLocal,
  setStartAtLocal,
  endAtLocal,
  setEndAtLocal,
  blockSelectClass,
  blockInputClass,
  inlineInputClass,
  capacity,
  setCapacity,
  ticketPrice,
  setTicketPrice,
  tasks,
  activities,
  taskPickerOpen,
  setTaskPickerOpen,
  activityPickerOpen,
  setActivityPickerOpen,
  taskPickerClass,
  taskByTitle,
  activityByTitle,
  togglingTask,
  togglingActivity,
  toggleTaskSuggestion,
  toggleActivitySuggestion,
  message,
  savingForm,
  isDirty,
  saveAllChanges,
  publish,
  approvePublication,
  user,
  id,
  leaveDialogOpen,
  confirmLeave,
  cancelLeave,
  formatEventStatus,
}) {
  return (
    <div className="mx-auto max-w-3xl min-w-0">
      {backTo}
      <PageHeader
        title="Gérer l'événement"
        subtitle={`${event.title} — ${formatEventStatus(event.status)}`}
      />
      <Card className="max-md:overflow-hidden">
        <div className="min-w-0 space-y-4">
          <label className="block space-y-1.5">
            <span className="text-lg font-medium tracking-wide text-stone-700">Titre</span>
            <div className={readOnlyFieldClass}>{event.title}</div>
          </label>

          {event.description && (
            <label className="block space-y-1.5">
              <span className="text-lg font-medium tracking-wide text-stone-700">Description</span>
              <div className={`${readOnlyFieldClass} leading-relaxed`}>{event.description}</div>
            </label>
          )}

          {event.image_url && (
            <label className="block space-y-1.5">
              <span className="text-lg font-medium tracking-wide text-stone-700">Image</span>
              <EventImage
                src={event.image_url}
                alt={event.title}
                className="max-h-48 w-full rounded-xl border border-white/50 object-cover"
              />
            </label>
          )}

          <div className="space-y-3">
            <div className="md:hidden">
              <MobileSelect
                label="Lieu"
                value={editLocation}
                onChange={setEditLocation}
                options={locationOptions}
                placeholder="Choisir lieu"
              />
            </div>
            <label className="hidden space-y-1.5 md:block">
              <span className="text-lg font-medium tracking-wide text-stone-700">Lieu</span>
              <select
                className={blockSelectClass}
                value={editLocation}
                onChange={(e) => setEditLocation(e.target.value)}
                aria-label="Lieu"
              >
                <option value="">Choisir lieu</option>
                {locationOrphan ? (
                  <option value={locationOrphan}>{locationOrphan} (non listé)</option>
                ) : null}
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
                value={room}
                onChange={setRoom}
                options={roomOptions}
                placeholder="— Choisir un espace —"
              />
            </div>
            <label className="hidden space-y-1.5 md:block">
              <span className="text-lg font-medium tracking-wide text-stone-700">Salle</span>
              <p className="text-sm text-stone-500">Espace ou salle précise.</p>
              <select
                value={room}
                onChange={(e) => setRoom(e.target.value)}
                className={blockSelectClass}
                aria-label="Salle"
              >
                <option value="">— Choisir un espace —</option>
                {roomOrphan ? <option value={roomOrphan}>{roomOrphan} (non listé)</option> : null}
                {eventRoomVenues.map((v) => (
                  <option key={v} value={v}>
                    {v}
                  </option>
                ))}
              </select>
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="md:hidden">
                <DateTimeField label="Début" value={startAtLocal} onChange={setStartAtLocal} />
              </div>
              <div className="md:hidden">
                <DateTimeField label="Fin" value={endAtLocal} onChange={setEndAtLocal} />
              </div>
              <label className="hidden space-y-1.5 md:block">
                <span className="text-sm font-medium text-stone-600">Début</span>
                <input
                  type="datetime-local"
                  value={startAtLocal}
                  onChange={(e) => setStartAtLocal(e.target.value)}
                  className={blockInputClass}
                  aria-label="Date et heure de début"
                />
              </label>
              <label className="hidden space-y-1.5 md:block">
                <span className="text-sm font-medium text-stone-600">Fin</span>
                <input
                  type="datetime-local"
                  value={endAtLocal}
                  onChange={(e) => setEndAtLocal(e.target.value)}
                  className={blockInputClass}
                  aria-label="Date et heure de fin"
                />
              </label>
            </div>
          </div>

          <div className="block space-y-1.5">
            <span className="text-lg font-medium tracking-wide text-stone-700">
              Capacité et prix du billet
            </span>
            <p className="text-sm text-stone-500">
              Nombre maximum de participants (personnes inscrites : {event.registered_count} /{' '}
              {event.capacity}).
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block space-y-1.5 sm:col-span-1">
                <span className="text-sm font-medium text-stone-600">Places</span>
                <input
                  type="number"
                  min={event.registered_count}
                  value={capacity}
                  onChange={(e) => setCapacity(Number(e.target.value))}
                  className={`${inlineInputClass} w-full max-w-none`}
                  aria-label="Nombre de places"
                />
              </label>
              <label className="block space-y-1.5 sm:col-span-1">
                <span className="text-sm font-medium text-stone-600">Prix du billet (MAD)</span>
                <input
                  type="number"
                  min={0}
                  step={0.01}
                  value={ticketPrice}
                  onChange={(e) => setTicketPrice(e.target.value)}
                  className={`${inlineInputClass} w-full max-w-none`}
                  aria-label="Prix du billet en MAD"
                />
              </label>
            </div>
          </div>

          <div className="block space-y-1.5">
            <div>
              <span className="text-sm font-medium text-stone-600">Tâches ajoutées</span>
              <div className="glass-panel mt-1.5 overflow-x-auto rounded-xl border border-white/40 px-3 py-3">
                {tasks.length === 0 ? (
                  <p className="text-sm text-stone-500">
                    Aucune tâche. Cliquez sur « Ajouter une tâche » pour choisir dans la liste.
                  </p>
                ) : (
                  <div className="flex flex-nowrap gap-2">
                    {tasks.map((t) => (
                      <div
                        key={t.id}
                        className="flex min-w-0 max-w-[min(100%,20rem)] shrink-0 rounded-lg border border-white/50 bg-white/45 px-3 py-2 shadow-sm"
                      >
                        <span
                          className={`overflow-x-auto whitespace-nowrap text-sm leading-tight ${
                            t.is_done ? 'line-through text-stone-500' : 'text-stone-800'
                          }`}
                          title={t.title}
                        >
                          {t.title}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
            <Button type="button" variant="secondary" onClick={() => setTaskPickerOpen((o) => !o)}>
              {taskPickerOpen ? 'Fermer la liste' : 'Ajouter une tâche'}
            </Button>
            {taskPickerOpen && (
              <div className={taskPickerClass}>
                <p className="mb-2 text-xs text-stone-500">
                  Cochez une tâche pour l&apos;ajouter ; décochez pour la retirer de la barre.
                </p>
                <ul className="space-y-0.5">
                  {eventTaskSuggestions.map((title) => {
                    const existing = taskByTitle.get(taskTitleKey(title));
                    const added = Boolean(existing);
                    const busy = togglingTask === title;
                    return (
                      <li key={title}>
                        <button
                          type="button"
                          disabled={busy}
                          aria-pressed={added}
                          onClick={() => void toggleTaskSuggestion(title)}
                          className="flex min-w-0 w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-white/40 disabled:cursor-wait disabled:opacity-60"
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
                              <svg className="h-3 w-3 text-white" viewBox="0 0 12 12" fill="none">
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
              <span className="text-sm font-medium text-stone-600">Activités ajoutées</span>
              <div className="glass-panel mt-1.5 overflow-x-auto rounded-xl border border-white/40 px-3 py-3">
                {activities.length === 0 ? (
                  <p className="text-sm text-stone-500">
                    Aucune activité. Cliquez sur « Ajouter une activité » pour choisir dans la liste.
                  </p>
                ) : (
                  <div className="flex flex-nowrap gap-2">
                    {activities.map((a) => (
                      <div
                        key={a.id}
                        className="flex min-w-0 max-w-[min(100%,20rem)] shrink-0 rounded-lg border border-white/50 bg-white/45 px-3 py-2 shadow-sm"
                      >
                        <span
                          className="overflow-x-auto whitespace-nowrap text-sm leading-tight text-stone-800"
                          title={a.title}
                        >
                          {a.title}
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
              {activityPickerOpen ? 'Fermer la liste' : 'Ajouter une activité'}
            </Button>
            {activityPickerOpen && (
              <div className={taskPickerClass}>
                <p className="mb-2 text-xs text-stone-500">
                  Cochez une activité pour l&apos;ajouter ; décochez pour la retirer de la barre.
                </p>
                <ul className="space-y-0.5">
                  {eventActivitySuggestions.map((title) => {
                    const added = Boolean(activityByTitle.get(taskTitleKey(title)));
                    const busy = togglingActivity === title;
                    return (
                      <li key={title}>
                        <button
                          type="button"
                          disabled={busy}
                          aria-pressed={added}
                          onClick={() => void toggleActivitySuggestion(title)}
                          className="flex min-w-0 w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-white/40 disabled:cursor-wait disabled:opacity-60"
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
                              <svg className="h-3 w-3 text-white" viewBox="0 0 12 12" fill="none">
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

          {event.status === 'published' && <EventFeedbacksSection eventId={id} />}

          {message && <p className="text-sm text-brand-800">{message}</p>}

          <div className="flex flex-wrap gap-3">
            <Button
              type="button"
              variant="secondary"
              disabled={savingForm || !isDirty}
              onClick={() => void saveAllChanges()}
            >
              {savingForm ? 'Enregistrement…' : 'Enregistrer'}
            </Button>
            {event.status === 'published' ? (
              <div className="flex items-center gap-2">
                <Badge tone="success" size="sm">
                  Publié
                </Badge>
                <span className="text-sm text-stone-600">Cet événement est visible en ligne.</span>
              </div>
            ) : event.status === 'pending_publication' ? (
              user?.role === 'admin' ? (
                <Button type="button" disabled={savingForm} onClick={() => void approvePublication()}>
                  Approuver la publication
                </Button>
              ) : (
                <div className="flex flex-col gap-1">
                  <Badge tone="warning" size="sm" className="w-fit">
                    En attente de validation
                  </Badge>
                  <span className="text-sm text-stone-600">
                    Un administrateur doit valider la publication.
                  </span>
                </div>
              )
            ) : (
              <Button type="button" disabled={savingForm} onClick={() => void publish()}>
                {user?.role === 'admin' ? "Publier l'événement" : 'Demander la publication'}
              </Button>
            )}
          </div>
        </div>
      </Card>

      <ConfirmDialog
        open={leaveDialogOpen}
        title="Modifications non enregistrées"
        message="Vous avez des modifications non enregistrées. Voulez-vous quitter cette page sans les enregistrer ?"
        confirmLabel="Quitter sans enregistrer"
        cancelLabel="Rester sur la page"
        confirmVariant="danger"
        onConfirm={confirmLeave}
        onCancel={cancelLeave}
      />
    </div>
  );
}
