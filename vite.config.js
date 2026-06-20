import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = import.meta.dirname ?? path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.tsx',
                'resources/js/booking-widget.tsx',
            ],
            refresh: [
                ...(typeof globalThis !== 'undefined' && globalThis.process?.env?.APP_ENV !== 'production'
                    ? [{ server: false, paths: ['app/Modules/Booking/Resources/js/**'] }]
                    : []),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@modules-properties': path.resolve(__dirname, './app/Modules/Properties/Resources/js'),
            '@modules-booking': path.resolve(__dirname, './app/Modules/Booking/Resources/js'),
            '@modules-ecommerce': path.resolve(__dirname, './app/Modules/ECommerce/Resources/js'),
            '@modules-posts': path.resolve(__dirname, './app/Modules/Posts/Resources/js'),
        },
    },
    build: {
        chunkSizeWarningLimit: 1700,
        minify: 'esbuild',
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/react')) return 'react-vendor';
                    if (id.includes('node_modules/@radix-ui')) return 'ui-vendor';
                    if (id.includes('node_modules/lucide-react')) return 'icons-vendor';
                    if (id.includes('node_modules/@inertiajs')) return 'inertia-vendor';
                    if (id.includes('node_modules/leaflet')) return 'leaflet-vendor';
                },
            },
        },
    },
});
