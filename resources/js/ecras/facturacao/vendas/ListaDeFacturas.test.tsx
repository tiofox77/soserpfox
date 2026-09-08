import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ListaDeFacturas from './ListaDeFacturas';
import type { FacturaDeVenda } from '@/api/facturacao';

/**
 * A lista de facturas em React.
 *
 * O que estes ensaios guardam é o que já custou caro no ecrã Livewire:
 *
 *  · uma factura já inteiramente creditada NÃO convida a creditar outra vez;
 *  · a sessão expirar não se confunde com falta de rede;
 *  · quem só vê os seus documentos não vê a lista de nomes dos colegas.
 */

function factura(por: Partial<FacturaDeVenda> = {}): FacturaDeVenda {
    return {
        id: 1,
        numero: 'FT SOSFT/000003',
        numero_agt: 'FT4226S75324N/000003',
        tipo: 'FT',
        cliente: { id: 5, nome: 'GABINETE PROVINCIAL DE SAÚDE DO BENGO', nif: '5417289442' },
        data: '2026-09-04',
        vencimento: '2026-09-19',
        estado: 'sent',
        estado_rotulo: 'Emitida',
        estado_cor: 'primaria',
        total: 2595187,
        pago: 0,
        saldo: 2595187,
        agt: { comunicada: true, rotulo: 'Emitida no Portal AGT' },
        armazem: 'Central',
        autor: 'Carlos',
        pode_creditar: true,
        pode_receber: true,
        e_rascunho: false,
        // Emitida: não se corrige por cima nem se apaga — rectifica-se por nota.
        pode_editar: false,
        pode_apagar: false,
        ...por,
    };
}

const OPCOES_VAZIAS = {
    armazens: [],
    estados: [{ valor: 'sent', rotulo: 'Emitida' }],
    autores: [],
    permissoes: {
        ve_de_todos: false, pode_criar: true, pode_editar: true, pode_apagar: true,
        pode_creditar: true, pode_debitar: true, pode_receber: true,
    },
    eliminacao_bloqueada: false,
};

function responder(facturas: FacturaDeVenda[], opcoes: unknown = OPCOES_VAZIAS) {
    return vi.fn((url: string) => {
        const corpo = url.includes('/opcoes')
            ? opcoes
            : {
                  data: facturas,
                  links: { first: null, last: null, prev: null, next: null },
                  meta: {
                      current_page: 1,
                      from: 1,
                      last_page: 1,
                      per_page: 15,
                      to: facturas.length,
                      total: facturas.length,
                      somas: { facturado: 0, por_receber: 0, vencido: 0 },
                      contagens: { rascunhos: 0, pendentes: 0, pagas: 0 },
                  },
              };

        return Promise.resolve({
            ok: true,
            status: 200,
            json: () => Promise.resolve(corpo),
        } as Response);
    });
}

function mostrar() {
    // Sem repetições nem cache entre ensaios: um teste não pode ver a resposta
    // que o anterior guardou.
    const cliente = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    });

    return render(
        <QueryClientProvider client={cliente}>
            <ListaDeFacturas />
        </QueryClientProvider>,
    );
}

