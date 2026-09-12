/**
 * A PONTE DOS ECRÃS DO SALÃO.
 *
 * Fala com `/api/v1/invoicing/react/salao/*` — a morada dos ecrãs com sessão,
 * a mesma de todos os outros módulos.
 */

import { api } from './cliente';
import type { GrupoDeIcones } from './catalogos';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

/* ─── O painel ────────────────────────────────────────────────────────── */

export type Serie = { etiquetas: string[]; valores: number[] };

export type MarcacaoNaAgenda = {
    id: number;
    numero: string;
    cliente: string;
    telefone: string | null;
    profissional: string | null;
    inicio: string | null;
    fim: string | null;
    duracao: number;
    estado: string;
    estado_rotulo: string;
    total: number;
    servicos: string[];
};

export type PainelDoSalao = {
    dia: string;
    resumo: {
        marcacoes: number;
        confirmadas: number;
        em_curso: number;
        concluidas: number;
        faltas: number;
        receita_do_dia: number;
        receita_do_mes: number;
        duracao_media: number;
        espera_media: number;
    };
    agenda: Array<{
        id: number; nome: string; especialidade: string | null;
        marcacoes: MarcacaoNaAgenda[];
    }>;
    a_seguir: MarcacaoNaAgenda[];
    por_dia: Serie;
    por_estado: Serie & { chaves: string[] };
    por_profissional: Serie;
    servicos: Serie;
};

/* ─── As marcações ────────────────────────────────────────────────────── */

export type Marcacao = {
    id: number;
    numero: string;
    client_id: number | null;
    cliente: string;
    telefone: string | null;
    professional_id: number | null;
    profissional: string | null;
    dia: string | null;
    inicio: string | null;
    fim: string | null;
    duracao: number;
    estado: string;
    estado_rotulo: string;
    origem: string;
    origem_rotulo: string;
    total: number;
    observacoes: string | null;
    servicos: Array<{ id: number; nome: string; duracao: number; preco: number }>;
    pode: Escolha[];
    /* Só na ficha completa. */
    chegou_em?: string | null;
    comecou_em?: string | null;
    acabou_em?: string | null;
    duracao_real?: number | null;
    espera?: number | null;
    motivo_do_cancelamento?: string | null;
};

export type OpcoesDasMarcacoes = {
    profissionais: Escolha[];
    servicos: Array<Escolha & { duracao: number; preco: number }>;
    estados: Escolha[];
    origens: Escolha[];
    permissoes: {
        pode_criar: boolean; pode_editar: boolean;
        pode_apagar: boolean; pode_criar_cliente: boolean;
    };
};

export type FiltrosDasMarcacoes = {
    procura?: string; estado?: string; profissional?: number | '';
    origem?: string; quando?: string; por_pagina?: number; page?: number;
};

export type MarcacaoParaGravar = {
    client_id: string;
    professional_id: string;
    date: string;
    start_time: string;
    service_ids: number[];
    notes: string;
    source: string;
};

/* ─── Os serviços ─────────────────────────────────────────────────────── */

export type CategoriaDeServicos = {
    id: number; nome: string; descricao: string | null; icone: string;
    cor: string; ordem: number; activa: boolean; servicos: number;
};

export type ServicoDoSalao = {
    id: number; nome: string; codigo: string | null; descricao: string | null;
    category_id: number | null; categoria: string | null;
    duracao: number; duracao_rotulo: string; preco: number; custo: number;
    comissao: number; activo: boolean; marcacao_online: boolean; margem: number | null;
};

/* ─── Os profissionais ────────────────────────────────────────────────── */

export type Profissional = {
    id: number; nome: string; alcunha: string | null; email: string | null;
    telefone: string | null; documento: string | null; morada: string | null;
    especialidade: string | null; nivel: string | null; nivel_rotulo: string | null;
    biografia: string | null; nascimento: string | null; admissao: string | null;
    dias: number[]; entrada: string | null; saida: string | null;
    almoco_de: string | null; almoco_ate: string | null;
    comissao: number; preco_hora: number; preco_dia: number;
    marcacao_online: boolean; activo: boolean; disponivel: boolean;
    servicos: Array<{ id: number; nome: string }>;
    service_ids: number[];
};

/* ─── As clientes ─────────────────────────────────────────────────────── */

export type ClienteDoSalao = {
    id: number; nome: string; email: string | null; telefone: string | null;
    whatsapp: string | null; nascimento: string | null; genero: string | null;
    morada: string | null; pais: string | null; provincia: string | null;
    cidade: string | null; codigo_postal: string | null;
    vip: boolean; alergias: string[]; visitas: number; gasto: number;
    pontos: number; ultima_visita: string | null; marcacoes: number;
};

