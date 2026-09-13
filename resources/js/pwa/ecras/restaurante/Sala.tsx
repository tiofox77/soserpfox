import { useMemo } from 'react';

import { t, tn } from '@/i18n';

import { dinheiro, useAccao, useBaseViva } from '../../ganchos';
import type { Registo } from '../../motor/base';
import { restaurante } from '../../motor/restaurante';
import { sync } from '../../motor/sincronizar';
import { CrachaDoTurno, type ControloDoTurno } from '../../turno/Turno';
import { avisar } from '../../ui/Dialogos';
import { ESTADOS_DA_MESA, corDaMesa, iconeDoEstado, imprimirTalao, mensagemDe, nomeDaMesa, pontoDoEstado, rotuloDoEstado } from './comum';

/**
 * VISTA: A SALA — a planta das mesas, o balcão, as comandas sem mesa e as
 * últimas contas para reimprimir.
 *
 * Tudo o que aqui se vê vem da base VIVA: uma sincronização que muda uma mesa,
 * outro empregado que a ocupa, uma conta que recebe o número fiscal — o ecrã
 * acompanha sozinho. O Alpine ouvia `pwa:synced` e relia tudo à mão; bastava
 * esquecer uma chamada para ficar a mostrar a sala de há uma hora.
 */
export function Sala({
    c,
    turnoAberto,
    salas,
    salaId,
    escolherSala,
    zonaId,
    escolherZona,
    todasAsMesas,
    abrirComanda,
}: {
    c: ControloDoTurno;
    turnoAberto: boolean;
    salas: Registo[];
    salaId: number | null;
    escolherSala: (id: number) => void;
    zonaId: number | null;
    escolherZona: (id: number | null) => void;
    todasAsMesas: Registo[] | null;
    abrirComanda: (uuid: string) => void;
}) {
    const zonas = useBaseViva<Registo[]>(() => restaurante.zonas(salaId), [salaId], []);
    // `null` enquanto a primeira leitura não volta: sem isto o «Sem mesas
    // sincronizadas» piscava a cada abertura do ecrã, antes de as mesas chegarem.
    const mesas = useBaseViva<Registo[] | null>(() => restaurante.mesas(salaId, zonaId), [salaId, zonaId], null);
    const comandas = useBaseViva<Registo[]>(() => restaurante.comandas(true), [], []);

    // Comandas ao balcão (sem mesa): não têm onde aparecer na planta.
    const comandasSemMesa = useMemo(() => comandas.filter((x) => x.status !== 'fechada' && !x.table_id), [comandas]);

    // Só as últimas: a lista serve para reimprimir o que acabou de sair, não
    // para ser um histórico — esse vive no sistema.
    const contasFechadas = useMemo(() => comandas.filter((x) => x.status === 'fechada').slice(0, 8), [comandas]);

    const contagem = useMemo(() => {
        const n: Record<string, number> = {};
        for (const m of mesas ?? []) n[m.status] = (n[m.status] ?? 0) + 1;

        return n;
    }, [mesas]);

    const [escolherMesa, aAbrirMesa] = useAccao(async (mesa: Registo) => {
        if (mesa.comanda) {
            abrirComanda(mesa.comanda.local_uuid);

            return;
        }

        if (['blocked', 'cleaning'].includes(mesa.status)) {
            avisar(t('Esta mesa não está disponível para atendimento.'), 'aviso');

            return;
        }

        try {
            const comanda = await restaurante.abrir({
                venue_id: salaId,
                table_id: mesa.id,
                guest_count: Math.max(1, Number(mesa.capacity) || 1),
            });
            abrirComanda(comanda.local_uuid);
        } catch (e) {
            avisar(mensagemDe(e), 'erro');
        }
    });

    // Com `useAccao`: um duplo toque no Balcão abria DUAS comandas ao balcão,
    // e a segunda ficava vazia na lista «Ao balcão» sem ninguém a ter pedido.
    const [abrirBalcao, aAbrirBalcao] = useAccao(async () => {
        try {
            const comanda = await restaurante.abrir({ venue_id: salaId, channel: 'counter', guest_count: 1 });
            abrirComanda(comanda.local_uuid);
        } catch (e) {
            avisar(mensagemDe(e), 'erro');
        }
    });

    const salaActual = salas.find((s) => s.id === salaId);
    const semMesasNaEscolha = mesas !== null && !mesas.length && !comandasSemMesa.length;

    return (
        <section className="p-3 space-y-3">

            {/* Sala e zona */}
            <div className="flex gap-2 items-center">
                {salas.length > 1 ? (
                    <div className="flex-1 min-w-0 flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12 border-2 border-transparent focus-within:border-orange-300 transition">
                        <i className="fas fa-store text-orange-500" aria-hidden="true" />
                        <select id="restaurante-sala" name="venue_id" aria-label={t('Sala')}
                                value={salaId ?? ''} onChange={(e) => escolherSala(Number(e.target.value))}
                                className="flex-1 min-w-0 bg-transparent text-sm font-semibold focus:outline-none border-0 py-0 cursor-pointer">
                            {salas.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </div>
                ) : (
                    <div className="flex-1 min-w-0 flex items-center gap-2 px-1 h-12">
                        <span className="w-9 h-9 shrink-0 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-white flex items-center justify-center shadow-sm pwa-flutua">
                            <i className="fas fa-utensils text-sm" aria-hidden="true" />
                        </span>
                        <p className="text-sm font-black text-slate-800 truncate">{salaActual?.name || t('POS Restaurante')}</p>
                    </div>
                )}

                <button type="button" onClick={() => void abrirBalcao()} disabled={!turnoAberto || aAbrirBalcao}
                        className="pwa-toque shrink-0 h-12 px-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-700 text-white text-sm font-bold shadow hover:shadow-lg disabled:opacity-40 disabled:cursor-not-allowed">
                    <i className={`fas ${aAbrirBalcao ? 'fa-spinner fa-spin' : 'fa-bag-shopping'} mr-1.5 text-orange-300`} aria-hidden="true" />{t('Balcão')}
                </button>

                {/* O CADEADO DO TURNO, o mesmo do POS de balcão: verde aberto,
                    vermelho fechado, e toca-se para abrir ou fechar. Quem está na
                    sala tem de poder abrir a caixa sem sair daqui. */}
                <button type="button" onClick={c.gerir} title={t('Gerir turno')} aria-label={t('Gerir turno')}
                        className={`pwa-toque shrink-0 h-12 px-3 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center transition-colors ${turnoAberto ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-red-100 text-red-700 hover:bg-red-200 pwa-pulsa'}`}>
                    <i className={`fas ${turnoAberto ? 'fa-lock-open' : 'fa-triangle-exclamation'} text-sm`} aria-hidden="true" />
                    <span>{turnoAberto ? t('Turno') : t('S/ turno')}</span>
                </button>
            </div>

            {/* O crachá diz QUAL turno está aberto — é o número que se confere no fecho. */}
            {turnoAberto && (
                <div className="flex items-center justify-between gap-2 -mt-1">
                    <CrachaDoTurno c={c} />
                    {mesas !== null && mesas.length > 0 && (
                        <p className="text-[11px] text-slate-500 font-semibold">
                            <i className="fas fa-chair mr-1 text-slate-400" aria-hidden="true" />
                            {t(':livres livres de :total', { livres: contagem.available ?? 0, total: mesas.length })}
                        </p>
                    )}
                </div>
            )}

            {/* Zonas */}
            {zonas.length > 0 && (
                <div className="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 no-scrollbar" role="group" aria-label={t('Zonas')}>
                    <button type="button" onClick={() => escolherZona(null)} aria-pressed={!zonaId}
                            className={`pwa-toque shrink-0 px-4 h-10 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap transition-colors ${!zonaId ? 'bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-orange-500/30' : 'bg-white text-slate-600 hover:bg-orange-50'}`}>
                        <i className="fas fa-layer-group mr-1.5" aria-hidden="true" />{t('Todas')}
                    </button>
                    {zonas.map((z) => (
                        <button key={z.id} type="button" onClick={() => escolherZona(z.id)} aria-pressed={zonaId === z.id}
                                className={`pwa-toque shrink-0 px-4 h-10 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap transition-colors ${zonaId === z.id ? 'bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-orange-500/30' : 'bg-white text-slate-600 hover:bg-orange-50'}`}>
                            {z.name}
                        </button>
                    ))}
                </div>
            )}

            {/* Comandas ao balcão (sem mesa): não têm onde aparecer na planta */}
            {comandasSemMesa.length > 0 && (
                <div className="space-y-2 pwa-entra">
                    <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        <i className="fas fa-bag-shopping mr-1" aria-hidden="true" />{t('Ao balcão')}
                    </p>
                    <div className="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 no-scrollbar">
                        {comandasSemMesa.map((x) => {
                            const artigos = (x.items || []).length;

                            return (
                                <button key={x.local_uuid} type="button" onClick={() => abrirComanda(x.local_uuid)}
                                        className="pwa-cartao pwa-toque shrink-0 min-w-[150px] text-left bg-white rounded-2xl shadow-sm border border-slate-200 hover:border-orange-300 p-3">
                                    <p className={`text-[10px] font-bold ${x._server_number ? 'text-slate-400' : 'text-amber-600'}`}>
                                        {!x._server_number && <i className="fas fa-clock mr-1" aria-hidden="true" />}
                                        {x._server_number || t('Por sincronizar')}
                                    </p>
                                    <p className="text-sm font-bold text-slate-800">{tn(':n artigo|:n artigos', artigos, { n: artigos })}</p>
                                    <p className="text-sm font-black text-orange-600 tabular-nums">{dinheiro(x.total)} Kz</p>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* ============ A PLANTA ============ */}
            {mesas === null && (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2.5" aria-hidden="true">
                    {[0, 1, 2, 3, 4, 5].map((i) => <div key={i} className="h-24 rounded-2xl bg-slate-200/70 animate-pulse" />)}
                </div>
            )}

            {mesas !== null && mesas.length > 0 && (
                <>
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2.5">
                        {mesas.map((m, i) => {
                            const bloqueada = !turnoAberto && !m.comanda;

                            // A entrada anima a CAIXA e não o botão: a animação fica com
                            // `transform` preso no fim e anulava o levantar ao passar o rato.
                            return (
                                <div key={m.id} className="pwa-entra" style={{ animationDelay: `${Math.min(i, 16) * 18}ms` }}>
                                <button type="button" onClick={() => void escolherMesa(m)} disabled={bloqueada}
                                        aria-busy={aAbrirMesa || undefined}
                                        className={`relative w-full h-full rounded-2xl border-2 p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md active:scale-[.98] disabled:opacity-45 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-sm ${corDaMesa(m.status)}`}>

                                    <div className="flex items-start justify-between gap-1">
                                        <span className="text-sm font-black leading-tight truncate">{m.name || m.code}</span>
                                        <span className="shrink-0 text-[10px] font-bold opacity-70">
                                            <i className="fas fa-user-group mr-0.5" aria-hidden="true" />{m.capacity || 0}
                                        </span>
                                    </div>

                                    <p className="mt-1 text-[10px] font-bold uppercase tracking-wide opacity-70 truncate">
                                        <i className={`fas ${iconeDoEstado(m.status)} mr-1`} aria-hidden="true" />{rotuloDoEstado(m.status)}
                                    </p>

                                    {/* Uma mesa ocupada mostra o que já lá está: é o número que
                                        o empregado precisa de dizer quando lhe perguntam
                                        quanto é, e sem rede não há mais nenhum sítio onde o ir
                                        buscar. */}
                                    {m.comanda && (
                                        <div className="mt-2 pt-2 border-t border-current/15">
                                            <p className="text-sm font-black tabular-nums">{dinheiro(m.comanda.total)} Kz</p>
                                            <p className="text-[10px] font-semibold opacity-70">
                                                {tn(':n artigo|:n artigos', m.comanda.artigos, { n: m.comanda.artigos })}
                                            </p>
                                        </div>
                                    )}

                                    {m.comanda && !m.comanda.numero && (
                                        <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-current opacity-60 animate-pulse"
                                              title={t('Por sincronizar')} />
                                    )}
                                </button>
                                </div>
                            );
                        })}
                    </div>

                    {/* A legenda das cores, com quantas mesas há em cada estado: o
                        empregado lê a sala de relance sem abrir mesa nenhuma. */}
                    <div className="flex flex-wrap gap-1.5 pt-1" aria-label={t('Legenda')}>
                        {ESTADOS_DA_MESA.map((estado) => {
                            const n = contagem[estado] ?? 0;

                            return (
                                <span key={estado}
                                      className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-white border border-slate-200 text-[10px] font-semibold transition-opacity ${n ? 'text-slate-600' : 'text-slate-400 opacity-60'}`}>
                                    <span className={`w-2 h-2 rounded-full ${pontoDoEstado(estado)}`} aria-hidden="true" />
                                    {rotuloDoEstado(estado)}
                                    <b className="tabular-nums">{n}</b>
                                </span>
                            );
                        })}
                    </div>
                </>
            )}

            {/* ============ ÚLTIMAS CONTAS ============
                Para reimprimir. A primeira impressão falha mais do que se pensa —
                papel a acabar, impressora desligada — e o talão provisório passa a
                definitivo assim que a comanda sobe. Sem isto, o cliente que volta
                a pedir a factura ficava sem ela.

                POR BAIXO DA PLANTA, e não por cima: o que se faz nesta página é
                sentar gente e abrir mesas. Com as contas em cima, as mesas desciam
                meio ecrã a cada conta fechada e o dedo acertava no sítio errado. */}
            {contasFechadas.length > 0 && (
                <div className="space-y-2 pwa-entra">
                    <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        <i className="fas fa-receipt mr-1" aria-hidden="true" />{t('Últimas contas')}
                    </p>
                    <div className="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 no-scrollbar">
                        {contasFechadas.map((x) => (
                            <div key={x.local_uuid} className="shrink-0 min-w-[190px] bg-white rounded-2xl shadow-sm border border-slate-200 p-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className={`text-[10px] font-bold truncate transition-colors ${x._invoice_number ? 'text-emerald-600' : 'text-amber-600'}`}>
                                            <i className={`fas ${x._invoice_number ? 'fa-circle-check' : 'fa-clock'} mr-1`} aria-hidden="true" />
                                            {x._invoice_number || t('Por sincronizar')}
                                        </p>
                                        <p className="text-sm font-black text-slate-800 tabular-nums">{dinheiro(x.total)} Kz</p>
                                        <p className="text-[10px] text-slate-400">{nomeDaMesa(x.table_id, todasAsMesas ?? [])}</p>
                                    </div>
                                    <button type="button" onClick={() => void imprimirTalao(x.local_uuid)}
                                            title={t('Imprimir talão')} aria-label={t('Imprimir talão')}
                                            className="pwa-toque shrink-0 h-9 w-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors">
                                        <i className="fas fa-print" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Sala por montar.

                DUAS CAUSAS, DUAS MENSAGENS. Isto dizia sempre "Sem mesas
                sincronizadas" — mesmo com as mesas todas no aparelho, quando o
                que estava vazio era a sala ou a zona escolhida. Mandava
                sincronizar outra vez, o que não resolvia nada, e fazia parecer
                uma avaria de sincronização o que era uma questão de escolha. */}
            {semMesasNaEscolha && (
                <div className="pwa-entra text-center py-16 text-slate-400">
                    <i className="fas fa-chair text-5xl mb-3 block opacity-40 pwa-flutua" aria-hidden="true" />

                    {(todasAsMesas ?? []).length > 0 ? (
                        <div>
                            <p className="text-sm font-medium">{t('Esta sala não tem mesas')}</p>
                            <p className="text-xs mt-1">
                                {zonaId ? t('A zona escolhida está vazia — veja as outras zonas.') : t('As mesas estão noutra sala — escolha-a acima.')}
                            </p>
                            {!!zonaId && (
                                <button type="button" onClick={() => escolherZona(null)}
                                        className="pwa-toque mt-4 text-xs bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg font-bold">
                                    <i className="fas fa-layer-group mr-1" aria-hidden="true" />{t('Ver a sala toda')}
                                </button>
                            )}
                        </div>
                    ) : (
                        <div>
                            <p className="text-sm font-medium">{t('Sem mesas sincronizadas')}</p>
                            <p className="text-xs mt-1">{t('Monte a sala em Restaurante → Salas e sincronize.')}</p>
                            <button type="button" onClick={() => void sync(true)}
                                    className="pwa-toque mt-4 text-xs bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold">
                                <i className="fas fa-rotate mr-1" aria-hidden="true" />{t('Sincronizar')}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}
