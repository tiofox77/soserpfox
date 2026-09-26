import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, describe, expect, it } from 'vitest';

import { PrimeiroPassoEAjuda } from './PrimeiroPassoEAjuda';

const passo = {
    modulo: 'hotel', titulo: 'Configure o primeiro quarto', texto: 'Comece pelo tipo de quarto.',
    botao: 'Criar o primeiro tipo de quarto', icone: 'fa-bed', url: '/hotel/room-types',
};
const ajuda = {
    texto_whatsapp: 'Aceito que a equipa do SOSERP me contacte por WhatsApp.', versao_whatsapp: '2026-09-26',
    telefone: null, suporte: '/support/tickets',
};

function montar(p: typeof passo | null = passo) {
    return render(
        <QueryClientProvider client={new QueryClient()}>
            <PrimeiroPassoEAjuda passo={p} ajuda={ajuda} />
        </QueryClientProvider>,
    );
}

beforeAll(() => {
    // O jsdom não abre um `<dialog>`: faz-se o que o browser faz, marcá-lo aberto.
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
    HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); };
});

describe('O próximo passo e a ajuda para começar', () => {
    it('mostra UM passo concreto que leva ao ecrã do módulo', () => {
        montar();

        expect(screen.getByRole('heading', { name: 'Configure o primeiro quarto' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Criar o primeiro tipo de quarto/ })).toHaveAttribute('href', '/hotel/room-types');
    });

    it('o WhatsApp começa desmarcado e o número só aparece quando a pessoa o marca', async () => {
        montar();
        await userEvent.click(screen.getByRole('button', { name: /Preciso de ajuda para começar/ }));

        const caixa = screen.getByRole('checkbox', { name: /contacte por WhatsApp/ });
        expect(caixa).not.toBeChecked();
        expect(screen.queryByLabelText(/Número com WhatsApp/)).not.toBeInTheDocument();

        await userEvent.click(caixa);
        expect(screen.getByLabelText(/Número com WhatsApp/)).toBeInTheDocument();
    });

    it('sem passo por fazer, fica só a ajuda', () => {
        montar(null);

        expect(screen.getByRole('heading', { name: 'Precisa de ajuda para começar?' })).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});
