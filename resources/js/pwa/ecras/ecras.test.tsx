import 'fake-indexeddb/auto';

import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import { ContextoDoPwa } from '../contexto';
import { db } from '../motor/base';
import type { PropsDoPwa } from '../tipos';
import { Dialogos } from '../ui/Dialogos';
import { Documentos } from './Documentos';
import { NovoDocumento } from './NovoDocumento';
import { Pos } from './Pos';

/**
 * AS REGRAS DOS ECRÃS, a correr — contra a base verdadeira (IndexedDB em
 * memória) e com o ecrã desenhado.
 *
 * Eram ensaios node que recortavam o Blade por expressão regular
 * (`tests/pwa/turno-obrigatorio.test.mjs`, `iec-e-selo-vao-por-escolha`,
 * `retencao-no-ecra`): provavam que uma frase estava no ficheiro. Agora prova-se
 * o que o operador vê e o que vai para a fila.
 */

const PROPS: PropsDoPwa = {
    ecra: 'pos',
    csrf: 'x',
    lingua: 'pt',
    versao: { numero: '2.0.1', assinatura: 'abc', etiqueta: '13/09/2026 10:00' },
    rotas: {
        entrada: '/invoicing/offline/login', pinEsquecido: '/invoicing/offline/pin-esquecido', inicio: '/invoicing/offline',
        catalogo: '/invoicing/offline/catalog', clientes: '/invoicing/offline/clients', novoCliente: '/invoicing/offline/clients/new',
        documentos: '/invoicing/offline/drafts', novoDocumento: '/invoicing/offline/drafts/new', pos: '/invoicing/offline/pos',
        restaurante: '/invoicing/offline/restaurant', sair: '/invoicing/offline/sair', aplicacao: '/invoicing/offline/exit',
        definirPin: '/invoicing/offline/pin', login: '/login', subscricaoExpirada: '/subscription-expired',
    },
    utilizador: { id: 1, nome: 'Ana', email: 'ana@x.ao' },
    empresa: 1,
    menu: [],
    provincias: ['Luanda'],
};

function desenhar(ecra: React.ReactNode) {
    return render(
        <ContextoDoPwa.Provider value={PROPS}>
            {ecra}
            <Dialogos />
        </ContextoDoPwa.Provider>,
    );
}

beforeAll(() => {
    // O jsdom não tem estes: o POS pergunta se o ecrã é largo, e o arranque
    // do motor não corre aqui (os ecrãs lêem a base directamente).
    window.matchMedia ??= ((q: string) => ({
        matches: false, media: q, onchange: null,
        addEventListener: () => {}, removeEventListener: () => {}, addListener: () => {}, removeListener: () => {}, dispatchEvent: () => false,
    })) as unknown as typeof window.matchMedia;
    Element.prototype.scrollIntoView ??= () => {};
    if (!('timeout' in AbortSignal)) (AbortSignal as any).timeout = () => new AbortController().signal;
});

beforeEach(async () => {
    await Promise.all(db.tables.map((t) => t.clear()));
    // Sem rede: nada de sincronizações a meio do ensaio.
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 503 })));
    await db.products.bulkPut([
        { id: 1, name: 'Água 1,5L', price: 500, tax_rate: 14, type: 'produto', manage_stock: false, category: 'Bebidas' },
        { id: 2, name: 'Sumo', price: 800, tax_rate: 14, type: 'produto', manage_stock: false, category: 'Bebidas' },
    ]);
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('o POS sem turno aberto', () => {
    it('não vende: o Finalizar fica desactivado, diz que é bloqueio e oferece abrir ali mesmo', async () => {
        const confirmar = vi.spyOn(window, 'confirm');
        await db.meta.put({ key: 'shift', value: { open: false } });

        desenhar(<Pos />);

        // Com artigos no carrinho, e mesmo assim não sai.
        const artigo = await screen.findByRole('button', { name: /Água 1,5L/ });
        await act(async () => { fireEvent.click(artigo); });

        const finalizar = await screen.findByRole('button', { name: /Finalizar Venda/ });
        expect(finalizar).toBeDisabled();

        expect(screen.getAllByText(/abra um turno para poder vender/).length).toBeGreaterThan(0);
        expect(screen.queryByText(/as vendas podem não entrar no fecho de caixa/)).toBeNull();
        expect(screen.getAllByRole('button', { name: /^Abrir( turno)?$/ }).length).toBeGreaterThan(0);

        // Um «quer vender à mesma?» é um convite: quem tem fila carrega em OK.
        expect(confirmar).not.toHaveBeenCalled();
        expect(await db.sync_queue.count()).toBe(0);
    });

    it('com turno aberto e o carrinho cheio, o Finalizar fica disponível', async () => {
        await db.meta.put({ key: 'shift', value: { open: true, number: 'T-1', opened_at: new Date().toISOString(), opening_balance: 0, cash_sales: 0 } });

        desenhar(<Pos />);

        const artigo = await screen.findByRole('button', { name: /Água 1,5L/ });
        await act(async () => { fireEvent.click(artigo); });

        await waitFor(() => expect(screen.getByRole('button', { name: /Finalizar Venda/ })).toBeEnabled());
    });
});

