import './bootstrap';
import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import AppOfflineBanner from './Components/AppOfflineBanner.vue';
import DesktopTitleBar from './Components/DesktopTitleBar.vue';

window.route = window.route || function(name, params) {
    const routes = {
        'admin.migrations.store': '/admin/migrations',
        'admin.migrations.show': (id) => `/admin/migrations/${id}`,
        'admin.payment-gateways.tripay': '/admin/payment-gateways/tripay',
        'admin.payment-gateways.tripay.test': '/admin/payment-gateways/tripay/test',
        'admin.payment-gateways.active-gateway': '/admin/payment-gateways/active',
        'admin.payment-gateways.active': '/admin/payment-gateways/active',
        'admin.payment-gateways.xendit': '/admin/payment-gateways/xendit',
        'admin.payment-gateways.xendit.test': '/admin/payment-gateways/xendit/test',
        'admin.payment-gateways.duitku': '/admin/payment-gateways/duitku',
        'admin.payment-gateways.duitku.test': '/admin/payment-gateways/duitku/test',
        'accounting.period-close.year.close': '/accounting/period-close/year-close',
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

if (typeof window !== 'undefined' && window.desktopAPI?.onNavigate) {
    window.desktopAPI.onNavigate((url) => {
        if (url && typeof url === 'string') {
            router.visit(url);
        }
    });
}

createInertiaApp({
    title: (title) => title ? `${title} - SIDBM Next` : 'SIDBM Next',
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h('div', [h(DesktopTitleBar), h(AppOfflineBanner), h(App, props)]),
        });
        app.config.globalProperties.route = window.route;
        app.use(plugin);
        app.mount(el);

        router.on('exception', (event) => {
            const err = event.detail?.exception;
            if (err && (err.message === 'Network Error' || !navigator.onLine)) {
                event.preventDefault();
                window.dispatchEvent(
                    new CustomEvent('app:network-error', {
                        detail: { message: 'Navigasi gagal: koneksi server terputus.' },
                    }),
                );
            }
        });
    },
    progress: {
        color: 'var(--color-secondary)',
    },
});
