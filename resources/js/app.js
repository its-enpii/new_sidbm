import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

createInertiaApp({
    title: (title) => title ? `${title} - SIDBM Next` : 'SIDBM Next',
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.mount(el);

        window.addEventListener('online', () => {
            console.log('Status: Online');
        });
        window.addEventListener('offline', () => {
            console.warn('Status: Offline');
        });
    },
    progress: {
        color: 'var(--color-secondary)',
    },
});
