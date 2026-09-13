import { useEffect, useState } from 'react';

import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { useEstadoDoMotor } from '../ganchos';
import { getOfflineAuthInfo, verifyOfflineAuth } from '../motor/acesso';
import { estadoDaLigacao, type EstadoDaLigacao } from '../motor/rede';
import { Dialogos } from '../ui/Dialogos';
import { MarcaDaEntrada } from './PinEsquecido';

/**
 * A ENTRADA DO PWA — funciona com rede e sem ela.
 *
 * COM REDE entra-se de verdade: o formulário vai ao servidor (POST nativo, com
 * `pwa=1` para voltar ao POS), nasce sessão, e o que se guarda no aparelho é
 * um verificador, nunca a palavra-passe.
 *
 * SEM REDE confere-se o PIN de turno contra o verificador que o aparelho já
 * tem. Isto NÃO é uma sessão do servidor: é um desbloqueio local para quem
 * está ao balcão continuar a vender com o que já está no aparelho.
 *
 * TRÊS ESTADOS da ligação e não dois: uma sessão expirada com o telemóvel
 * cheio de sinal não é falta de rede, e mandar o operador pelo PIN — que com
 * rede nunca chega ao POS — fechava-o num beco.
 */
export function Entrada() {
    const { csrf, rotas, erro: erroDoServidor, email: emailAntigo } = usePwa();
    const motor = useEstadoDoMotor();

    const [estado, setEstado] = useState<EstadoDaLigacao>(navigator.onLine ? 'online' : 'offline');
    const [usarOffline, setUsarOffline] = useState(false);
    const [email, setEmail] = useState(emailAntigo || '');
    const [pin, setPin] = useState('');
    const [erro, setErro] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [aEnviar, setAEnviar] = useState(false);
    const [temAcessoOffline, setTemAcessoOffline] = useState(false);
    const [janelaExpirada, setJanelaExpirada] = useState(false);
    const [abanar, setAbanar] = useState(0);

    const temRede = estado !== 'offline';

    useEffect(() => {
        let vivo = true;

        const actualizar = async () => {
            // `navigator.onLine` só diz que há uma interface de rede: num Wi-Fi
            // sem internet dizia que sim e mostrava o formulário que nunca
            // poderia entrar. O ping real decide.
            const e = await estadoDaLigacao();
            if (!vivo) return;
            setEstado(e);
            // Sem rede vai-se direito ao PIN. COM rede não: aí o caminho é a palavra-passe.
            if (e === 'offline') setUsarOffline(true);
        };

        const semRede = () => { setEstado('offline'); setUsarOffline(true); };

        window.addEventListener('online', actualizar);
        window.addEventListener('offline', semRede);
        void actualizar();

        void (async () => {
            try {
                // Há acesso offline se a empresa já foi sincronizada (funcionários
                // com PIN e a janela não caducou) ou se existe o verificador legado.
                const info = await getOfflineAuthInfo();
                const temFuncionarios = info.employees > 0 && !info.window_expired;
                const temLegado = !!info.legacy && new Date(info.legacy.expires_at) >= new Date();
                if (!vivo) return;
                setTemAcessoOffline(temFuncionarios || temLegado);
                setJanelaExpirada(info.employees > 0 && info.window_expired);
                // Num aparelho legado (um só operador), pré-preenche o email.
                if (!temFuncionarios && temLegado && info.legacy) setEmail(info.legacy.email);
            } catch { /* sem base, sem acesso offline */ }
        })();

        return () => {
            vivo = false;
            window.removeEventListener('online', actualizar);
            window.removeEventListener('offline', semRede);
        };
    }, []);

    const entrarLocal = async (e: React.FormEvent) => {
        e.preventDefault();
        if (ocupado) return;
        setOcupado(true);
        setErro('');

        try {
            const r = await verifyOfflineAuth(email, pin);

            if (!r.ok) {
                if (r.reason === 'NO_ENGINE') {
                    setErro(t('O motor offline ainda não carregou. Tente outra vez.'));
                } else if (r.reason === 'LOCKED') {
                    const seg = Math.max(1, Math.ceil(((r.until ?? Date.now()) - Date.now()) / 1000));
                    setErro(t('Demasiadas tentativas. Espere :seg segundos.', { seg }));
                } else if (r.reason === 'EXPIRED_WINDOW' || r.reason === 'EXPIRED') {
                    setErro(t('O acesso offline deste aparelho expirou. É preciso entrar uma vez com ligação.'));
                    setTemAcessoOffline(false);
                    setJanelaExpirada(true);
                } else {
                    // Uma mensagem só para email e PIN: distingui-los diria a quem tenta se o email existe.
                    setErro(t('Email ou PIN que não conferem.'));
                }
                setAbanar((n) => n + 1);

                return;
            }

            window.location.href = rotas.pos;
        } catch {
            setErro(t('Não foi possível verificar neste aparelho.'));
        } finally {
            setOcupado(false);
            setPin('');
        }
    };

    const pendentes = motor.pendingCount;

    // O estado da ligação fica no DOM: é o que os ensaios lêem (e quem depura no telemóvel).
    return (
        <div className="min-h-screen flex items-center justify-center p-4" data-ensaio="entrada" data-estado={estado}>
            <div className="w-full max-w-sm">
                <MarcaDaEntrada titulo="SOS ERP" subtitulo={t('Ponto de venda')} />

                {/* Em que modo se vai entrar — quem está ao balcão tem de saber. */}
                <div className="mb-3 text-center min-h-[26px]">
                    {estado === 'online' && (
                        <span className="pwa-entra inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-100 text-xs font-semibold">
                            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />{t('Com ligação')}
                        </span>
                    )}
                    {estado === 'sessao_expirada' && (
                        <span className="pwa-entra inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-500/20 text-sky-100 text-xs font-semibold">
                            <span className="w-2 h-2 rounded-full bg-sky-300" />{t('Sessão expirada — entre outra vez')}
                        </span>
                    )}
                    {estado === 'subscricao_expirada' && (
                        <span className="pwa-entra inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-500/25 text-red-100 text-xs font-semibold">
                            <span className="w-2 h-2 rounded-full bg-red-300" />{t('Subscrição expirada')}
                        </span>
                    )}
                    {estado === 'offline' && (
                        <span className="pwa-entra inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 text-amber-100 text-xs font-semibold">
                            <span className="w-2 h-2 rounded-full bg-amber-400" />{t('Sem ligação — entrada local')}
                        </span>
                    )}
                </div>

                <div key={abanar} className={`pwa-cresce bg-white rounded-2xl shadow-2xl p-6 ${abanar ? 'pwa-abana' : ''}`}>
                    {temRede && !usarOffline ? (
                        // Com rede: formulário normal, para o servidor. É ele que cria sessão.
                        <form method="POST" action={rotas.login} onSubmit={() => setAEnviar(true)}>
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="pwa" value="1" />

                            {estado === 'sessao_expirada' && (
                                <div className="pwa-entra mb-4 rounded-lg bg-sky-50 border border-sky-200 p-3 text-xs text-sky-900">
                                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                                    {t('A sua sessão expirou. Entre com a palavra-passe para continuar — o que ficou por enviar continua guardado neste aparelho.')}
                                </div>
                            )}

                            <label htmlFor="entrada-email" className="block text-xs font-bold text-gray-600 mb-1">{t('Email')}</label>
                            <div className="relative mb-3">
                                <i className="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true" />
                                <input id="entrada-email" type="email" name="email" required autoComplete="username"
                                       value={email} onChange={(e) => setEmail(e.target.value)}
                                       className="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none transition"
                                       placeholder="seu@email.com" />
                            </div>

                            <label htmlFor="entrada-password" className="block text-xs font-bold text-gray-600 mb-1">{t('Palavra-passe')}</label>
                            {/* Sem estado: submete nativamente, e um segredo não se espelha em JS sem razão. */}
                            <div className="relative mb-4">
                                <i className="fas fa-key absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true" />
                                <input id="entrada-password" type="password" name="password" required autoComplete="current-password"
                                       className="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none transition"
                                       placeholder="••••••••" />
                            </div>

                            {erroDoServidor && <p role="alert" className="pwa-entra text-red-600 text-xs mb-3">{erroDoServidor}</p>}

                            <button type="submit" disabled={aEnviar}
                                    className="pwa-toque w-full bg-blue-700 hover:bg-blue-800 disabled:opacity-60 text-white font-bold py-2.5 rounded-lg text-sm shadow-lg shadow-blue-700/20">
                                <i className={`fas ${aEnviar ? 'fa-spinner fa-spin' : 'fa-right-to-bracket'} mr-1.5`} aria-hidden="true" />{t('Entrar')}
                            </button>

                            {/* Recurso para Wi-Fi sem internet e browsers que dizem sempre que há rede. */}
                            {temAcessoOffline && (
                                <button type="button" onClick={() => setUsarOffline(true)}
                                        className="pwa-toque w-full mt-3 border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold py-2.5 rounded-lg text-sm">
                                    <i className="fas fa-plane mr-1" aria-hidden="true" />{t('Entrar com PIN offline')}
                                </button>
                            )}
                        </form>
                    ) : !temAcessoOffline ? (
                        <div className="pwa-entra text-center py-4">
                            <i className="fas fa-wifi text-3xl text-amber-500 mb-2 pwa-flutua" aria-hidden="true" />
                            <p className="text-sm font-semibold text-gray-800">{t('Este aparelho ainda não sincronizou a empresa.')}</p>
                            <p className="text-xs text-gray-500 mt-1">{t('Ligue à internet e sincronize uma vez. Depois, qualquer funcionário com PIN entra sem rede.')}</p>
                            {janelaExpirada && (
                                <p className="text-xs text-amber-700 mt-2 font-semibold">{t('O acesso offline deste aparelho caducou. É preciso sincronizar de novo com internet.')}</p>
                            )}
                            {temRede && (
                                <button type="button" onClick={() => setUsarOffline(false)} className="w-full mt-3 text-xs text-blue-700 font-semibold underline">
                                    {t('Voltar ao login com internet')}
                                </button>
                            )}
                        </div>
                    ) : (
                        // Qualquer funcionário activo entra com o seu email e o PIN de turno.
                        <form onSubmit={(e) => void entrarLocal(e)} className="pwa-entra">
                            {estado === 'sessao_expirada' && (
                                <div className="mb-4 rounded-lg bg-sky-50 border border-sky-200 p-3 text-xs text-sky-900">
                                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                                    {t('Há rede: o PIN não chega para entrar. Volte atrás e use a palavra-passe.')}
                                    <button type="button" onClick={() => setUsarOffline(false)} className="block mt-2 font-bold underline">
                                        {t('Entrar com palavra-passe')}
                                    </button>
                                </div>
                            )}

                            <label htmlFor="entrada-email-local" className="block text-xs font-bold text-gray-600 mb-1">{t('Email')}</label>
                            <input id="entrada-email-local" type="email" required autoComplete="username"
                                   value={email} onChange={(e) => setEmail(e.target.value)}
                                   className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3 focus:border-amber-500 focus:ring-2 focus:ring-amber-100 focus:outline-none transition"
                                   placeholder="seu@email.com" />

                            <label htmlFor="entrada-pin" className="block text-xs font-bold text-gray-600 mb-1">{t('PIN de turno')}</label>
                            <input id="entrada-pin" type="password" inputMode="numeric" pattern="[0-9]*" maxLength={6} required autoComplete="off"
                                   value={pin} onChange={(e) => setPin(e.target.value)}
                                   className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-lg text-center tracking-[0.4em] mb-4 focus:border-amber-500 focus:ring-2 focus:ring-amber-100 focus:outline-none transition"
                                   placeholder="••••" />

                            {erro && <p role="alert" className="pwa-entra text-red-600 text-xs mb-3">{erro}</p>}

                            <button type="submit" disabled={ocupado}
                                    className="pwa-toque w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white font-bold py-2.5 rounded-lg text-sm shadow-lg shadow-amber-500/20">
                                {ocupado
                                    ? <><i className="fas fa-spinner fa-spin mr-1.5" aria-hidden="true" />{t('A verificar…')}</>
                                    : t('Entrar sem rede')}
                            </button>

                            {/* Esqueceu o PIN e não há rede: um gestor presente autoriza um novo. */}
                            <a href={`${rotas.pinEsquecido}?voltar=${encodeURIComponent(rotas.entrada)}`}
                               className="block w-full mt-3 text-center text-xs text-amber-800 font-semibold underline">
                                {t('Esqueci o PIN')}
                            </a>

                            <p className="text-[11px] text-gray-400 mt-3 leading-snug">
                                {t('O PIN define-se com internet, em "PIN de turno". As vendas ficam em fila e sobem ao servidor assim que houver rede.')}
                            </p>
                            {temRede && (
                                <button type="button" onClick={() => setUsarOffline(false)} className="w-full mt-3 text-xs text-blue-700 font-semibold underline">
                                    {t('Voltar ao login com internet')}
                                </button>
                            )}
                        </form>
                    )}
                </div>

                <p className="text-center text-blue-200 text-[11px] mt-4 min-h-[16px]">
                    {pendentes > 0 && (
                        <span className="pwa-entra">
                            <i className="fas fa-clock mr-1" aria-hidden="true" />{pendentes} {t('venda(s) por enviar neste aparelho')}
                        </span>
                    )}
                </p>
            </div>
            <Dialogos />
        </div>
    );
}

