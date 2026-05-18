/**
 * datetime-local → API : conserve l'heure saisie (pas de conversion UTC).
 * @param {string} value ex. "2026-07-16T15:30"
 * @returns {string|null} ex. "2026-07-16 15:30:00"
 */
export function datetimeLocalToApi(value) {
  if (!value) return null;
  const normalized = value.trim().replace('T', ' ');
  if (normalized.length === 16) {
    return `${normalized}:00`;
  }
  return normalized;
}

/**
 * Date API → valeur pour <input type="datetime-local">.
 * @param {string} value
 * @returns {string}
 */
export function apiToDatetimeLocal(value) {
  if (!value) return '';
  const s = String(value).trim();
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
  if (m) {
    return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}`;
  }
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Parse datetime-local en Date locale (comparaisons début/fin). */
export function splitDatetimeLocal(value) {
  if (!value) return { date: '', time: '' };
  const [date, time = ''] = String(value).split('T');
  return { date, time: time.slice(0, 5) };
}

export function joinDatetimeLocal(date, time) {
  if (!date) return '';
  return `${date}T${time || '00:00'}`;
}

export function parseDatetimeLocal(value) {
  const api = datetimeLocalToApi(value);
  if (!api) return null;
  const [datePart, timePart] = api.split(' ');
  const [y, mo, d] = datePart.split('-').map(Number);
  const [h, mi, se = 0] = timePart.split(':').map(Number);
  return new Date(y, mo - 1, d, h, mi, se);
}
