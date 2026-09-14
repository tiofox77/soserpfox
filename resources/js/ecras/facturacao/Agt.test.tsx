import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import Agt from './Agt';
import CredenciaisAgt from './CredenciaisAgt';
import type { Contribuinte, EstadoDaAgt, OpcoesDaAgt } from '@/api/agt';

/**
 * A AGT EM REACT, SEM BROWSER.
 *
 * O que se guarda aqui é o que a auditoria de paridade com o ecrã de sempre
 * encontrou em falta, e que numa área fiscal custa caro:
 *
 *  · mudar o ambiente que emite era UM clique, sem pergunta — agora abre a
 *    confirmação, e para produção não confirma com um requisito a falso;
 *  · o aviso de «os documentos não estão a ser comunicados» não existia;
 *  · «Repor e reenviar» mandava à AGT, sem pergunta, um documento já recusado;
 *  · as séries eram «registada» ou «por registar», e uma rejeitada ou uma não
 *    fiscal dizia o mesmo que uma à espera.
 */

beforeAll(() => {
    // O jsdom não abre um `<dialog>`: faz-se o que o browser faz, marcá-lo aberto.
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
    HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); };
});

const OPCOES: OpcoesDaAgt = {
    ambientes: [{ valor: 'sandbox', rotulo: 'Homologação' }, { valor: 'production', rotulo: 'Produção' }],
    operacoes: [{ valor: 'listarFacturas', rotulo: 'Listar facturas' }],
    cae: [{ codigo: '47110', descricao: 'Comércio a retalho' }],
    empresas: [],
    permissoes: { pode_editar: true, escolhe_empresa: false },
};

function estado(por: Partial<EstadoDaAgt> = {}): EstadoDaAgt {
    const aVer = por.ambiente ?? 'sandbox';

    return {
        empresa: { id: 7, nome: 'Farmácia Central', nif: '5001636863' },
        ambiente: aVer,
        definicoes: { agt_environment: 'sandbox', agt_auto_submit: true, agt_eac_code: '47110', agt_require_validation: true },
        ambientes: {
            sandbox: { rotulo: 'Homologação', chaves: true, activo: true, a_ver: aVer === 'sandbox', produtor: true },
            production: { rotulo: 'Produção', chaves: false, activo: false, a_ver: aVer === 'production', produtor: true },
        },
        chaves: { publica: true, privada: true, produtor: true },
        em_falta: [],
        relatorio: {},
        series: [],
        submissoes: [],
        logs: [],
        ...por,
    };
}

type Pedido = { url: string; metodo: string; corpo: Record<string, unknown> };

function servidor(respostas: { estado?: (url: string) => EstadoDaAgt; contribuinte?: Contribuinte; escrita?: (p: Pedido) => { status?: number; corpo: unknown } }) {
    const pedidos: Pedido[] = [];

    const fetch = vi.fn((url: string, opcoes: RequestInit = {}) => {
        const p: Pedido = { url, metodo: opcoes.method ?? 'GET', corpo: opcoes.body ? JSON.parse(String(opcoes.body)) : {} };
        pedidos.push(p);

        let status = 200;
        let corpo: unknown = {};

        if (url.includes('/agt/opcoes')) corpo = OPCOES;
        else if (url.includes('/agt/estado')) corpo = (respostas.estado ?? (() => estado()))(url);
        else if (url.includes('/agt/contribuinte') && p.metodo === 'GET') corpo = { data: respostas.contribuinte, permissoes: { pode_editar: true } };
        else if (respostas.escrita) ({ status = 200, corpo } = respostas.escrita(p));
        else corpo = { message: 'Feito.' };

        return Promise.resolve({ ok: status < 400, status, json: () => Promise.resolve(corpo) } as Response);
    });

    vi.stubGlobal('fetch', fetch);

    return pedidos;
}

function mostrar(ecra: React.ReactNode) {
    const cliente = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 }, mutations: { retry: false } } });

    return render(<QueryClientProvider client={cliente}>{ecra}</QueryClientProvider>);
}

