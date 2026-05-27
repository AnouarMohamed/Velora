import axios from 'axios';

function transformRoles(obj, fromRole, toRole) {
  if (obj === null || obj === undefined) return obj;
  if (typeof obj !== 'object') return obj;

  if (Array.isArray(obj)) {
    return obj.map(item => transformRoles(item, fromRole, toRole));
  }

  const newObj = {};
  for (const key in obj) {
    if (Object.prototype.hasOwnProperty.call(obj, key)) {
      let value = obj[key];
      if (key === 'role' && value === fromRole) {
        value = toRole;
      } else {
        value = transformRoles(value, fromRole, toRole);
      }
      newObj[key] = value;
    }
  }
  return newObj;
}

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api',
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    
    if (config.params) {
        config.params = transformRoles(config.params, 'organizer', 'organisateur');
    }
    
    if (config.data instanceof FormData) {
        if (config.data.has('role') && config.data.get('role') === 'organizer') {
            config.data.set('role', 'organisateur');
        }
    } else if (config.data) {
        config.data = transformRoles(config.data, 'organizer', 'organisateur');
        config.headers['Content-Type'] = 'application/json';
    }
    return config;
});

api.interceptors.response.use((response) => {
    if (response.data) {
        response.data = transformRoles(response.data, 'organisateur', 'organizer');
    }
    return response;
}, (error) => {
    if (error.response?.status === 401) {
        localStorage.removeItem('token');
        if (!window.location.pathname.startsWith('/login')) {
            window.location.href = '/login';
        }
    }
    return Promise.reject(error);
});

export default api;
