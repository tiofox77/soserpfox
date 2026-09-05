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
 * Por isso escreve para `public/react/` e o Blade lê o MANIFESTO para saber o
 * nome do ficheiro. A publicação continua a ser por FTP — é uma pasta em vez
 * de um ficheiro, e mais nada.
 *
 * PORQUE NÃO UM NOME FIXO COM `?v=filemtime`, QUE ERA O PLANO. Foi tentado e
 * está errado, de uma maneira que só se vê no browser: os pedaços importam a
 * entrada por caminho relativo e SEM a query (`import ... from "../app.js"`).
 * O browser trata `/react/app.js?v=123` e `/react/app.js` como dois módulos
 * diferentes e carrega o React DUAS VEZES — o componente é desenhado por uma
 * cópia e os hooks vêm da outra, e o ecrã morre com «Cannot read properties of
 * null (reading 'useState')». Com o hash no nome, há um só caminho e uma só
 * cópia.
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
        outDir: 'public/react',
        emptyOutDir: true,
        // O Tailwind vem do `/vendor/js/tailwind.js` que o layout já carrega e
        // que observa o DOM — as classes dos componentes React são apanhadas
        // quando eles montam. Não há CSS para gerar aqui.
        cssCodeSplit: false,
        sourcemap: true,
        // O manifesto é o que diz ao Blade qual é o ficheiro de hoje.
        manifest: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/js/react.tsx'),
            output: {
                entryFileNames: 'app-[hash].js',
                chunkFileNames: 'pedacos/[name]-[hash].js',
                assetFileNames: 'recursos/[name]-[hash][extname]',
            },
        },
    },
});