beforeEach(() => {
    document.head.innerHTML = '<meta name="csrf-token" content="ensaio">';
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const escritas = (pedidos: Pedido[], pedaco: string) => pedidos.filter((p) => p.metodo === 'POST' && p.url.includes(pedaco));

describe('AGT Angola', () => {
    it('um servidor sem os campos novos não rebenta o ecrã nem inventa alarmes', async () => {
        servidor({});

        mostrar(<Agt />);

        expect(await screen.findByText('Contexto de operação')).toBeInTheDocument();
        expect(document.querySelector('[data-aviso-comunicacao]')).toBeNull();
    });

    it('diz que os documentos não estão a ser comunicados, com os motivos e a contagem', async () => {
        servidor({
            estado: () => estado({
                definicoes: { agt_environment: 'production', agt_auto_submit: true, agt_eac_code: '', agt_require_validation: true },
                ambientes: {
                    sandbox: { rotulo: 'Homologação', chaves: true, activo: false, a_ver: false, produtor: true },
                    production: { rotulo: 'Produção', chaves: false, activo: true, a_ver: true, produtor: true },
                },
                ambiente: 'production',
                cae_em_falta: true,
                comunicacao: { comunica: false, motivos: ['Não há chaves RSA instaladas para esta empresa.'], emitidos_30d: 1108, por_comunicar: 1108 },
            }),
        });

        mostrar(<Agt />);

        const aviso = await screen.findByText('Os documentos NÃO estão a ser comunicados à AGT');
        const caixa = aviso.closest('[data-aviso-comunicacao]');
        expect(caixa).toHaveAttribute('data-aviso-comunicacao', 'bloqueado');
        expect(caixa).toHaveClass('bg-red-50');
        expect(within(caixa as HTMLElement).getByText('Não há chaves RSA instaladas para esta empresa.')).toBeInTheDocument();
        expect((caixa as HTMLElement).querySelector('[data-contagem]')?.textContent).toMatch(/emitidas nos últimos 30 dias, .*por comunicar/);
    });

    it('só em homologação, o aviso é âmbar e diz para onde vão os documentos', async () => {
        servidor({ estado: () => estado({ comunicacao: { comunica: false, motivos: ['O ambiente activo é o de homologação.'], emitidos_30d: 3, por_comunicar: 3 } }) });

        mostrar(<Agt />);

        const aviso = await screen.findByText('Os documentos estão a ir para o ambiente de testes da AGT');
        expect(aviso.closest('[data-aviso-comunicacao]')).toHaveClass('bg-amber-50');
    });

    it('passar a emitir em produção pede confirmação e não confirma com um requisito em falta', async () => {
        const pedidos = servidor({
            estado: (url) => estado({
                ambiente: url.includes('ambiente=production') ? 'production' : 'sandbox',
                prontidao_producao: [
                    { chave: 'chaves', rotulo: 'Par RSA de produção instalado', ok: false },
                    { chave: 'cae', rotulo: 'CAE definido', ok: true },
                ],
            }),
        });

        mostrar(<Agt />);

        await userEvent.click(await screen.findByRole('button', { name: /Produção/, pressed: false }));
        const botao = await screen.findByRole('button', { name: /Passar a emitir aqui/ });

        // A vermelho: é o botão que dá valor fiscal aos documentos.
        expect(botao.className).toMatch(/from-red-600/);

        await userEvent.click(botao);

        // Abriu a pergunta e NÃO mandou nada.
        const janela = await screen.findByRole('dialog');
        expect(escritas(pedidos, '/agt/ambiente')).toHaveLength(0);
        expect(within(janela).getByText(/com valor fiscal/)).toBeInTheDocument();
        expect(within(janela).getByText('Par RSA de produção instalado').closest('li')).toHaveAttribute('data-ok', '0');
        expect(within(janela).getByRole('button', { name: /Sim, passar a emitir em Produção/ })).toBeDisabled();
    });

    it('confirmado, o pedido leva a confirmação e avisa das submissões que ficam à espera', async () => {
        const pedidos = servidor({
            estado: (url) => estado({
                ambiente: url.includes('ambiente=production') ? 'production' : 'sandbox',
                pendentes_por_ambiente: { homologacao: 4, producao: 0 },
                prontidao_producao: [{ chave: 'chaves', rotulo: 'Par RSA de produção instalado', ok: true }],
            }),
            escrita: () => ({ corpo: { message: 'A empresa passou a emitir em Produção.', tipo: 'success', ambiente_activo: 'production', pendentes_por_ambiente: { homologacao: 4, producao: 0 } } }),
        });

        mostrar(<Agt />);

        await userEvent.click(await screen.findByRole('button', { name: /Produção/, pressed: false }));
        await userEvent.click(await screen.findByRole('button', { name: /Passar a emitir aqui/ }));

        const janela = await screen.findByRole('dialog');
        expect(within(janela).getByText(/Há 4 submissões de Homologação por concluir/)).toBeInTheDocument();

        await userEvent.click(within(janela).getByRole('button', { name: /Sim, passar a emitir em Produção/ }));

        await waitFor(() => expect(escritas(pedidos, '/agt/ambiente')).toHaveLength(1));
        expect(escritas(pedidos, '/agt/ambiente')[0]?.corpo).toMatchObject({ ambiente: 'production', confirmar: true });
    });

    it('a recusa do servidor mostra a lista do que falta', async () => {
        servidor({
            estado: (url) => estado({ ambiente: url.includes('ambiente=production') ? 'production' : 'sandbox', prontidao_producao: [{ chave: 'chaves', rotulo: 'Par RSA de produção instalado', ok: true }] }),
            escrita: () => ({ status: 422, corpo: { message: 'Falta configurar produção.', prontidao: [{ chave: 'produtor', rotulo: 'Credenciais do produtor de produção', ok: false }] } }),
        });

        mostrar(<Agt />);

        await userEvent.click(await screen.findByRole('button', { name: /Produção/, pressed: false }));
        await userEvent.click(await screen.findByRole('button', { name: /Passar a emitir aqui/ }));
        const janela = await screen.findByRole('dialog');
        await userEvent.click(within(janela).getByRole('button', { name: /Sim, passar a emitir em Produção/ }));

        expect(await within(janela).findByText('Falta configurar produção.')).toBeInTheDocument();
        expect(within(janela).getByText('Credenciais do produtor de produção').closest('li')).toHaveAttribute('data-ok', '0');
    });

    it('substituir o par que assina no ambiente activo pergunta antes, e só então confirma', async () => {
        const pedidos = servidor({});

        mostrar(<Agt />);

        await userEvent.click(await screen.findByRole('tab', { name: /Chaves/ }));
        await userEvent.type(screen.getByLabelText(/^Chave pública/), 'PUBLICA');
        await userEvent.type(screen.getByLabelText(/^Chave privada/), 'PRIVADA');
        await userEvent.click(screen.getByRole('button', { name: /Guardar par/ }));

        const janela = await screen.findByRole('dialog');
        expect(within(janela).getByText(/assinam os documentos de Homologação/)).toBeInTheDocument();
        expect(escritas(pedidos, '/agt/chaves')).toHaveLength(0);

        await userEvent.click(within(janela).getByRole('button', { name: /Substituir o par/ }));
        await waitFor(() => expect(escritas(pedidos, '/agt/chaves')).toHaveLength(1));
        expect(escritas(pedidos, '/agt/chaves')[0]?.corpo).toMatchObject({ ambiente: 'sandbox', confirmar: true });
    });

    it('as séries dizem registada, por registar, rejeitada (com o erro) e não aplicável', async () => {
        servidor({
            estado: () => estado({
                series: [
                    { id: 1, series_code: 'FT A', name: 'Série FT A', document_type: 'invoice', agt_series_id: 'FT7626S9153N', registada: true, estado: 'registada', tipo_rotulo: 'Factura', atcud: 'JJ7Q3K9P', erros: [] },
                    { id: 2, series_code: 'NC A', name: 'Série NC A', document_type: 'credit_note', agt_series_id: null, registada: false, estado: 'rejeitada', tipo_rotulo: 'Nota de Crédito', atcud: null, erros: [{ codigo: 'E12', descricao: 'Série já existe' }] },
                    { id: 3, series_code: 'PP A', name: 'Série PP A', document_type: 'proforma', agt_series_id: null, registada: false, estado: 'nao_aplicavel', tipo_rotulo: 'Proforma', atcud: null, erros: [] },
                    { id: 4, series_code: 'FR A', name: 'Série FR A', document_type: 'pos', agt_series_id: null, registada: false },
                ],
            }),
        });

        mostrar(<Agt />);

        await userEvent.click(await screen.findByRole('tab', { name: /Séries/ }));

        expect(document.querySelector('[data-serie="FT A"]')).toHaveAttribute('data-estado', 'registada');
        expect(screen.getByText('JJ7Q3K9P')).toBeInTheDocument();
        expect(screen.getByText('Factura')).toBeInTheDocument();

        const rejeitada = document.querySelector('[data-serie="NC A"]') as HTMLElement;
        expect(rejeitada).toHaveAttribute('data-estado', 'rejeitada');
        expect(within(rejeitada).getByText('Rejeitada')).toHaveClass('bg-red-50');
        expect(within(rejeitada).getByText('E12')).toBeInTheDocument();
        expect(within(rejeitada).getByText('Série já existe')).toBeInTheDocument();

        const naoFiscal = document.querySelector('[data-serie="PP A"]') as HTMLElement;
        expect(within(naoFiscal).getByText('Não aplicável')).toHaveClass('bg-slate-100');

        // Sem `estado` (servidor antigo), cai no `registada` de sempre.
        expect(document.querySelector('[data-serie="FR A"]')).toHaveAttribute('data-estado', 'por_registar');
    });

    it('a sincronização mostra o resultado série a série', async () => {
        servidor({
            escrita: () => ({ corpo: { message: '1 sincronizada(s); 1 falharam.', details: [
                { serie_id: 1, codigo: 'FT A', ok: true, erro: null, codigo_erro: null },
                { serie_id: 2, codigo: 'NC A', ok: false, erro: 'Série já existe', codigo_erro: 'E12' },
            ] } }),
        });

        mostrar(<Agt />);

        await userEvent.click(await screen.findByRole('tab', { name: /Séries/ }));
        await userEvent.click(screen.getByRole('button', { name: /Sincronizar com a AGT/ }));

        const painel = await waitFor(() => {
            const p = document.querySelector('[data-resultado-sincronizacao]');
            expect(p).not.toBeNull();
            return p as HTMLElement;
        });
        expect(painel).toHaveAttribute('data-resultado-sincronizacao', 'falhou');
        expect(within(painel).getByText('E12')).toBeInTheDocument();
        expect(within(painel).getByText('Série já existe')).toBeInTheDocument();
    });

    it('repor e reenviar pergunta antes, e só reenviar aparece a quem pode', async () => {
        const pedidos = servidor({
            estado: () => estado({
                submissoes: [
                    { id: 11, quando: '01/09/2026 10:00', document_type_code: 'FT', document_number: 'FT A/000011', status: 'rejected', agt_reference: null, atcud: null, error_code: 'E70', error_message: 'Imposto por linha', retry_count: 3, tentativas_max: 3, pode_reenviar: false, pode_repor: true },
                    { id: 12, quando: '01/09/2026 11:00', document_type_code: 'FT', document_number: 'FT A/000012', status: 'pending', agt_reference: null, atcud: null, error_code: null, error_message: null, retry_count: 1, tentativas_max: 3, pode_reenviar: true, pode_repor: false },
                ],
            }),
        });

        mostrar(<Agt />);

        // A que ainda espera conta no separador.
        const separador = await screen.findByRole('tab', { name: /Submissões/ });
        expect(within(separador).getByText('1')).toBeInTheDocument();

        await userEvent.click(separador);

        const esgotada = document.querySelector('[data-submissao="11"]') as HTMLElement;
        expect(within(esgotada).getByText('3/3')).toBeInTheDocument();
        expect(within(esgotada).getByText('E70')).toBeInTheDocument();
        expect(within(esgotada).queryByRole('button', { name: /^Reenviar$/ })).toBeNull();

        const aEspera = document.querySelector('[data-submissao="12"]') as HTMLElement;
        expect(within(aEspera).queryByRole('button', { name: /Repor e reenviar/ })).toBeNull();
        expect(within(aEspera).getByRole('button', { name: /Reenviar/ })).toBeInTheDocument();

        await userEvent.click(within(esgotada).getByRole('button', { name: /Repor e reenviar/ }));
        const janela = await screen.findByRole('dialog');
        expect(within(janela).getByText('Repor as tentativas e reenviar este documento à AGT agora?')).toBeInTheDocument();
        expect(escritas(pedidos, '/reenviar')).toHaveLength(0);

        await userEvent.click(within(janela).getByRole('button', { name: /Repor e reenviar/ }));
        await waitFor(() => expect(escritas(pedidos, '/agt/submissoes/11/reenviar')).toHaveLength(1));
        expect(escritas(pedidos, '/agt/submissoes/11/reenviar')[0]?.corpo).toMatchObject({ repor: true });
    });

    it('a consulta mostra o resultado com HTTP e tempo, e o JSON fechado', async () => {
        servidor({ escrita: () => ({ corpo: { ok: false, mensagem: 'Documento não encontrado', http: 404, ms: 321, testado_em: '14/09/2026 10:00:00', data: { errorList: [] } } }) });

        mostrar(<Agt />);

        await userEvent.click(await screen.findByRole('tab', { name: /Consultar a AGT/ }));
        expect(screen.getByRole('option', { name: /documentos RECEBIDOS/ })).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: /^Consultar$/ }));

        expect(await screen.findByText('Documento não encontrado')).toBeInTheDocument();
        const painel = document.querySelector('[data-resultado]') as HTMLElement;
        expect(painel).toHaveAttribute('data-resultado', 'falhou');
        expect(within(painel).getByText('HTTP 404')).toBeInTheDocument();
        expect(within(painel).getByText(/321 ms/)).toBeInTheDocument();
        expect(painel.querySelector('details')).not.toHaveAttribute('open');
    });
});

