import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { useEvento } from '../ganchos';
import { enableOfflineAuth, getOfflineAuthInfo, isOfflineAuthEnabled, isPwaUnlocked, verifyOfflineAuth, type InformacaoDoAcesso } from '../motor/acesso';
import { db, lerMeta } from '../motor/base';
import { avisar, Nota } from '../ui/Dialogos';
import { CAMPO, Folha, ROTULO } from '../ui/Folha';

/**
 * O PORTÃO — quem entra num separador novo sem rede tem de pôr o PIN.
 *
 * Fechar a aplicação apaga o `sessionStorage`, e é lá que vive o desbloqueio
 * (`pwa_unlocked`). Por isso volta a pedir: isso está certo — impede que outra
 * pessoa se sente à caixa em nome de quem lá estava.
 *
 * O QUE MUDOU: o portão aparecia também COM rede e sessão viva, e ficava lá
 * mesmo depois de a sincronização provar que a sessão era boa. Agora sai
 * sozinho quando a sincronização destranca o separador.
 *
 * Dois modos: funcionários com PIN (qualquer um entra, com o seu email) e o
 * legado de um só operador (o email já vem preenchido e pede-se a palavra-passe).
 */
export function PortaoOffline() {
    const { rotas } = usePwa();
    const [info, setInfo] = useState<InformacaoDoAcesso | null>(null);
    const [aberto, setAberto] = useState(false);
    const [caducado, setCaducado] = useState(false);
    const [email, setEmail] = useState('');
    const [segredo, setSegredo] = useState('');
    const [erro, setErro] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [abanar, setAbanar] = useState(0);
    const campoSegredo = useRef<HTMLInputElement>(null);

    const avaliar = useCallback(async () => {
        if (isPwaUnlocked()) { setAberto(false); return; }

        const i = await getOfflineAuthInfo();
        const ligado = await isOfflineAuthEnabled();
        setInfo(i);

        // Acesso offline caducou (funcionários sincronizados, janela passada):
        // mostrar a explicar, e não assumir sessão.
        if (i.window_expired && i.employees > 0) {
            setCaducado(true);
            setAberto(true);
            return;
        }

        setCaducado(false);
        // Sem acesso offline: a página foi servida, portanto há sessão; a
        // sincronização marca o desbloqueio.
        setAberto(ligado);
    }, []);

    useEffect(() => { void avaliar(); }, [avaliar]);
    useEvento('pwa:session-expired', () => { setTimeout(() => void avaliar(), 200); });
    // A sincronização provou a sessão: o separador fica destrancado e o portão sai.
    useEvento('pwa:synced', () => { if (isPwaUnlocked()) setAberto(false); });

    const legado = info && info.employees === 0 && info.legacy ? info.legacy.email : null;

    useEffect(() => {
        if (!aberto) return;
        if (legado) setEmail(legado);
        setTimeout(() => campoSegredo.current?.focus(), 50);
    }, [aberto, legado]);

    if (!aberto) return null;

    const entrar = async (e: React.FormEvent) => {
        e.preventDefault();
        setErro('');
        setOcupado(true);
        try {
            const r = await verifyOfflineAuth(email, segredo);
            if (r.ok) {
                setAberto(false);
                setSegredo('');
                window.dispatchEvent(new CustomEvent('pwa:offline-login-success', { detail: r }));
                avisar(t('Bem-vindo, :nome.', { nome: r.name }), 'ok', { duracao: 2500 });
                return;
            }
            let msg = t('Email ou PIN que não conferem.');
            if (r.reason === 'NO_ENGINE') msg = t('O motor offline ainda não carregou. Ligue-se à rede e recarregue uma vez.');
            else if (r.reason === 'LOCKED') msg = t('Demasiadas tentativas. Aguarde um momento.');
            else if (r.reason === 'EXPIRED' || r.reason === 'EXPIRED_WINDOW') msg = t('O acesso offline caducou — sincronize com internet.');
            else if (r.reason === 'NO_CACHE') msg = t('Este aparelho ainda não sincronizou a empresa.');
            else if (r.reason === 'EMAIL_MISMATCH') msg = t('Este email não está registado offline.');
            setErro(msg);
            setAbanar((n) => n + 1);
        } catch (err) {
            setErro((err as Error).message || t('Erro inesperado'));
        } finally {
            setOcupado(false);
        }
    };

    const subtitulo = caducado
        ? t('O acesso offline caducou — ligue-se à internet e sincronize.')
        : legado
            ? t('Insira a palavra-passe de :email', { email: legado })
            : t('Entre com o seu email e PIN de turno');

    const voltar = encodeURIComponent(window.location.pathname + window.location.search);

    return (
        <div id="pwa-offline-login" className="pwa-fundo fixed inset-0 z-[100] bg-gradient-to-br from-slate-900 to-blue-900 flex items-center justify-center p-4">
            <div key={abanar} className={`pwa-cresce bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 ${abanar ? 'pwa-abana' : ''}`}>
                <div className="text-center mb-5">
                    <div className="w-16 h-16 mx-auto bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center mb-3 shadow-lg pwa-flutua">
                        <i className="fas fa-lock text-white text-2xl" aria-hidden="true" />
                    </div>
                    <h2 className="text-xl font-bold text-gray-900">{t('Login Offline')}</h2>
                    <p className={`text-xs mt-1 ${caducado ? 'text-amber-700 font-semibold' : 'text-gray-500'}`}>{subtitulo}</p>
                </div>
                <form onSubmit={(e) => void entrar(e)} className="space-y-3">
                    <div>
                        <label htmlFor="pwa-offline-login-email" className={ROTULO}>{t('Email')}</label>
                        <input id="pwa-offline-login-email" type="email" required autoComplete="username"
                               value={email} readOnly={!!legado} onChange={(e) => setEmail(e.target.value)}
                               className={`${CAMPO} ${legado ? 'bg-slate-50' : ''}`} />
                    </div>
                    <div>
                        <label htmlFor="pwa-offline-login-password" className={ROTULO}>
                            {legado ? t('Palavra-passe') : t('PIN de turno (4 a 6 dígitos)')}
                        </label>
                        <input ref={campoSegredo} id="pwa-offline-login-password" type="password" required
                               value={segredo} onChange={(e) => setSegredo(e.target.value)}
                               {...(legado
                                   ? { autoComplete: 'current-password', inputMode: 'text' as const }
                                   : { autoComplete: 'off', inputMode: 'numeric' as const, maxLength: 6 })}
                               className={`${CAMPO} ${legado ? '' : 'text-center text-lg tracking-[0.4em]'}`} />
                    </div>
                    {erro && <p role="alert" className="pwa-entra text-xs text-red-600 font-bold text-center">{erro}</p>}
                    <button type="submit" disabled={ocupado}
                            className="pwa-toque w-full bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-bold py-3 rounded-xl shadow-lg disabled:opacity-50">
                        <i className={`fas ${ocupado ? 'fa-spinner fa-spin' : 'fa-unlock'} mr-1`} aria-hidden="true" />
                        {ocupado ? t('A verificar…') : t('Desbloquear')}
                    </button>
                </form>
                <p className="mt-3 text-center">
                    {/* Esqueceu o PIN sem rede: um gestor presente autoriza um novo. */}
                    <a id="pwa-offline-login-esqueci" href={`${rotas.pinEsquecido}?voltar=${voltar}`}
                       className="text-xs text-blue-700 font-semibold underline">{t('Esqueci o PIN')}</a>
                </p>
                <p className="mt-3 text-[10px] text-center text-gray-400">
                    {t('Se voltar à internet, faça login normal em')} <a href={rotas.entrada} className="text-blue-600 underline">{rotas.entrada}</a>.
                </p>
            </div>
        </div>
    );
}

