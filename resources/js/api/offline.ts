import { api } from './cliente';

export type Inventario = {
    vendas: number; clientes: number; rascunhos: number; comandas: number; turnos: number;
    gerado_em: string | null; dispositivo: string | null; operador: string | null;
};

export type Resultado = { importadas?: number; ja_existiam?: number } & Record<string, unknown>;

export const copiaOffline = {
    analisar: (corpo: FormData) => api.enviar<{ inventario: Inventario }>('/copia-offline/analisar', corpo),
    importar: (corpo: FormData) => api.enviar<{ resultado: Resultado; message: string }>('/copia-offline/importar', corpo),
};

export const pin = {
    estado: () => api.ler<{ ja_tem_pin: boolean; nome: string }>('/pin'),
    definir: (corpo: { pin: string; pin_confirmation: string; password: string }) => api.criar<{ ja_tem_pin: boolean; message: string }>('/pin', corpo),
};
