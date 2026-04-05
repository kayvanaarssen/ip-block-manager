import './bootstrap';
import '../css/app.css';

import { createApp, h, watch } from 'vue';
import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

function applyTheme(preference) {
    if (preference === 'dark') {
        document.documentElement.classList.add('dark');
    } else if (preference === 'light') {
        document.documentElement.classList.remove('dark');
    } else {
        // auto: follow system preference
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
}

// Listen for system theme changes (for auto mode)
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const page = usePage();
    const pref = page.props?.auth?.user?.theme_preference || 'auto';
    if (pref === 'auto') {
        applyTheme('auto');
    }
});

createInertiaApp({
    title: (title) => title ? `${title} - IP Block Manager` : 'IP Block Manager',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);

        // Apply theme on initial load
        const initialPref = props.initialPage.props?.auth?.user?.theme_preference || 'auto';
        applyTheme(initialPref);

        // Watch for theme preference changes (e.g. after saving in Profile)
        watch(
            () => usePage().props?.auth?.user?.theme_preference,
            (pref) => applyTheme(pref || 'auto'),
        );

        return app;
    },
    progress: {
        color: '#6366f1',
    },
});
