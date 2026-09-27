import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PedidoDePagamento } from '@/api/compras';

import Pagamentos from './Pagamentos';

const pedido: PedidoDePagamento = {
    id: 7, numero: 'PP-2026-000007', estado: 'por_pagar', estado_rotulo: 'Por pagar', valor: 150000,
    data_limite: '2026-09-20', atrasado: true, forma_sugerida: 'transfer', notas: 'IBAN AO06 0040',
    pedido_por: 'Ana Compras', pedido_em: '2026-09-18 10:00', tesoureiro: null, motivo_recusa: null,
    pago_por: null, pago_em: null, forma_paga: null, recibo: null, aprovacao: null, votos: [],
    fornecedor: 'Distribuidora Luanda', encomenda: { id: 3, numero: 'ENC-2026-000003' },
};

const api = vi.hoisted(() => ({
    opcoes: vi.fn(),
    lista: vi.fn(),
    pagar: vi.fn(),
}));

vi.mock('@/api/compras', () => ({
    pagamentosAFornecedores: {
        opcoes: api.opcoes,
        lista: api.lista,
        ficha: vi.fn(),
        pagar: api.pagar,
        recusar: vi.fn(),
        decidir: vi.fn(),
        cancelar: vi.fn(),
    },
}));

function opcoes(podePagar: boolean) {
    return {
        estados: [{ valor: 'por_pagar', rotulo: 'Por pagar' }],
        formas: [{ valor: 'transfer', rotulo: 'Transferência bancária' }, { valor: 'cash', rotulo: 'Dinheiro' }],
        contas: [{ id: 1, nome: 'BFA Principal', saldo: 100000 }],
        caixas: [{ id: 9, nome: 'Caixa da loja', saldo: 500000, aberta: true }],
        permissoes: { pode_pagar: podePagar, pode_aprovar: false },
    };
}

function montar() {
    return render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <Pagamentos />
        </QueryClientProvider>,
    );
}

beforeAll(() => {
    // O jsdom não abre um `<dialog>`: faz-se o que o browser faz, marcá-lo aberto.
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
    HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); };
});

beforeEach(() => {
    api.lista.mockResolvedValue({
        data: [pedido],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1, from: 1, to: 1 },
        resumo: { por_pagar: 1, valor_por_pagar: 150000, atrasados: 1, em_aprovacao: 0, pagos_no_mes: 0 },
    });
    api.pagar.mockResolvedValue({ message: 'Pagamento registado' });
});

describe('Pagamentos a fornecedores — o lado da tesouraria', () => {
    it('o tesoureiro vê o saldo de cada conta e é avisado antes de a deixar a descoberto', async () => {
        api.opcoes.mockResolvedValue(opcoes(true));
        montar();

        await userEvent.click(await screen.findByRole('button', { name: /Pagar/ }));
        const janela = screen.getByRole('dialog');

        // A conta sugerida tem 100 000 e o pedido é de 150 000.
        expect(within(janela).getByRole('option', { name: /BFA Principal/ })).toBeInTheDocument();
        expect(within(janela).getByRole('alert')).toHaveTextContent('O saldo não chega');

        // Em numerário sai da caixa — que tem saldo — e o aviso desaparece.
        await userEvent.selectOptions(within(janela).getByLabelText(/Forma de pagamento/), 'cash');
        expect(within(janela).getByRole('option', { name: /Caixa da loja/ })).toBeInTheDocument();
        expect(within(janela).queryByRole('alert')).not.toBeInTheDocument();

        await userEvent.click(within(janela).getByRole('button', { name: /Pagar 150/ }));
        expect(api.pagar).toHaveBeenCalledWith(7, expect.objectContaining({ forma: 'cash', cash_register_id: 9, account_id: null }));
    });

    it('quem só vê não tem o botão de pagar', async () => {
        api.opcoes.mockResolvedValue(opcoes(false));
        montar();

        expect(await screen.findByText('PP-2026-000007')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /^Pagar$/ })).not.toBeInTheDocument();
    });
});