/* ─── Os tempos ───────────────────────────────────────────────────────── */

export type RelatorioDeTempos = {
    de: string;
    ate: string;
    resumo: {
        atendimentos: number; tempo_total: number; tempo_medio: number; espera_media: number;
        a_horas: number; atrasados: number; mais_rapidos: number; eficiencia: number;
    };
    data: Array<{
        id: number; numero: string; dia: string | null; inicio: string | null;
        cliente: string; profissional: string | null;
        previsto: number; real: number | null; diferenca: number | null;
        espera: number | null; total: number; servicos: string[];
    }>;
    por_profissional: Array<{
        nome: string; atendimentos: number; tempo_medio: number; tempo_total: number;
        previsto_medio: number; receita: number; eficiencia: number;
    }>;
    por_servico: Array<{ nome: string; total: number; receita: number; previsto_medio: number }>;
    opcoes: { profissionais: Escolha[]; servicos: Escolha[] };
};

/* ─── As definições ───────────────────────────────────────────────────── */

export type RegrasDoSalao = {
    opening_time: string;
    closing_time: string;
    working_days: number[];
    slot_interval: number;
    min_advance_booking_hours: number;
    max_advance_booking_days: number;
    cancellation_hours: number;
    reminder_hours: number;
    online_booking_enabled: boolean;
    require_confirmation: boolean;
    no_show_fee_percent: number;
    require_deposit: boolean;
    deposit_percent: number;
    allow_online_payment: boolean;
};

export type PaginaDoSalao = {
    salon_name: string; salon_description: string; salon_address: string;
    salon_phone: string; salon_whatsapp: string; salon_email: string;
    salon_instagram: string; salon_facebook: string; salon_tiktok: string;
    salon_website: string; salon_google_maps_url: string;
    primary_color: string; secondary_color: string;
    meta_title: string; meta_description: string;
    welcome_message: string; confirmation_message: string;
    booking_terms: string; cancellation_policy: string;
    featured_services: number[];
};

export type DefinicoesDoSalao = {
    regras: RegrasDoSalao;
    pagina: PaginaDoSalao;
    logo: string | null;
    capa: string | null;
    endereco: string | null;
    url_de_marcacao: string | null;
    dias: Array<{ valor: number; rotulo: string }>;
    servicos: Array<Escolha & { preco: number; duracao: number }>;
    permissoes: { pode_editar: boolean };
};

type Meta = {
    current_page: number; last_page: number; per_page: number;
    total: number; from: number | null; to: number | null;
};

const R = '/salao';

