import { t } from '@/i18n';

import { db, lerMeta, type Registo } from './base';
import { enqueue } from './fila';
import { idDoAparelho } from './rede';
import { sync } from './sincronizar';
import { uuidV4 } from './util';

/**
 * A ENTRADA SEM REDE — o PIN de turno e o verificador legado.
 *
 * Isto NÃO é uma sessão do servidor: é um desbloqueio local, para quem está ao
 * balcão continuar a vender com o que já está no aparelho. Impede que outra
 * pessoa se sente à caixa em nome de quem lá estava; não protege a base local
 * de quem leve o aparelho.
 */

declare global {
    interface Window {
        bcrypt?: BcryptJs;
        dcodeIO?: { bcrypt?: BcryptJs };
    }
}

interface BcryptJs {
    compare(s: string, hash: string, cb: (err: Error | null, ok: boolean) => void): void;
    hash(s: string, rounds: number, cb: (err: Error | null, hash: string) => void): void;
}

export type MotivoDeRecusa =
    | 'MISSING' | 'LOCKED' | 'EXPIRED_WINDOW' | 'NO_ENGINE' | 'BAD_PIN' | 'NO_CACHE' | 'EXPIRED'
    | 'EMAIL_MISMATCH' | 'BAD_PASSWORD' | 'ALVO_DESCONHECIDO' | 'GESTOR_SEM_DIREITO' | 'TAMANHO' | 'OBVIO';

export type ResultadoDaEntrada =
    | { ok: true; name: string; email: string; local_uuid?: string }
    | { ok: false; reason: MotivoDeRecusa; until?: number };

/**
 * O verificador legado: SHA-256 repetido 10 000 vezes sobre sal+email+password.
 * Só existe para os aparelhos de um operador que ainda não têm funcionários com PIN.
 */
async function hashDasCredenciais(email: string, password: string, sal: string): Promise<string> {
    let dados: Uint8Array = new TextEncoder().encode(sal + ':' + email.toLowerCase().trim() + ':' + password);

    for (let i = 0; i < 10000; i++) {
        dados = new Uint8Array(await crypto.subtle.digest('SHA-256', dados as BufferSource));
    }

    return Array.from(dados).map((b) => b.toString(16).padStart(2, '0')).join('');
}

function gerarSal(): string {
    const a = new Uint8Array(16);
    crypto.getRandomValues(a);

    return Array.from(a).map((b) => b.toString(16).padStart(2, '0')).join('');
}

export async function enableOfflineAuth(password: string): Promise<boolean> {
    const user = await lerMeta<Registo>('user');
    if (!user?.email) throw new Error(t('Sem utilizador autenticado para configurar login offline.'));
    if (!password || password.length < 4) throw new Error(t('Password muito curta (mínimo 4 caracteres).'));

    const sal = gerarSal();
    const expira = new Date();
    expira.setDate(expira.getDate() + 90);

    await db.meta.put({
        key: 'auth_cache',
        value: {
            email: String(user.email).toLowerCase().trim(),
            name: user.name || user.email,
            user_id: user.id,
            tenant_id: await lerMeta('tenant_id'),
            salt: sal,
            hash: await hashDasCredenciais(user.email, password, sal),
            enabled_at: new Date().toISOString(),
            expires_at: expira.toISOString(),
        },
    });

    return true;
}

/**
 * O motor de bcrypt (servido pelo domínio, pré-guardado). Se não carregou,
 * null — e quem chama TEM de distinguir isso de PIN errado, senão tranca toda
 * a gente em silêncio e ainda conta como tentativa.
 */
export function bcryptEngine(): BcryptJs | null {
    return window.bcrypt || window.dcodeIO?.bcrypt || null;
}

export function bcryptCompare(segredo: string, hash: string | null | undefined): Promise<boolean> {
    const bc = bcryptEngine();

    return new Promise((resolve) => {
        if (!bc || !hash) return resolve(false);
        try { bc.compare(String(segredo), hash, (err, ok) => resolve(!err && !!ok)); } catch { resolve(false); }
    });
}

function bcryptHash(segredo: string, rondas = 12): Promise<string | null> {
    const bc = bcryptEngine();

    return new Promise((resolve) => {
        if (!bc) return resolve(null);
        try { bc.hash(String(segredo), rondas, (err, hash) => resolve(err ? null : hash)); } catch { resolve(null); }
    });
}

/**
 * A janela de validade offline. FAIL-CLOSED: um sync real grava SEMPRE
 * `offline_valid_until`; faltar a chave tem de NEGAR, senão apagá-la reabria
 * o acesso para sempre.
 */
async function janelaOk(): Promise<boolean> {
    const ate = await lerMeta<string>('offline_valid_until');

    return !!ate && new Date(ate) >= new Date();
}

