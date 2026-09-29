import { useRef } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ListaFlutuante } from './ListaFlutuante';

/**
 * A lista de sugestões sai do cartão (que tem `overflow-hidden` e a cortava —
 * recibo, 29/09/2026): numa página vai para o `body`; dentro de uma janela,
 * para o `<dialog>`.
 */

function Campo({ naJanela = false, aoCarregar = vi.fn() }: { naJanela?: boolean; aoCarregar?: () => void }) {
    const ancora = useRef<HTMLDivElement>(null);
    const conteudo = (
        <section data-testid="cartao" className="overflow-hidden">
            <div ref={ancora}>
                <input aria-label="Cliente" />
            </div>
            <ListaFlutuante ancora={ancora} role="listbox">
                <li><button type="button" onClick={aoCarregar}>Aurora Kiala</button></li>
            </ListaFlutuante>
        </section>
    );

    return naJanela ? <dialog open>{conteudo}</dialog> : conteudo;
}

describe('ListaFlutuante', () => {
    it('numa página, sai do cartão para o body, fixa', () => {
        render(<Campo />);

        const lista = screen.getByRole('listbox');
        expect(screen.getByTestId('cartao').contains(lista)).toBe(false);
        expect(lista.parentElement).toBe(document.body);
        expect(lista.style.position).toBe('fixed');
    });

    it('numa janela, vai para o próprio dialog (a camada de topo)', () => {
        render(<Campo naJanela />);

        const lista = screen.getByRole('listbox');
        expect(screen.getByTestId('cartao').contains(lista)).toBe(false);
        expect(lista.parentElement?.tagName).toBe('DIALOG');
        expect(lista.style.position).toBe('absolute');
    });

    it('carregar na lista não tira o foco ao campo, e o clique chega à opção', () => {
        const aoCarregar = vi.fn();
        render(<Campo aoCarregar={aoCarregar} />);

        const opcao = screen.getByRole('button', { name: 'Aurora Kiala' });
        // `fireEvent` devolve false quando o `preventDefault` foi chamado.
        expect(fireEvent.mouseDown(opcao)).toBe(false);

        fireEvent.click(opcao);
        expect(aoCarregar).toHaveBeenCalled();
    });
});
