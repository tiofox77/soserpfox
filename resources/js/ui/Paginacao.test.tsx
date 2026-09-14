import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Paginacao, paginasVisiveis } from './Paginacao';

describe('paginação por número', () => {
    it('poucas páginas: todas, sem reticências', () => {
        expect(paginasVisiveis(1, 1)).toEqual([1]);
        expect(paginasVisiveis(3, 7)).toEqual([1, 2, 3, 4, 5, 6, 7]);
    });

    it('muitas páginas: a primeira, a última, a actual com os vizinhos e reticências', () => {
        expect(paginasVisiveis(1, 397)).toEqual([1, 2, 'depois', 397]);
        expect(paginasVisiveis(200, 397)).toEqual([1, 'antes', 199, 200, 201, 'depois', 397]);
        expect(paginasVisiveis(397, 397)).toEqual([1, 'antes', 396, 397]);
    });

    it('uma reticência que esconderia uma só página mostra a página', () => {
        expect(paginasVisiveis(4, 20)).toEqual([1, 2, 3, 4, 5, 'depois', 20]);
        expect(paginasVisiveis(17, 20)).toEqual([1, 'antes', 16, 17, 18, 19, 20]);
    });

    it('uma página só não mostra nada', () => {
        const { container } = render(<Paginacao pagina={1} ultima={1} aMudar={() => {}} />);
        expect(container.innerHTML).toBe('');
    });

    it('com uma página só, o «Por página» continua à vista e os números não', () => {
        render(<Paginacao pagina={1} ultima={1} aMudar={() => {}} extra={<label>Por página</label>} />);
        expect(screen.getByText('Por página')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Página seguinte' })).toBeNull();
    });

    it('carregar num número, nas setas e ir directo à página', () => {
        const aMudar = vi.fn();
        render(<Paginacao pagina={1} ultima={397} aMudar={aMudar} total={5952} de={1} ate={15} />);

        expect(screen.getByRole('button', { name: 'Página 1' }).getAttribute('aria-current')).toBe('page');
        expect((screen.getByRole('button', { name: 'Página anterior' }) as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByText(/1–15 de 5\s?952/)).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Página 2' }));
        expect(aMudar).toHaveBeenLastCalledWith(2);

        fireEvent.click(screen.getByRole('button', { name: 'Última página' }));
        expect(aMudar).toHaveBeenLastCalledWith(397);

        fireEvent.change(screen.getByRole('spinbutton', { name: 'Ir para a página' }), { target: { value: '9999' } });
        fireEvent.submit(screen.getByRole('spinbutton', { name: 'Ir para a página' }).closest('form')!);
        expect(aMudar).toHaveBeenLastCalledWith(397);

        // A página em que já se está não volta a pedir.
        aMudar.mockClear();
        fireEvent.click(screen.getByRole('button', { name: 'Página 1' }));
        expect(aMudar).not.toHaveBeenCalled();
    });
});
