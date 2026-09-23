import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';

import { CampoDoArtigo, trocarArtigo, useArtigosConhecidos, type ArtigoDaLinha } from './EscolhaDeArtigo';

/**
 * O ARTIGO DE UMA LINHA, escolhido numa janela com procura (23/09/2026). O
 * `<select>` antigo parava nos 500 artigos que o editor carrega.
 */

beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
    HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); };
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const CATALOGO: ArtigoDaLinha[] = [
    { id: 1, name: 'Cimento 50kg', code: 'CIM-50', price: 5000, unit: 'SC', type: 'produto' },
    { id: 2, name: 'Edição de vídeo', code: null, price: 1200000, unit: 'UN', type: 'servico' },
];

const doServidor = (a: ArtigoDaLinha & { categoria?: string }) => ({
    ...a, sku: null, cost: a.price / 2, manage_stock: a.type === 'produto', stock: 12, em_falta: false, esgotado: false,
    category: a.categoria ? { id: 9, name: a.categoria } : null,
});

/** O servidor: a lista de artigos (com os filtros no endereço) e as categorias. */
function servidor(recusa = false) {
    const pedidos: string[] = [];

    vi.stubGlobal('fetch', vi.fn((url: string) => {
        pedidos.push(url);
        const responder = (status: number, corpo: unknown) =>
            Promise.resolve({ ok: status < 400, status, json: () => Promise.resolve(corpo) } as Response);

        if (recusa) return responder(403, { message: 'Sem permissão.' });
        if (url.includes('/products/opcoes')) return responder(200, { categorias: [{ id: 9, name: 'Construção', parent_id: null, mae: null }] });

        const servico = url.includes('tipo=servico');
        const artigos = [
            doServidor({ ...CATALOGO[0]!, categoria: 'Construção' }),
            doServidor(CATALOGO[1]!),
        ].filter((a) => !servico || a.type === 'servico');

        return responder(200, { data: artigos, meta: { total: artigos.length, pagina: 1, por_pagina: 60, ultima: 1 }, resumo: {} });
    }));

    return pedidos;
}

function mostrar(aoEscolher = vi.fn(), artigo: ArtigoDaLinha | null = null) {
    const cliente = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } });
    render(
        <QueryClientProvider client={cliente}>
            <CampoDoArtigo n={1} artigo={artigo} aoEscolher={aoEscolher} catalogo={CATALOGO} />
        </QueryClientProvider>,
    );

    return aoEscolher;
}

const campo = () => screen.getByRole('button', { name: 'Artigo da linha 1' });

describe('o campo do artigo da linha', () => {
    it('mostra o artigo escolhido, e sem artigo convida a escolher', () => {
        servidor();
        mostrar(vi.fn(), CATALOGO[0]!);

        expect(campo()).toHaveTextContent('Cimento 50kg');
        // Uma linha só: o código vai na linha do crachá, e o nome inteiro no título.
        expect(campo()).toHaveAttribute('title', 'Cimento 50kg');
        expect(campo()).toHaveAttribute('data-artigo-escolhido', '1');
    });

    it('escrever uma letra abre a procura já começada', async () => {
        servidor();
        mostrar();

        expect(campo()).toHaveTextContent('Escolher artigo…');
        fireEvent.keyDown(campo(), { key: 'c' });

        expect(await screen.findByRole('searchbox', { name: 'Pesquisar produtos' })).toHaveValue('c');
    });

    it('carregar num cartão troca o artigo da linha e fecha a janela', async () => {
        servidor();
        const aoEscolher = mostrar();

        fireEvent.click(campo());
        const cartao = await screen.findByText('Edição de vídeo');
        fireEvent.click(cartao.closest('[data-artigo]')!);

        expect(aoEscolher).toHaveBeenCalledWith(expect.objectContaining({ id: 2, name: 'Edição de vídeo', price: 1200000, type: 'servico' }));
        await waitFor(() => expect(screen.queryByRole('searchbox')).toBeNull());
    });

    it('Enter na procura escolhe o primeiro resultado', async () => {
        servidor();
        const aoEscolher = mostrar();

        fireEvent.click(campo());
        await screen.findByText('Cimento 50kg');
        fireEvent.keyDown(screen.getByRole('searchbox', { name: 'Pesquisar produtos' }), { key: 'Enter' });

        expect(aoEscolher).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    });

    it('arruma por tipo e por categoria, e o filtro vai ao servidor', async () => {
        const pedidos = servidor();
        mostrar();

        fireEvent.click(campo());
        expect(await screen.findByText('Construção', { selector: 'span' })).toBeInTheDocument();
        expect(await screen.findByRole('combobox', { name: 'Categoria' })).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Serviços/ }));

        await waitFor(() => expect(pedidos.some((u) => u.includes('tipo=servico'))).toBe(true));
        await waitFor(() => expect(screen.queryByText('Cimento 50kg')).toBeNull());
        expect(screen.getByText('Edição de vídeo')).toBeInTheDocument();
    });

    it('sem acesso ao catálogo, procura na lista que o editor carregou e diz-o', async () => {
        servidor(true);
        mostrar();

        fireEvent.click(campo());

        expect(await screen.findByText(/Sem acesso ao catálogo completo/)).toBeInTheDocument();
        expect(screen.getByText('Cimento 50kg')).toBeInTheDocument();
    });
});

describe('trocar e conhecer artigos', () => {
    it('trocar o artigo leva o preço e o nome, mas não apaga uma descrição escrita no editor', () => {
        const linhas = [
            { product_id: 1, description: 'Cimento 50kg', price: 5000 },
            { product_id: 1, description: '<p><strong>Âmbito</strong></p>', price: 5000 },
        ];

        const [a, b] = [trocarArtigo(linhas, 0, CATALOGO[1]!)[0]!, trocarArtigo(linhas, 1, CATALOGO[1]!)[1]!];

        expect(a).toEqual({ product_id: 2, description: 'Edição de vídeo', price: 1200000 });
        expect(b.description).toBe('<p><strong>Âmbito</strong></p>');
    });

    it('um artigo fora dos 500 carregados mostra-se pelo que se escolheu, ou pela linha', () => {
        const { result } = renderHook(() => useArtigosConhecidos(CATALOGO));

        expect(result.current.um(1)?.name).toBe('Cimento 50kg');
        expect(result.current.um(777)).toBeNull();
        expect(result.current.um(777, { description: 'Ben-u-ron 500mg', price: 850 })?.name).toBe('Ben-u-ron 500mg');

        act(() => result.current.lembrar({ id: 777, name: 'Ben-u-ron 500mg', code: 'BEN', price: 850, unit: 'CX', type: 'produto' }));
        expect(result.current.um(777)?.code).toBe('BEN');
    });
});
