import { MutationObserver, QueryClient } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { ErroDaApi } from '@/api/cliente';

import { avisar, EVENTO_DE_AVISO, type Aviso } from './avisos';
import { cacheDeMutacoesComAvisos } from './avisosDasMutacoes';

/**
 * TODO O CRUD AVISA NO CANTO — sem repetir o que o ecrã já avisou.
 */

let recebidos: Aviso[] = [];
const ouvinte = (e: Event) => recebidos.push((e as CustomEvent<Aviso>).detail);

beforeEach(() => {
    recebidos = [];
    window.addEventListener(EVENTO_DE_AVISO, ouvinte);
});

afterEach(() => {
    window.removeEventListener(EVENTO_DE_AVISO, ouvinte);
});

const esperar = (ms = 40) => new Promise((ok) => setTimeout(ok, ms));

async function correr(opcoes: ConstructorParameters<typeof MutationObserver>[1]) {
    const cliente = new QueryClient({ mutationCache: cacheDeMutacoesComAvisos(5) });
    const observador = new MutationObserver(cliente, opcoes);
    await observador.mutate(undefined as never).catch(() => undefined);
    await esperar();
}

describe('os avisos das mutações', () => {
    it('gravar avisa com a mensagem do servidor', async () => {
        await correr({ mutationFn: async () => ({ message: 'Cliente criado.' }) });

        expect(recebidos).toEqual([expect.objectContaining({ texto: 'Cliente criado.', tipo: 'ok' })]);
    });

    it('sem mensagem, avisa na mesma que ficou guardado', async () => {
        await correr({ mutationFn: async () => ({ id: 3 }) });

        expect(recebidos).toEqual([expect.objectContaining({ texto: 'Guardado com sucesso.', tipo: 'ok' })]);
    });

    it('um erro da API avisa com a mensagem do erro', async () => {
        await correr({ mutationFn: async () => { throw new ErroDaApi(422, 'O NIF já existe.'); } });

        expect(recebidos).toEqual([expect.objectContaining({ texto: 'O NIF já existe.', tipo: 'erro' })]);
    });

    it('a sessão morta não avisa: a casca mostra o ecrã de voltar a entrar', async () => {
        await correr({ mutationFn: async () => { throw new ErroDaApi(419, 'CSRF'); } });

        expect(recebidos).toEqual([]);
    });

    it('um ecrã que já avisou não fica com o aviso repetido', async () => {
        await correr({
            mutationFn: async () => ({ message: 'Apagado.' }),
            onSuccess: () => avisar('Artigo apagado.', 'ok'),
        });

        expect(recebidos.map((a) => a.texto)).toEqual(['Artigo apagado.']);
    });

    it('uma pré-visualização não avisa; uma acção a cada toque só avisa com mensagem', async () => {
        await correr({ mutationFn: async () => ({ previsao: 'x' }), meta: { aviso: false } });
        await correr({ mutationFn: async () => ({ estado: {} }), meta: { aviso: 'so-com-mensagem' } });

        expect(recebidos).toEqual([]);

        await correr({ mutationFn: async () => ({ message: 'Bloco removido.' }), meta: { aviso: 'so-com-mensagem' } });

        expect(recebidos.map((a) => a.texto)).toEqual(['Bloco removido.']);
    });
});
