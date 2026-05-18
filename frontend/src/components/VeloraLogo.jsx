import { Link } from 'react-router-dom';

const sizes = {
  ticket: {
    img: 'h-11 w-11 min-w-11 max-md:h-7 max-md:w-7 max-md:min-w-0',
    text: 'text-lg tracking-[0.12em] max-md:text-[0.65rem] max-md:tracking-[0.08em]',
    pull: '-ml-1 max-md:-ml-0.5',
  },
  sm: { img: 'h-11 w-11 min-w-11', text: 'text-lg tracking-[0.12em]', pull: '-ml-1' },
  md: { img: 'h-14 w-14 min-w-14', text: 'text-xl tracking-[0.14em]', pull: '-ml-2' },
  lg: { img: 'h-20 w-20 min-w-20', text: 'text-2xl tracking-[0.16em]', pull: '-ml-3' },
  xl: { img: 'h-28 w-28 min-w-28', text: 'text-3xl tracking-[0.18em]', pull: '-ml-4' },
  hero: { img: 'h-32 w-32 min-w-32 sm:h-36 sm:w-36', text: 'text-4xl tracking-[0.18em]', pull: '-ml-5' },
  box: {
    img: 'h-36 w-36 min-w-36 sm:h-44 sm:w-44 max-md:h-14 max-md:w-14 max-md:min-w-0',
    text: 'translate-y-1 text-5xl tracking-[0.2em] sm:translate-y-1.5 sm:text-6xl max-md:translate-y-0 max-md:text-xl max-md:tracking-[0.12em]',
    pull: '-ml-3 sm:-ml-4 max-md:-ml-0.5',
  },
};

export function VeloraLogo({
  size = 'md',
  showText = true,
  linkTo = '/',
  centered = false,
}) {
  const s = sizes[size];
  const content = (
    <>
      <img
        src="/images/velora-logo.png"
        alt="VELORA"
        width={256}
        height={256}
        decoding="async"
        className={`logo-velora ${s.img} shrink-0 object-contain`}
      />
      {showText && (
        <span
          className={`font-display ${s.text} ${s.pull} shrink font-semibold leading-none text-gradient-gold`}
        >
          VELORA
        </span>
      )}
    </>
  );

  const wrapClass = centered
    ? 'velora-brand mx-auto inline-flex max-w-full min-w-0 flex-row items-center justify-center'
    : 'velora-brand inline-flex max-w-full min-w-0 items-center';

  if (linkTo !== false && linkTo !== undefined) {
    return (
      <Link to={linkTo} className={`${wrapClass} transition hover:opacity-90`}>
        {content}
      </Link>
    );
  }

  return <div className={wrapClass}>{content}</div>;
}
