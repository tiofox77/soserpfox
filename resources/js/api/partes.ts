import { api } from './cliente';

/**
 * A OUTRA PARTE DE UM DOCUMENTO — o cliente, ou o fornecedor — criada sem
 * largar o documento a meio.
 *
 * É o «cliente rápido» dos ecrãs em Blade, e a razão de existir é a mesma:
 * está-se a emitir, o cliente não existe no sistema, e ir criá-lo ao ecrã dos
 * clientes era perder as linhas já escritas.
 *
 * O QUE AQUI **NÃO** HÁ É UM SEGUNDO CAMINHO DE CRIAÇÃO. Isto chama as portas
 * que já existem — `/clients` e `/catalogos/fornecedores` —, com as mesmas
 * validações (o NIF angolano com verificador e único por empresa, o país em
 * código ISO) e as mesmas permissões. Uma criação «rápida» que aceitasse o
 * que o formulário completo recusa não seria rápida: seria uma porta das
 * traseiras para meter na base o que a casa recusa à frente.
 */

/** Uma parte como as listas do emissor a mostram. */
export type Parte = { id: number; name: string; nif: string | null };

/**
 * O que as opções do emissor dizem sobre criar a parte aqui mesmo.
 *
 * `pode` é a permissão da FICHA (criar clientes, criar fornecedores) — que é
 * outra coisa que não a de emitir o documento. O país por omissão vem do
 * servidor (`Geografia::PAIS_PADRAO`) e não de uma constante escrita aqui.
 */
export type CriarParte = {
    tipo: 'cliente' | 'fornecedor';
    pode: boolean;
    pais_padrao: string;
};

/** Os cinco campos do formulário rápido, os mesmos de sempre. */
export type ParteRapida = {
    name: string;
    nif: string;
    email: string;
    phone: string;
    address: string;
};

export const PARTE_VAZIA: ParteRapida = { name: '', nif: '', email: '', phone: '', address: '' };

export const partes = {
    /** Cria pela porta de sempre e devolve a parte já pronta a escolher. */
    criar: async (onde: CriarParte, dados: ParteRapida): Promise<Parte> => {
        const corpo = {
            // Empresa por omissão, como o formulário rápido sempre assumiu.
            // Quem precisa de uma pessoa singular usa o ecrã completo, que
            // tem o tipo e a morada inteira.
            type: 'pessoa_juridica',
            name: dados.name,
            nif: dados.nif,
            email: dados.email || null,
            phone: dados.phone || null,
            address: dados.address || null,
            country: onde.pais_padrao,
        };

        const resposta = await api.criar<{ data: Parte }>(
            onde.tipo === 'fornecedor' ? '/catalogos/fornecedores' : '/clients',
            corpo,
        );

        return { id: resposta.data.id, name: resposta.data.name, nif: resposta.data.nif ?? null };
    },
};
