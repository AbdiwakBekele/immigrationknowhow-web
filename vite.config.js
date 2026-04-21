import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    // Use IPv4 loopback so `public/hot` matches Laravel/Herd reliably (avoids [::1] vs 127.0.0.1 split and odd HMR/client edge cases on Windows).
    server: {
        host: '127.0.0.1',
        https: false,
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
        },
    },
    optimizeDeps: {
        include: ['@stripe/stripe-js', 'dompurify', 'marked'],
        // pdfjs-dist triggers a Vite 8 dep-optimizer crash (chunk.fileName / exportsData).
        // Exclude it so dev resolves the published ESM directly instead of pre-bundling.
        exclude: ['pdfjs-dist'],
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
            dompurify: path.resolve(projectRoot, 'node_modules/dompurify/dist/purify.es.mjs'),
            marked: path.resolve(projectRoot, 'node_modules/marked/lib/marked.esm.js'),
            'pdfjs-dist': path.resolve(projectRoot, 'node_modules/pdfjs-dist'),
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
