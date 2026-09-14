import { useState } from 'react';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { ItemDaTransferencia } from '@/api/transferencias';
import { transferencias } from '@/api/transferencias';

import { Carrinho } from './Carrinho';

/*
 * «SELECCIONO E NÃO APARECE ONDE VÃO OS PRODUTOS.» A lista de sugestões ficava
 * sempre aberta por cima da tabela: juntava-se um artigo, a lista reabria sem
 * ele e tapava a linha da quantidade.
 */

const ARTIGOS = [
    { id: 1, name: 'DICLOFENAC gel', code: '8906101702725', unit: 'un', disponivel: 9, custo: 100 },
    { id: 2, name: 'Doriz tabelas', code: '6009708841513', unit: 'un', disponivel: 30, custo: 50 },
];

function ComEstado({ comTecto = true }: { comTecto?: boolean }) {
    const [itens, porItens] = useState<ItemDaTransferencia[]>([]);

    return <Carrinho armazem="1" itens={itens} aoMudar={porItens} comTecto={comTecto} />;
}

describe('carrinho das transferências', () => {
    let pedido: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        vi.useFakeTimers();
        pedido = vi.spyOn(transferencias, 'artigos').mockResolvedValue({ data: ARTIGOS } as never);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    const esperarProcura = async () => {
        await act(async () => {
            await vi.advanceTimersByTimeAsync(350);
        });
    };

    it('a lista só abre quando se procura, e fecha ao juntar — a linha fica à vista', async () => {
        render(<ComEstado />);
        await esperarProcura();

        // Com a janela aberta e sem procurar, nada tapa o carrinho.
        expect(screen.queryByRole('listbox')).toBeNull();
        expect(pedido).not.toHaveBeenCalled();

        fireEvent.change(screen.getByRole('combobox'), { target: { value: 'd' } });
        await esperarProcura();
        expect(screen.getByRole('listbox')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: /DICLOFENAC gel/ }));

        expect(screen.queryByRole('listbox')).toBeNull();
        const quantidade = screen.getByRole('spinbutton', { name: 'Quantidade de DICLOFENAC gel' }) as HTMLInputElement;
        expect(quantidade.value).toBe('1');
        expect(screen.getByRole('combobox')).toBe(document.activeElement);

        // Mudar a quantidade não volta a perguntar ao servidor.
        const antes = pedido.mock.calls.length;
        fireEvent.change(quantidade, { target: { value: '4' } });
        await esperarProcura();
        expect(pedido.mock.calls.length).toBe(antes);
        expect(quantidade.value).toBe('4');
    });

    it('o artigo já junto sai das sugestões, e o tecto é o que o armazém tem', async () => {
        render(<ComEstado />);

        fireEvent.change(screen.getByRole('combobox'), { target: { value: 'd' } });
        await esperarProcura();
        fireEvent.click(screen.getByRole('button', { name: /DICLOFENAC gel/ }));

        fireEvent.click(screen.getByRole('combobox'));
        await esperarProcura();
        const lista = within(screen.getByRole('listbox'));
        expect(lista.queryByRole('button', { name: /DICLOFENAC gel/ })).toBeNull();
        expect(lista.getByRole('button', { name: /Doriz tabelas/ })).toBeTruthy();

        fireEvent.mouseDown(document.body);
        expect(screen.queryByRole('listbox')).toBeNull();

        const quantidade = screen.getByRole('spinbutton', { name: 'Quantidade de DICLOFENAC gel' }) as HTMLInputElement;
        fireEvent.change(quantidade, { target: { value: '50' } });
        expect(quantidade.value).toBe('9');
    });

    it('o leitor de código de barras: Enter junta o artigo do código exacto', async () => {
        render(<ComEstado />);

        const caixa = screen.getByRole('combobox');
        fireEvent.change(caixa, { target: { value: '6009708841513' } });
        await esperarProcura();
        fireEvent.keyDown(caixa, { key: 'Enter' });

        expect(screen.getByRole('spinbutton', { name: 'Quantidade de Doriz tabelas' })).toBeTruthy();
        expect(screen.queryByRole('listbox')).toBeNull();
    });
});
