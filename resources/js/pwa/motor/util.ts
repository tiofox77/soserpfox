/**
 * Utilidades pequenas do motor, sem base nem rede — por isso ensaiáveis à parte.
 */

/**
 * A data de HOJE no relógio de quem está a vender.
 *
 * `new Date().toISOString().slice(0, 10)` devolve a data em UTC. Angola está
 * uma hora à frente: entre a meia-noite e a uma da manhã o UTC ainda está no
 * dia anterior — e isto vai para o `invoice_date`, a data FISCAL. Uma venda
 * feita à 00:30 de dia 15 saía datada de dia 14.
 */
export function dataDeHoje(agora = new Date()): string {
    const dois = (n: number) => String(n).padStart(2, '0');

    return `${agora.getFullYear()}-${dois(agora.getMonth() + 1)}-${dois(agora.getDate())}`;
}

/** Soma `dias` a hoje, no calendário de quem vende. */
export function dataDaquiA(dias: number, agora = new Date()): string {
    const d = new Date(agora);
    d.setDate(d.getDate() + dias);

    return dataDeHoje(d);
}

/**
 * Um identificador em forma de UUID, feito no aparelho.
 *
 * A comanda e cada artigo nascem sem rede; é este identificador que os liga
 * quando sobem e que impede um reenvio de lançar a mesma comanda duas vezes.
 * Tem de ser UUID a sério: a coluna é char(36) e o servidor exige o formato.
 * O ramo de reserva usa `getRandomValues` — nunca `Math.random`, que numa sala
 * com dez tablets é uma colisão que acontece.
 */
export function uuidV4(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const b = new Uint8Array(16);
    crypto.getRandomValues(b);
    b[6] = ((b[6] ?? 0) & 0x0f) | 0x40;
    b[8] = ((b[8] ?? 0) & 0x3f) | 0x80;

    const hex = [...b].map((n) => n.toString(16).padStart(2, '0')).join('');

    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
}

/**
 * As formas equivalentes de um código de barras lido.
 *
 * Espelha `App\Support\CodigoDeBarras` no servidor — se um dia mudar lá, muda
 * aqui. O mesmo artigo pode estar guardado com o envelope GS1 à frente (o
 * «01» é identificador de aplicação) ou só com o EAN-13 de dentro, e o leitor
 * tanto manda um como o outro.
 */
export function formasDeCodigo(lido: unknown): string[] {
    const t = String(lido ?? '').trim();
    const d = t.replace(/\D/g, '');
    const formas = [t];

    if (d && d !== t) formas.push(d);

    if (d.length >= 16 && d.startsWith('01')) {
        const gtin = d.substr(2, 14);
        formas.push(gtin);
        if (gtin[0] === '0') formas.push(gtin.substr(1));
    }

    if (d.length === 14 && d[0] === '0') formas.push(d.substr(1));

    if (d.length === 13) {
        formas.push('0' + d);
        formas.push('010' + d);
    }

    return formas.filter((f, i) => f !== '' && formas.indexOf(f) === i);
}

export function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
}

/**
 * O motivo de uma recusa, tirado do JSON que o servidor pôs no corpo.
 * `HTTP 422: {"error":"..."}` → `...`. Sem JSON, null.
 */
export function motivoDoServidor(texto: unknown): string | null {
    try {
        const s = String(texto ?? '');
        const inicio = s.indexOf('{');
        if (inicio < 0) return null;
        const corpo = JSON.parse(s.slice(inicio));

        return corpo.error || corpo.message || null;
    } catch {
        return null;
    }
}

export const arredondar2 = (v: number): number => Math.round(v * 100) / 100;

export const numero = (v: unknown): number => {
    const n = parseFloat(String(v ?? ''));

    return Number.isFinite(n) ? n : 0;
};

/**
 * O que entra na base TEM DE SER DADOS SIMPLES.
 *
 * O IndexedDB não sabe clonar Proxies nem funções: rebentava com «could not be
 * cloned» e o documento não chegava a ser gravado. Com o Alpine era o Proxy
 * reactivo; com React é um objecto que pode trazer o que o ecrã lá pôs. Passar
 * por JSON deixa só os valores.
 */
export function soDados<T>(valor: T): T {
    return JSON.parse(JSON.stringify(valor ?? {})) as T;
}

/** Um identificador local legível: `pos_1770000000000_ab12cd`. */
export function idLocal(prefixo: string): string {
    return `${prefixo}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
}

/** Espera `ms` ou até `promessa` resolver, o que vier primeiro — e limpa o temporizador. */
export async function comLimite(promessa: Promise<unknown>, ms: number): Promise<void> {
    let travao: ReturnType<typeof setTimeout> | undefined;
    const limite = new Promise<void>((resolve) => { travao = setTimeout(resolve, ms); });

    try {
        await Promise.race([promessa, limite]);
    } finally {
        clearTimeout(travao);
    }
}
