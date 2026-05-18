import { jsx as _jsx } from "react/jsx-runtime";
/** Corrige les anciennes URLs absolues (localhost) pour le proxy Vite. */
export function resolveImageSrc(src) {
    if (!src)
        return null;
    if (src.startsWith('http://localhost/') || src.startsWith('https://localhost/')) {
        return src.replace(/^https?:\/\/localhost/, '');
    }
    if (src.startsWith('http://127.0.0.1:8000/')) {
        return src.replace(/^https?:\/\/127\.0\.0\.1:8000/, '');
    }
    return src;
}
export function EventImage({ src, alt, className = '' }) {
    const resolved = resolveImageSrc(src);
    if (!resolved)
        return null;
    return (_jsx("img", { src: resolved, alt: alt, className: `w-full object-cover ${className}`, loading: "lazy" }));
}
