import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

// Built without websockets (VITE_REALTIME=polling npm run build): leave the Echo and Pusher
// libraries out of the page, since echo.js never uses them in that case.
const polling = process.env.VITE_REALTIME === 'polling';
const stub = fileURLToPath(new URL('./resources/js/realtime-stub.js', import.meta.url));

export default defineConfig({
    resolve: {
        alias: polling ? { 'laravel-echo': stub, 'pusher-js': stub } : {},
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
