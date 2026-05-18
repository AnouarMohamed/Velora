import { Link } from 'react-router-dom';
import {
  ArrowRight,
  Building2,
  Cake,
  GraduationCap,
  Heart,
  Mic2,
  Sparkles,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { VeloraLogo } from '../components/VeloraLogo';
import { Button } from '../components/ui';

const eventTypes = [
  {
    icon: Heart,
    title: 'Mariages & célébrations',
    desc: 'Cérémonies, réceptions et moments d’exception sur mesure.',
  },
  {
    icon: Building2,
    title: 'Galas & soirées',
    desc: 'Dîners de gala, soirées prestigieuses et événements VIP.',
  },
  {
    icon: Mic2,
    title: 'Conférences & séminaires',
    desc: 'Congrès, formations et rencontres professionnelles.',
  },
  {
    icon: GraduationCap,
    title: 'Lancements & salons',
    desc: 'Inaugurations, expositions et présentations de marque.',
  },
  {
    icon: Cake,
    title: 'Fêtes privées',
    desc: 'Anniversaires, baptêmes et réunions familiales.',
  },
  {
    icon: Sparkles,
    title: 'Événements d’entreprise',
    desc: 'Team building, conventions et soirées collaborateurs.',
  },
];

export function HomePage() {
  const { user } = useAuth();

  return (
    <div>
      <section className="glass-panel relative mx-auto max-w-3xl overflow-hidden rounded-2xl px-5 py-8 sm:px-8 sm:py-10 max-md:px-4 max-md:py-6">
        <div className="relative z-10">
          <div className="max-md:flex max-md:justify-center max-md:overflow-hidden">
            <VeloraLogo size="box" linkTo={false} showText centered />
          </div>

          <h1 className="font-display mt-4 text-2xl font-medium leading-snug text-stone-900 sm:text-3xl max-md:text-center max-md:text-xl">
            L&apos;art de{' '}
            <span className="text-gradient-gold">créer des moments</span> inoubliables
          </h1>

          <p className="mt-3 text-base font-normal leading-relaxed tracking-wide text-stone-700 sm:text-lg max-md:text-center max-md:text-sm">
            VELORA connecte clients, organisateurs, participants et administrateurs dans une
            expérience élégante et moderne.
          </p>

          <div className="mt-5 flex flex-wrap gap-2 max-md:justify-center">
            {user ? (
              <Link to="/dashboard">
                <Button className="max-md:w-full">Accéder au tableau de bord <ArrowRight className="h-4 w-4" /></Button>
              </Link>
            ) : (
              <Link to="/register">
                <Button className="max-md:w-full">Créer un compte</Button>
              </Link>
            )}
          </div>
        </div>
      </section>

      <section className="mt-10 max-md:mt-8">
        <div className="mb-6 text-center">
          <h2 className="font-display text-3xl font-medium text-stone-900 sm:text-4xl max-md:text-2xl">
            VELORA organise <span className="text-gradient-gold">tout type d&apos;événement</span>
          </h2>
          <p className="mx-auto mt-3 max-w-2xl text-lg leading-relaxed text-stone-700 max-md:text-base">
            De l&apos;intime au grand prestige, nous accompagnons chaque projet avec le même soin
            du détail, de la demande à la réception de vos invités.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {eventTypes.map(({ icon: Icon, title, desc }) => (
            <div
              key={title}
              className="glass-panel glass-panel-hover flex flex-col gap-3 p-6 transition duration-300 max-md:p-5"
            >
              <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-brand-400/30 bg-white/50 max-md:h-11 max-md:w-11">
                <Icon className="h-6 w-6 text-brand-700 max-md:h-5 max-md:w-5" />
              </div>
              <h3 className="font-display text-xl font-medium text-stone-900 max-md:text-lg">{title}</h3>
              <p className="text-base leading-relaxed text-stone-700 max-md:text-sm">{desc}</p>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}
