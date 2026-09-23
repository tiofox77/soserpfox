import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import PontoDeVenda from './PontoDeVenda';
import type { OpcoesDoPos } from '@/api/pos';

/**
 * UMA VENDA, UMA FACTURA.
 *
 * A Luk Simões recebeu a FR 003253 e a FR 003254 com um segundo de diferença,
 * do mesmo browser: o «Confirmar» disparou duas vezes, e cada disparo levava
 * um identificador novo — o servidor, que só reconhece o mesmo, gravou duas
 * (23/09/2026).
 */

beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
    HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); };
    // O jsdom não desloca nem observa: a grelha pede as duas coisas.
    Element.prototype.scrollTo = function scrollTo() {} as typeof Element.prototype.scrollTo;
    Element.prototype.scrollIntoView = function scrollIntoView() {};
    vi.stubGlobal('IntersectionObserver', class { observe() {} unobserve() {} disconnect() {} });
});

const OPCOES: OpcoesDoPos = {
    modulo: null,
    categorias_de_servicos: [],
    turno: { id: 428, numero: 'T-428', aberto_em: null, abertura: 0 },
    rota_dos_turnos: '/turnos',
    armazem: { id: 12, nome: 'Loja' },
    categorias: [],
    logotipo: null,
    formas_de_pagamento: [{ valor: 'cash', rotulo: 'Numerário' }],
    definicoes: { esconde_sem_stock: false, montantes_rapidos: [], taxa_irt: 0, mascara_de_preco: false },
    dono_do_carrinho: { empresa: 19, operador: 38 },
    permissoes: { pode_vender: true, pode_criar_cliente: true, pode_mudar_preco: false },
};

const VENDA = {
    id: 16729, numero: 'FR FR4226S61319N/003253', numero_interno: 'FR SOSFR/003253', total: 500, data: null,
    cliente: 'Consumidor Final', qr: null, atcud: null, formato: 'talao', papeis: { talao: '/t', a4: '/a4' }, preview: '/a4', message: 'Venda registada.',
};

type Pedido = { url: string; corpo: Record<string, unknown> };

function servidor(respostasDaVenda: Array<{ status: number; corpo: unknown }>) {
    const vendas: Pedido[] = [];

    vi.stubGlobal('fetch', vi.fn((url: string, opcoes: RequestInit = {}) => {
        let status = 200;
        let corpo: unknown = {};

        if (url.includes('/pos/opcoes')) corpo = OPCOES;
        else if (url.includes('/pos/artigos')) corpo = { data: [], meta: { pagina: 1, por_pagina: 60, mais: false } };
        else if (url.includes('/pos/vender')) {
            vendas.push({ url, corpo: JSON.parse(String(opcoes.body)) });
            ({ status, corpo } = respostasDaVenda[vendas.length - 1] ?? { status: 200, corpo: VENDA });
        }

        return Promise.resolve({ ok: status < 400, status, json: () => Promise.resolve(corpo) } as Response);
    }));

    return vendas;
}

function mostrar() {
    const cliente = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 }, mutations: { retry: false } } });

    return render(<QueryClientProvider client={cliente}><PontoDeVenda /></QueryClientProvider>);
}

beforeEach(() => {
    document.head.innerHTML = '<meta name="csrf-token" content="ensaio">';
    // Um carrinho à espera, como o espelho do balcão o guarda.
    localStorage.setItem('pos_carrinho_t19_u38', JSON.stringify({
        ts: Date.now(),
        itens: [{ id: 7596, nome: 'LEOPARDE VICK INALADOR', unidade: 'UN', servico: false, preco: 500, quantidade: 1, stock: 11, taxa: 0 }],
    }));
});

afterEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

async function abrirOPagamento() {
    fireEvent.click(await screen.findByRole('button', { name: /Finalizar Venda/ }));

    return screen.findByRole('button', { name: /Confirmar Venda/ });
}

describe('fechar a venda no balcão', () => {
    it('dois disparos seguidos do «Confirmar» são uma venda só', async () => {
        const vendas = servidor([]);
        mostrar();

        const confirmar = await abrirOPagamento();
        // No mesmo instante, antes de o ecrã se redesenhar — o duplo clique.
        fireEvent.click(confirmar);
        fireEvent.click(confirmar);

        await waitFor(() => expect(vendas.length).toBeGreaterThan(0));
        await new Promise((r) => setTimeout(r, 50));
        expect(vendas).toHaveLength(1);
    });

    it('tentar outra vez depois de um erro leva o MESMO identificador', async () => {
        const vendas = servidor([{ status: 503, corpo: { message: 'Sem ligações livres.' } }]);
        mostrar();

        const confirmar = await abrirOPagamento();
        fireEvent.click(confirmar);
        await waitFor(() => expect(vendas).toHaveLength(1));

        // O botão volta a estar disponível e a operadora carrega outra vez.
        await waitFor(() => expect(screen.getByRole('button', { name: /Confirmar Venda/ })).not.toBeDisabled());
        fireEvent.click(screen.getByRole('button', { name: /Confirmar Venda/ }));
        await waitFor(() => expect(vendas).toHaveLength(2));

        expect(vendas[1]!.corpo.local_uuid).toBe(vendas[0]!.corpo.local_uuid);
    });
});
