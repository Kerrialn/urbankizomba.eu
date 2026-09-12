import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import symfonyPlugin from 'vite-plugin-symfony';

export default defineConfig({
    plugins: [
        tailwindcss(),
        symfonyPlugin(),
    ],
    build: {
        rollupOptions: {
            input: {
                app: './assets/app.js',
            },
        },
    },
});
