import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import inertia from '@inertiajs/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { platformRefreshGlobs } from './vendor/nreljed/platform/platform.aliases.js';
import { blogRefreshGlobs } from './vendor/nreljed/blog/blog.aliases.js';
import {
    marketingAliases,
    marketingRefreshGlobs,
} from './vendor/nreljed/marketing-module/marketing.aliases.js';
import { packageDedupes } from './vendor/nreljed/platform/vite.dedupe.js';

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
                'vendor/nreljed/shop-module/src/Resources/js/**',
                ...platformRefreshGlobs(),
                ...blogRefreshGlobs(),
                ...marketingRefreshGlobs(),
            ],
        }),
        react(),
        inertia(),
        tailwindcss(),
    ],
    resolve: {
        dedupe: packageDedupes,
        alias: {
            '@nreljed/platform': path.resolve(__dirname, './vendor/nreljed/platform/resources/js'),
            '@nreljed/platform/*': path.resolve(__dirname, './vendor/nreljed/platform/resources/js'),
            '@nreljed/blog': path.resolve(__dirname, './vendor/nreljed/blog/src/Resources/js'),
            '@nreljed/blog/*': path.resolve(__dirname, './vendor/nreljed/blog/src/Resources/js'),
            '@nreljed/shop-module': path.resolve(__dirname, './vendor/nreljed/shop-module/src/Resources/js'),
            '@nreljed/shop-module/*': path.resolve(__dirname, './vendor/nreljed/shop-module/src/Resources/js'),
            '@nreljed/marketing-module': path.resolve(__dirname, './vendor/nreljed/marketing-module/src/Resources/js'),
            '@nreljed/marketing-module/*': path.resolve(__dirname, './vendor/nreljed/marketing-module/src/Resources/js'),
            ...marketingAliases(),
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
