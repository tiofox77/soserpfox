/**
 * A PONTE DAS CÓPIAS DE SEGURANÇA — a mesma para a plataforma e para cada empresa.
 *
 * Só a raiz muda: `/api/v1/plataforma/react/copias` (a base inteira, super
 * admin) ou `/api/v1/invoicing/react/copias` (os dados da empresa activa).
 */

import { criarApi } from './cliente';

export type Ambito = 'plataforma' | 'empresa';

type Recado = { message: string };

export type CampoDoFornecedor = {
    chave: string;
    rotulo: string;
    tipo: 'text' | 'password' | 'number' | 'select' | 'checkbox';
    obrigatorio: boolean;
    omissao?: string | boolean;
    ajuda?: string;
    segredo?: boolean;
    opcoes?: Array<{ valor: string; rotulo: string }>;
};

export type Fornecedor = {
    nome: string;
    icone: string;
    cor: string;
    oauth: 'google' | 'microsoft' | 'dropbox' | null;
    configurado: boolean;
    descricao: string;
    campos: CampoDoFornecedor[];
    disponivel: boolean;
};

export type Destino = {
    id: number;
    nome: string;
    tipo: string;
    fornecedor: string;
    icone: string;
    cor: string;
    oauth: string | null;
    pasta: string | null;
    manter: number;
    activo: boolean;
    ligado: boolean;
    testado_em: string | null;
    ultimo_erro: string | null;
    configuracao: Record<string, string | number | boolean | null>;
};

export type Envio = {
    destino_id: number;
    destino: string | null;
    tipo: string | null;
    estado: 'pendente' | 'enviado' | 'falhou' | 'apagado';
    erro: string | null;
    enviado_em: string | null;
};

export type Copia = {
    id: number;
    ficheiro: string | null;
    tamanho: number;
    cifrada: boolean;
    origem: 'automatica' | 'manual' | 'antes_de_restaurar' | 'carregada';
    estado: 'a_correr' | 'concluida' | 'falhou';
    erro: string | null;
    no_servidor: boolean;
    linhas: number | null;
    tabelas: number | null;
    iniciada_em: string | null;
    concluida_em: string | null;
    duracao_s: number | null;
    envios: Envio[];
};

export type Restauro = {
    id: number;
    copia_id: number | null;
    ficheiro: string | null;
    estado: 'a_correr' | 'concluido' | 'falhou' | 'recusado';
    erro: string | null;
    resumo: Record<string, unknown> | null;
    copia_previa_id: number | null;
    iniciado_em: string | null;
    concluido_em: string | null;
};

export type PainelDasCopias = {
    ambito: Ambito;
    agenda: {
        activa: boolean;
        intervalo_horas: number;
        manter_locais: number;
        cifrar: boolean;
        tem_frase: boolean;
        ultima_em: string | null;
        proxima_em: string | null;
    };
    intervalos: number[];
    a_correr: boolean;
    espaco_bytes: number;
    destinos: Destino[];
    fornecedores: Record<string, Fornecedor>;
    retorno_oauth: string;
    copias: Copia[];
    paginacao: { pagina: number; ultima: number; total: number };
    restauros: Restauro[];
    avisos: Array<{ cor: 'aviso' | 'perigo'; texto: string }>;
};

export type FicheiroRemoto = { remoto: string; nome: string; tamanho: number | null; data: string | null };

export type AplicacoesOAuth = {
    retorno: string;
    aplicacoes: Record<'google' | 'microsoft' | 'dropbox', { configurado: boolean; client_id: string | null }>;
};

const RAIZES: Record<Ambito, { api: string; caminho: string }> = {
    plataforma: { api: '/api/v1/plataforma/react', caminho: '/copias' },
    empresa: { api: '/api/v1/invoicing/react', caminho: '/copias' },
};

export function apiDasCopias(ambito: Ambito) {
    const { api: raiz, caminho: C } = RAIZES[ambito];
    const api = criarApi(raiz);

    return {
        ler: (pagina = 1) => api.ler<PainelDasCopias>(C, { pagina }),
        agenda: (dados: Record<string, unknown>) => api.guardar<Recado>(`${C}/agenda`, dados),
        fazer: () => api.criar<Recado & { copia_id: number }>(`${C}/fazer`, {}),

        destinos: {
            criar: (dados: Record<string, unknown>) => api.criar<Recado & { destino: Destino }>(`${C}/destinos`, dados),
            guardar: (id: number, dados: Record<string, unknown>) => api.guardar<Recado & { destino: Destino }>(`${C}/destinos/${id}`, dados),
            apagar: (id: number) => api.apagar<Recado>(`${C}/destinos/${id}`),
            testar: (id: number) => api.criar<Recado>(`${C}/destinos/${id}/testar`, {}),
            ligar: (id: number) => api.ler<{ url: string }>(`${C}/destinos/${id}/ligar`),
            ficheiros: (id: number) => api.ler<{ ficheiros: FicheiroRemoto[] }>(`${C}/destinos/${id}/ficheiros`),
        },

        /** Descarrega-se por ligação normal (a resposta é o próprio ficheiro). */
        descarregar: (id: number) => `${raiz}${C}/${id}/descarregar`,
        reenviar: (id: number, destino_id?: number) => api.criar<Recado>(`${C}/${id}/enviar`, destino_id ? { destino_id } : {}),
        apagar: (id: number) => api.apagar<Recado>(`${C}/${id}`),

        restaurar: (dados: Record<string, unknown>) => api.criar<Recado & { restauro_id: number }>(`${C}/restaurar`, dados),
        restaurarCarregado: (ficheiro: File, dados: { frase: string; senha: string; confirmacao: string }) => {
            const corpo = new FormData();
            corpo.append('copia', ficheiro);
            corpo.append('frase', dados.frase);
            corpo.append('senha', dados.senha);
            corpo.append('confirmacao', dados.confirmacao);

            return api.enviar<Recado & { restauro_id: number }>(`${C}/restaurar/carregar`, corpo);
        },
        restauro: (id: number) => api.ler<{ restauro: Restauro }>(`${C}/restauros/${id}`),

        aplicacoes: () => api.ler<AplicacoesOAuth>(`${C}/aplicacoes`),
        guardarAplicacao: (fornecedor: string, dados: { client_id: string; client_secret: string }) =>
            api.guardar<Recado>(`${C}/aplicacoes/${fornecedor}`, dados),
    };
}
