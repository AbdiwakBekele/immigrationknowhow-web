import '../css/app.css';
import 'flag-icons/css/flag-icons.min.css';

import { createApp, Fragment, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import AppFeedback from './Components/App/AppFeedback.vue';
import GlobalTranslateFab from './Components/App/GlobalTranslateFab.vue';

const appName = import.meta.env.VITE_APP_NAME || 'ImmigrationKnowHow';

const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
const normalizedPageEntries = Object.entries(pages).map(([key, value]) => [
    key.replace(/\\/g, '/').toLowerCase(),
    value,
]);

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const path = `./Pages/${name}.vue`;
        let page = pages[path];
        if (!page) {
            const normalizedPath = path.replace(/\\/g, '/').toLowerCase();
            const match = normalizedPageEntries.find(([entryPath]) => entryPath === normalizedPath);
            page = match?.[1];
        }
        if (!page) {
            throw new Error(`Missing Inertia page: "${name}" (expected ${path}).`);
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        
        return createApp({
            render: () => h(Fragment, [
                h(AppFeedback),
                h(App, props),
                h(GlobalTranslateFab),
            ]),
        })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .use(Toast, {
                position: 'top-right',
                timeout: 4000,
                closeOnClick: true,
                pauseOnFocusLoss: true,
                pauseOnHover: true,
                draggable: true,
                draggablePercent: 0.6,
                showCloseButtonOnHover: false,
                hideProgressBar: false,
                closeButton: 'button',
                icon: true,
                rtl: false,
                maxToasts: 4,
                newestOnTop: true,
            })
            .mount(el);
    },
    progress: {
        color: '#0EA5E9',
        showSpinner: true,
    },
});
