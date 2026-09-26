import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import Assistente from './Assistente';

const plano = {
    id: 8, slug: 'pacote-hotel', nome: 'Pacote Hotel', descricao: null, preco: 19900, gratuito: false, destaque: false,
    utilizadores: 10, empresas: 1, dias_de_teste: 30, recusa: null, com_teste: true, teste_sem_pagamento: true,
};

function estado(passo: number) {
    return {
        passo, autenticado: false,
        campos: {
            name: 'Ana', email: 'ana@exemplo.ao', company_name: '', company_nif: '', company_regime: '', company_address: '',
            company_phone: '', company_email: '', selected_plan_id: 8, payment_method: '', payment_reference: '', reseller_code: '',
        },
        plano_veio_do_link: true, passo_antes_da_senha: null, sem_teste: null, planos: [plano], guardado_em: null, aviso: null,
        revendedor: null, modulo: { slug: 'hotel', nome: 'Hotel' },
    };
}

function montar(passo: number) {
    return render(
        <QueryClientProvider client={new QueryClient()}>
            <Assistente estado={estado(passo)} regimes={[]} conta={{ banco: 'BAI', titular: 'SOS', iban: 'AO06 0000' }}
                site="/" entrar="/login" logo="/storage/settings/logo.png" nome="SOS ERP" />
        </QueryClientProvider>,
    );
}

describe('Assistente do registo — no telemóvel e fiel às condições', () => {
    it('mostra o logótipo da marca e, em todos os passos, o módulo e os dias VERDADEIROS do teste', () => {
        montar(1);

        expect(screen.getByRole('img', { name: 'SOS ERP' })).toHaveAttribute('src', '/storage/settings/logo.png');
        expect(screen.getByText(/Está a registar-se para: Hotel/)).toBeInTheDocument();
        // O Hotel dá 30 dias — não os 14 genéricos.
        expect(screen.getByText(/30 dias grátis, sem pagamento para começar/)).toBeInTheDocument();
    });

    it('a senha tem «mostrar» e não pede confirmação; os campos ajudam o preenchimento automático', async () => {
        montar(1);

        const senha = screen.getByPlaceholderText('Mínimo 8 caracteres, com letras e números.');
        expect(senha).toHaveAttribute('type', 'password');
        expect(senha).toHaveAttribute('autocomplete', 'new-password');
        expect(screen.queryByPlaceholderText('Digite a senha novamente')).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Mostrar a senha' }));
        expect(senha).toHaveAttribute('type', 'text');

        expect(screen.getByPlaceholderText('João Silva')).toHaveAttribute('autocomplete', 'name');
        expect(screen.getByPlaceholderText('joao@empresa.vip')).toHaveAttribute('autocomplete', 'email');
    });

    it('com teste sem pagamento, o último passo é uma Confirmação e o comprovativo é opcional e recolhido', async () => {
        montar(4);

        expect(screen.getByRole('heading', { name: 'Confirmação' })).toBeInTheDocument();
        expect(screen.getByText(/O teste grátis de 30 dias começa assim que concluir/)).toBeInTheDocument();
        expect(screen.queryByText('Transferência Bancária')).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: /Já fez a transferência\?/ }));
        expect(screen.getByText('Transferência Bancária')).toBeInTheDocument();
    });

    it('o texto do «Próximo» fica num <span> (o tradutor do browser não parte o React)', () => {
        montar(1);

        const proximo = screen.getByRole('button', { name: /Próximo/ });
        expect(proximo.querySelector('span')?.textContent).toBe('Próximo');
    });
});
