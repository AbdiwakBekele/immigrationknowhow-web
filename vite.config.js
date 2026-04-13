import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    // Use IPv4 loopback so `public/hot` matches Laravel/Herd reliably (avoids [::1] vs 127.0.0.1 split and odd HMR/client edge cases on Windows).
    server: {
        host: '127.0.0.1',
        hmr: {
            host: '127.0.0.1',
        },
    },
    optimizeDeps: {
        include: ['@stripe/stripe-js'],
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    if (id.includes('/@inertiajs/') || id.includes('/vue/') || id.includes('/@vue/')) {
                        return 'framework';
                    }

                    if (id.includes('/@heroicons/') || id.includes('/@headlessui/')) {
                        return 'ui';
                    }

                    if (id.includes('/chart.js/') || id.includes('/vue-chartjs/')) {
                        return 'charts';
                    }

                    if (id.includes('/lodash/') || id.includes('/lodash-es/')) {
                        return 'utils';
                    }

                    return 'vendor';
                },
            },
        },
    },
});
