import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('legendas das acoes', () => {
    beforeEach(() => {
        vi.resetModules();
        document.body.innerHTML = '';
        document.head.querySelector('#accoes-react-estilo-global')?.remove();
    });

    it('mostra por cima a legenda de um botao que tem apenas icone', async () => {
        document.body.innerHTML = `
            <div class="ecra-react">
                <button aria-label="Editar cliente"><i class="fas fa-pen"></i></button>
            </div>`;

        const { activarDicasDeAccao } = await import('./DicasDeAccao');
        activarDicasDeAccao();

        const botao = document.querySelector('button')!;
        botao.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        await vi.waitFor(() => expect(document.querySelector('[role="tooltip"]')?.textContent).toBe('Editar cliente'));
        expect(document.querySelector('[role="tooltip"]')?.getAttribute('aria-hidden')).toBe('false');
    });

    it('nao repete uma legenda num botao que ja tem texto', async () => {
        document.body.innerHTML = `
            <div class="ecra-react">
                <button aria-label="Guardar"><i class="fas fa-save"></i>Guardar</button>
            </div>`;

        const { activarDicasDeAccao } = await import('./DicasDeAccao');
        activarDicasDeAccao();
        document.querySelector('button')!.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));

        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(document.querySelector('[role="tooltip"]')?.getAttribute('aria-hidden')).toBe('true');
    });

    it('da uma area tactil minima aos botoes que mostram apenas um icone', async () => {
        document.body.innerHTML = `
            <div class="ecra-react">
                <button aria-label="Descarregar PDF"><i class="fas fa-file-pdf"></i></button>
            </div>`;

        const { activarDicasDeAccao } = await import('./DicasDeAccao');
        activarDicasDeAccao();

        const css = document.querySelector('#accoes-react-estilo-global')?.textContent ?? '';
        expect(css).toContain('min-width: 36px');
        expect(css).toContain('min-height: 36px');
    });
});
