import { act, render, renderHook, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { abrirOPapel, useImprimirAoGravar } from './imprimirAoGravar';
import { PainelDeSucesso, PapelBloqueado } from './PecasDoEditor';

/**
 * «IMPRIMIR AO GRAVAR» nos editores: quando abre o PDF, quando não abre, e o
 * que se diz quando o browser o bloqueia.
 */
describe('imprimir ao gravar', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('abre o PDF numa janela nova e corta a ligação de volta', () => {
        const janela = { opener: window } as unknown as Window;
        const open = vi.spyOn(window, 'open').mockReturnValue(janela);

        expect(abrirOPapel('/invoicing/sales/invoices/7/pdf')).toBe(true);
        expect(open).toHaveBeenCalledWith('/invoicing/sales/invoices/7/pdf', '_blank');
        expect(janela.opener).toBeNull();
    });

    it('diz que foi bloqueado quando o browser não deixa abrir', () => {
        vi.spyOn(window, 'open').mockReturnValue(null);

        expect(abrirOPapel('/x/pdf')).toBe(false);
    });

    it('desligado nas definições, não abre nada', () => {
        const open = vi.spyOn(window, 'open').mockReturnValue(null);
        const { result } = renderHook(() => useImprimirAoGravar(false));

        act(() => result.current.depoisDeGravar('/x/pdf', 'pending'));

        expect(open).not.toHaveBeenCalled();
        expect(result.current.bloqueado).toBe(false);
    });

    it('um rascunho nunca se imprime, mesmo com o interruptor ligado', () => {
        const open = vi.spyOn(window, 'open').mockReturnValue(null);
        const { result } = renderHook(() => useImprimirAoGravar(true));

        act(() => result.current.depoisDeGravar('/x/pdf', 'draft'));

        expect(open).not.toHaveBeenCalled();
        expect(result.current.bloqueado).toBe(false);
    });

    it('ligado e emitido, abre — e se o browser bloquear, fica marcado até se esquecer', () => {
        const open = vi.spyOn(window, 'open').mockReturnValue(null);
        const { result } = renderHook(() => useImprimirAoGravar(true));

        // Uma nota ou um adiantamento não mandam estado: nascem emitidos.
        act(() => result.current.depoisDeGravar('/invoicing/credit-notes/3/pdf'));

        expect(open).toHaveBeenCalledWith('/invoicing/credit-notes/3/pdf', '_blank');
        expect(result.current.bloqueado).toBe(true);

        act(() => result.current.esquecer());

        expect(result.current.bloqueado).toBe(false);
    });

    it('o painel de sucesso mostra o aviso do papel bloqueado por cima dos botões', () => {
        render(
            <PainelDeSucesso numero="ORC 2026/1" mensagem="Gravado." aviso={<PapelBloqueado />}>
                <button type="button">PDF</button>
            </PainelDeSucesso>,
        );

        expect(screen.getByRole('alert').textContent).toContain('A impressão foi bloqueada');
        expect(screen.getByRole('button', { name: 'PDF' })).toBeTruthy();
    });
});