describe('a ficha do contribuinte', () => {
    const CONTRIBUINTE: Contribuinte = {
        agt_environment: 'production', tax_registration_number: '5001636863', agt_establishment_number: 'SEDE', agt_auto_submit: true,
        agt_require_validation: true, agt_notification_emails: '', agt_eac_code: '99999', produtor: false, chave_legado: true,
    };

    it('com a chave instalada, a caixa esconde-se e remover pede confirmação', async () => {
        const pedidos = servidor({ contribuinte: CONTRIBUINTE });

        mostrar(<CredenciaisAgt />);

        expect(await screen.findByText('Chave privada instalada.')).toBeInTheDocument();
        expect(screen.queryByLabelText(/^Chave privada em PEM/)).toBeNull();

        await userEvent.click(screen.getByRole('button', { name: /Substituir a chave/ }));
        expect(screen.getByLabelText(/^Chave privada em PEM/)).toHaveValue('');
        expect(screen.getByText(/a chave instalada é substituída/)).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: /^Cancelar$/ }));
        await userEvent.click(screen.getByRole('button', { name: /Remover chave/ }));

        const janela = await screen.findByRole('dialog');
        expect(within(janela).getByText('A chave será removida permanentemente.')).toBeInTheDocument();
        expect(escritas(pedidos, '/chave/remover')).toHaveLength(0);

        await userEvent.click(within(janela).getByRole('button', { name: /Remover chave/ }));
        await waitFor(() => expect(escritas(pedidos, '/agt/contribuinte/chave/remover')).toHaveLength(1));
        expect(escritas(pedidos, '/agt/contribuinte/chave/remover')[0]?.corpo).toMatchObject({ confirmar: true });
    });

    it('um CAE gravado que não está na lista não parece vazio, e sem produtor diz-se a vermelho', async () => {
        servidor({ contribuinte: CONTRIBUINTE });

        mostrar(<CredenciaisAgt />);

        expect(await screen.findByRole('option', { name: '(código gravado: 99999)' })).toBeInTheDocument();
        expect(screen.getByRole('combobox')).toHaveValue('99999');
        expect(document.querySelector('[data-sem-produtor]')).not.toBeNull();
    });
});
