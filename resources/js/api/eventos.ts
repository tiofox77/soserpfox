/**
 * A PONTE DOS ECRÃS DOS EVENTOS.
 *
 * Fala com `/api/v1/invoicing/react/eventos/*` — a morada dos ecrãs com sessão,
 * a mesma de todos os outros módulos.
 */

import { api } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

export type Serie = { etiquetas: string[]; valores: number[] };

type Meta = {
    current_page: number; last_page: number; per_page: number;
    total: number; from: number | null; to: number | null;
};

/* ─── O evento ────────────────────────────────────────────────────────── */

export type Evento = {
    id: number;
    numero: string;
    nome: string;
    client_id: number | null;
    cliente: string | null;
    venue_id: number | null;
    local: string | null;
    type_id: number | null;
    tipo: string | null;
    tipo_icone: string | null;
    tipo_cor: string | null;
    inicio: string | null;
    fim: string | null;
    estado: string;
    estado_rotulo: string;
    estado_icone: string;
    fase: string;
    fase_rotulo: string;
    progresso: number;
    pessoas: number;
    valor: number;
    cor: string | null;
};

export type FichaDoEvento = Evento & {
    descricao: string | null;
    notas: string | null;
    responsavel: string | null;
    cliente_telefone: string | null;
    cliente_email: string | null;
    local_morada: string | null;
    local_cidade: string | null;
    local_capacidade: number;
    montagem_em: string | null;
    desmontagem_em: string | null;
    confirmado_em: string | null;
    concluido_em: string | null;
    pode: Escolha[];
    pode_avancar: boolean;
};

export type Tarefa = {
    id: number;
    tarefa: string;
    descricao: string | null;
    fase: string;
    fase_rotulo: string;
    obrigatoria: boolean;
    feita: boolean;
    feita_em: string | null;
};

export type OpcoesDaAgenda = {
    clientes: Escolha[];
    locais: Array<Escolha & { capacidade: number }>;
    tipos: Array<Escolha & { icone: string; cor: string }>;
    estados: Array<Escolha & { icone: string }>;
    fases: Escolha[];
    emojis: Escolha[];
    permissoes: {
        pode_gerir: boolean;
        pode_criar_cliente: boolean;
        pode_criar_local: boolean;
        pode_criar_tipo: boolean;
    };
};

export type FiltrosDaAgenda = {
    procura?: string; estado?: string; fase?: string;
    tipo?: number | ''; cliente?: number | '';
    de?: string; ate?: string; por_pagina?: number; page?: number;
};

export type DiaDoCalendario = {
    dia: string;
    numero: number;
    do_mes: boolean;
    hoje: boolean;
    eventos: Evento[];
};

/* ─── O painel ────────────────────────────────────────────────────────── */

export type PainelDosEventos = {
    resumo: {
        do_mes: number; confirmados: number; a_decorrer: number;
        valor_do_mes: number; equipamento_em_uso: number;
        equipamento_total: number; tecnicos: number; locais: number;
    };
    a_seguir: Array<Evento & { local: string | null }>;
    em_atraso: Array<{
        id: number; nome: string; com_quem: string | null;
        devolver_em: string | null; dias: number;
    }>;
    por_mes: Serie;
    por_estado: Serie & { chaves: string[] };
    por_fase: Serie & { chaves: string[] };
    por_tipo: Serie;
};

/* ─── Os equipamentos ─────────────────────────────────────────────────── */

export type CategoriaDeEquipamentos = {
    id: number; nome: string; icone: string; cor: string;
    ordem: number; activa: boolean; equipamentos: number;
};

