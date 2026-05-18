export function Card({ children, className = '' }) {
  return <div className={`glass-panel p-6 max-md:p-4 ${className}`}>{children}</div>;
}

export function Button({ children, variant = 'primary', className = '', ...props }) {
  const base =
    'inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-lg font-medium tracking-wide transition-all duration-300 disabled:opacity-50 max-md:px-4 max-md:py-2.5 max-md:text-base';
  const variants = {
    primary:
      'border border-brand-500/45 bg-white/45 text-stone-900 shadow-velora backdrop-blur-md hover:border-brand-500/65 hover:bg-white/60 hover:shadow-velora-lg',
    secondary:
      'border border-white/50 bg-white/30 text-stone-800 backdrop-blur-md hover:border-brand-400/50 hover:bg-white/50',
    danger: 'border border-red-300/60 bg-red-50/80 text-red-800 hover:bg-red-100/90',
    ghost: 'border-transparent bg-transparent text-stone-600 hover:bg-white/60 hover:text-brand-700',
  };
  return (
    <button type="button" className={`${base} ${variants[variant]} ${className}`} {...props}>
      {children}
    </button>
  );
}

export function Input({ label, className = '', ...props }) {
  return (
    <label className="block space-y-1.5">
      {label && (
        <span className="text-lg font-medium tracking-wide text-stone-700 max-md:text-base">
          {label}
        </span>
      )}
      <input
        className={`glass-panel w-full min-w-0 px-4 py-3 text-lg text-stone-900 outline-none placeholder:text-stone-500 focus:border-brand-500/50 max-md:px-3 max-md:py-2.5 max-md:text-base ${className}`}
        {...props}
      />
    </label>
  );
}

export function Textarea({ label, className = '', ...props }) {
  return (
    <label className="block space-y-1.5">
      {label && (
        <span className="text-lg font-medium tracking-wide text-stone-700 max-md:text-base">
          {label}
        </span>
      )}
      <textarea
        className={`glass-panel w-full min-w-0 px-4 py-3 text-lg text-stone-900 outline-none placeholder:text-stone-500 focus:border-brand-500/50 max-md:px-3 max-md:py-2.5 max-md:text-base ${className}`}
        {...props}
      />
    </label>
  );
}

export function Badge({ children, tone = 'default', size = 'md', className = '' }) {
  const tones = {
    default: 'border border-stone-300/60 bg-white/70 text-stone-600',
    success: 'border border-emerald-300/60 bg-emerald-50/80 text-emerald-800',
    warning: 'border border-brand-400/40 bg-brand-50/80 text-brand-800',
    danger: 'border border-red-300/60 bg-red-50/80 text-red-800',
  };
  const sizes = {
    sm: 'px-2.5 py-1 text-xs leading-tight',
    md: 'px-3 py-1 text-sm',
  };
  return (
    <span
      className={`inline-flex w-fit shrink-0 rounded-full font-medium tracking-wide ${sizes[size]} ${tones[tone]} ${className}`}
    >
      {children}
    </span>
  );
}

export function PageHeader({ title, subtitle, action }) {
  return (
    <div className="mb-8 flex items-end justify-between gap-4 max-md:mb-6 max-md:flex-col max-md:items-stretch max-md:gap-3">
      <div className="min-w-0 flex-1">
        <h1 className="font-display text-5xl font-medium tracking-wide text-stone-900 max-md:text-2xl">
          {title}
        </h1>
        {subtitle && (
          <p className="mt-2 text-xl font-normal tracking-wide text-stone-700 max-md:mt-1 max-md:text-base">
            {subtitle}
          </p>
        )}
      </div>
      {action && (
        <div className="flex shrink-0 flex-wrap gap-2 max-md:w-full">{action}</div>
      )}
    </div>
  );
}
