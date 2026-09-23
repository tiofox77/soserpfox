import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { AProcessar } from './AProcessar';

/**
 * «A registar a venda…»: enquanto grava, nada mais se toca, e sair da
 * página pergunta primeiro (23/09/2026).
 */

afterEach(() => {
    vi.useRealTimers();
});

const aberto = () => document.querySelector('dialog')?.hasAttribute('open') ?? false;

describe('o ecrã «a processar»', () => {
    it('abre com a venda a gravar, não fecha com o Escape, e diz quando a rede está lenta', () => {
        vi.useFakeTimers();
        const { rerender } = render(<AProcessar activo={false} />);
        expect(aberto()).toBe(false);

        rerender(<AProcessar activo />);
        expect(aberto()).toBe(true);
        expect(screen.getByRole('alertdialog', { name: 'A registar a venda…' })).toBeInTheDocument();

        // O Escape dispara `cancel`: fica impedido.
        const cancelar = new Event('cancel', { cancelable: true });
        document.querySelector('dialog')!.dispatchEvent(cancelar);
        expect(cancelar.defaultPrevented).toBe(true);

        // Sair da página a meio: o browser pergunta.
        const sair = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(sair);
        expect(sair.defaultPrevented).toBe(true);

        expect(screen.queryByText(/A ligação está lenta/)).toBeNull();
        act(() => { vi.advanceTimersByTime(6000); });
        expect(screen.getByText(/A ligação está lenta/)).toBeInTheDocument();

        rerender(<AProcessar activo={false} />);
        expect(aberto()).toBe(false);

        const depois = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(depois);
        expect(depois.defaultPrevented).toBe(false);
    });

    it('um clique no fundo não o fecha', () => {
        render(<AProcessar activo titulo="A emitir o documento…" />);

        fireEvent.click(document.querySelector('dialog')!);

        expect(aberto()).toBe(true);
        expect(screen.getByRole('alertdialog', { name: 'A emitir o documento…' })).toBeInTheDocument();
    });
});
