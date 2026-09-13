import { useQuery } from '@tanstack/react-query';

import { casca } from '@/api/casca';
import { t } from '@/i18n';
import { cascata } from '@/ui/SemNada';
import { cls } from '@/ui/tokens';

import { tom } from './comum';

/**
 * O PAINEL DOS AVISOS DA PLATAFORMA, no ecrã de entrada.
 *
 * A barra do topo é passageira: aparece, dispensa-se, e some. Isto é o outro
 * lado — fica quieto, não interrompe, e mostra TUDO o que está no ar para esta
 * empresa, incluindo o que já foi dispensado (marcado como lido). É o sítio a
 * que se volta para reler o número do suporte.
 *
 * Sem avisos não se desenha: uma caixa a dizer «sem avisos» em quase todos os
 * dias só ensina a não olhar para ali.
 */
export default function Avisos() {
    const pedido = useQuery({ queryKey: ['casca', 'avisos'], queryFn: casca.avisos, staleTime: 60_000 });
    const avisos = pedido.data?.avisos ?? [];

    if (avisos.length === 0) return null;

    return (
        <section className="animate-fade-in mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-lg">
            <header className="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15">
                    <i className="fas fa-bullhorn icon-float text-white" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <h3 className="font-bold leading-tight text-white">{t('Avisos da plataforma')}</h3>
                    <p className="text-xs text-indigo-100">{t('Comunicações da equipa SOS ERP')}</p>
                </div>
                <span className="ml-auto shrink-0 rounded-full bg-white/20 px-3 py-1 text-xs font-bold text-white">{avisos.length}</span>
            </header>

            <div className="divide-y divide-gray-100">
                {avisos.map((a, i) => {
                    const c = tom(a.cor);

                    return (
                        <div key={a.id} className={cls('cascata flex items-start gap-4 px-6 py-4', a.dispensada && 'bg-gray-50/60')} style={cascata(i)}>
                            <span className={cls('flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', c.suave, a.dispensada && 'opacity-60')}>
                                <i className={cls('fas', a.icone, c.icone)} aria-hidden="true" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className={cls('font-bold', a.dispensada ? 'text-gray-600' : 'text-gray-900')}>{a.titulo}</p>
                                    {a.dispensada && <span className="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold text-gray-500">{t('Lido')}</span>}
                                </div>
                                <p className={cls('mt-1 whitespace-pre-line text-sm', a.dispensada ? 'text-gray-500' : 'text-gray-700')}>{a.corpo}</p>
                                <div className="mt-2 flex flex-wrap items-center gap-4">
                                    {a.ligacao && (
                                        <a href={a.ligacao} target="_blank" rel="noopener noreferrer" className="text-sm font-semibold text-indigo-600 underline hover:text-indigo-800">
                                            {a.texto_da_ligacao || t('Saber mais')}
                                        </a>
                                    )}
                                    {a.termina && <span className="text-xs text-gray-400"><i className="fas fa-clock mr-1" aria-hidden="true" />{t('até')} {a.termina}</span>}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
