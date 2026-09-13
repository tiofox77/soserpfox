import { useEffect, useState } from 'react';

import { t, tn } from '@/i18n';

import { dataEHora, useAccao, useBaseViva, useEstadoDoMotor } from '../../ganchos';
import { getQueue, retryFailedJob, type ItemDaFila } from '../../motor/fila';
import { sync } from '../../motor/sincronizar';
import { motivoDoServidor } from '../../motor/util';
import { avisar } from '../../ui/Dialogos';

/**
 * «POR ENVIAR» — a fila do aparelho, em linguagem de quem está ao balcão.
 *
 * Contar não chega: quem está à caixa precisa de ver O QUÊ ficou preso. «3 por
 * enviar» não se distingue de «3 perdidos».
 *
 * A fila é uma consulta viva: quando a sincronização (a do cabeçalho, a
 * automática ou a deste botão) entrega um trabalho, ele sai da lista sozinho.
 */
export function PorEnviar() {
    const fila = useBaseViva<ItemDaFila[] | null>(() => getQueue(), [], null);
    const motor = useEstadoDoMotor();
    const [tudoEnviado, setTudoEnviado] = useState(false);
    const [aRepetir, setARepetir] = useState<number | null>(null);

    // «Está tudo enviado.» fica uns segundos depois de «Enviar agora» esvaziar a
    // fila — no Blade a frase existia mas o cartão escondia-se antes de a mostrar.
    useEffect(() => {
        if (!tudoEnviado) return;
        const id = setTimeout(() => setTudoEnviado(false), 4000);

        return () => clearTimeout(id);
    }, [tudoEnviado]);

    const [enviarAgora, aEnviar] = useAccao(async () => {
        // O Blade carregava e não acontecia nada sem rede: a sincronização sai
        // logo sem dizer porquê, e o botão parecia avariado.
        if (!navigator.onLine) {
            avisar(t('Sem internet — sync adiada'), 'aviso');
            return;
        }

        try {
            await sync(true);
        } catch {
            // A sincronização trata e mostra os próprios erros (faixa do estado).
        }

        if (!(await getQueue()).length) setTudoEnviado(true);
    });

    const repetir = async (id: number | undefined) => {
        if (id === undefined || aRepetir !== null) return;
        // Um trabalho com erro fica parado de propósito, para não repetir um
        // pedido que talvez tenha chegado. Quem decide repetir é quem está à
        // caixa e sabe se a venda saiu.
        setARepetir(id);
        try {
            await retryFailedJob(id);
        } catch (err) {
            avisar((err as Error)?.message || t('Erro inesperado'), 'erro');
        } finally {
            setARepetir(null);
        }
    };

    if (!fila || (!fila.length && !tudoEnviado)) return null;

    const comErro = fila.filter((j) => j.estado === 'failed').length;
    const ocupado = aEnviar || motor.syncing;

    // Os nomes das operações vêm do motor em português; aqui passam pelo tradutor.
    const TIPOS: Record<string, string> = {
        Venda: t('Venda'),
        Cliente: t('Cliente'),
        Documento: t('Documento'),
        'Abertura de turno': t('Abertura de turno'),
        'Fecho de turno': t('Fecho de turno'),
        Comanda: t('Comanda'),
        'Saída de sessão': t('Saída de sessão'),
    };

    return (
        <section className={`pwa-entra bg-white rounded-2xl shadow-sm p-4 mb-4 border ${comErro ? 'border-red-200' : 'border-amber-100'}`}
                 aria-labelledby="inicio-por-enviar">
            <div className="flex items-center justify-between gap-2 mb-3">
                <h2 id="inicio-por-enviar" className="text-sm font-bold text-gray-800 flex items-center gap-1.5 min-w-0">
                    <i className={`fas fa-paper-plane text-blue-600 ${ocupado ? 'animate-pulse' : ''}`} aria-hidden="true" />
                    {t('Por enviar')}
                    {fila.length > 0 && (
                        <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${comErro ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>
                            {fila.length}
                        </span>
                    )}
                </h2>
                {fila.length > 0 && (
                    <button type="button" onClick={() => void enviarAgora()} disabled={ocupado}
                            className="pwa-toque shrink-0 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-xs font-bold shadow-sm">
                        {ocupado
                            ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A enviar…')}</>
                            : <><i className="fas fa-cloud-arrow-up mr-1" aria-hidden="true" />{t('Enviar agora')}</>}
                    </button>
                )}
            </div>

            {/* A lista rola por dentro: com muitas coisas presas, o «Vender» não
                pode ser empurrado para fora do primeiro ecrã. */}
            {fila.length > 0 && (
                <ul className="divide-y divide-gray-100 max-h-72 overflow-y-auto overscroll-contain -mx-1 px-1">
                    {fila.map((j) => {
                        const falhou = j.estado === 'failed';
                        const motivo = j.erro ? (motivoDoServidor(j.erro) || j.erro) : null;

                        return (
                            <li key={j.id ?? `${j.tipo}-${j.quando}`} className="pwa-entra py-2">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex items-start gap-2">
                                        <span className={`mt-0.5 w-7 h-7 shrink-0 rounded-lg flex items-center justify-center text-[11px] ${falhou ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'}`}>
                                            <i className={`fas ${falhou ? 'fa-triangle-exclamation' : 'fa-clock'}`} aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-xs font-semibold text-gray-800">{TIPOS[j.tipo] ?? j.tipo}</p>
                                            {j.detalhe && <p className="text-[11px] text-gray-500 truncate">{j.detalhe}</p>}
                                            <p className="text-[10px] text-gray-400">{dataEHora(j.quando)}</p>
                                        </div>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${falhou ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>
                                            {falhou ? t('com erro') : t('à espera')}
                                        </span>
                                        {j.tentativas > 0 && (
                                            <p className="text-[10px] text-gray-400 mt-0.5">
                                                {tn(':n tentativa|:n tentativas', j.tentativas, { n: j.tentativas })}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {motivo && (
                                    <div className="mt-1 ml-9 flex items-center justify-between gap-2">
                                        <p className="text-[10px] text-red-600 truncate" title={j.erro ?? undefined}>{motivo}</p>
                                        <button type="button" onClick={() => void repetir(j.id)} disabled={aRepetir !== null}
                                                className="pwa-toque shrink-0 text-[10px] font-bold text-blue-700 hover:underline disabled:opacity-50">
                                            <i className={`fas ${aRepetir === j.id ? 'fa-spinner fa-spin' : 'fa-rotate-right'} mr-1`} aria-hidden="true" />
                                            {t('Repetir')}
                                        </button>
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {!fila.length && (
                <p className="pwa-entra text-xs text-emerald-600 font-semibold text-center py-2">
                    <i className="fas fa-circle-check mr-1" aria-hidden="true" />{t('Está tudo enviado.')}
                </p>
            )}
        </section>
    );
}
