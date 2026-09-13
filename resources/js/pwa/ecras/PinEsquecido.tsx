import { useEffect, useState } from 'react';

import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { getOfflineAuthInfo, reporPinOffline, type MotivoDeRecusa } from '../motor/acesso';
import { estadoDaLigacao, type EstadoDaLigacao } from '../motor/rede';
import { Dialogos } from '../ui/Dialogos';

/** O logótipo e o título das duas páginas públicas. */
export function MarcaDaEntrada({ titulo, subtitulo }: { titulo: string; subtitulo: string }) {
    return (
        <div className="text-center mb-6 pwa-entra">
            {/* O ícone do PWA e não o do tenant: está pré-guardado e aparece sem rede. */}
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/15 mb-3 shadow-lg pwa-flutua">
                <img src="/pwa/icon-192x192.png" alt="SOS ERP" className="w-11 h-11 object-contain" draggable={false} />
            </div>
            <h1 className="text-white text-xl font-bold">{titulo}</h1>
            <p className="text-blue-200 text-sm">{subtitulo}</p>
        </div>
    );
}

function Passo({ n, cor = 'bg-blue-700', children }: { n: number; cor?: string; children: string }) {
    return (
        <p className="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-2 flex items-center">
            <span className={`inline-flex items-center justify-center w-5 h-5 rounded-full ${cor} text-white text-[10px] mr-1.5`}>{n}</span>
            {children}
        </p>
    );
}

const CAMPO_PIN = 'w-full px-3 py-2.5 border border-gray-300 rounded-lg text-lg text-center focus:border-amber-500 focus:ring-2 focus:ring-amber-100 focus:outline-none transition';
const CAMPO = 'w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none transition';

/**
 * ESQUECI O PIN — sem rede.
 *
 * O PIN de turno é um bcrypt: não se «acha», só se repõe. Um GESTOR PRESENTE
 * autoriza um PIN novo ali mesmo: põe o seu email e o seu PIN (conferido
 * contra o verificador que o aparelho já tem), o funcionário escolhe o novo, e
 * o aparelho calcula o bcrypt e guarda-o. Entra-se de imediato. A reposição vai
 * na fila e o servidor decide quando houver rede: se recusar, a sincronização
 * seguinte repõe o PIN antigo.
 */
