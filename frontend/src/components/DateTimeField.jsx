import { joinDatetimeLocal, splitDatetimeLocal } from '../lib/datetime';

const fieldClass =
  'glass-panel w-full min-w-0 max-w-full px-3 py-2.5 text-base text-stone-900 outline-none focus:border-brand-500/50 sm:px-4 sm:py-3 sm:text-lg';

export function DateTimeField({ label, value, onChange }) {
  const { date, time } = splitDatetimeLocal(value);

  const setDate = (nextDate) => onChange(joinDatetimeLocal(nextDate, time));
  const setTime = (nextTime) => onChange(joinDatetimeLocal(date, nextTime));

  return (
    <div className="block min-w-0 space-y-1.5">
      {label && (
        <span className="text-base font-medium tracking-wide text-stone-700 sm:text-lg">
          {label}
        </span>
      )}
      <div className="grid min-w-0 grid-cols-1 gap-2 min-[400px]:grid-cols-2">
        <input
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          className={fieldClass}
        />
        <input
          type="time"
          value={time}
          onChange={(e) => setTime(e.target.value)}
          className={fieldClass}
        />
      </div>
    </div>
  );
}