export type Equipamento = {
    id: number;
    nome: string;
    category_id: number | null;
    categoria: string | null;
    categoria_icone: string | null;
    categoria_cor: string | null;
    numero_de_serie: string | null;
    local: string | null;
    descricao: string | null;
    estado: string;
    estado_rotulo: string;
    estado_icone: string;
    aquisicao: string | null;
    preco_de_compra: number;
    valor_actual: number;
    emprestado_a: string | null;
    borrowed_to_client_id: number | null;
    borrowed_to_technician_id: number | null;
    emprestado_em: string | null;
    devolver_em: string | null;
    devolvido_em: string | null;
    preco_por_dia: number;
    ultima_manutencao: string | null;
    proxima_manutencao: string | null;
    notas_de_manutencao: string | null;
    utilizacoes: number;
    horas: number;
    imagem: string | null;
    activo: boolean;
    atrasado: boolean;
    dias_de_atraso: number;
};

export type Aviso = {
    tipo: 'perigo' | 'aviso';
    icone: string;
    equipamento_id: number;
    mensagem: string;
};

export type ResumoDosEquipamentos = {
    total: number; disponivel: number; em_uso: number; emprestado: number;
    manutencao: number; avariado: number; valor: number; taxa_de_uso: number;
};

export type Conjunto = {
    id: number;
    nome: string;
    descricao: string | null;
    category_id: number | null;
    categoria: string | null;
    categoria_icone: string | null;
    categoria_cor: string | null;
    activo: boolean;
    itens: Array<{
        id: number; nome: string; estado: string;
        estado_rotulo: string; quantidade: number;
    }>;
};

export type OpcoesDosEquipamentos = {
    categorias: CategoriaDeEquipamentos[];
    estados: Array<Escolha & { icone: string }>;
    clientes: Escolha[];
    tecnicos: Escolha[];
    locais: string[];
    emojis: Escolha[];
    permissoes: { pode_gerir: boolean };
};

export type PainelDosEquipamentos = {
    dias: number;
    resumo: ResumoDosEquipamentos;
    por_categoria: Serie;
    movimentos: Serie;
    mais_usados: Array<{
        id: number; nome: string; utilizacoes: number;
        estado: string; estado_rotulo: string; valor: number;
    }>;
    manutencoes: Array<{ id: number; nome: string; quando: string | null; atrasada: boolean }>;
    avisos: Aviso[];
    actividade: Array<{
        id: number; equipamento: string | null; accao: string;
        quem: string | null; cliente: string | null;
        quando: string | null; notas: string | null;
    }>;
};

/* ─── Os locais, os tipos e os técnicos ───────────────────────────────── */

export type Local = {
    id: number; nome: string; morada: string | null; cidade: string | null;
    telefone: string | null; contacto: string | null; capacidade: number;
    notas: string | null; activo: boolean; eventos: number;
};

export type TipoDeEvento = {
    id: number; nome: string; icone: string; cor: string;
    descricao: string | null; ordem: number; activo: boolean; eventos: number;
};

export type Tecnico = {
    id: number;
    nome: string;
    email: string | null;
    telefone: string | null;
    documento: string | null;
    morada: string | null;
    especialidades: string[];
    especialidades_rotulos: string[];
    nivel: string;
    nivel_rotulo: string;
    preco_hora: number;
    preco_dia: number;
    nascimento: string | null;
    admissao: string | null;
    notas: string | null;
    activo: boolean;
    disponivel: boolean;
    do_rh: boolean;
};

/* ─── Os relatórios ───────────────────────────────────────────────────── */

export type RelatorioDeEventos = {
    de: string;
    ate: string;
    resumo: {
        eventos: number; valor: number; pessoas: number;
        valor_medio: number; concluidos: number; cancelados: number;
    };
    por_mes: Serie & { receita: number[] };
    por_estado: Serie & { chaves: string[] };
    por_cliente: Serie & { receita: number[] };
    por_tipo: Serie;
    data: Array<{
        id: number; numero: string; nome: string;
        cliente: string | null; local: string | null;
        tipo: string | null; tipo_icone: string | null;
        inicio: string | null; fim: string | null;
        estado: string; estado_rotulo: string;
        pessoas: number; valor: number;
    }>;
    opcoes: { clientes: Escolha[]; tipos: Escolha[]; estados: Escolha[] };
};

