import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'react-hot-toast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <Toaster
                    position="top-right"
                    toastOptions={{
                        duration: 3000,
                        style: {
                            background: '#1a1d2e',
                            color: '#f5f5f0',
                            border: '2px solid #d4af37',
                            borderRadius: '12px',
                            padding: '16px',
                        },
                        success: {
                            iconTheme: {
                                primary: '#d4af37',
                                secondary: '#f5f5f0',
                            },
                        },
                        error: {
                            iconTheme: {
                                primary: '#ef4444',
                                secondary: '#f5f5f0',
                            },
                        },
                    }}
                />
            </>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