/** Desactiva MESMO o acesso offline: só volta com nova sincronização com rede. */
async function expirarAcessoOffline(): Promise<void> {
    try {
        await db.employees.clear();
        for (const k of ['auth_cache', 'offline_valid_until', 'pin_attempts']) await db.meta.delete(k);
        try { sessionStorage.removeItem('pwa_unlocked'); } catch { /* ignora */ }
    } catch { /* ignora */ }
}

// A trava anti-força-bruta por conta: um PIN é de baixa entropia. 5 falhas → 60 s.
async function trancadoAte(email: string): Promise<number> {
    const todas = (await lerMeta<Record<string, { count: number; until: number }>>('pin_attempts')) || {};
    const ate = todas[email]?.until || 0;

    return ate > Date.now() ? ate : 0;
}

async function registarFalha(email: string): Promise<void> {
    const todas = (await lerMeta<Record<string, { count: number; until: number }>>('pin_attempts')) || {};
    const rec = todas[email] || { count: 0, until: 0 };
    rec.count = (rec.count || 0) + 1;
    if (rec.count >= 5) { rec.until = Date.now() + 60000; rec.count = 0; }
    todas[email] = rec;
    await db.meta.put({ key: 'pin_attempts', value: todas });
}

async function limparFalhas(email: string): Promise<void> {
    const todas = (await lerMeta<Record<string, unknown>>('pin_attempts')) || {};
    if (todas[email]) {
        delete todas[email];
        await db.meta.put({ key: 'pin_attempts', value: todas });
    }
}

function destrancar(): void {
    try { sessionStorage.setItem('pwa_unlocked', '1'); } catch { /* ignora */ }
    try { sessionStorage.setItem('pwa_unlocked_at', new Date().toISOString()); } catch { /* ignora */ }
}

export async function isOfflineAuthEnabled(): Promise<boolean> {
    if ((await db.employees.count()) > 0 && (await janelaOk())) return true;

    const legado = await lerMeta<Registo>('auth_cache');

    return !!legado && new Date(legado.expires_at) >= new Date();
}

export interface InformacaoDoAcesso {
    employees: number;
    valid_until: string | null;
    window_expired: boolean;
    legacy: { email: string; name: string; expires_at: string } | null;
}

export async function getOfflineAuthInfo(): Promise<InformacaoDoAcesso> {
    const funcionarios = await db.employees.count();
    const ate = await lerMeta<string>('offline_valid_until');
    const legado = await lerMeta<Registo>('auth_cache');

    return {
        employees: funcionarios,
        valid_until: ate || null,
        window_expired: ate ? new Date(ate) < new Date() : false,
        legacy: legado ? { email: legado.email, name: legado.name, expires_at: legado.expires_at } : null,
    };
}

/**
 * Entra sem rede. `segredo` é o PIN de turno (funcionário sincronizado) ou a
 * password (verificador legado); descobre-se pelo email.
 */
export async function verifyOfflineAuth(email: string, segredo: string): Promise<ResultadoDaEntrada> {
    const mail = String(email || '').toLowerCase().trim();
    if (!mail || !segredo) return { ok: false, reason: 'MISSING' };

    const ate = await trancadoAte(mail);
    if (ate) return { ok: false, reason: 'LOCKED', until: ate };

    const emp = await db.employees.get(mail);

    if (emp?.pin_hash) {
        if (!(await janelaOk())) {
            // A janela caducou: apagam-se os verificadores, não só se recusa.
            await expirarAcessoOffline();

            return { ok: false, reason: 'EXPIRED_WINDOW' };
        }

        // Motor de cripto ausente ≠ PIN errado: não conta como tentativa.
        if (!bcryptEngine()) return { ok: false, reason: 'NO_ENGINE' };

        if (!(await bcryptCompare(segredo, emp.pin_hash))) {
            await registarFalha(mail);

            return { ok: false, reason: 'BAD_PIN' };
        }

        await limparFalhas(mail);

        // Quem entra passa a ser o operador activo — é ele que o talão e a AGT registam.
        await db.meta.put({ key: 'user', value: { id: emp.id, name: emp.name, email: mail, tenant_id: await lerMeta('tenant_id') } });
        destrancar();

        return { ok: true, name: emp.name, email: mail };
    }

    const cache = await lerMeta<Registo>('auth_cache');
    if (!cache) return { ok: false, reason: 'NO_CACHE' };
    if (new Date(cache.expires_at) < new Date()) return { ok: false, reason: 'EXPIRED' };
    if (mail !== cache.email) return { ok: false, reason: 'EMAIL_MISMATCH' };

    if ((await hashDasCredenciais(mail, segredo, cache.salt)) !== cache.hash) {
        await registarFalha(mail);

        return { ok: false, reason: 'BAD_PASSWORD' };
    }

    await limparFalhas(mail);
    destrancar();

    return { ok: true, name: cache.name, email: cache.email };
}

