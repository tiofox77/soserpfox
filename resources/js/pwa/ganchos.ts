import { liveQuery } from 'dexie';
import { useCallback, useEffect, useRef, useState, useSyncExternalStore, type DependencyList } from 'react';

import { etiquetaIntl } from '@/i18n';

import { fotografiaDoEstado, subscrever, type EstadoDoMotor } from './motor/estado';

/**
 * OS GANCHOS DOS ECRÃS AO MOTOR.
 *
 * Os ecrãs em Alpine faziam cada um a sua ginástica: ouviam `pwa:synced` e
 * recarregavam a página inteira, ou liam a base num `setInterval`. Aqui:
 *
 *  • o ESTADO do motor (rede, a sincronizar, pendentes) chega por
 *    `useEstadoDoMotor` e o ecrã redesenha quando muda;
 *  • o que está na BASE LOCAL chega por `useBaseViva`, que é uma consulta
 *    viva do Dexie — a sincronização escreve e a lista actualiza-se sozinha,
 *    sem recarregar nada nem perder o que se estava a escrever.
 */

export function useEstadoDoMotor(): EstadoDoMotor {
    return useSyncExternalStore(subscrever, fotografiaDoEstado, fotografiaDoEstado);
}

/**
 * Uma consulta à base local que se refaz quando as tabelas que lê mudam.
 *
 * `inicial` é o que se mostra enquanto a primeira leitura não volta (em regra,
 * uns milissegundos) — nunca `undefined` a espalhar-se pelo ecrã.
 */
export function useBaseViva<T>(consulta: () => Promise<T>, deps: DependencyList, inicial: T): T {
    const [valor, setValor] = useState<T>(inicial);

    useEffect(() => {
        const assinatura = liveQuery(consulta).subscribe({
            next: (v) => setValor(v),
            error: (e) => console.warn('[PWA] consulta à base local falhou', e),
        });

        return () => assinatura.unsubscribe();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);

    return valor;
}

/** Ouve um evento `pwa:*` (ou outro) na janela, com o ouvinte sempre actual. */
export function useEvento<T = unknown>(nome: string, ouvinte: (detalhe: T) => void): void {
    const actual = useRef(ouvinte);
    actual.current = ouvinte;

    useEffect(() => {
        const f = (e: Event) => actual.current((e as CustomEvent<T>).detail);
        window.addEventListener(nome, f);

        return () => window.removeEventListener(nome, f);
    }, [nome]);
}

/** Uma acção assíncrona com o seu «a trabalhar…» — para os botões não se carregarem duas vezes. */
export function useAccao<A extends unknown[], R>(fn: (...args: A) => Promise<R>): [(...args: A) => Promise<R | undefined>, boolean] {
    const [ocupado, setOcupado] = useState(false);
    const aCorrer = useRef(false);
    const actual = useRef(fn);
    actual.current = fn;

    const correr = useCallback(async (...args: A) => {
        if (aCorrer.current) return undefined;
        aCorrer.current = true;
        setOcupado(true);
        try {
            return await actual.current(...args);
        } finally {
            aCorrer.current = false;
            setOcupado(false);
        }
    }, []);

    return [correr, ocupado];
}

/** Um valor que só assenta depois de o utilizador parar de escrever. */
export function useAssentado<T>(valor: T, ms = 200): T {
    const [assentado, setAssentado] = useState(valor);

    useEffect(() => {
        const id = setTimeout(() => setAssentado(valor), ms);

        return () => clearTimeout(id);
    }, [valor, ms]);

    return assentado;
}

/** Relógio para textos como «há 3 min» — redesenha de tantos em tantos segundos. */
export function useRelogio(segundos = 30): number {
    const [agora, setAgora] = useState(() => Date.now());

    useEffect(() => {
        const id = setInterval(() => setAgora(Date.now()), segundos * 1000);

        return () => clearInterval(id);
    }, [segundos]);

    return agora;
}

// ── Formatos ─────────────────────────────────────────────────────────────

const moeda = new Map<string, Intl.NumberFormat>();

/** 1234.5 → «1.234,50» (sem o «Kz»: cada ecrã põe-no com o tamanho que lhe cabe). */
export function dinheiro(v: unknown): string {
    const etiqueta = etiquetaIntl();
    let f = moeda.get(etiqueta);

    if (!f) {
        f = new Intl.NumberFormat(etiqueta, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        moeda.set(etiqueta, f);
    }

    const n = Number(v);

    return f.format(Number.isFinite(n) ? n : 0);
}

/** «1.234,50 Kz» */
export const kz = (v: unknown): string => `${dinheiro(v)} Kz`;

export function hora(iso: unknown): string {
    if (!iso) return '';
    const d = new Date(String(iso));

    return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString(etiquetaIntl(), { hour: '2-digit', minute: '2-digit' });
}

export function dataCurta(iso: unknown): string {
    if (!iso) return '';
    const d = new Date(String(iso));

    return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString(etiquetaIntl(), { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export function dataEHora(iso: unknown): string {
    if (!iso) return '';
    const d = new Date(String(iso));

    return Number.isNaN(d.getTime()) ? '' : d.toLocaleString(etiquetaIntl(), { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
