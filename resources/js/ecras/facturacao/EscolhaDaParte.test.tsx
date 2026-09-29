import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { Parte } from '@/api/partes';
import { EscolhaDaParte, compactar, soEscolher } from './EscolhaDaParte';

/**
 * O CLIENTE QUE NÃO VEIO NA LISTA (29/09/2026).
 *
 * As opções trazem os 500 primeiros por ordem alfabética. Na JG Inox a
 * «T.P.A.» era a 545.ª e não se encontrava na factura nem na proforma. A caixa
 * passou a procurar também no servidor, e um documento que se abre com uma
 * parte de fora da lista mostra-a pelo id.
 */

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const LISTA: Parte[] = [
    { id: 1, name: 'Aurora Kiala', nif: null },
    { id: 2, name: 'Bento Mavungo', nif: '5000000002' },
];

const TPA: Parte = { id: 545, name: 'T.P.A.- TELEVISAO PUBLICA DE ANGOLA', nif: '5410003055' };

/** O servidor: `/partes/clientes?procura=` e `?id=`. */
function servidor() {
    const pedidos: string[] = [];

    vi.stubGlobal('fetch', vi.fn((url: string) => {
        pedidos.push(url);
        const achou = url.includes('procura=tele') || url.includes('id=545');

        return Promise.resolve({
            ok: true,
            status: 200,
            json: () => Promise.resolve({ data: achou ? [TPA] : [] }),
        } as Response);
    }));

    return pedidos;
}

function mostrar(valor = '', aoEscolher = vi.fn()) {
    const cliente = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } });
    render(
        <QueryClientProvider client={cliente}>
            <EscolhaDaParte criar={soEscolher('cliente')} partes={LISTA} valor={valor} aoEscolher={aoEscolher} />
        </QueryClientProvider>,
    );

    return aoEscolher;
}

describe('compactar', () => {
    it('tira acentos, maiúsculas e pontuação', () => {
        expect(compactar('T.P.A.- TELEVISÃO')).toBe('tpatelevisao');
        expect(compactar('tpa')).toBe('tpa');
    });
});

describe('EscolhaDaParte', () => {
    it('procura no servidor o que não veio na lista, e escolhe-o', async () => {
        const pedidos = servidor();
        const aoEscolher = mostrar();

        const caixa = screen.getByRole('combobox', { name: 'Cliente' });
        fireEvent.focus(caixa);
        fireEvent.change(caixa, { target: { value: 'televisão' } });

        const opcao = await screen.findByRole('option', { name: /TELEVISAO PUBLICA/ });
        expect(pedidos.some((u) => u.includes('/partes/clientes') && u.includes('procura=televis'))).toBe(true);

        fireEvent.mouseDown(opcao);
        expect(aoEscolher).toHaveBeenCalledWith('545');
    });

    it('um documento com um cliente de fora da lista mostra o nome dele', async () => {
        const pedidos = servidor();
        mostrar('545');

        await waitFor(() => expect(screen.getByRole('combobox', { name: 'Cliente' })).toHaveValue(TPA.name));
        expect(pedidos.some((u) => u.includes('/partes/clientes') && u.includes('id=545'))).toBe(true);
    });

    it('na lista, encontra sem acentos nem pontuação e sem ir ao servidor', async () => {
        const pedidos = servidor();
        mostrar();

        const caixa = screen.getByRole('combobox', { name: 'Cliente' });
        fireEvent.focus(caixa);
        fireEvent.change(caixa, { target: { value: 'b' } });

        expect(await screen.findByRole('option', { name: /Bento Mavungo/ })).toBeInTheDocument();
        expect(screen.queryByRole('option', { name: /Aurora/ })).not.toBeInTheDocument();
        // Uma letra só não vai ao servidor.
        expect(pedidos.filter((u) => u.includes('procura='))).toHaveLength(0);
    });

    it('sem permissão de criar não mostra «Novo cliente»', () => {
        servidor();
        mostrar();

        expect(screen.queryByRole('button', { name: /Novo cliente/ })).not.toBeInTheDocument();
    });
});
