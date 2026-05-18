import { jsx as _jsx, Fragment as _Fragment } from "react/jsx-runtime";
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
export function ProtectedRoute({ children, roles, }) {
    const { user, loading, hasRole } = useAuth();
    if (loading) {
        return (_jsx("div", { className: "flex min-h-[50vh] items-center justify-center", children: _jsx("div", { className: "h-10 w-10 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" }) }));
    }
    if (!user)
        return _jsx(Navigate, { to: "/login", replace: true });
    if (roles && !roles.some((r) => hasRole(r))) {
        return _jsx(Navigate, { to: "/dashboard", replace: true });
    }
    return _jsx(_Fragment, { children: children });
}
