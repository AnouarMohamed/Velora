import { jsx as _jsx, Fragment as _Fragment, jsxs as _jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useState } from 'react';
import api from '../../lib/api';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { Button, Card, Input, PageHeader } from '../../components/ui';
function formatAdminUserApiError(err) {
    const ax = err;
    const data = ax.response?.data;
    if (data?.errors) {
        const parts = Object.values(data.errors).flatMap((v) => (Array.isArray(v) ? v : [String(v)]));
        if (parts.length)
            return parts.join(' ');
    }
    return data?.message ?? 'Une erreur est survenue.';
}
export function AdminUsersPage() {
    const [users, setUsers] = useState([]);
    const [form, setForm] = useState({ name: '', email: '', password: '', role: 'participant' });
    const [editingUser, setEditingUser] = useState(null);
    const [editForm, setEditForm] = useState({
        name: '',
        email: '',
        password: '',
        role: 'participant',
    });
    const [editSaving, setEditSaving] = useState(false);
    const [editError, setEditError] = useState('');
    const [createError, setCreateError] = useState('');
    const [creating, setCreating] = useState(false);
    const [userToDelete, setUserToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState('');
    const load = async () => {
        const r = await api.get('/admin/users');
        setUsers(r.data.data);
    };
    useEffect(() => {
        void load();
    }, []);
    const create = async (e) => {
        e.preventDefault();
        setCreateError('');
        setCreating(true);
        try {
            await api.post('/admin/users', form);
            setForm({ name: '', email: '', password: '', role: 'participant' });
            await load();
        }
        catch (err) {
            setCreateError(formatAdminUserApiError(err));
        }
        finally {
            setCreating(false);
        }
    };
    const startEdit = (u) => {
        setEditingUser(u);
        setEditForm({ name: u.name, email: u.email, password: '', role: u.role });
        setEditError('');
    };
    const cancelEdit = () => {
        setEditingUser(null);
        setEditError('');
    };
    const saveEdit = async (e) => {
        e.preventDefault();
        if (!editingUser)
            return;
        setEditSaving(true);
        setEditError('');
        try {
            const payload = {
                name: editForm.name,
                email: editForm.email,
                role: editForm.role,
            };
            if (editForm.password.trim()) {
                payload.password = editForm.password;
            }
            await api.patch(`/admin/users/${editingUser.id}`, payload);
            cancelEdit();
            await load();
        }
        catch (err) {
            setEditError(formatAdminUserApiError(err));
        }
        finally {
            setEditSaving(false);
        }
    };
    const requestDelete = (u) => {
        setDeleteError('');
        setUserToDelete(u);
    };
    const cancelDelete = useCallback(() => {
        if (deleting)
            return;
        setUserToDelete(null);
        setDeleteError('');
    }, [deleting]);
    const confirmDelete = async () => {
        if (!userToDelete)
            return;
        setDeleting(true);
        setDeleteError('');
        try {
            await api.delete(`/admin/users/${userToDelete.id}`);
            if (editingUser?.id === userToDelete.id)
                cancelEdit();
            setUserToDelete(null);
            await load();
        }
        catch (err) {
            setDeleteError(formatAdminUserApiError(err));
        }
        finally {
            setDeleting(false);
        }
    };
    return (_jsxs("div", { className: "space-y-8", children: [_jsx(ConfirmDialog, { open: userToDelete !== null, title: "Supprimer l'utilisateur ?", cancelLabel: "Annuler", confirmLabel: "Supprimer", onCancel: cancelDelete, onConfirm: () => void confirmDelete(), busy: deleting, children: userToDelete && (_jsxs(_Fragment, { children: [_jsxs("p", { children: ["Vous \u00EAtes sur le point de supprimer ", _jsx("strong", { className: "font-medium text-stone-900", children: userToDelete.name }), userToDelete.email ? (_jsxs(_Fragment, { children: [' ', "(", _jsx("span", { className: "break-all text-stone-800", children: userToDelete.email }), ")"] })) : null, "."] }), _jsx("p", { className: "mt-3 text-stone-600", children: "Cette action est irr\u00E9versible." }), deleteError ? _jsx("p", { className: "mt-4 text-sm text-red-800", children: deleteError }) : null] })) }), _jsx(PageHeader, { title: "Gestion des utilisateurs" }), _jsxs(Card, { children: [_jsx("h3", { className: "font-semibold", children: "Cr\u00E9er un utilisateur" }), _jsxs("form", { onSubmit: create, className: "mt-4 grid gap-4 sm:grid-cols-2", children: [_jsx(Input, { label: "Nom", value: form.name, onChange: (e) => setForm({ ...form, name: e.target.value }), required: true }), _jsx(Input, { label: "Email", type: "email", value: form.email, onChange: (e) => setForm({ ...form, email: e.target.value }), required: true }), _jsx(Input, { label: "Mot de passe", type: "password", value: form.password, onChange: (e) => setForm({ ...form, password: e.target.value }), required: true }), _jsxs("label", { className: "block space-y-1.5 text-sm", children: ["R\u00F4le", _jsxs("select", { className: "glass-panel w-full px-4 py-2.5 text-stone-800", value: form.role, onChange: (e) => setForm({ ...form, role: e.target.value }), children: [_jsx("option", { value: "admin", children: "Admin" }), _jsx("option", { value: "organizer", children: "Organisateur" }), _jsx("option", { value: "participant", children: "Participant" }), _jsx("option", { value: "client", children: "Client" })] })] }), createError && _jsx("p", { className: "sm:col-span-2 text-sm text-red-700", children: createError }), _jsx(Button, { type: "submit", className: "sm:col-span-2", disabled: creating, children: creating ? 'Création…' : 'Créer' })] })] }), _jsx("div", { className: "glass-panel overflow-x-auto", children: _jsxs("table", { className: "w-full text-left text-sm", children: [_jsx("thead", { className: "border-b border-white/10 bg-white/5 text-stone-400", children: _jsxs("tr", { children: [_jsx("th", { className: "px-4 py-3", children: "Nom" }), _jsx("th", { className: "px-4 py-3", children: "Email" }), _jsx("th", { className: "px-4 py-3", children: "R\u00F4le" }), _jsx("th", { className: "px-4 py-3" })] }) }), _jsx("tbody", { children: users.length === 0 ? (_jsx("tr", { children: _jsx("td", { colSpan: 4, className: "px-4 py-8 text-center text-stone-500", children: "Aucun utilisateur pour le moment. Cr\u00E9ez-en un avec le formulaire ci-dessus." }) })) : (users.map((u) => (_jsxs("tr", { className: "border-t border-white/10", children: [_jsx("td", { className: "px-4 py-3", children: u.name }), _jsx("td", { className: "px-4 py-3", children: u.email }), _jsx("td", { className: "px-4 py-3", children: u.role }), _jsx("td", { className: "px-4 py-3", children: _jsxs("div", { className: "flex flex-wrap gap-2", children: [_jsx(Button, { type: "button", variant: "secondary", onClick: () => startEdit(u), children: "Modifier" }), _jsx(Button, { type: "button", variant: "danger", onClick: () => requestDelete(u), children: "Supprimer" })] }) })] }, u.id)))) })] }) }), editingUser && (_jsxs(Card, { children: [_jsxs("h3", { className: "font-semibold", children: ["Modifier l'utilisateur ", _jsxs("span", { className: "font-normal text-stone-500", children: ["(", editingUser.email, ")"] })] }), _jsxs("form", { onSubmit: saveEdit, className: "mt-4 grid gap-4 sm:grid-cols-2", children: [_jsx(Input, { label: "Nom", value: editForm.name, onChange: (e) => setEditForm({ ...editForm, name: e.target.value }), required: true }), _jsx(Input, { label: "Email", type: "email", value: editForm.email, onChange: (e) => setEditForm({ ...editForm, email: e.target.value }), required: true }), _jsx("div", { className: "sm:col-span-2", children: _jsx(Input, { label: "Nouveau mot de passe", type: "password", value: editForm.password, onChange: (e) => setEditForm({ ...editForm, password: e.target.value }), placeholder: "Laisser vide pour ne pas changer" }) }), _jsxs("label", { className: "block space-y-1.5 text-sm sm:col-span-2", children: ["R\u00F4le", _jsxs("select", { className: "glass-panel w-full px-4 py-2.5 text-stone-800", value: editForm.role, onChange: (e) => setEditForm({ ...editForm, role: e.target.value }), children: [_jsx("option", { value: "admin", children: "Admin" }), _jsx("option", { value: "organizer", children: "Organisateur" }), _jsx("option", { value: "participant", children: "Participant" }), _jsx("option", { value: "client", children: "Client" })] })] }), editError && _jsx("p", { className: "sm:col-span-2 text-sm text-red-700", children: editError }), _jsxs("div", { className: "flex flex-wrap gap-2 sm:col-span-2", children: [_jsx(Button, { type: "button", variant: "ghost", onClick: cancelEdit, children: "Annuler" }), _jsx(Button, { type: "submit", disabled: editSaving, children: editSaving ? 'Enregistrement…' : 'Enregistrer les modifications' })] })] })] }))] }));
}
