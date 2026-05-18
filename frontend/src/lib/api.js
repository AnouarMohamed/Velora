import axios from 'axios';
const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api',
    headers: { Accept: 'application/json' },
});
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    if (!(config.data instanceof FormData)) {
        config.headers['Content-Type'] = 'application/json';
    }
    return config;
});
api.interceptors.response.use((r) => r, (error) => {
    if (error.response?.status === 401) {
        localStorage.removeItem('token');
        if (!window.location.pathname.startsWith('/login')) {
            window.location.href = '/login';
        }
    }
    return Promise.reject(error);
});
export default api;