/** As regras do PIN vêm do servidor na sincronização; sem ela, o mínimo aceitável. */
export async function regrasDoPin(): Promise<{ min: number; max: number; obvios: string[] }> {
    return (await lerMeta('pin_regras')) || { min: 4, max: 6, obvios: ['0000', '1111', '1234', '4321', '123456', '000000', '111111'] };
}

export async function recusaDoPinNovo(pin: string): Promise<'TAMANHO' | 'OBVIO' | null> {
    const r = await regrasDoPin();
    const s = String(pin || '');
    if (!/^\d+$/.test(s) || s.length < r.min || s.length > r.max) return 'TAMANHO';

    return (r.obvios || []).includes(s) ? 'OBVIO' : null;
}

/**
 * Repõe o PIN de um funcionário sem rede, com um GESTOR PRESENTE a autorizar.
 *
 * O gestor põe o SEU email e PIN (com a mesma trava da entrada); só quem pode
 * gerir utilizadores autoriza. O verificador novo fica logo no aparelho e vai
 * na fila para o servidor decidir; se ele recusar, a sincronização seguinte
 * repõe o antigo — o servidor manda.
 */
export async function reporPinOffline({ email, gestorEmail, gestorPin, pinNovo }: Record<string, string>): Promise<ResultadoDaEntrada> {
    const alvo = String(email || '').toLowerCase().trim();
    const gestor = String(gestorEmail || '').toLowerCase().trim();
    if (!alvo || !gestor || !gestorPin || !pinNovo) return { ok: false, reason: 'MISSING' };

    if (!(await janelaOk())) {
        await expirarAcessoOffline();

        return { ok: false, reason: 'EXPIRED_WINDOW' };
    }

    if (!bcryptEngine()) return { ok: false, reason: 'NO_ENGINE' };

    const empAlvo = await db.employees.get(alvo);
    if (!empAlvo) return { ok: false, reason: 'ALVO_DESCONHECIDO' };

    const ate = await trancadoAte(gestor);
    if (ate) return { ok: false, reason: 'LOCKED', until: ate };

    // Primeiro o PIN, só depois o direito: quem tenta não fica a saber se o
    // email do gestor existe sem ter o PIN dele.
    const empGestor = await db.employees.get(gestor);
    const confere = !!empGestor?.pin_hash && (await bcryptCompare(gestorPin, empGestor.pin_hash));

    if (!confere) {
        await registarFalha(gestor);

        return { ok: false, reason: 'BAD_PIN' };
    }

    await limparFalhas(gestor);
    if (!empGestor!.pode_repor_pin) return { ok: false, reason: 'GESTOR_SEM_DIREITO' };

    const recusa = await recusaDoPinNovo(pinNovo);
    if (recusa) return { ok: false, reason: recusa };

    const hash = await bcryptHash(pinNovo, 12);
    if (!hash) return { ok: false, reason: 'NO_ENGINE' };

    const agora = new Date().toISOString();
    await db.employees.update(alvo, { pin_hash: hash, updated_at: agora });
    await limparFalhas(alvo);

    const local_uuid = uuidV4();
    await enqueue('repor_pin', {
        local_uuid,
        user_id: empAlvo.id,
        email: alvo,
        authorized_by: empGestor!.id,
        authorized_email: gestor,
        pin_hash: hash,
        reposto_em: agora,
        aparelho: await idDoAparelho(),
    });

    return { ok: true, name: empAlvo.name, email: alvo, local_uuid };
}

export async function clearOfflineAuth(): Promise<void> {
    await db.meta.delete('auth_cache');
    try { sessionStorage.removeItem('pwa_unlocked'); } catch { /* ignora */ }
    try { sessionStorage.removeItem('pwa_unlocked_at'); } catch { /* ignora */ }
}

export function isPwaUnlocked(): boolean {
    try { return sessionStorage.getItem('pwa_unlocked') === '1'; } catch { return false; }
}

/**
 * Sair, com rede ou sem ela.
 *
 * SAIR É SEMPRE LOCAL PRIMEIRO: tranca o aparelho (o acesso offline fica, é
 * com ele que a próxima pessoa entra) e a saída entra na fila para a sessão do
 * servidor fechar quando houver ligação. NÃO se apaga a base: pode ter vendas
 * por enviar.
 */
export async function sair(): Promise<boolean> {
    try { sessionStorage.removeItem('pwa_unlocked'); } catch { /* ignora */ }
    try { sessionStorage.removeItem('pwa_unlocked_at'); } catch { /* ignora */ }

    await enqueue('logout', { pedido_em: new Date().toISOString() }, false);

    if (navigator.onLine) {
        try { await sync(false); } catch { /* a saída não espera pela rede */ }
    }

    return true;
}
