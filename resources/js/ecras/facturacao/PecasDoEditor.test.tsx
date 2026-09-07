import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import {
    ApagarLinha,
    Aviso,
    CartaoDeTotais,
    FaixaDeDuplicado,
    FaixaDoDocumento,
    NaoAbriu,
    PainelDeSucesso,
    ParcelaDoTotal,
    SemNada,
    TotalGrande,
} from './PecasDoEditor';

describe('peças do editor', () => {
    it('o painel de sucesso mostra o número, a mensagem e a AGT', () => {
        render(
            <PainelDeSucesso numero="FT SOSFT/000003" mensagem="Factura emitida." agt="Comunicada à AGT">
                <button type="button">PDF</button>
            </PainelDeSucesso>,
        );

        expect(screen.getByText('FT SOSFT/000003')).toBeTruthy();
        expect(screen.getByText('Factura emitida.')).toBeTruthy();
        expect(screen.getByText('Comunicada à AGT')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'PDF' })).toBeTruthy();
    });

    it('o cartão dos totais mostra as parcelas e o total grande', () => {
        const { container } = render(
            <CartaoDeTotais titulo="Totais" aContar>
                <dl>
                    <ParcelaDoTotal rotulo="Valor bruto" valor="1.000,00" />
                    <ParcelaDoTotal rotulo="Imposto" valor="140,00" icone="fa-percent" realce="imposto" />
                </dl>
                <TotalGrande rotulo="Total" valor="1.140,00" nota="Contado no servidor" />
            </CartaoDeTotais>,
        );

        expect(screen.getByText('Totais')).toBeTruthy();
        expect(screen.getByText('Imposto')).toBeTruthy();
        expect(screen.getByText('1.140,00')).toBeTruthy();
        expect(screen.getByText('Contado no servidor')).toBeTruthy();
        // O cabeçalho leva o gradiente da casa.
        expect(container.querySelector('.bg-gradient-to-r')).toBeTruthy();
        expect(screen.getByRole('status')).toBeTruthy();
    });

    it('a faixa do documento distingue rascunho de só-leitura por ícone', () => {
        const { container, rerender } = render(
            <FaixaDoDocumento soLeitura={false} numero="Rascunho" estado="draft">
                pode alterar
            </FaixaDoDocumento>,
        );

        expect(container.querySelector('[data-documento-aberto]')).toBeTruthy();
        expect(container.querySelector('.fa-pen-to-square')).toBeTruthy();

        rerender(
            <FaixaDoDocumento soLeitura numero="FT/1" estado="sent">
                só leitura
            </FaixaDoDocumento>,
        );

        expect(container.querySelector('.fa-lock')).toBeTruthy();
    });

    it('a faixa do duplicado guarda o número da origem no data-*', () => {
        const { container } = render(<FaixaDeDuplicado numeroDaOrigem="FT/9">veio dali</FaixaDeDuplicado>);

        expect(container.querySelector('[data-duplicado-de="FT/9"]')).toBeTruthy();
    });

    it('o vazio, o aviso, o erro e o apagar linha desenham-se', () => {
        const { container } = render(
            <>
                <SemNada icone="fa-calculator">Escolha um artigo e uma quantidade para ver os totais.</SemNada>
                <Aviso>Já não se edita.</Aviso>
                <NaoAbriu titulo="Não abriu" mensagem="Verifique a ligação." />
                <ApagarLinha aoCarregar={() => {}} rotulo="Apagar linha 1" />
            </>,
        );

        expect(screen.getByText('Escolha um artigo e uma quantidade para ver os totais.')).toBeTruthy();
        expect(screen.getByText('Já não se edita.')).toBeTruthy();
        expect(screen.getByText('Não abriu')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Apagar linha 1' })).toBeTruthy();
        // O círculo de 80px do estado vazio.
        expect(container.querySelector('.h-20.w-20')).toBeTruthy();
        expect(screen.getAllByRole('alert').length).toBe(2);
    });
});