export const salao = {
    painel: (dia?: string) => api.ler<PainelDoSalao>(`${R}/painel`, { dia }),

    marcacoes: {
        opcoes: () => api.ler<OpcoesDasMarcacoes>(`${R}/marcacoes/opcoes`),
        lista: (f: FiltrosDasMarcacoes) => api.ler<{
            data: Marcacao[];
            meta: Meta;
            resumo: {
                hoje: number; por_atender: number; concluidas_no_mes: number;
                receita_do_mes: number; online: number; no_sistema: number;
            };
        }>(`${R}/marcacoes`, f as Record<string, string | number>),
        calendario: (f: { dia?: string; vista?: string; profissional?: number | '' }) =>
            api.ler<{
                vista: string; dia: string; de: string; ate: string;
                dias: Record<string, Marcacao[]>;
            }>(`${R}/marcacoes/calendario`, f),
        ficha: (id: number) => api.ler<{ data: Marcacao }>(`${R}/marcacoes/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Marcacao }>(`${R}/marcacoes/${id}`, dados)
               : api.criar<Recado & { data: Marcacao }>(`${R}/marcacoes`, dados),
        estado: (id: number, estado: string, motivo?: string) =>
            api.criar<Recado & { data: Marcacao }>(`${R}/marcacoes/${id}/estado`, { estado, motivo }),
        clientes: (procura: string) => api.ler<{ data: Escolha[] }>(`${R}/marcacoes/clientes`, { procura }),
        clienteRapido: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Escolha }>(`${R}/marcacoes/clientes`, dados),
    },

    servicos: {
        opcoes: () => api.ler<{
            categorias: CategoriaDeServicos[];
            galeria_de_icones: GrupoDeIcones[];
            permissoes: {
                pode_criar: boolean; pode_editar: boolean;
                pode_apagar: boolean; pode_gerir_categorias: boolean;
            };
        }>(`${R}/servicos/opcoes`),
        lista: (f: { procura?: string; categoria?: number | ''; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: ServicoDoSalao[]; meta: Meta; categorias: CategoriaDeServicos[];
                resumo: { total: number; activos: number; categorias: number };
            }>(`${R}/servicos`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado>(`${R}/servicos/${id}`, dados) : api.criar<Recado>(`${R}/servicos`, dados),
        alternar: (id: number) => api.criar<Recado & { activo: boolean }>(`${R}/servicos/${id}/alternar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/servicos/${id}`),
        guardarCategoria: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { categorias: CategoriaDeServicos[] }>(`${R}/servicos/categorias/${id}`, dados)
               : api.criar<Recado & { categorias: CategoriaDeServicos[] }>(`${R}/servicos/categorias`, dados),
        moverCategoria: (id: number, direccao: 'cima' | 'baixo') =>
            api.criar<{ categorias: CategoriaDeServicos[] }>(`${R}/servicos/categorias/${id}/mover`, { direccao }),
        apagarCategoria: (id: number) =>
            api.apagar<Recado & { categorias: CategoriaDeServicos[] }>(`${R}/servicos/categorias/${id}`),
    },

    profissionais: {
        opcoes: () => api.ler<{
            dias: Array<{ valor: number; rotulo: string }>;
            niveis: Escolha[];
            servicos: Escolha[];
            permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
        }>(`${R}/profissionais/opcoes`),
        lista: (f: { procura?: string; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: Profissional[]; meta: Meta;
                resumo: { total: number; activos: number; na_marcacao_online: number };
            }>(`${R}/profissionais`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Profissional }>(`${R}/profissionais/${id}`, dados)
               : api.criar<Recado & { data: Profissional }>(`${R}/profissionais`, dados),
        alternar: (id: number) => api.criar<Recado & { activo: boolean }>(`${R}/profissionais/${id}/alternar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/profissionais/${id}`),
        doRh: () => api.ler<{
            data: Array<{ id: number; nome: string; email: string | null; telefone: string | null; cargo: string | null }>;
        }>(`${R}/profissionais/do-rh`),
        importarDoRh: (employee_ids: number[]) =>
            api.criar<Recado>(`${R}/profissionais/do-rh`, { employee_ids }),
    },

    clientes: {
        opcoes: () => api.ler<{
            paises: Escolha[]; provincias: Escolha[]; generos: Escolha[];
            permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
        }>(`${R}/clientes/opcoes`),
        lista: (f: { procura?: string; vip?: string; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: ClienteDoSalao[]; meta: Meta;
                resumo: { total: number; vip: number; normais: number };
            }>(`${R}/clientes`, f),
        ficha: (id: number) => api.ler<{
            data: ClienteDoSalao;
            marcacoes: Array<{
                id: number; numero: string; dia: string | null; inicio: string | null;
                profissional: string | null; estado: string; estado_rotulo: string;
                total: number; servicos: string[];
            }>;
        }>(`${R}/clientes/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: ClienteDoSalao }>(`${R}/clientes/${id}`, dados)
               : api.criar<Recado & { data: ClienteDoSalao }>(`${R}/clientes`, dados),
        vip: (id: number) => api.criar<Recado & { vip: boolean }>(`${R}/clientes/${id}/vip`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/clientes/${id}`),
    },

    tempos: (f: { de?: string; ate?: string; profissional?: number | ''; servico?: number | '' }) =>
        api.ler<RelatorioDeTempos>(`${R}/tempos`, f),

    definicoes: {
        ler: () => api.ler<DefinicoesDoSalao>(`${R}/definicoes`),
        guardar: (dados: RegrasDoSalao) => api.guardar<Recado>(`${R}/definicoes`, dados),
        guardarPagina: (dados: PaginaDoSalao) =>
            api.guardar<Recado & { endereco: string; url_de_marcacao: string }>(`${R}/definicoes/pagina`, dados),
        imagem: (qual: 'logo' | 'capa', ficheiro: File) => {
            const corpo = new FormData();
            corpo.append('qual', qual);
            corpo.append('ficheiro', ficheiro);

            return api.enviar<Recado & { url: string }>(`${R}/definicoes/imagem`, corpo);
        },
        removerImagem: (qual: 'logo' | 'capa') => api.apagar<Recado>(`${R}/definicoes/imagem`, { qual }),
        novoEndereco: () =>
            api.criar<Recado & { endereco: string; url_de_marcacao: string }>(`${R}/definicoes/endereco`, {}),
    },
};
