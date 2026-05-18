import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { VeloraLogo } from '../components/VeloraLogo';
import { Button, Card, Input } from '../components/ui';
export function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('participant@demo.local');
    const [password, setPassword] = useState('password');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const submit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await login(email, password);
            navigate('/dashboard');
        }
        catch {
            setError('Identifiants invalides.');
        }
        finally {
            setLoading(false);
        }
    };
    return (_jsx("div", { className: "mx-auto max-w-md", children: _jsxs(Card, { children: [_jsx("div", { className: "mb-8 flex justify-center", children: _jsx(VeloraLogo, { size: "xl", linkTo: "/", centered: true, showText: false }) }), _jsx("h1", { className: "font-display text-center text-3xl font-medium text-stone-900", children: "Connexion" }), _jsxs("form", { onSubmit: submit, className: "mt-6 space-y-4", children: [_jsx(Input, { label: "Email", type: "email", value: email, onChange: (e) => setEmail(e.target.value), required: true }), _jsx(Input, { label: "Mot de passe", type: "password", value: password, onChange: (e) => setPassword(e.target.value), required: true }), error && _jsx("p", { className: "text-center text-sm text-red-600", children: error }), _jsx(Button, { type: "submit", className: "w-full", disabled: loading, children: loading ? 'Connexion…' : 'Se connecter' })] }), _jsxs("p", { className: "mt-4 text-center text-sm text-stone-500", children: ["Pas de compte ? ", _jsx(Link, { to: "/register", className: "font-medium text-brand-700 hover:underline", children: "S'inscrire" })] })] }) }));
}
