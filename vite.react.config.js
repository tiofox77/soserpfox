import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * O PACOTE DOS ECRÃS EM REACT — e porque tem config própria.
 *
 * O layout desta aplicação não usa `@vite`: carrega ficheiros de `public/js`
 * com `?v={{ filemtime(...) }}`, e a publicação é por FTP, ficheiro a ficheiro.
 * O manifesto do Vite não encaixa nisso, e o service worker precacha por URL
 * escrito à mão — com nomes gerados, essa lista deixava de poder existir.
 *
 * Por isso:
 *
 *   ENTRADA COM NOME FIXO   `public/js/react/app.js`, carregado como os outros,
 *                           com `?v=filemtime` a servir de cache-busting — o
 *                           mesmo padrão que o `painel-facturacao.js` já usa.
 *
 *   PEDAÇOS COM HASH        os `import()` preguiçosos saem com hash no nome, e
 *                           é preciso: a entrada vem sempre fresca e aponta
 *                           para os hashes novos, portanto nenhum browser fica
 *                           com um pedaço velho.
 *
 * A construção de sempre (`vite.config.js`) não é tocada. Se isto correr mal,
 * apaga-se `public/js/react` e a aplicação fica exactamente como estava.
 */
export default defineConfig({
    plugins: [react()],

    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },

    /*
     * DESLIGADO, E NÃO SE VOLTA A LIGAR.
     *
     * O Vite copia o `publicDir` (por omissão, `<raiz>/public`) para dentro do
     * `outDir` no fim da construção. Como o `outDir` daqui vive DENTRO de
     * `public/`, isso é `public/` a copiar-se para dentro de si próprio, outra
     * vez, e outra: a primeira tentativa correu oito minutos a 100% de CPU a
     * cavar uma árvore de pastas até ser preciso matá-la à mão.
     *
     * Aqui não há nada para copiar — os ficheiros estáticos já estão em
     * `public/` e é para lá que isto escreve.
     */
    publicDir: false,

    build: {
        outDir: 'public/js/react',
        emptyOutDir: true,
        // O Tailwind vem do `/vendor/js/tailwind.js` que o layout já carrega e
        // que observa o DOM — as classes dos componentes React são apanhadas
        // quando eles montam. Não há CSS para gerar aqui.
        cssCodeSplit: false,
        sourcemap: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/js/react.tsx'),
            output: {
                entryFileNames: 'app.js',
                chunkFileNames: 'pedacos/[name]-[hash].js',
                assetFileNames: 'recursos/[name]-[hash][extname]',
            },
        },
    },
});
