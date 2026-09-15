import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import inertia from '@inertiajs/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { platformAliases } from '../../packages/platform/platform.aliases.js';
import { blogAliases } from '../../packages/blog/blog.aliases.js';
import { packageDedupes } from '../../packages/platform/vite.dedupe.js';

const __dirname = import.meta.dirname ?? path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.tsx',
            ],
            refresh: [
                'resources/js/**',
                'app/**/Resources/js/**',
                '../../packages/shop-module/src/Resources/js/**',
                '../../packages/platform/resources/themes/**',
                '../../packages/platform/resources/js/**',
                '../../packages/blog/src/Resources/js/**',
            ],
        }),
        react(),
        inertia(),
        tailwindcss(),
    ],
    resolve: {
        dedupe: packageDedupes,
        alias: {
            ...platformAliases(),
            ...blogAliases(),
            '@dashboard-themes': path.resolve(__dirname, '../../packages/platform/resources/themes'),
            '@shop': path.resolve(__dirname, '../../packages/shop-module/src/Resources/js'),
            '@nreljed/platform': path.resolve(__dirname, '../../packages/platform/resources/js'),
            '@nreljed/platform/*': path.resolve(__dirname, '../../packages/platform/resources/js'),
            '@nreljed/blog': path.resolve(__dirname, '../../packages/blog/src/Resources/js'),
            '@nreljed/blog/*': path.resolve(__dirname, '../../packages/blog/src/Resources/js'),
        },
    },
    build: {
        chunkSizeWarningLimit: 1700,
        minify: 'esbuild',
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('Components/ui/')) return 'ui-kit';
                    if (id.includes('node_modules/react')) return 'react-vendor';
                    if (id.includes('node_modules/lucide-react')) return 'icons-vendor';
                    if (id.includes('node_modules/@inertiajs')) return 'inertia-vendor';
                    if (id.includes('node_modules/leaflet')) return 'leaflet-vendor';
                    if (id.includes('node_modules/@dnd-kit')) return 'dnd-vendor';
                    if (id.includes('node_modules/date-fns')) return 'date-vendor';
                    if (id.includes('node_modules/@zxcvbn-ts')) return 'zxcvbn-vendor';
                    if (id.includes('node_modules/react-phone-number-input') || id.includes('node_modules/libphonenumber-js')) return 'phone-vendor';
                    if (id.includes('node_modules/react-hook-form')) return 'forms-vendor';
                    if (id.includes('node_modules/@base-ui')) return 'baseui-vendor';
                },
            },
        },
    },
});