import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';

import EditorDeDescricao from './EditorDeDescricao';
import { eRica, paraHtml, textoDaDescricao } from './descricaoRica';

/**
 * A DESCRIÇÃO FORMATADA das linhas de proformas e orçamentos (23/09/2026).
 */

beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
    HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); };
    // O ProseMirror mede a selecção; o jsdom não sabe medir.
    Range.prototype.getBoundingClientRect = () => ({ x: 0, y: 0, width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0, toJSON: () => ({}) });
    Range.prototype.getClientRects = () => ({ length: 0, item: () => null, [Symbol.iterator]: [][Symbol.iterator] }) as unknown as DOMRectList;
    document.elementFromPoint = () => null;
});

describe('as contas da descrição', () => {
    it('sabe o que é formatado e o que é o texto de sempre', () => {
        expect(eRica('<p>Olá</p>')).toBe(true);
        expect(eRica('Preço < 1000 Kz')).toBe(false);
    });

    it('o texto antigo abre em parágrafos, escapado', () => {
        expect(paraHtml('Linha 1\nA < B')).toBe('<p>Linha 1</p><p>A &lt; B</p>');
        expect(paraHtml('')).toBe('');
    });

    it('na linha vê-se o texto, com as listas em pontos, sem correr nada', () => {
        const alerta = vi.spyOn(window, 'alert').mockImplementation(() => undefined);

        expect(textoDaDescricao('<h3>Fases</h3><ul><li><p>Filmagem</p></li><li><p>Edição</p></li></ul><img src=x onerror="alert(1)">'))
            .toBe('Fases\n• Filmagem\n• Edição');
        expect(alerta).not.toHaveBeenCalled();
    });
});

describe('o editor da descrição', () => {
    it('abre um texto antigo e guarda-o formatado', async () => {
        const aoGuardar = vi.fn();
        render(<EditorDeDescricao artigo="Edição de vídeo" valor={'Filmagem\nEdição'} aoGuardar={aoGuardar} aoFechar={() => undefined} />);

        expect(await screen.findByText('Filmagem')).toBeInTheDocument();
        expect(screen.getByRole('toolbar', { name: 'Formatação' })).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Guardar descrição/ }));

        await waitFor(() => expect(aoGuardar).toHaveBeenCalledWith('<p>Filmagem</p><p>Edição</p>'));
    });

    it('um editor vazio guarda «sem descrição»', async () => {
        const aoGuardar = vi.fn();
        render(<EditorDeDescricao artigo="Edição de vídeo" valor="" aoGuardar={aoGuardar} aoFechar={() => undefined} />);

        fireEvent.click(await screen.findByRole('button', { name: /Guardar descrição/ }));

        await waitFor(() => expect(aoGuardar).toHaveBeenCalledWith(''));
    });
});
