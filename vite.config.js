import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
           server: {
        https: false,
        host: '127.0.0.1',
        port: 5174,
        strictPort: true,
        origin: 'http://127.0.0.1:5174',
        // ADD THIS BLOCK:
        cors: {
            origin: 'http://127.0.0.1:8000', 
        },
    },
});