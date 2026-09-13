import { t, tn } from '@/i18n';

import { dataCurta, dataEHora, hora, kz, useEstadoDoMotor } from '../../ganchos';
import { Folha } from '../../ui/Folha';
import { Camada } from './Camada';
import type { ControloDoPos } from './usePos';

/**
 * A GAVETA DOS DOCUMENTOS DO APARELHO — as vendas por enviar, as enviadas, e os
 * trabalhos que o servidor recusou (com o «tentar outra vez»).
 */
export function GavetaDePendentes({ pos }: { pos: ControloDoPos }) {
    const motor = useEstadoDoMotor();
    const fechar = () => pos.setMostrarPendentes(false);
    const ultima = pos.ultimaSync;
    // A data mantém o formato da casa; o que se traduz é a frase à volta.
    const rotuloDaSync = ultima ? t('Sync: :quando', { quando: `${dataCurta(ultima)} ${hora(ultima)}` }) : t('Nunca sincronizado');
    const aSincronizar = pos.aSincronizar || motor.syncing;

    return (
        <Camada>
            <Folha aberta={pos.mostrarPendentes} aoFechar={fechar} titulo={t('Documentos Offline')} icone="fa-clock"
                   cor="from-amber-500 to-orange-600" zIndex="z-[60]" largura="sm:max-w-md"
                   subtitulo={pos.turno.pendentes > 0 ? tn(':n documento por sincronizar|:n documentos por sincronizar', pos.turno.pendentes, { n: pos.turno.pendentes }) : undefined}>
                <div className="-m-5">
                    <div className="px-4 py-3 border-b text-xs text-gray-600 flex items-center justify-between gap-2 bg-slate-50">
                        <span className="truncate"><i className="fas fa-clock-rotate-left mr-1 text-gray-400" aria-hidden="true" />{rotuloDaSync}</span>
                        <button type="button" onClick={() => void pos.sincronizarAgora()} disabled={aSincronizar || !motor.online}
                                className="pwa-toque bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1.5 rounded-lg text-[10px] font-bold whitespace-nowrap shrink-0 disabled:opacity-60">
                            <i className={`fas fa-rotate mr-1 ${aSincronizar ? 'fa-spin' : ''}`} aria-hidden="true" />{t('Sincronizar')}
                        </button>
                    </div>

                    {/* Trabalhos falhados */}
                    {pos.falhados.length > 0 && (
                        <div className="border-b pwa-entra">
                            <div className="px-4 py-2 bg-red-50 flex items-center justify-between gap-2">
                                {/* Em português o plural não se vê aqui, mas em EN e FR vê-se
                                    («1 with a permanent error» / «3 with permanent errors»):
                                    por isso vai por tn com as duas formas iguais em PT. */}
                                <span className="text-xs font-bold text-red-700">
                                    <i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />
                                    {tn(':n com erro permanente|:n com erro permanente', pos.falhados.length, { n: pos.falhados.length })}
                                </span>
                                <button type="button" onClick={() => void pos.repetirTodos()}
                                        className="pwa-toque text-[10px] bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded-lg font-bold">
                                    <i className="fas fa-rotate-right mr-1" aria-hidden="true" />{t('Tentar todos')}
                                </button>
                            </div>
                            <div className="divide-y max-h-40 overflow-y-auto">
                                {pos.falhados.map((j) => (
                                    <div key={j.id ?? `${j.op}-${j.created_at}`} className="px-4 py-2 hover:bg-red-50 transition-colors">
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="text-xs font-bold text-red-700 truncate font-mono">{j.op}</p>
                                                <p className="text-[10px] text-red-500 truncate" title={j.last_error || undefined}>{j.last_error || t('Erro desconhecido')}</p>
                                            </div>
                                            {j.id != null && (
                                                <button type="button" onClick={() => void pos.repetirTrabalho(j.id as number)}
                                                        className="pwa-toque shrink-0 text-[10px] bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded-lg font-bold">
                                                    {t('Retry')}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="divide-y">
                        {pos.vendas.map((v) => {
                            const sincronizada = !!v._synced;

                            return (
                                <div key={String(v.local_uuid)} className="p-4 hover:bg-gray-50 transition-colors">
                                    <div className="flex items-start justify-between mb-1 gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold text-sm truncate font-mono">{String((sincronizada ? v._server_number : v.provisional_number) || v.provisional_number || '')}</p>
                                            <p className="text-xs text-gray-500 truncate">{String(v.client_name ?? '')} · {dataEHora(v.created_at)}</p>
                                        </div>
                                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap ${sincronizada ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                            <i className={`fas ${sincronizada ? 'fa-check' : 'fa-hourglass-half'} mr-1`} aria-hidden="true" />
                                            {sincronizada ? t('Sincronizada') : t('Pendente')}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-emerald-700 font-bold text-sm tabular-nums">{kz(v.total)}</p>
                                        <div className="flex gap-1.5">
                                            <button type="button" onClick={() => void pos.partilhar(v)} disabled={pos.aPartilhar}
                                                    className="pwa-toque text-xs bg-teal-600 hover:bg-teal-700 text-white px-2.5 py-1 rounded-lg font-bold disabled:opacity-60">
                                                <i className="fas fa-file-pdf mr-1" aria-hidden="true" />{t('PDF')}
                                            </button>
                                            <button type="button" onClick={() => void pos.reimprimir(v)}
                                                    className="pwa-toque text-xs bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded-lg font-bold">
                                                <i className="fas fa-print mr-1" aria-hidden="true" />{t('Reimprimir')}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}

                        {!pos.vendas.length && !pos.falhados.length && (
                            <div className="p-8 text-center text-gray-400 text-sm italic">
                                <i className="fas fa-inbox block text-4xl mb-2 opacity-40 not-italic pwa-flutua" aria-hidden="true" />
                                {t('Sem documentos registados')}
                            </div>
                        )}
                    </div>
                </div>
            </Folha>
        </Camada>
    );
}
