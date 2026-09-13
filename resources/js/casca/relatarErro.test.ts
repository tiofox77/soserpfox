import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ErroDaApi } from '@/api/cliente';

/** Os erros do browser chegam ao servidor — sem repetir e sem os da API. */
describe('relatar erros do browser', () => {
    let pedidos: Array<Record<string, unknown>>;

    beforeEach(() => {
        vi.resetModules();
        pedidos = [];
        vi.stubGlobal('fetch', vi.fn(async (_url: string, init: RequestInit) => {
            pedidos.push(JSON.parse(String(init.body)));
            return new Response(null, { status: 202 });
        }));
    });

    afterEach(() => vi.unstubAllGlobals());

    it('o erro de um ecrã vai uma vez só, com o ecrã e o endereço', async () => {
        const { relatarErro } = await import('./relatarErro');

        relatarErro({ mensagem: 'x is undefined', pilha: 'TypeError', ecra: 'facturacao/pos', origem: 'ecra' });
        relatarErro({ mensagem: 'x is undefined', pilha: 'TypeError', ecra: 'facturacao/pos', origem: 'ecra' });

        expect(pedidos).toHaveLength(1);
        expect(pedidos[0]).toMatchObject({ mensagem: 'x is undefined', ecra: 'facturacao/pos', origem: 'ecra' });
    });

    it('nunca mais de dez por página', async () => {
        const { relatarErro } = await import('./relatarErro');

        for (let i = 0; i < 25; i++) relatarErro({ mensagem: `erro ${i}`, origem: 'janela' });

        expect(pedidos).toHaveLength(10);
    });

    it('uma promessa rejeitada pela API não se relata: o servidor já a registou', async () => {
        const { ligarRelatoDeErros } = await import('./relatarErro');
        ligarRelatoDeErros();

        const rejeitar = (razao: unknown) => {
            const evento = new Event('unhandledrejection') as Event & { reason: unknown };
            evento.reason = razao;
            window.dispatchEvent(evento);
        };

        rejeitar(new ErroDaApi(422, 'O NIF já existe.'));
        expect(pedidos).toHaveLength(0);

        rejeitar(new Error('Failed to fetch dynamically imported module'));
        expect(pedidos).toHaveLength(1);
        expect(pedidos[0]).toMatchObject({ origem: 'promessa' });
    });
});
