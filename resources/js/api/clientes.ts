import { api, type Pagina } from './cliente';

/** O que um cliente é, do lado de cá. Sem palavra-passe: ver ClientResource. */
export type Cliente = {
    id: number;
    type: 'pessoa_juridica' | 'pessoa_fisica';
    tipo_rotulo: string;
    name: string;
    nif: string;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    /** A morada do logótipo, já pronta a mostrar. */
    logo: string | null;
    /** O que está gravado na coluna — a chave que não muda com o domínio. */
    logo_caminho: string | null;
    /** A condição de pagamento: é dela que sai o vencimento das facturas. */
    payment_term_id: number | null;
    /** O nome da condição, já feito — para a lista não cruzar duas listas. */
    condicao_pagamento: string | null;
    address: string | null;
    city: string | null;
    province: string | null;
    municipality: string | null;
    neighbourhood: string | null;
    postal_code: string | null;
    country: string;
    pais_nome: string | null;
    /** Se tem porta aberta para o portal. O FACTO, nunca a senha. */
    portal_access: boolean;
    documentos: number;
    pode_apagar: boolean;
};

/**
 * O que se envia ao gravar. O `id` não vai no corpo — vai no endereço.
 *
 * `portal_password` e `portal_repor_senha` NÃO são colunas: são uma ordem
 * para o serviço do portal. Viajam só quando são pedidas — ver `paraGravar`.
 */
export type ClienteParaGravar = Omit<
    Cliente,
    | 'id'
    | 'tipo_rotulo'
    | 'pais_nome'
    | 'documentos'
    | 'pode_apagar'
    // O LOGÓTIPO NÃO VIAJA NO CORPO: é um ficheiro, vai em multipart pela
    // rota própria, depois de a ficha estar gravada (antes disso não há id
    // nem pasta onde o pôr). O nome da condição também não — grava-se o id.
    | 'logo'
    | 'logo_caminho'
    | 'condicao_pagamento'
> & {
    portal_password?: string;
    portal_repor_senha?: boolean;
    /** Desligado, o acesso é dado sem email de boas-vindas. */
    portal_avisar?: boolean;
};

export type FiltrosDeClientes = {
    procura?: string;
    tipo?: string;
    provincia?: string;
    por_pagina?: number;
    page?: number;
};

export type OpcoesDosClientes = {
    provincias: string[];
    /** As que nasceram na reforma de 2024 — o ecrã assinala-as. */
    provincias_novas: string[];
    /** província => municípios. A cascata vem resolvida do servidor. */
    municipios: Record<string, string[]>;
    /** município => bairros SUGERIDOS. Sugere, nunca fecha. */
    bairros: Record<string, string[]>;
    paises: Record<string, string>;
    pais_padrao: string;
    /** Onde o cliente entra, para o formulário o poder dizer. */
    portal_url: string;
    tipos: Array<{ valor: string; rotulo: string }>;
    /** O catálogo de condições desta empresa — só as activas. */
    condicoes_pagamento: Array<{ id: number; nome: string; dias: number; padrao: boolean }>;
    /** Se este utilizador pode abrir as definições para as gerir. */
    pode_gerir_condicoes: boolean;
    url_condicoes: string;
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

/**
 * O QUE VAI MESMO PARA O SERVIDOR.
 *
 * GUARDAR A FICHA NÃO PODE TROCAR A SENHA DE QUEM JÁ TEM ACESSO: só mudar o
 * telefone deixaria o cliente de fora do portal sem ninguém perceber porquê.
 * O servidor já se defende disso, mas a senha em branco nem sai daqui — e
 * fica num sítio só, para nenhum caminho do ecrã a poder mandar por engano.
 */
function paraGravar(dados: ClienteParaGravar): ClienteParaGravar {
    const corpo = { ...dados };

    if (!corpo.portal_password?.trim()) {
        delete corpo.portal_password;
    }

    if (!corpo.portal_repor_senha) {
        delete corpo.portal_repor_senha;
    }

    // Avisar é a omissão do servidor: só viaja quando se DESLIGA.
    if (corpo.portal_avisar !== false) {
        delete corpo.portal_avisar;
    }

    return corpo;
}

export const clientes = {
    lista: (filtros: FiltrosDeClientes) => api.ler<Pagina<Cliente>>('/clients', filtros),
    opcoes: () => api.ler<OpcoesDosClientes>('/clients/opcoes'),
    criar: (dados: ClienteParaGravar) => api.criar<{ data: Cliente }>('/clients', paraGravar(dados)),
    guardar: (id: number, dados: ClienteParaGravar) =>
        api.guardar<{ data: Cliente }>(`/clients/${id}`, paraGravar(dados)),
    apagar: (id: number) => api.apagar<{ message: string }>(`/clients/${id}`),

    /**
     * O LOGÓTIPO VAI À PARTE, em multipart — um ficheiro não cabe em JSON.
     *
     * A nova substitui a que lá estava: é um logótipo, não um histórico deles.
     */
    logotipo: (id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('logotipo', ficheiro);

        return api.enviar<{ data: Cliente; message: string }>(`/clients/${id}/logotipo`, corpo);
    },

    apagarLogotipo: (id: number) =>
        api.apagar<{ data: Cliente; message: string }>(`/clients/${id}/logotipo`),
};
