import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { hsiColors } from './theme/hsiTheme';
import { autoTranslatePlugin, setActiveLocale } from './i18n';
import { setActiveCurrency } from './currency';

const appName = import.meta.env.VITE_APP_NAME || 'HSI Family Office';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Langue active (FR / EN) : fixée au chargement, puis suivie à chaque navigation.
        setActiveLocale(props.initialPage.props.locale);
        router.on('navigate', (event) => setActiveLocale(event.detail.page.props.locale));
        // Devise du fonds : les libellés « ($) » prennent le symbole choisi par l'administrateur.
        setActiveCurrency(props.initialPage.props.currency);
        router.on('navigate', (event) => setActiveCurrency(event.detail.page.props.currency));

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(autoTranslatePlugin)
            .mount(el);
    },
    progress: {
        color: hsiColors.gold,
    },
});
