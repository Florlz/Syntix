import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        warmup: {
            clientFiles: [
                'resources/js/Pages/**/*.jsx',
                'resources/js/Layouts/**/*.jsx',
                'resources/js/Components/**/*.jsx',
            ],
        },
        watch: {
            usePolling: true,
            interval: 500,
            ignored: [
                '**/.git/**',
                '**/.superpowers/**',
                '**/bootstrap/cache/**',
                '**/node_modules/**',
                '**/public/build/**',
                '**/storage/framework/**',
                '**/vendor/**',
            ],
        },
        cors: {
            origin: [
                'http://localhost:8000',
            ],
        },
        hmr: {
            host: 'localhost',
        },
        origin: 'http://localhost:5173',
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: [
                'favicon.ico',
                'icons/icon.svg',
                'icons/icon-192.png',
                'icons/icon-512.png',
            ],
            manifest: {
                name: 'Syntix',
                short_name: 'Syntix',
                description: 'Syntix progressive web application',
                theme_color: '#111827',
                background_color: '#f9fafb',
                display: 'standalone',
                scope: '/',
                start_url: '/',
                icons: [
                    {
                        src: '/icons/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                cleanupOutdatedCaches: true,
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: ({ url, request }) =>
                            request.method === 'GET'
                            && url.pathname.startsWith('/events/')
                            && (url.pathname.endsWith('/scoreboard')
                                || url.pathname.endsWith('/bracket')),
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'syntix-public-scoreboards',
                            networkTimeoutSeconds: 3,
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            expiration: {
                                maxEntries: 20,
                                maxAgeSeconds: 300,
                            },
                        },
                    },
                ],
            },
        }),
    ],
});
