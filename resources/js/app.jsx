import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'react-hot-toast';
import { themeColors } from './config/theme';

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
                            background: themeColors.navy900,
                            color: themeColors.surface,
                            border: `1px solid ${themeColors.ice500}`,
                            borderRadius: '12px',
                            padding: '16px',
                        },
                        success: {
                            iconTheme: {
                                primary: themeColors.ice500,
                                secondary: themeColors.navy900,
                            },
                        },
                        error: {
                            iconTheme: {
                                primary: themeColors.navy700,
                                secondary: themeColors.surface,
                            },
                        },
                    }}
                />
            </>
        );
    },
    progress: {
        color: themeColors.ice500,
    },
});