export function PinEsquecido() {
    const { rotas } = usePwa();

    const [pronto, setPronto] = useState(false);
    const [estado, setEstado] = useState<EstadoDaLigacao>(navigator.onLine ? 'online' : 'offline');
    const [temAcessoOffline, setTemAcessoOffline] = useState(false);
    const [janelaExpirada, setJanelaExpirada] = useState(false);
    const [email, setEmail] = useState('');
    const [gestorEmail, setGestorEmail] = useState('');
    const [gestorPin, setGestorPin] = useState('');
    const [pinNovo, setPinNovo] = useState('');
    const [pinNovo2, setPinNovo2] = useState('');
    const [erro, setErro] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [feito, setFeito] = useState(false);
    const [nome, setNome] = useState('');
    const [abanar, setAbanar] = useState(0);

    // Só se volta para dentro do próprio site: um `voltar` para fora seria uma
    // porta aberta a quem quisesse desviar o operador.
    const pedido = new URLSearchParams(window.location.search).get('voltar') || '';
    const voltar = pedido.startsWith('/') && !pedido.startsWith('//') ? pedido : rotas.entrada;

    useEffect(() => {
        let vivo = true;
        void (async () => {
            const e = await estadoDaLigacao();
            try {
                const info = await getOfflineAuthInfo();
                if (!vivo) return;
                setTemAcessoOffline(info.employees > 0 && !info.window_expired);
                setJanelaExpirada(info.employees > 0 && info.window_expired);
            } catch { /* sem base */ }
            if (!vivo) return;
            setEstado(e);
            setPronto(true);
        })();

        return () => { vivo = false; };
    }, []);

    const MSG: Partial<Record<MotivoDeRecusa | 'DIFERENTES' | 'FALHOU', string>> = {
        MISSING: t('Preencha todos os campos.'),
        NO_ENGINE: t('O motor offline ainda não carregou. Tente outra vez.'),
        EXPIRED_WINDOW: t('O acesso offline deste aparelho expirou. É preciso entrar uma vez com ligação.'),
        ALVO_DESCONHECIDO: t('Este email não está sincronizado neste aparelho. Só quem já tinha PIN pode repô-lo aqui.'),
        BAD_PIN: t('Email ou PIN do gestor que não conferem.'),
        GESTOR_SEM_DIREITO: t('Esta conta não pode autorizar: só quem gere utilizadores.'),
        TAMANHO: t('O PIN tem de ter 4 a 6 dígitos.'),
        OBVIO: t('Escolha um PIN menos óbvio.'),
        DIFERENTES: t('Os dois PIN não coincidem.'),
        FALHOU: t('Não foi possível repor neste aparelho.'),
    };

    const repor = async (e: React.FormEvent) => {
        e.preventDefault();
        if (ocupado) return;
        setErro('');

        if (pinNovo !== pinNovo2) {
            setErro(MSG.DIFERENTES!);
            setAbanar((n) => n + 1);
            return;
        }

        setOcupado(true);
        try {
            const r = await reporPinOffline({ email, gestorEmail, gestorPin, pinNovo });

            if (!r.ok) {
                if (r.reason === 'LOCKED') {
                    const seg = Math.max(1, Math.ceil(((r.until ?? Date.now()) - Date.now()) / 1000));
                    setErro(t('Demasiadas tentativas. Espere :seg segundos.', { seg }));
                } else if (r.reason === 'EXPIRED_WINDOW') {
                    setErro(MSG.EXPIRED_WINDOW!);
                    setTemAcessoOffline(false);
                    setJanelaExpirada(true);
                } else {
                    setErro(MSG[r.reason] || MSG.FALHOU!);
                }
                setAbanar((n) => n + 1);
                return;
            }

            setNome(r.name || email);
            setFeito(true);
        } catch {
            setErro(MSG.FALHOU!);
        } finally {
            setOcupado(false);
            setGestorPin('');
            setPinNovo('');
            setPinNovo2('');
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center p-4">
            <div className="w-full max-w-sm">
                <MarcaDaEntrada titulo={t('Esqueci o PIN')} subtitulo={t('Um gestor presente autoriza um PIN novo')} />

                <div key={abanar} className={`pwa-cresce bg-white rounded-2xl shadow-2xl p-6 ${abanar ? 'pwa-abana' : ''}`}>
                    {!pronto && (
                        <div className="py-8 text-center text-gray-400"><i className="fas fa-spinner fa-spin text-2xl" aria-hidden="true" /></div>
                    )}

                    {/* Este aparelho nunca sincronizou: não há verificadores para conferir ninguém. */}
                    {pronto && !temAcessoOffline && !feito && (
                        <div className="pwa-entra text-center py-4">
                            <i className="fas fa-wifi text-3xl text-amber-500 mb-2 pwa-flutua" aria-hidden="true" />
                            <p className="text-sm font-semibold text-gray-800">{t('Este aparelho ainda não sincronizou a empresa.')}</p>
                            <p className="text-xs text-gray-500 mt-1">{t('Ligue à internet e sincronize uma vez. Depois, qualquer funcionário com PIN entra sem rede.')}</p>
                            {janelaExpirada && (
                                <p className="text-xs text-amber-700 mt-2 font-semibold">{t('O acesso offline deste aparelho caducou. É preciso sincronizar de novo com internet.')}</p>
                            )}
                        </div>
                    )}

                    {/* Feito: o PIN novo já serve neste aparelho. */}
                    {feito && (
                        <div className="pwa-entra text-center py-2">
                            <div className="pwa-cresce w-14 h-14 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-3">
                                <i className="fas fa-check text-emerald-600 text-2xl" aria-hidden="true" />
                            </div>
                            <p className="text-sm font-semibold text-gray-800">
                                {t('PIN reposto para')} <span>{nome}</span>.
                            </p>
                            <p className="text-xs text-gray-500 mt-2 leading-snug">
                                {t('Já serve neste aparelho. Chega ao servidor e aos outros aparelhos na próxima sincronização com internet.')}
                            </p>
                            <button type="button" onClick={() => { window.location.href = voltar; }}
                                    className="pwa-toque w-full mt-4 bg-blue-700 hover:bg-blue-800 text-white font-bold py-2.5 rounded-lg text-sm">
                                {t('Entrar com o PIN novo')}
                            </button>
                        </div>
                    )}

                    {pronto && temAcessoOffline && !feito && (
                        <form onSubmit={(e) => void repor(e)} className="pwa-entra space-y-4">
                            <div>
                                <Passo n={1}>{t('Quem esqueceu o PIN')}</Passo>
                                <label htmlFor="pin-email" className="block text-xs font-bold text-gray-600 mb-1">{t('Email')}</label>
                                <input id="pin-email" type="email" required autoComplete="username" value={email}
                                       onChange={(e) => setEmail(e.target.value)} className={CAMPO} placeholder="seu@email.com" />
                            </div>

                            <div className="rounded-lg bg-amber-50 border border-amber-200 p-3">
                                <Passo n={2} cor="bg-amber-500">{t('O gestor autoriza')}</Passo>
                                <label htmlFor="pin-gestor-email" className="block text-xs font-bold text-gray-600 mb-1">{t('Email do gestor')}</label>
                                <input id="pin-gestor-email" type="email" required autoComplete="off" value={gestorEmail}
                                       onChange={(e) => setGestorEmail(e.target.value)} className={`${CAMPO} mb-2`} placeholder="gestor@email.com" />
                                <label htmlFor="pin-gestor-pin" className="block text-xs font-bold text-gray-600 mb-1">{t('PIN do gestor')}</label>
                                <input id="pin-gestor-pin" type="password" inputMode="numeric" pattern="[0-9]*" maxLength={6} required autoComplete="off"
                                       value={gestorPin} onChange={(e) => setGestorPin(e.target.value)}
                                       className={`${CAMPO_PIN} tracking-[0.4em]`} placeholder="••••" />
                            </div>

                            <div>
                                <Passo n={3}>{t('O PIN novo')}</Passo>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label htmlFor="pin-novo" className="block text-xs font-bold text-gray-600 mb-1">{t('PIN novo')}</label>
                                        <input id="pin-novo" type="password" inputMode="numeric" pattern="[0-9]*" maxLength={6} required autoComplete="new-password"
                                               value={pinNovo} onChange={(e) => setPinNovo(e.target.value)}
                                               className={`${CAMPO_PIN} tracking-[0.3em]`} placeholder="••••" />
                                    </div>
                                    <div>
                                        <label htmlFor="pin-novo-2" className="block text-xs font-bold text-gray-600 mb-1">{t('Repetir')}</label>
                                        <input id="pin-novo-2" type="password" inputMode="numeric" pattern="[0-9]*" maxLength={6} required autoComplete="new-password"
                                               value={pinNovo2} onChange={(e) => setPinNovo2(e.target.value)}
                                               className={`${CAMPO_PIN} tracking-[0.3em]`} placeholder="••••" />
                                    </div>
                                </div>
                                <p className="text-[11px] text-gray-400 mt-1">{t('4 a 6 dígitos. Nada de 1234, datas ou dígitos repetidos.')}</p>
                            </div>

                            {erro && <p role="alert" className="pwa-entra text-red-600 text-xs">{erro}</p>}

                            <button type="submit" disabled={ocupado}
                                    className="pwa-toque w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white font-bold py-2.5 rounded-lg text-sm shadow-lg shadow-amber-500/20">
                                {ocupado
                                    ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A calcular…')}</>
                                    : <><i className="fas fa-key mr-1.5" aria-hidden="true" />{t('Repor o PIN')}</>}
                            </button>

                            {estado === 'online' && (
                                <p className="text-[11px] text-gray-400 leading-snug">
                                    {t('Há rede: também pode definir o PIN em Utilizadores, ou em "PIN de turno" com a sua palavra-passe.')}
                                </p>
                            )}
                        </form>
                    )}

                    <a href={voltar} className="block mt-4 text-center text-xs text-blue-700 font-semibold underline">
                        <i className="fas fa-arrow-left mr-1" aria-hidden="true" />{t('Voltar')}
                    </a>
                </div>
            </div>
            <Dialogos />
        </div>
    );
}
