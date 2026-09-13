import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * O PACOTE DO PWA — separado do da aplicação web, e num ficheiro só.
 *
 * O da aplicação web parte-se em pedaços (`import()` preguiçoso, um por ecrã).
 * Isso é bom com rede e mau sem ela: o service worker tem de ter guardado
 * TODOS os pedaços antes de a rede faltar, e um pedaço esquecido é um ecrã que
 * não abre no dia em que o servidor cai — sem erro nenhum que se veja, só um
 * carregamento que não acaba.
 *
 * Aqui não há pedaços: `inlineDynamicImports` junta tudo num ficheiro, e é
 * esse ficheiro (o nome vem do manifesto) que o PwaController põe na lista de
 * pré-guardados do sw.js e na conta da versão. Um ficheiro, um URL, e a
 * versão muda quando os bytes mudam.
 *
 * Mesmas regras do `vite.react.config.js`: `publicDir: false` (senão o Vite
 * copiava `public/` para dentro de si próprio) e nome com hash, sem `?v=`.
 */
export default defineConfig({
    plugins: [react()],

    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },

    publicDir: false,
    base: '/pwa-app/',

    build: {
        outDir: 'public/pwa-app',
        emptyOutDir: true,
        cssCodeSplit: false,
        sourcemap: true,
        manifest: true,
        // Telemóveis de balcão com anos: nada que precise de um browser de ontem.
        target: 'es2019',
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/js/pwa.tsx'),
            output: {
                entryFileNames: 'pwa-[hash].js',
                inlineDynamicImports: true,
                assetFileNames: 'recursos/[name]-[hash][extname]',
            },
        },
    },
});
