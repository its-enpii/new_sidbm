import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const vitePort = Number(process.env.VITE_PORT || 5173);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: `http://localhost:${vitePort}`,
        cors: {
            origin: [
                'http://localhost:8081',
                'http://127.0.0.1:8081',
                'http://localhost:8080',
                'http://127.0.0.1:8080',
            ],
        },
        hmr: {
            host: 'localhost',
            clientPort: vitePort,
        },
    },
});
