import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { useAccao, useEstadoDoMotor, useRelogio } from '../ganchos';
import { sair } from '../motor/acesso';
import { promptInstall } from '../motor/instalar';
import { sync } from '../motor/sincronizar';
import { confirmar } from '../ui/Dialogos';

/** «· sync há 3 min» — o número entra por `:n`, que noutras línguas não fica no meio da frase. */
export function textoDaUltimaSync(lastSync: string | null, agora = Date.now()): string | null {
    if (!lastSync) return null;
    const quando = new Date(lastSync).getTime();
    if (Number.isNaN(quando)) return null;
    const diff = Math.round((agora - quando) / 60000);

    if (diff < 1) return t('· sync agora');
    if (diff < 60) return t('· sync há :n min', { n: diff });
    if (diff < 1440) return t('· sync há :n h', { n: Math.round(diff / 60) });

    return t('· sync há :n d', { n: Math.round(diff / 1440) });
}

export function Cabecalho() {
    const { rotas, versao } = usePwa();
    const e = useEstadoDoMotor();
    const agora = useRelogio(30);
    const ultima = textoDaUltimaSync(e.lastSync, agora);

    /*
     * SAIR É SEMPRE LOCAL PRIMEIRO.
     *
     * Só tomava o caminho local quando `navigator.onLine` era falso — e isso só
     * diz que há placa de rede. Com Wi-Fi sem saída o formulário ia ao servidor
     * e ficava pendurado: quem carregava em Sair não saía, e a caixa seguinte
     * não conseguia entrar. A saída acontece no aparelho e é comunicada ao
     * servidor quando der (a fila leva-a). A base local não se toca.
     */
    const [sairJa, aSair] = useAccao(async () => {
        if (!(await confirmar(t('Sair da conta?'), {
            texto: t('Sair da conta? As vendas por enviar ficam guardadas neste aparelho.'),
            sim: t('Sair'),
            icone: 'fa-arrow-right-from-bracket',
        }))) return;

        try { await sair(); } catch { /* falhar a comunicar não pode impedir a saída */ }
        window.location.href = rotas.entrada;
    });

    return (
        <header className="bg-gradient-to-r from-blue-700 to-blue-800 text-white shadow-lg sticky top-0 z-40">
            <div className="px-4 py-3 flex items-center justify-between gap-2">
                <a href={rotas.inicio} className="flex items-center gap-2 min-w-0 flex-1 group">
                    {/* O ícone do PWA e não o logótipo da empresa: este está
                        pré-guardado e aparece sempre, inclusive sem rede. */}
                    <span className="w-9 h-9 shrink-0 bg-white rounded-lg flex items-center justify-center overflow-hidden shadow-sm transition group-hover:scale-105">
                        <img src="/pwa/icon-192x192.png" alt="SOS ERP" className="w-7 h-7 object-contain" draggable={false} />
                    </span>
                    <div className="min-w-0">
                        <p className="font-bold text-sm leading-tight">{t('PWA Faturação')}</p>
                        {/* A VERSÃO CABE NUMA LINHA, e é a DATA que se lê: responde a
                            «já tenho a correcção de hoje?». O número só no ecrã largo. */}
                        <p className="text-xs opacity-75 leading-tight truncate"
                              title={t('Versão :v · assinatura :h', { v: versao.numero, h: versao.assinatura })}>
                            <span className="font-mono hidden sm:inline">v{versao.numero} · </span>
                            <span className="font-semibold">{versao.etiqueta}</span>
                            {ultima && <span id="pwa-last-sync-badge" className="ml-1 font-normal">{ultima}</span>}
                        </p>
                    </div>
                </a>

                <div className="flex items-center gap-2 shrink-0">
                    {e.instalavel && (
                        <button type="button" onClick={() => void promptInstall()} title={t('Instalar aplicação')}
                                className="pwa-toque px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-amber-950 rounded-lg text-xs font-bold">
                            <i className="fas fa-download mr-1" aria-hidden="true" />{t('Instalar')}
                        </button>
                    )}
                    <button type="button" id="pwa-sync-btn" onClick={() => void sync(true)} disabled={e.syncing}
                            title={t('Sincronizar agora')} aria-label={t('Sincronizar agora')}
                            className="pwa-toque px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-lg text-xs font-semibold disabled:opacity-60">
                        <i className={`fas fa-rotate ${e.syncing ? 'fa-spin' : ''}`} aria-hidden="true" />
                    </button>
                    <button type="button" onClick={() => void sairJa()} disabled={aSair}
                            className="pwa-toque px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-lg text-xs font-semibold">
                        <i className={`fas ${aSair ? 'fa-spinner fa-spin' : 'fa-arrow-right-from-bracket'} mr-1`} aria-hidden="true" />{t('Sair')}
                    </button>
                    <a href={rotas.aplicacao} title={t('Ir para a aplicação completa')} aria-label={t('Ir para a aplicação completa')}
                       className="pwa-toque px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-xs font-semibold">
                        <i className="fas fa-up-right-from-square" aria-hidden="true" />
                    </a>
                </div>
            </div>
        </header>
    );
}
