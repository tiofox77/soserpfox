import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa, SemNada, cascata } from './faixa';

/**
 * O ASPECTO DE SEMPRE, PROVADO SEM BROWSER.
 *
 * O que aqui se verifica não é bonito nem feio — é o que os ensaios do
 * browser procuram e o que se perdeu na migração: o cabeçalho com gradiente e
 * ícone, o estado vazio com desenho, e a porta dos relatórios com uma cor por
 * secção. Um `data-mapa` a menos ou um título com texto a mais parte o
 * `react.relatorios.spec.js`, e isso vê-se aqui em dois segundos em vez de ao
 * fim de uma construção inteira.
 */

describe('a faixa de cabeçalho', () => {
    it('leva gradiente, ícone, título, subtítulo e acções', () => {
        const { container } = render(
            <Faixa icone="fa-chart-bar" titulo="Relatórios" subtitulo="Mapas da facturação" accoes={<a href="/x" className={ACCAO_DA_FAIXA}>CSV</a>}>
                <EstadoNaFaixa icone="fa-flask">A emitir em Homologação</EstadoNaFaixa>
            </Faixa>,
        );

        expect(screen.getByRole('heading', { name: 'Relatórios' })).toBeTruthy();
        expect(screen.getByText('Mapas da facturação')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'CSV' })).toBeTruthy();
        expect(screen.getByText('A emitir em Homologação')).toBeTruthy();
        expect(container.querySelector('.bg-gradient-to-r')).toBeTruthy();
        expect(container.querySelector('.fa-chart-bar')).toBeTruthy();
    });

    it('o título é um h2 — o h1 da página é o do layout', () => {
        render(<Faixa icone="fa-key" titulo="PIN" />);

        expect(screen.getByRole('heading', { level: 2, name: 'PIN' })).toBeTruthy();
    });

    it('o ícone não entra no nome do título', () => {
        // O `react.series-auditoria.spec.js` procura o texto EXACTO.
        render(<Faixa icone="fa-hashtag" titulo="Séries de Documentos" subtitulo="Numeração dos documentos fiscais" />);

        expect(screen.getByRole('heading', { name: 'Séries de Documentos' })).toBeTruthy();
    });
});

describe('o estado vazio', () => {
    it('tem o círculo, o ícone, o que se passa e o que fazer', () => {
        const { container } = render(
            <SemNada icone="fa-hashtag" titulo="Nenhuma série encontrada" frase="Crie a primeira." accao={<button type="button">Criar a primeira</button>} />,
        );

        expect(screen.getByText('Nenhuma série encontrada')).toBeTruthy();
        expect(screen.getByText('Crie a primeira.')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Criar a primeira' })).toBeTruthy();
        // O círculo de 80 px do ecrã em Blade.
        expect(container.querySelector('.h-20.w-20.rounded-full')).toBeTruthy();
        expect(container.querySelector('.fa-hashtag')).toBeTruthy();
    });
});

describe('a entrada em cascata', () => {
    it('dá o atraso na variável que o CSS lê, com tecto', () => {
        expect(cascata(0)).toEqual({ '--i': 0 });
        expect(cascata(3)).toEqual({ '--i': 3 });
        // Numa lista longa a última linha não pode entrar um segundo depois.
        expect(cascata(80)).toEqual({ '--i': 12 });
    });
});

/* ─── A porta dos relatórios ──────────────────────────────────────────── */

vi.mock('@/api/relatorios', () => ({
    relatorios: {
        seccoes: () =>
            Promise.resolve({
                seccoes: [
                    {
                        titulo: 'Rentabilidade & Análise',
                        icone: 'fa-coins',
                        cor: 'emerald',
                        relatorios: [
                            { slug: 'charts', nome: 'Relatório em Gráficos', desc: 'Evolução e rankings', icone: 'fa-chart-area', caminho: '/invoicing/reports/charts' },
                            { slug: 'margin', nome: 'Análise de Margem', desc: 'Lucro por produto', icone: 'fa-percentage', caminho: '/invoicing/reports/margin' },
                        ],
                    },
                    {
                        titulo: 'Fiscal & SAFT',
                        icone: 'fa-landmark',
                        cor: 'red',
                        relatorios: [
                            { slug: 'vat', nome: 'Mapa de IVA', desc: 'IVA liquidado', icone: 'fa-percent', caminho: '/invoicing/reports/vat' },
                        ],
                    },
                ],
            }),
    },
}));

async function abrirOHub() {
    const { default: RelatoriosHub } = await import('./RelatoriosHub');
    const cache = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    return render(
        <QueryClientProvider client={cache}>
            <RelatoriosHub />
        </QueryClientProvider>,
    );
}

describe('a porta dos relatórios', () => {
    it('dá a cada secção o seu cartão colorido, e um cartão por mapa', async () => {
        const { container } = await abrirOHub();

        // O nome da secção é o nome da secção e mais nada: é assim que o
        // `react.relatorios.spec.js` lhe chega.
        expect(await screen.findByRole('heading', { name: 'Rentabilidade & Análise' })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Fiscal & SAFT' })).toBeTruthy();

        // Um `data-mapa` por relatório — o ensaio conta-os.
        expect(container.querySelectorAll('[data-mapa]').length).toBe(3);
        expect(container.querySelector('[data-mapa="vat"]')?.getAttribute('href')).toBe('/invoicing/reports/vat');

        // A cor da secção chega ao cartão: verde na rentabilidade, vermelho no
        // fiscal. Sem isto, vinte e três caixas brancas iguais.
        expect(container.querySelector('.from-emerald-500')).toBeTruthy();
        expect(container.querySelector('.from-red-500')).toBeTruthy();

        // E o movimento: os cartões entram em cascata.
        expect(container.querySelector('[data-mapa].entra')).toBeTruthy();
    });
});
