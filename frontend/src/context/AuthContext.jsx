import { jsx as _jsx } from "react/jsx-runtime";
import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import api from '../lib/api';
const AuthContext = createContext(null);
export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const refreshUser = useCallback(async () => {
        const token = localStorage.getItem('token');
        if (!token) {
            setUser(null);
            setLoading(false);
            return;
        }
        try {
            const { data } = await api.get('/user');
            setUser(data);
        }
        catch {
            localStorage.removeItem('token');
            setUser(null);
        }
        finally {
            setLoading(false);
        }
    }, []);
    useEffect(() => {
        refreshUser();
    }, [refreshUser]);
    const login = async (email, password) => {
        const { data } = await api.post('/login', { email, password });
        localStorage.setItem('token', data.token);
        setUser(data.user);
    };
    const register = async (payload) => {
        const { data } = await api.post('/register', payload);
        localStorage.setItem('token', data.token);
        setUser(data.user);
    };
    const logout = async () => {
        try {
            await api.post('/logout');
        }
        catch {
            /* ignore */
        }
        localStorage.removeItem('token');
        setUser(null);
    };
    const hasRole = (...roles) => {
        if (!user)
            return false;
        if (user.role === 'admin')
            return roles.includes('admin') || roles.includes('organizer');
        return roles.includes(user.role);
    };
    return (_jsx(AuthContext.Provider, { value: { user, loading, login, register, logout, hasRole, refreshUser }, children: children }));
}
export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx)
        throw new Error('useAuth must be used within AuthProvider');
    return ctx;
}
