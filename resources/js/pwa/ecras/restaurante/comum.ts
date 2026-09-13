import { t } from '@/i18n';

import { lerMeta, type Registo } from '../../motor/base';
import { restaurante } from '../../motor/restaurante';
import { PosOfflineTicket } from '../../papel';
import { avisar } from '../../ui/Dialogos';

/**
 * O QUE A SALA E A COMANDA PARTILHAM — os estados da mesa (rótulo, cor,
 * ícone), o nome de uma mesa e a impressão do talão.
 */

export type Vista = 'sala' | 'comanda';

/** O que o modal do recibo precisa de saber da conta que acabou de fechar. */
export interface Recibo {
    local_uuid: string;
    numero: string | null;
    provisorio: string | null;
    total: number;
}

/** Os oito estados que o servidor dá a uma mesa, pela ordem em que se vivem. */
export const ESTADOS_DA_MESA = ['available', 'reserved', 'occupied', 'waiting_kitchen', 'served', 'billing', 'cleaning', 'blocked'] as const;

export function rotuloDoEstado(estado: unknown): string {
    // As frases escritas uma a uma (e não `t(estado)`): é assim que a extracção
    // das traduções as encontra.
    switch (estado) {
        case 'available': return t('Livre');
        case 'reserved': return t('Reservada');
        case 'occupied': return t('Ocupada');
        case 'waiting_kitchen': return t('Na cozinha');
        case 'served': return t('Servida');
        case 'billing': return t('A pedir conta');
        case 'cleaning': return t('Em limpeza');
        case 'blocked': return t('Bloqueada');
        default: return String(estado ?? '');
    }
}

const CORES: Record<string, string> = {
    available: 'bg-emerald-50 border-emerald-300 text-emerald-800',
    reserved: 'bg-cyan-50 border-cyan-300 text-cyan-800',
    occupied: 'bg-amber-50 border-amber-400 text-amber-900',
    waiting_kitchen: 'bg-violet-50 border-violet-300 text-violet-800',
    served: 'bg-sky-50 border-sky-300 text-sky-800',
    billing: 'bg-orange-50 border-orange-300 text-orange-800',
    cleaning: 'bg-slate-100 border-slate-300 text-slate-500',
    blocked: 'bg-red-50 border-red-300 text-red-700',
};

/** O ponto de cor da legenda — o mesmo tom da borda do cartão. */
const PONTOS: Record<string, string> = {
    available: 'bg-emerald-400',
    reserved: 'bg-cyan-400',
    occupied: 'bg-amber-400',
    waiting_kitchen: 'bg-violet-400',
    served: 'bg-sky-400',
    billing: 'bg-orange-400',
    cleaning: 'bg-slate-400',
    blocked: 'bg-red-400',
};

const ICONES: Record<string, string> = {
    available: 'fa-circle-check',
    reserved: 'fa-calendar-check',
    occupied: 'fa-utensils',
    waiting_kitchen: 'fa-fire-burner',
    served: 'fa-bell-concierge',
    billing: 'fa-receipt',
    cleaning: 'fa-broom',
    blocked: 'fa-ban',
};

export const corDaMesa = (estado: unknown): string => CORES[String(estado)] ?? 'bg-white border-slate-200 text-slate-700';
export const pontoDoEstado = (estado: unknown): string => PONTOS[String(estado)] ?? 'bg-slate-300';
export const iconeDoEstado = (estado: unknown): string => ICONES[String(estado)] ?? 'fa-chair';

/**
 * Procura em TODAS as mesas e não só nas da zona escolhida: a lista das
 * últimas contas mostra mesas de qualquer zona, e uma conta que dissesse só
 * "Mesa" não ajudava ninguém a encontrá-la.
 */
export function nomeDaMesa(id: unknown, todasAsMesas: Registo[]): string {
    if (!id) return t('Balcão');

    const m = todasAsMesas.find((x) => x.id === id);

    return m ? String(m.name || m.code) : t('Mesa');
}

/**
 * Imprime o talão da conta — o mesmo do balcão, porque é o mesmo documento
 * fiscal. Muda o cabeçalho: leva a mesa e o número da comanda.
 */
export async function imprimirTalao(uuid: string): Promise<void> {
    const talao = await restaurante.talao(uuid);

    if (!talao) {
        avisar(t('Não foi possível montar o talão desta conta.'), 'erro');

        return;
    }

    const empresa = (await lerMeta<Registo>('company')) || {};

    try {
        PosOfflineTicket.print(talao, empresa);
    } catch (e) {
        // O papel vai no pacote, mas a janela de impressão pode ser recusada
        // pelo browser: o aviso antigo continua a ser o conselho certo.
        console.error('[Restaurante] impressão do talão falhou', e);
        avisar(t('O módulo de impressão não carregou. Recarregue a página com internet.'), 'erro');
    }
}

/** A mensagem de um erro do motor, legível. */
export const mensagemDe = (e: unknown): string => (e instanceof Error ? e.message : String(e));
