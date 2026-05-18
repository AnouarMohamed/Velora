import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { formatPrice, formatPriceAmount } from '../lib/format';
export function PriceText({ value, size = 'lg', className = '' }) {
    const n = Number(value);
    if (n <= 0) {
        return _jsx("span", { className: className, children: "Gratuit" });
    }
    const amountClass = size === 'lg' ? 'text-2xl font-bold text-brand-900' : 'text-xl font-bold text-brand-900';
    const currencyClass = size === 'lg' ? 'ml-1 text-sm font-semibold text-stone-700' : 'ml-0.5 text-xs font-semibold text-stone-700';
    return (_jsxs("span", { className: className, children: [_jsx("span", { className: amountClass, children: formatPriceAmount(value) }), _jsx("span", { className: currencyClass, children: "MAD" })] }));
}
/** Prix sur une ligne (listes, tableaux). */
export function PriceInline({ value, className = '' }) {
    const n = Number(value);
    return _jsx("span", { className: className, children: n <= 0 ? 'Gratuit' : formatPrice(value) });
}
