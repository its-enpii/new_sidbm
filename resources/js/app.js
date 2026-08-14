import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

window.route = window.route || function(name, params) {
    const routes = {
        'admin.migrations.store': '/admin/migrations',
        'admin.migrations.show': (id) => `/admin/migrations/${id}`,
        'admin.payment-gateways.tripay': '/admin/payment-gateways/tripay',
        'admin.payment-gateways.active-gateway': '/admin/payment-gateways/active',
        'admin.payment-gateways.active': '/admin/payment-gateways/active',
        'admin.payment-gateways.xendit': '/admin/payment-gateways/xendit',
        'admin.payment-gateways.duitku': '/admin/payment-gateways/duitku',
        'onboarding.opening-balances.store': '/onboarding/opening-balances',
        'membership.members.import': '/membership/members/import',
        'membership.groups.import': '/membership/groups/import',
        'onboarding.active-loans.import': '/onboarding/active-loans',
        'onboarding.templates.download': (type) => `/onboarding/templates/${type}`,
    };
    const target = routes[name];
    if (typeof target === 'function') return target(params);
    if (target) return target;
    return `/${name.replace(/\./g, '/')}`;
};

createInertiaApp({
    title: (title) => title ? `${title} - SIDBM Next` : 'SIDBM Next',
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.globalProperties.route = window.route;
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
