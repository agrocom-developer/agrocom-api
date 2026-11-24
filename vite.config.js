import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // `transicion-vista.css` va aparte del bundle a propósito:
            // `@view-transition` es una regla de documento y no se puede
            // acotar por selector, así que solo las pantallas del flujo de
            // autenticación la incluyen (ver ese archivo).
            input: ['resources/css/app.css', 'resources/css/transicion-vista.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