beforeEach(() => {
    document.head.innerHTML = '<meta name="csrf-token" content="ensaio">';
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Lista de facturas de venda', () => {
    it('mostra o número interno e o da AGT', async () => {
        vi.stubGlobal('fetch', responder([factura()]));

        mostrar();

        expect(await screen.findByText('FT SOSFT/000003')).toBeInTheDocument();
        expect(screen.getByText('FT4226S75324N/000003')).toBeInTheDocument();
        expect(screen.getByText('GABINETE PROVINCIAL DE SAÚDE DO BENGO')).toBeInTheDocument();
    });

    it('escreve o dinheiro à maneira daqui', async () => {
        vi.stubGlobal('fetch', responder([factura({ total: 2550698.08, saldo: 0, pago: 2550698.08 })]));

        mostrar();

        expect(await screen.findByText('2 550 698,08')).toBeInTheDocument();
    });

    /**
     * O DEFEITO QUE ISTO GUARDA. No ecrã Livewire o botão de creditar aparecia
     * em qualquer factura emitida, incluindo as já anuladas por inteiro — e só
     * no fim, com a nota toda preenchida, é que o travão a recusava.
     */
    it('não convida a creditar uma factura já totalmente creditada', async () => {
        vi.stubGlobal(
            'fetch',
            responder([
                factura({ id: 1, numero: 'FT SOSFT/000001', pode_creditar: false }),
                factura({ id: 2, numero: 'FT SOSFT/000002', pode_creditar: true }),
            ]),
        );

        mostrar();

        await screen.findByText('FT SOSFT/000001');

        // A que pode: um link verdadeiro para o ecrã da nota.
        expect(screen.getByRole('link', { name: 'Nota de crédito' })).toHaveAttribute(
            'href',
            '/invoicing/credit-notes/create?invoice=2',
        );

        // A que não pode: nada em que carregar, e a razão à vista.
        expect(screen.getAllByRole('link', { name: 'Nota de crédito' })).toHaveLength(1);
        expect(
            screen.getByTitle('Já totalmente creditada — não há nada por anular'),
        ).toBeInTheDocument();
    });

    it('só oferece receber quando ainda falta receber', async () => {
        vi.stubGlobal(
            'fetch',
            responder([factura({ pode_receber: false, saldo: 0, pago: 2595187 })]),
        );

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        expect(screen.queryByRole('link', { name: 'Receber' })).not.toBeInTheDocument();
    });

    /**
     * Quem não pode ver os documentos dos colegas também não vê os NOMES
     * deles. O servidor devolve a lista vazia; o ecrã não pode desenhar um
     * selector vazio a dizer que existe gente.
     */
    it('esconde o filtro por autor de quem só vê os seus', async () => {
        vi.stubGlobal('fetch', responder([factura()]));

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        expect(screen.queryByText('Emitida por')).not.toBeInTheDocument();
    });

    it('mostra o filtro por autor a quem vê os de todos', async () => {
        vi.stubGlobal(
            'fetch',
            responder([factura()], {
                ...OPCOES_VAZIAS,
                autores: [{ id: 3, name: 'Ana' }],
                permissoes: { ...OPCOES_VAZIAS.permissoes, ve_de_todos: true },
            }),
        );

        mostrar();

        expect(await screen.findByText('Emitida por')).toBeInTheDocument();
        expect(screen.getByRole('option', { name: 'Ana' })).toBeInTheDocument();
    });

    /**
     * O BOTÃO DE CRIAR — que a lista não tinha.
     *
     * Ao migrar do Blade perdeu-se o cabeçalho inteiro, e com ele o «Nova
     * Fatura». A lista mostrava facturas e não tinha por onde se fazer uma.
     */
    it('tem por onde fazer uma factura nova', async () => {
        vi.stubGlobal('fetch', responder([factura()]));

        mostrar();

        expect(await screen.findByRole('link', { name: /Nova Factura$/ })).toHaveAttribute(
            'href',
            '/invoicing/sales/invoices/create',
        );
    });

    it('esconde o botão de criar de quem não pode criar', async () => {
        vi.stubGlobal(
            'fetch',
            responder([factura()], {
                ...OPCOES_VAZIAS,
                permissoes: { ...OPCOES_VAZIAS.permissoes, pode_criar: false },
            }),
        );

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        expect(screen.queryByRole('link', { name: /Nova Factura/ })).not.toBeInTheDocument();
    });

    /**
     * A FACTURA-RECIBO É OUTRO DOCUMENTO. Filtrando por FR, o ecrã de sempre
     * trocava o título e o botão — e o botão levava o tipo consigo, para a
     * factura nova nascer do tipo que se está a ver.
     */
    it('filtrando por FR, o titulo e o botao acompanham', async () => {
        vi.stubGlobal('fetch', responder([factura({ tipo: 'FR' })]));

        const { container } = render(
            <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } })}>
                <ListaDeFacturas tipo="FR" />
            </QueryClientProvider>,
        );

        expect(await screen.findByRole('heading', { name: 'Facturas-Recibo' })).toBeInTheDocument();

        // O TÍTULO NÃO ESPERA PELAS OPÇÕES e o botão espera: quem pode criar
        // vem do servidor. Procurar os dois da mesma maneira lia o ecrã a meio.
        expect(await screen.findByRole('link', { name: /Nova Factura-Recibo/ })).toHaveAttribute(
            'href',
            '/invoicing/sales/invoices/create?type=FR',
        );

        expect(container).toBeTruthy();
    });

    /**
     * EDITAR E ELIMINAR SÓ ENQUANTO É RASCUNHO.
     *
     * Depois de finalizada a factura tem número de série e assinatura:
     * corrige-se por nota de crédito, nunca por cima (Decreto 71/25). A
     * decisão vem do servidor — o ecrã só a desenha.
     */
    it('nao oferece editar nem eliminar uma factura ja emitida', async () => {
        vi.stubGlobal('fetch', responder([factura({ pode_editar: false, pode_apagar: false })]));

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        expect(screen.queryByRole('link', { name: 'Editar rascunho' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument();
    });

    it('oferece editar e eliminar um rascunho', async () => {
        vi.stubGlobal(
            'fetch',
            responder([factura({ id: 7, e_rascunho: true, pode_editar: true, pode_apagar: true })]),
        );

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        expect(screen.getByRole('link', { name: 'Editar rascunho' })).toHaveAttribute(
            'href',
            '/invoicing/sales/invoices/7/edit',
        );
        expect(screen.getByRole('button', { name: /Eliminar/ })).toBeInTheDocument();
    });

    /**
     * BLOQUEADO À CHAVE PELO ADMINISTRADOR: o botão fica à vista e a dizer
     * porquê. Escondê-lo faz procurar um botão que existe.
     */
    it('com a eliminacao bloqueada, o botao fica apagado e diz porque', async () => {
        vi.stubGlobal(
            'fetch',
            responder([factura({ pode_apagar: true, pode_editar: true })], {
                ...OPCOES_VAZIAS,
                eliminacao_bloqueada: true,
            }),
        );

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument();
        expect(
            screen.getByTitle('A eliminação está bloqueada pelo administrador — só se anula.'),
        ).toBeInTheDocument();
    });

    /** A outra metade da rectificação, que tinha ficado de fora. */
    it('oferece a nota de debito', async () => {
        vi.stubGlobal('fetch', responder([factura({ id: 4 })]));

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        expect(screen.getByRole('link', { name: 'Nota de débito (acrescentar)' })).toHaveAttribute(
            'href',
            '/invoicing/debit-notes/create?invoice=4',
        );
    });

    it('diz que não há nada e deixa limpar os filtros', async () => {
        vi.stubGlobal('fetch', responder([]));

        mostrar();

        expect(await screen.findByText('Nenhuma factura com estes filtros')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Limpar filtros/ })).toBeInTheDocument();
    });

    /**
     * A SESSÃO EXPIRAR NÃO É FALTA DE REDE. Dizer «sem ligação» a quem só
     * precisa de voltar a entrar manda a pessoa reiniciar o router.
     */
    it('distingue a sessão expirada de uma falha de rede', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 419,
                    json: () => Promise.resolve({ message: 'CSRF token mismatch.' }),
                } as Response),
            ),
        );

        mostrar();

        expect(await screen.findByText('A sessão expirou')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Voltar a entrar/ })).toBeInTheDocument();
    });

    it('explica quando não há permissão, em vez de mostrar uma tabela vazia', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 403,
                    json: () => Promise.resolve({ message: 'Sem permissão para ver facturas de venda.' }),
                } as Response),
            ),
        );

        mostrar();

        expect(await screen.findByText('Sem acesso a este ecrã')).toBeInTheDocument();
    });

    it('volta à primeira página quando se muda um filtro', async () => {
        const espia = responder([factura()]);
        vi.stubGlobal('fetch', espia);

        mostrar();

        await screen.findByText('FT SOSFT/000003');

        await userEvent.type(screen.getByPlaceholderText('Número, série ou cliente'), 'SOSFT');

        await waitFor(() => {
            const pedidos = espia.mock.calls.map((c) => String(c[0]));
            expect(pedidos.some((u) => u.includes('procura=SOSFT'))).toBe(true);
            // A página nunca viaja como 7 depois de mudar a procura.
            expect(pedidos.every((u) => !u.includes('page=7'))).toBe(true);
        });
    });
});