/**
 * O CONVITE A TER ACESSO SEM REDE — o PIN de turno de QUEM ESTÁ A USAR.
 *
 * Antes só aparecia quando o aparelho não tinha acesso offline NENHUM, e então
 * oferecia o verificador legado da palavra-passe. Numa empresa em que um colega
 * já tinha PIN nunca aparecia — e quem não tinha o seu ficava fora do POS no
 * primeiro corte de rede, sem nunca ter sido avisado. E a página «PIN de turno»,
 * que a entrada sem rede manda procurar, não tinha ligação em lado nenhum.
 *
 * Agora: depois de uma sincronização com rede, se o email de quem entrou não
 * está entre os funcionários com PIN, aparece o convite para o definir (com o
 * regresso ao PWA). A palavra-passe neste aparelho continua como recurso, mas
 * só numa empresa onde ninguém tem PIN — é o caso do operador sozinho.
 */
export function AtivarLoginOffline() {
    const { rotas } = usePwa();
    const [banner, setBanner] = useState(false);
    const [semPinNaEmpresa, setSemPinNaEmpresa] = useState(false);
    const [modal, setModal] = useState(false);
    const [password, setPassword] = useState('');
    const [erro, setErro] = useState('');
    const [ocupado, setOcupado] = useState(false);

    const talvez = useCallback(async () => {
        try { if (sessionStorage.getItem('pwa_setup_dismissed') === '1') return; } catch { /* sem armazenamento */ }
        if (document.getElementById('pwa-offline-login')) return;
        if (!navigator.onLine) return;
        const user = await lerMeta<{ email?: string }>('user');
        if (!user?.email) return;

        const email = String(user.email).toLowerCase().trim();
        if ((await db.employees.get(email))?.pin_hash) {
            setBanner(false);

            return;
        }

        const ninguem = (await db.employees.count()) === 0;
        // Operador sozinho que já activou a palavra-passe neste aparelho: tem acesso.
        if (ninguem && (await isOfflineAuthEnabled())) return;

        setSemPinNaEmpresa(ninguem);
        setBanner(true);
    }, []);

    useEvento('pwa:synced', () => { setTimeout(() => void talvez(), 1500); });

    const dispensar = () => {
        setBanner(false);
        try { sessionStorage.setItem('pwa_setup_dismissed', '1'); } catch { /* sem armazenamento */ }
    };

    const activar = async (e: React.FormEvent) => {
        e.preventDefault();
        setErro('');
        setOcupado(true);
        try {
            await enableOfflineAuth(password);
            setModal(false);
            setPassword('');
            avisar(t('Pode agora usar o PWA mesmo sem internet (90 dias).'), 'ok', { titulo: t('Login offline ativado') });
        } catch (err) {
            setErro((err as Error).message || t('Erro ao ativar'));
        } finally {
            setOcupado(false);
        }
    };

    return (
        <>
            {banner && (
                <div id="pwa-offline-login-setup" className="pwa-sobe fixed bottom-20 inset-x-3 z-[90] bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-2xl shadow-2xl p-4">
                    <div className="flex items-center gap-3">
                        <div className="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i className="fas fa-shield-halved text-xl" aria-hidden="true" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="font-bold text-sm">{t('Defina o seu PIN de turno')}</p>
                            <p className="text-xs opacity-90">{t('Sem PIN não entra neste aparelho quando faltar a internet.')}</p>
                            {semPinNaEmpresa && (
                                <button type="button" onClick={() => { setBanner(false); setModal(true); }}
                                        className="mt-1 text-[11px] underline opacity-90">{t('Usar antes a palavra-passe neste aparelho')}</button>
                            )}
                        </div>
                        <a id="pwa-definir-pin" href={`${rotas.definirPin}?voltar=${encodeURIComponent(window.location.pathname)}`}
                           className="pwa-toque bg-white text-teal-700 px-3 py-2 rounded-lg text-xs font-bold whitespace-nowrap">
                            <i className="fas fa-key mr-1" aria-hidden="true" />{t('Definir PIN')}
                        </a>
                        <button type="button" onClick={dispensar} title={t('Mais tarde')} aria-label={t('Mais tarde')}
                                className="text-white/70 hover:text-white text-lg px-1">&times;</button>
                    </div>
                </div>
            )}

            <Folha aberta={modal} aoFechar={() => { setModal(false); setErro(''); setPassword(''); }}
                   titulo={t('Ativar login offline')} icone="fa-shield-halved" cor="from-emerald-600 to-teal-700" zIndex="z-[110]">
                <form onSubmit={(e) => void activar(e)} className="space-y-3">
                    <p className="text-xs text-gray-500">{t('Digite a sua password atual para ativar o acesso offline. Será guardada em formato encriptado neste dispositivo (válido 90 dias).')}</p>
                    <div>
                        <label htmlFor="pwa-offline-setup-password" className={ROTULO}>{t('Password')}</label>
                        <input id="pwa-offline-setup-password" type="password" required minLength={4} autoComplete="current-password"
                               value={password} onChange={(e) => setPassword(e.target.value)} className={CAMPO} autoFocus />
                    </div>
                    {erro && <Nota tipo="erro" icone="fa-circle-exclamation">{erro}</Nota>}
                    <button type="submit" disabled={ocupado}
                            className="pwa-toque w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl shadow-lg disabled:opacity-50">
                        <i className={`fas ${ocupado ? 'fa-spinner fa-spin' : 'fa-check'} mr-1`} aria-hidden="true" />{t('Ativar login offline')}
                    </button>
                </form>
            </Folha>
        </>
    );
}
