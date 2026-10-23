import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { i18nVue } from 'laravel-vue-i18n';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import type { SharedProps } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'OpenBengkel';
// Bundled eagerly (a few KB) so the first paint is already translated, without a flash of keys.
const translations = import.meta.glob<{ default: Record<string, string> }>('../../lang/*.json', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const shared = props.initialPage.props as unknown as SharedProps;

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                lang: shared.locale,
                fallbackLang: 'id',
                resolve: (lang: string) => translations[`../../lang/${lang}.json`]?.default ?? {},
            })
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
