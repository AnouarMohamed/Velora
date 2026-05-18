import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';

const triggerClass =
  'glass-panel flex w-full min-w-0 max-w-full items-center justify-between gap-2 rounded-xl px-3 py-2.5 pr-3 text-left text-base text-stone-800 outline-none focus:border-brand-500/50';

const listClass =
  'absolute left-0 right-0 z-30 mt-1 max-h-52 w-full max-w-full overflow-x-hidden overflow-y-auto rounded-xl border border-white/50 bg-white/95 p-1 shadow-velora-lg backdrop-blur-md';

export function MobileSelect({
  label,
  value,
  onChange,
  options,
  placeholder = 'Choisir…',
  required = false,
}) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef(null);
  const listId = useId();

  useEffect(() => {
    if (!open) return;
    const onPointer = (e) => {
      if (!rootRef.current?.contains(e.target)) setOpen(false);
    };
    const onKey = (e) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onPointer);
    document.addEventListener('touchstart', onPointer);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onPointer);
      document.removeEventListener('touchstart', onPointer);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const selected = options.find((o) => o.value === value);

  return (
    <div ref={rootRef} className="relative block min-w-0 space-y-1.5">
      {label && (
        <span className="text-base font-medium tracking-wide text-stone-700 sm:text-lg">
          {label}
        </span>
      )}

      {required && (
        <input
          tabIndex={-1}
          aria-hidden
          className="pointer-events-none absolute h-0 w-0 opacity-0"
          value={value}
          onChange={() => {}}
          required
        />
      )}

      <button
        type="button"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listId}
        className={triggerClass}
        onClick={() => setOpen((o) => !o)}
      >
        <span className={`min-w-0 flex-1 ${value ? 'truncate' : 'text-stone-500'}`}>
          {selected?.label ?? placeholder}
        </span>
        <ChevronDown
          className={`h-5 w-5 shrink-0 text-stone-500 transition ${open ? 'rotate-180' : ''}`}
        />
      </button>

      {open && (
        <ul id={listId} role="listbox" className={listClass}>
          {!required && (
            <li role="option">
              <button
                type="button"
                className="w-full rounded-lg px-3 py-2.5 text-left text-sm text-stone-600 hover:bg-brand-50/80 sm:text-base"
                onClick={() => {
                  onChange('');
                  setOpen(false);
                }}
              >
                {placeholder}
              </button>
            </li>
          )}
          {options.map((opt) => (
            <li key={opt.value} role="option" aria-selected={value === opt.value}>
              <button
                type="button"
                className={`w-full rounded-lg px-3 py-2.5 text-left text-sm leading-snug break-words hover:bg-brand-50/80 sm:text-base ${
                  value === opt.value ? 'bg-brand-50/90 font-medium text-brand-900' : 'text-stone-800'
                }`}
                onClick={() => {
                  onChange(opt.value);
                  setOpen(false);
                }}
              >
                {opt.label}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
