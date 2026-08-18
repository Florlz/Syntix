import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { registerSW } from 'virtual:pwa-register';
import { clearTheme } from './lib/theme';
const appName = import.meta.env.VITE_APP_NAME || 'Syntix';
if (import.meta.env.PROD) {
    registerSW({ immediate: true });
} else if ('serviceWorker' in navigator) {
    navigator.serviceWorker
        .getRegistrations()
        .then((registrations) =>
            Promise.all(registrations.map((registration) => registration.unregister())),
        );

    caches.keys().then((keys) =>
        Promise.all(
            keys
                .filter(
                    (key) =>
                        key.startsWith('syntix-') || key.startsWith('workbox-'),
                )
                .map((key) => caches.delete(key)),
        ),
    );
}
router.on('navigate', ({ detail }) => {
    if (detail.page.props.ui?.theme_scope !== 'admin') {
        clearTheme();
    }
});
createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
