import { t, tn } from '@/i18n';

import { hora, kz, useBaseViva, useRelogio } from '../../ganchos';
import { getPosSales } from '../../motor/vendas';
import { dataDeHoje } from '../../motor/util';

interface VendaDoDia {
    chave: string;
    numero: string;
    emitida: boolean;
    cliente: string;
    total: number;
    hora: string;
}

interface ResumoDoDia {
    total: number;
    porEmitir: number;
    valor: number;
    ultimas: VendaDoDia[];
}

/** Quantas se mostram na lista. O valor e as contagens são do dia inteiro. */
const NA_LISTA = 8;

/**
 * AS FACTURAS VENDIDAS HOJE.
 *
 * Quem está ao balcão quer saber o que já vendeu e se está tudo entregue ao
 * servidor — não o número de artigos no catálogo.
 *
 * «HOJE» É O DO RELÓGIO DE QUEM VENDE. O Blade comparava o `created_at` (ISO em
 * UTC) com `new Date().toISOString().slice(0, 10)` — dois UTC, e Angola está
 * uma hora à frente: entre a meia-noite e a uma da manhã as vendas do dia
 * apareciam como de ontem, e as de ontem às 23:30 como de hoje. A hora na lista
 * saía também em UTC (`slice(11, 16)`). Agora cada venda passa pelo relógio
 * local (`dataDeHoje(new Date(created_at))`), e o dia muda sozinho à
 * meia-noite com o ecrã aberto.
 */
export function FacturasDeHoje() {
    const agora = useRelogio(60);
    const hoje = dataDeHoje(new Date(agora));

    const vendas = useBaseViva<ResumoDoDia | null>(async () => {
        const doDia = (await getPosSales()).filter((v) => {
            const quando = new Date(String(v.created_at || ''));

            return !Number.isNaN(quando.getTime()) && dataDeHoje(quando) === hoje;
        });

        return {
            total: doDia.length,
            porEmitir: doDia.filter((v) => !v._synced).length,
            valor: doDia.reduce((s, v) => s + (Number(v.total) || 0), 0),
            ultimas: doDia.slice(0, NA_LISTA).map((v) => ({
                chave: String(v.local_uuid),
                numero: String((v._synced ? v._server_number : null) || v.provisional_number || '—'),
                emitida: !!v._synced,
                // O motor grava «Consumidor Final» em português; mostra-se na língua de quem lê.
                cliente: v.client_name && v.client_name !== 'Consumidor Final' ? String(v.client_name) : t('Consumidor Final'),
                total: Number(v.total) || 0,
                hora: hora(v.created_at),
            })),
        };
    }, [hoje], null);

    const emitidas = vendas ? vendas.total - vendas.porEmitir : 0;
    const resto = vendas ? vendas.total - vendas.ultimas.length : 0;

    return (
        <section className="pwa-entra bg-white rounded-2xl shadow-sm p-4 mb-4" style={{ animationDelay: '230ms' }} aria-labelledby="inicio-facturas-de-hoje">
            <div className="flex items-center justify-between gap-2 mb-3">
                <h2 id="inicio-facturas-de-hoje" className="text-sm font-bold text-gray-800">
                    <i className="fas fa-receipt text-emerald-600 mr-1" aria-hidden="true" />{t('Facturas de hoje')}
                </h2>
                <span className="text-sm font-bold text-emerald-700 tabular-nums">{vendas ? kz(vendas.valor) : '—'}</span>
            </div>

            {/* O DIA VAZIO OCUPA UMA LINHA, não meio ecrã.

                Estava aqui uma caixa verde grande com «EMITIDAS 0» e, logo por
                baixo, «Ainda não há vendas hoje» — duas maneiras de dizer o mesmo
                nada, e a ocupar mais espaço do que um dia cheio de vendas. De
                manhã, que é quando isto se abre, era o maior elemento do ecrã. */}
            {vendas && vendas.total > 0 && (
                <div className="flex gap-2 mb-3">
                    <div className="flex-1 bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl px-3 py-2">
                        <p className="text-[10px] uppercase font-bold text-emerald-700">
                            <i className="fas fa-circle-check mr-1" aria-hidden="true" />{t('Emitidas')}
                        </p>
                        <p className="text-lg font-bold text-emerald-800 tabular-nums">{emitidas}</p>
                    </div>
                    {/* Só aparece quando há alguma por emitir: um zero permanente a
                        vermelho ensina o operador a ignorar o aviso. */}
                    {vendas.porEmitir > 0 && (
                        <div className="pwa-entra flex-1 bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl px-3 py-2">
                            <p className="text-[10px] uppercase font-bold text-amber-700">
                                <i className="fas fa-hourglass-half mr-1" aria-hidden="true" />{t('Por emitir')}
                            </p>
                            <p className="text-lg font-bold text-amber-800 tabular-nums">{vendas.porEmitir}</p>
                        </div>
                    )}
                </div>
            )}

            {vendas && !vendas.ultimas.length && (
                <p className="text-xs text-gray-400">
                    <i className="fas fa-mug-hot mr-1" aria-hidden="true" />{t('Ainda não há vendas hoje.')}
                </p>
            )}

            {!!vendas?.ultimas.length && (
                <ul className="divide-y divide-gray-100">
                    {vendas.ultimas.map((v) => (
                        <li key={v.chave} className="py-2 flex items-center justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-xs font-semibold truncate font-mono">{v.numero}</p>
                                <p className="text-[11px] text-gray-500 truncate">
                                    {v.hora}{v.hora ? ' · ' : ''}{v.cliente}
                                </p>
                            </div>
                            <div className="text-right shrink-0">
                                <p className="text-xs font-bold tabular-nums">{kz(v.total)}</p>
                                <p className={`text-[10px] font-semibold ${v.emitida ? 'text-emerald-600' : 'text-amber-600'}`}>
                                    <i className={`fas ${v.emitida ? 'fa-check' : 'fa-clock'} mr-0.5`} aria-hidden="true" />
                                    {v.emitida ? t('emitida') : t('por emitir')}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {resto > 0 && (
                <p className="text-[10px] text-gray-400 text-center pt-2">
                    {tn('… e mais :n venda hoje|… e mais :n vendas hoje', resto, { n: resto })}
                </p>
            )}
        </section>
    );
}
