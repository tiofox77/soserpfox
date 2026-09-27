import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, describe, expect, it, vi } from 'vitest';

import type { DadosDoDreIntegrado } from '@/api/tesouraria';

import { DreIntegrado } from './DreIntegrado';

const api = vi.hoisted(() => ({ detalhe: vi.fn(), naturezas: vi.fn(), guardarNaturezas: vi.fn() }));

vi.mock('@/api/tesouraria', () => ({ relatoriosDaTesouraria: api }));

const dados: DadosDoDreIntegrado = {
    linhas: [
        { rubrica: 'vendas', rotulo: 'Vendas (sem IVA)', valor: 12000, nivel: 0, total: false, final: false },
        { rubrica: null, rotulo: 'Receita líquida', valor: 10300, nivel: 0, total: true, final: false },
        { rubrica: 'cmv', rotulo: 'Custo das mercadorias vendidas (CMV)', valor: -4500, nivel: 0, total: false, final: false },
        { rubrica: null, rotulo: 'Prejuízo do período', valor: -100, nivel: 0, total: true, final: true },
    ],
    receita: { vendas: 12000, descontos: 1000, notas_debito: 300, notas_credito: 1000, receita_liquida: 10300 },
    cmv: { vendido: 5000, devolvido: 500, liquido: 4500 },
    despesas: { documentos: 800, movimentos: 4700, total: 5500, por_categoria: [{ categoria: 'salary', rotulo: 'Salários', valor: 3000 }] },
    lucro_bruto: 5800,
    margem_bruta: 56.3,
    resultado_operacional: 300,
    resultado_liquido: -100,
    fora_do_resultado: [{ rubrica: 'transferencia', rotulo: 'Transferência entre contas e caixas', saidas: 5000, entradas: 0 }],
    por_classificar: [{ categoria: 'comissoes_bancarias', rotulo: 'Comissões bancárias' }],
    mensal: [{ mes: 'set/2026', receita_liquida: 10300, cmv: 4500, lucro_bruto: 5800, despesas: 5500, resultado: -100 }],
};

beforeAll(() => {
    // O jsdom não abre um `<dialog>`: faz-se o que o browser faz, marcá-lo aberto.
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
    HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); };
});

function montar() {
    return render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <DreIntegrado d={dados} filtros={{ periodo: 'month', de: '2026-09-01', ate: '2026-09-30' }} />
        </QueryClientProvider>,
    );
}

describe('DRE Integrado — o resultado do período', () => {
    it('diz o prejuízo como prejuízo e pede a classificação do que falta', () => {
        montar();

        expect(screen.getAllByText('Prejuízo do período').length).toBeGreaterThan(0);
        expect(screen.getByRole('alert')).toHaveTextContent('Comissões bancárias');
    });

    it('um clique numa linha abre os documentos que a compõem, com ligação', async () => {
        api.detalhe.mockResolvedValue({
            titulo: 'Custo das mercadorias vendidas (CMV)', total: 4500, limitado: false,
            linhas: [{ data: '2026-09-10', documento: 'FT 2026/1', ligacao: '/invoicing/sales/invoices/1/preview', descricao: 'Mercadoria — 10 × 500,00', valor: 5000 }],
        });
        montar();

        await userEvent.click(screen.getByRole('button', { name: /Ver a origem de Custo das mercadorias vendidas/ }));

        expect(api.detalhe).toHaveBeenCalledWith(expect.objectContaining({ rubrica: 'cmv', de: '2026-09-01', ate: '2026-09-30' }));
        const janela = await screen.findByRole('dialog');
        expect(within(janela).getByRole('link', { name: 'FT 2026/1' })).toHaveAttribute('href', '/invoicing/sales/invoices/1/preview');
    });
});