const R = '/eventos';

export const eventos = {
    painel: () => api.ler<PainelDosEventos>(`${R}/painel`),

    agenda: {
        opcoes: () => api.ler<OpcoesDaAgenda>(`${R}/agenda/opcoes`),
        lista: (f: FiltrosDaAgenda) => api.ler<{
            data: Evento[];
            meta: Meta;
            resumo: {
                total: number; orcamento: number; confirmados: number;
                a_decorrer: number; concluidos_no_mes: number;
            };
        }>(`${R}/agenda`, f as Record<string, string | number>),
        calendario: (f: { mes?: string; estado?: string; fase?: string; tipo?: number | '' }) =>
            api.ler<{ mes: string; rotulo: string; dias: DiaDoCalendario[] }>(`${R}/agenda/calendario`, f),
        ficha: (id: number) => api.ler<{ data: FichaDoEvento; tarefas: Tarefa[] }>(`${R}/agenda/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Evento }>(`${R}/agenda/${id}`, dados)
               : api.criar<Recado & { data: Evento }>(`${R}/agenda`, dados),
        mover: (id: number, start_date: string, end_date: string) =>
            api.criar<Recado & { data: Evento }>(`${R}/agenda/${id}/mover`, { start_date, end_date }),
        estado: (id: number, estado: string) =>
            api.criar<Recado & { data: Evento }>(`${R}/agenda/${id}/estado`, { estado }),
        avancarFase: (id: number) => api.criar<Recado & { data: Evento }>(`${R}/agenda/${id}/fase`, {}),
        tarefa: (id: number) =>
            api.criar<Recado & { feita: boolean; progresso: number }>(`${R}/agenda/tarefas/${id}`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/agenda/${id}`),
        clienteRapido: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Escolha }>(`${R}/agenda/clientes`, dados),
        localRapido: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Escolha & { capacidade: number } }>(`${R}/agenda/locais`, dados),
        tipoRapido: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Escolha & { icone: string; cor: string } }>(`${R}/agenda/tipos`, dados),
    },

    equipamentos: {
        opcoes: () => api.ler<OpcoesDosEquipamentos>(`${R}/equipamentos/opcoes`),
        lista: (f: {
            procura?: string; categoria?: number | ''; estado?: string;
            local?: string; por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Equipamento[]; meta: Meta;
            resumo: ResumoDosEquipamentos;
            categorias: CategoriaDeEquipamentos[];
            avisos: Aviso[];
        }>(`${R}/equipamentos`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Equipamento }>(`${R}/equipamentos/${id}`, dados)
               : api.criar<Recado & { data: Equipamento }>(`${R}/equipamentos`, dados),
        imagem: (id: number, ficheiro: File) => {
            const corpo = new FormData();
            corpo.append('imagem', ficheiro);

            return api.enviar<Recado & { imagem: string }>(`${R}/equipamentos/${id}/imagem`, corpo);
        },
        emprestar: (id: number, dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Equipamento }>(`${R}/equipamentos/${id}/emprestar`, dados),
        devolver: (id: number) =>
            api.criar<Recado & { data: Equipamento }>(`${R}/equipamentos/${id}/devolver`, {}),
        manutencao: (id: number, dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Equipamento }>(`${R}/equipamentos/${id}/manutencao`, dados),
        historial: (id: number) => api.ler<{
            data: Array<{
                id: number; accao: string; quando: string | null;
                de: string | null; ate: string | null;
                cliente: string | null; utilizador: string | null;
                evento: string | null; notas: string | null;
            }>;
        }>(`${R}/equipamentos/${id}/historial`),
        apagar: (id: number) => api.apagar<Recado>(`${R}/equipamentos/${id}`),
        painel: (dias?: number) => api.ler<PainelDosEquipamentos>(`${R}/equipamentos/painel`, { dias }),

        categorias: {
            guardar: (id: number | null, dados: Record<string, unknown>) =>
                id ? api.guardar<Recado>(`${R}/equipamentos/categorias/${id}`, dados)
                   : api.criar<Recado>(`${R}/equipamentos/categorias`, dados),
            alternar: (id: number) =>
                api.criar<Recado & { activa: boolean }>(`${R}/equipamentos/categorias/${id}/alternar`, {}),
            apagar: (id: number) => api.apagar<Recado>(`${R}/equipamentos/categorias/${id}`),
        },

        conjuntos: {
            lista: (f: { procura?: string; por_pagina?: number; page?: number }) =>
                api.ler<{ data: Conjunto[]; meta: Meta; equipamentos: Escolha[] }>(`${R}/equipamentos/conjuntos`, f),
            guardar: (id: number | null, dados: Record<string, unknown>) =>
                id ? api.guardar<Recado>(`${R}/equipamentos/conjuntos/${id}`, dados)
                   : api.criar<Recado>(`${R}/equipamentos/conjuntos`, dados),
            juntar: (id: number, equipment_id: number, quantity: number) =>
                api.criar<Recado>(`${R}/equipamentos/conjuntos/${id}/itens`, { equipment_id, quantity }),
            tirar: (id: number, equipamento: number) =>
                api.apagar<Recado>(`${R}/equipamentos/conjuntos/${id}/itens/${equipamento}`),
            apagar: (id: number) => api.apagar<Recado>(`${R}/equipamentos/conjuntos/${id}`),
        },
    },

    locais: {
        lista: (f: { procura?: string; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: Local[]; meta: Meta;
                resumo: { total: number; activos: number; lugares: number };
                permissoes: { pode_gerir: boolean };
            }>(`${R}/locais`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado>(`${R}/locais/${id}`, dados) : api.criar<Recado>(`${R}/locais`, dados),
        alternar: (id: number) => api.criar<Recado & { activo: boolean }>(`${R}/locais/${id}/alternar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/locais/${id}`),
    },

    tipos: {
        lista: () => api.ler<{
            data: TipoDeEvento[]; emojis: Escolha[];
            resumo: { total: number; activos: number };
            permissoes: { pode_gerir: boolean };
        }>(`${R}/tipos`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado>(`${R}/tipos/${id}`, dados) : api.criar<Recado>(`${R}/tipos`, dados),
        mover: (id: number, direccao: 'cima' | 'baixo') =>
            api.criar<Recado>(`${R}/tipos/${id}/mover`, { direccao }),
        alternar: (id: number) => api.criar<Recado & { activo: boolean }>(`${R}/tipos/${id}/alternar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/tipos/${id}`),
    },

    tecnicos: {
        opcoes: () => api.ler<{
            niveis: Escolha[];
            especialidades: Array<Escolha & { icone: string }>;
            permissoes: { pode_gerir: boolean };
        }>(`${R}/tecnicos/opcoes`),
        lista: (f: {
            procura?: string; especialidade?: string; nivel?: string;
            por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Tecnico[]; meta: Meta;
            resumo: { total: number; activos: number; do_rh: number };
            permissoes: { pode_gerir: boolean };
        }>(`${R}/tecnicos`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Tecnico }>(`${R}/tecnicos/${id}`, dados)
               : api.criar<Recado & { data: Tecnico }>(`${R}/tecnicos`, dados),
        alternar: (id: number) => api.criar<Recado & { activo: boolean }>(`${R}/tecnicos/${id}/alternar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/tecnicos/${id}`),
        doRh: () => api.ler<{
            data: Array<{
                id: number; nome: string; email: string | null;
                telefone: string | null; cargo: string | null;
            }>;
        }>(`${R}/tecnicos/do-rh`),
        importarDoRh: (ids: number[]) =>
            api.criar<Recado & { importados: number }>(`${R}/tecnicos/do-rh`, { ids }),
    },

    relatorios: (f: { de?: string; ate?: string; cliente?: number | ''; tipo?: number | ''; estado?: string }) =>
        api.ler<RelatorioDeEventos>(`${R}/relatorios`, f),
};
