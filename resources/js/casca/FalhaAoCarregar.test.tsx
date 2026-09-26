import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { carregarComRecuperacao, FalhaAoCarregar } from './FalhaAoCarregar';

vi.mock('@/casca/relatarErro', () => ({ relatarErro: vi.fn() }));

const semEsperas = { esperas: [0, 0] };

describe('carregarComRecuperacao — o pedaço de um ecrã que não chega', () => {
    beforeEach(() => window.sessionStorage.clear());
    afterEach(() => vi.restoreAllMocks());

    it('a rede volta a meio: tenta outra vez e não recarrega', async () => {
        const recarregar = vi.fn();
        const carregar = vi.fn()
            .mockRejectedValueOnce(new TypeError('Failed to fetch dynamically imported module'))
            .mockResolvedValueOnce({ default: 'Ecra' });

        await expect(carregarComRecuperacao('registo/assistente', carregar, { ...semEsperas, recarregar })).resolves.toEqual({ default: 'Ecra' });
        expect(carregar).toHaveBeenCalledTimes(2);
        expect(recarregar).not.toHaveBeenCalled();
    });

    it('a falha persiste: recarrega a página UMA vez e rejeita', async () => {
        const recarregar = vi.fn();
        const carregar = vi.fn().mockRejectedValue(new TypeError('Failed to fetch dynamically imported module'));

        await expect(carregarComRecuperacao('registo/assistente', carregar, { ...semEsperas, recarregar })).rejects.toThrow();
        expect(carregar).toHaveBeenCalledTimes(3);
        expect(recarregar).toHaveBeenCalledTimes(1);
    });

    it('nunca recarrega em ciclo: dentro de um minuto não volta a recarregar', async () => {
        const recarregar = vi.fn();
        const carregar = vi.fn().mockRejectedValue(new TypeError('falhou'));

        await expect(carregarComRecuperacao('registo/assistente', carregar, { ...semEsperas, recarregar })).rejects.toThrow();
        // A página recarregada volta a falhar logo a seguir:
        await expect(carregarComRecuperacao('registo/assistente', carregar, { ...semEsperas, recarregar })).rejects.toThrow();

        expect(recarregar).toHaveBeenCalledTimes(1);
    });

    it('sem sessionStorage (janela privada) não recarrega sozinho — mostra o botão', async () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('bloqueado'); });
        const recarregar = vi.fn();

        await expect(carregarComRecuperacao('x', vi.fn().mockRejectedValue(new Error('falhou')), { ...semEsperas, recarregar })).rejects.toThrow();
        expect(recarregar).not.toHaveBeenCalled();
    });
});

describe('FalhaAoCarregar — o que se mostra', () => {
    it('diz o que se passou e deixa tentar de novo, sem falar em perder o que se escreveu', async () => {
        const aoTentar = vi.fn();
        render(<FalhaAoCarregar aoTentar={aoTentar} />);

        expect(screen.getByRole('alert')).toHaveTextContent('Esta página não carregou');
        expect(screen.getByRole('alert')).toHaveTextContent('palavra-passe');

        await userEvent.click(screen.getByRole('button', { name: /Tentar de novo/ }));
        expect(aoTentar).toHaveBeenCalledOnce();
    });
});
