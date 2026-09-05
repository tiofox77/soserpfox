import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * A construção de sempre, INTOCADA.
 *
 * O pacote do React tem config própria (`vite.react.config.js`) porque sai
 * para outro sítio e com outras regras — ver lá porquê. O plugin do React fica
 * aqui na mesma, para o Vitest saber ler JSX.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.test.{ts,tsx}'],
        setupFiles: ['resources/js/teste/preparar.ts'],
    },
});
