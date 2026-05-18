import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { VeloraLogo } from '../components/VeloraLogo';
import { Button, Card, Input } from '../components/ui';
export function RegisterPage() {
    const { register } = useAuth();
    const navigate = useNavigate();
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'participant',
    });
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const submit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await register(form);
            navigate('/dashboard');
        }
        catch (err) {
            const data = err.response?.data;
            if (data?.errors?.email) {
                const emailErr = Object.values(data.errors.email).flat()[0];
                setError(typeof emailErr === 'string' ? emailErr : 'Cette adresse e-mail est déjà utilisée.');
            }
            else {
                setError(data?.message || 'Inscription impossible. Vérifiez les champs.');
            }
        }
        finally {
            setLoading(false);
        }
    };
    return (_jsx("div", { className: "mx-auto max-w-md", children: _jsxs(Card, { children: [_jsx("div", { className: "mb-8 flex justify-center", children: _jsx(VeloraLogo, { size: "xl", linkTo: "/", centered: true, showText: false }) }), _jsx("h1", { className: "font-display text-center text-3xl font-medium text-stone-900", children: "Inscription" }), _jsxs("form", { onSubmit: submit, className: "mt-6 space-y-4", children: [_jsx(Input, { label: "Nom", value: form.name, onChange: (e) => setForm({ ...form, name: e.target.value }), required: true }), _jsx(Input, { label: "Email", type: "email", value: form.email, onChange: (e) => setForm({ ...form, email: e.target.value }), required: true }), _jsxs("label", { className: "block space-y-1.5", children: [_jsx("span", { className: "text-lg font-medium text-stone-600", children: "R\u00F4le" }), _jsxs("select", { className: "select-field glass-panel w-full px-4 py-3 text-lg text-stone-800", value: form.role, onChange: (e) => setForm({ ...form, role: e.target.value }), children: [_jsx("option", { value: "participant", children: "Participant" }), _jsx("option", { value: "client", children: "Client" })] })] }), _jsx(Input, { label: "Mot de passe", type: "password", value: form.password, onChange: (e) => setForm({ ...form, password: e.target.value }), required: true }), _jsx(Input, { label: "Confirmer", type: "password", value: form.password_confirmation, onChange: (e) => setForm({ ...form, password_confirmation: e.target.value }), required: true }), error && _jsx("p", { className: "text-center text-base font-medium text-red-600", children: error }), _jsx(Button, { type: "submit", className: "w-full", disabled: loading, children: "Cr\u00E9er mon compte" })] }), _jsxs("p", { className: "mt-4 text-center text-sm text-stone-500", children: ["D\u00E9j\u00E0 inscrit ? ", _jsx(Link, { to: "/login", className: "font-medium text-brand-700 hover:underline", children: "Connexion" })] })] }) }));
}
