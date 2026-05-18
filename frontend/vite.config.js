import { defineConfig, loadEnv } from 'vite';
import { cwd } from 'node:process';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, cwd(), '');
    const backendUrl = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000';

    return {
        plugins: [react(), tailwindcss()],
        server: {
            host: '0.0.0.0',
            port: 5173,
            proxy: {
                '/api': {
                    target: backendUrl,
                    changeOrigin: true,
                },
                '/storage': {
                    target: backendUrl,
                    changeOrigin: true,
                },
            },
        },
    };
});
