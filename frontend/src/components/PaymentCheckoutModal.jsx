import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Building2, CreditCard, X } from 'lucide-react';
import { formatPrice } from '../lib/format';

const fieldClass =
  'w-full rounded-md border border-stone-300 bg-white px-3 py-2.5 text-base text-stone-900 outline-none placeholder:text-stone-400 focus:border-sky-500 focus:ring-1 focus:ring-sky-500';

const labelClass = 'mb-1 block text-sm font-medium text-stone-700';

export function PaymentCheckoutModal({ open, registration, userEmail, onClose, onConfirm, busy }) {
  const [email, setEmail] = useState(userEmail ?? '');
  const [method, setMethod] = useState('card');
  const [cardNumber, setCardNumber] = useState('');
  const [expiry, setExpiry] = useState('');
  const [cvc, setCvc] = useState('');
  const [cardName, setCardName] = useState('');
  const [country, setCountry] = useState('MA');
  const [error, setError] = useState('');

  useEffect(() => {
    if (open) {
      setEmail(userEmail ?? '');
      setMethod('card');
      setCardNumber('');
      setExpiry('');
      setCvc('');
      setCardName('');
      setCountry('MA');
      setError('');
    }
  }, [open, userEmail]);

  useEffect(() => {
    if (!open) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKeyDown = (e) => {
      if (e.key === 'Escape' && !busy) onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener('keydown', onKeyDown);
    };
  }, [open, busy, onClose]);

  if (!open || !registration) return null;

  const amountLabel = formatPrice(registration.amount);
  const eventTitle = registration.event?.title ?? 'Événement';

  const formatCardNumber = (value) => {
    const digits = value.replace(/\D/g, '').slice(0, 16);
    return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
  };

  const formatExpiry = (value) => {
    const digits = value.replace(/\D/g, '').slice(0, 4);
    if (digits.length <= 2) return digits;
    return `${digits.slice(0, 2)} / ${digits.slice(2)}`;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    if (!email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      setError('Veuillez saisir une adresse e-mail valide.');
      return;
    }
    if (method === 'card') {
      const digits = cardNumber.replace(/\s/g, '');
      if (digits.length < 13) {
        setError('Numéro de carte invalide.');
        return;
      }
      if (!/^\d{2}\s*\/\s*\d{2}$/.test(expiry.trim())) {
        setError('Date d\'expiration invalide (MM / AA).');
        return;
      }
      if (cvc.replace(/\D/g, '').length < 3) {
        setError('CVC invalide.');
        return;
      }
      if (!cardName.trim()) {
        setError('Nom du titulaire requis.');
        return;
      }
    }
    try {
      await onConfirm({
        email: email.trim(),
        method,
        card_holder: cardName.trim() || null,
        country,
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Le paiement a échoué.');
    }
  };

  return createPortal(
    <div
      className="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto bg-stone-900/50 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="payment-checkout-title"
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      <div
        className="my-auto w-full max-w-lg shrink-0 font-[system-ui,sans-serif]"
        onClick={(e) => e.stopPropagation()}
        role="document"
      >
        <div className="max-h-[calc(100dvh-2rem)] overflow-y-auto overflow-x-hidden rounded-xl bg-white shadow-2xl ring-1 ring-stone-200">
          <div className="flex items-center justify-between border-b border-stone-200 px-5 py-4">
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-stone-500">Paiement</p>
              <h2 id="payment-checkout-title" className="text-lg font-semibold text-stone-900">
                {eventTitle}
              </h2>
              <p className="text-sm text-stone-600">{amountLabel}</p>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg p-2 text-stone-500 hover:bg-stone-100"
              aria-label="Fermer"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6 p-5 text-base">
            <section>
              <h3 className="mb-3 text-sm font-semibold text-stone-900">Coordonnées</h3>
              <label className="block">
                <span className={labelClass}>E-mail</span>
                <input
                  type="email"
                  className={fieldClass}
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="nom@exemple.com"
                  autoComplete="email"
                />
              </label>
            </section>

            <section>
              <h3 className="mb-3 text-sm font-semibold text-stone-900">Moyen de paiement</h3>
              <div className="overflow-hidden rounded-lg border border-stone-200">
                <label
                  className={`flex cursor-pointer items-center gap-3 border-b border-stone-200 px-4 py-3 ${
                    method === 'card' ? 'bg-stone-50' : 'bg-white'
                  }`}
                >
                  <input
                    type="radio"
                    name="payment-method"
                    checked={method === 'card'}
                    onChange={() => setMethod('card')}
                    className="h-4 w-4 accent-sky-600"
                  />
                  <CreditCard className="h-5 w-5 text-stone-600" />
                  <span className="font-medium text-stone-900">Carte</span>
                </label>

                {method === 'card' && (
                  <div className="space-y-4 border-b border-stone-200 bg-white p-4">
                    <div>
                      <span className={labelClass}>Informations de la carte</span>
                      <div className="relative">
                        <input
                          type="text"
                          inputMode="numeric"
                          className={`${fieldClass} pr-28`}
                          placeholder="1234 1234 1234 1234"
                          value={cardNumber}
                          onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
                          autoComplete="cc-number"
                        />
                        <div className="pointer-events-none absolute right-3 top-1/2 flex -translate-y-1/2 gap-1 text-[10px] font-bold text-stone-400">
                          <span>VISA</span>
                          <span>MC</span>
                          <span>AMEX</span>
                        </div>
                      </div>
                      <div className="mt-2 grid grid-cols-2 gap-2">
                        <input
                          type="text"
                          inputMode="numeric"
                          className={fieldClass}
                          placeholder="MM / AA"
                          value={expiry}
                          onChange={(e) => setExpiry(formatExpiry(e.target.value))}
                          autoComplete="cc-exp"
                        />
                        <input
                          type="text"
                          inputMode="numeric"
                          className={fieldClass}
                          placeholder="CVC"
                          value={cvc}
                          onChange={(e) => setCvc(e.target.value.replace(/\D/g, '').slice(0, 4))}
                          autoComplete="cc-csc"
                        />
                      </div>
                    </div>

                    <label className="block">
                      <span className={labelClass}>Nom du titulaire de la carte</span>
                      <input
                        type="text"
                        className={fieldClass}
                        placeholder="Nom complet"
                        value={cardName}
                        onChange={(e) => setCardName(e.target.value)}
                        autoComplete="cc-name"
                      />
                    </label>

                    <label className="block">
                      <span className={labelClass}>Pays ou région</span>
                      <select
                        className={fieldClass}
                        value={country}
                        onChange={(e) => setCountry(e.target.value)}
                      >
                        <option value="MA">Maroc</option>
                        <option value="FR">France</option>
                        <option value="ES">Espagne</option>
                        <option value="BE">Belgique</option>
                        <option value="CA">Canada</option>
                        <option value="US">États-Unis</option>
                      </select>
                    </label>
                  </div>
                )}

                <label
                  className={`flex cursor-pointer items-center justify-between gap-3 px-4 py-3 ${
                    method === 'bank' ? 'bg-stone-50' : 'bg-white'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <input
                      type="radio"
                      name="payment-method"
                      checked={method === 'bank'}
                      onChange={() => setMethod('bank')}
                      className="h-4 w-4 accent-sky-600"
                    />
                    <Building2 className="h-5 w-5 text-stone-600" />
                    <span className="font-medium text-stone-900">Banque</span>
                  </div>
                  <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                    Paiement sécurisé
                  </span>
                </label>

                {method === 'bank' && (
                  <div className="border-t border-stone-200 bg-white p-4">
                    <p className="text-sm text-stone-600">
                      Vous serez redirigé vers votre banque pour confirmer le paiement de{' '}
                      <strong>{amountLabel}</strong>.
                    </p>
                  </div>
                )}
              </div>
            </section>

            {error && <p className="text-sm text-red-600">{error}</p>}

            <button
              type="submit"
              disabled={busy}
              className="w-full rounded-lg bg-sky-500 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-sky-600 disabled:opacity-60"
            >
              {busy ? 'Traitement…' : `Payer ${amountLabel}`}
            </button>
          </form>
        </div>
      </div>
    </div>,
    document.body,
  );
}
