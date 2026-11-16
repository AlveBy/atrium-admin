import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

// The admin ships prebuilt: `npm run build` writes dist/, which
// `php artisan atrium:install` publishes to public/vendor/atrium.
// `npm run dev` drops a hot file there so the demo app loads from Vite.
export default defineConfig({
    base: '/vendor/atrium/',
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            publicDirectory: '.',
            buildDirectory: 'dist',
            hotFile: '../../public/vendor/atrium/hot',
            refresh: ['resources/js/**', 'resources/views/**'],
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
    resolve: {
        alias: {
            '@atrium': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        emptyOutDir: true,
    },
});