describe('o novo documento', () => {
    it('o IEC e o Selo vão por ESCOLHA do código, e nunca como valor', async () => {
        await db.iec_pautais.put({ pautal_code: '2203.00', description: 'Cerveja', rate_percentage: 8 });
        await db.is_verbas.put({ verba_no: '23.3', description: 'Recibos' });

        desenhar(<NovoDocumento />);

        await act(async () => { fireEvent.click(await screen.findByRole('button', { name: /^\s*Adicionar\s*$/ })); });
        const folha = await waitFor(() => {
            const f = document.querySelector<HTMLElement>('[data-ensaio="folha-produto"]');
            expect(f).not.toBeNull();

            return f!;
        });
        const produto = await within(folha).findByRole('button', { name: /Água 1,5L/ });
        await act(async () => { fireEvent.click(produto); });

        const iec = await waitFor(() => {
            const s = document.querySelector<HTMLSelectElement>('select[name="iec_pautal"]');
            expect(s).not.toBeNull();

            return s!;
        });

        // A linha nasce sem escolha; o ecrã oferece o código da tabela da AGT.
        expect(iec.value).toBe('');
        expect([...iec.options].map((o) => o.value)).toContain('2203.00');
        expect(document.querySelector('select[name="is_verba"]')).not.toBeNull();

        // Nenhuma caixa de valor: seria o aparelho a apurar o imposto.
        expect(document.querySelector('[name="iec_amount"], [name="is_amount"]')).toBeNull();
    });

    it('a retenção só existe em serviços, e nasce com 6,5%', async () => {
        desenhar(<NovoDocumento />);

        expect(document.querySelector('[name="withholding_percentage"]')).toBeNull();

        const servico = await waitFor(() => {
            const c = document.querySelector<HTMLInputElement>('input[name="is_service"]');
            expect(c).not.toBeNull();

            return c!;
        });
        await act(async () => { fireEvent.click(servico); });

        const pct = await waitFor(() => {
            const c = document.querySelector<HTMLInputElement>('[name="withholding_percentage"]');
            expect(c).not.toBeNull();

            return c!;
        });
        expect(parseFloat(pct.value)).toBe(6.5);
    });
});

describe('a lista dos documentos', () => {
    it('um documento não se apaga no aparelho — nem o emitido, nem o por enviar', async () => {
        await db.draft_documents.bulkPut([
            { local_uuid: 'd_1', doc_type: 'FT', client_name: 'Cliente Emitido', items: [], total: 1140, created_at: new Date().toISOString(), _synced: 1, _server_number: 'FT A/1' },
            { local_uuid: 'd_2', doc_type: 'proforma', client_name: 'Cliente Pendente', items: [], total: 570, created_at: new Date().toISOString(), _synced: 0 },
        ]);

        desenhar(<Documentos />);

        await screen.findByText('Cliente Emitido');
        await screen.findByText('Cliente Pendente');

        expect(screen.queryByRole('button', { name: /apagar|eliminar/i })).toBeNull();
        expect(document.querySelector('.fa-trash')).toBeNull();
    });
});
