import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import { autoTranslateTransform } from './resources/js/i18n/autoTranslateTransform.js';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                // Textes des pages traduits automatiquement (FR → EN), voir resources/js/i18n/autoTranslateTransform.js
                compilerOptions: {
                    nodeTransforms: [autoTranslateTransform],
                },
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
