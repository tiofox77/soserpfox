import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { inventario, type Contagem as ContagemDoServidor } from '@/api/compras';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * A CONTAGEM FÍSICA: contar a prateleira, comparar com o sistema, acertar.
 *
 * O ecrã vive em DOIS MODOS. Sem contagem aberta mostra o histórico e o botão
 * de abrir; com uma aberta mostra a lista de artigos para escrever o contado, e
 * a diferença aparece na hora.
 *
 * UMA CONTAGEM ABERTA RETOMA-SE SOZINHA: a meio de contar quinhentos artigos,
 * sair do ecrã e voltar não pode recomeçar do zero.
 *
 * E O FECHO MOSTRA O RESUMO ANTES DO BOTÃO — quantos acertos e quanto custam.
 * Ninguém deve fechar um inventário às cegas, e uma confirmação do browser não
 * tem como dizer isto.
 */
export default function Contagem() {
    const cache = useQueryClient();

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [filtros, porFiltros] = useState<{
        procura?: string; filtro?: string; por_pagina?: number; page?: number;
    }>({ filtro: 'todos', por_pagina: 50, page: 1 });

    const [aAbrir, porAAbrir] = useState<{ warehouse_id: string; notas: string } | null>(null);
    const [aFechar, porAFechar] = useState(false);
    const [aCancelar, porACancelar] = useState(false);
    const [aVer, porAVer] = useState<number | null>(null);

    const estado = useQuery({
        queryKey: ['inventario', 'contagem'],
        queryFn: () => inventario.contagem.estado(),
    });

    const aberta = estado.data?.aberta ?? null;

    const linhas = useQuery({
        queryKey: ['inventario', 'contagem', 'linhas', aberta?.id, filtros],
        queryFn: () => inventario.contagem.linhas(aberta!.id, filtros),
        enabled: aberta !== null,
    });

    const resumoDoFecho = useQuery({
        queryKey: ['inventario', 'contagem', 'resumo', aberta?.id],
        queryFn: () => inventario.contagem.resumo(aberta!.id),
        enabled: aberta !== null && aFechar,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['inventario'] });
    };

    const abrir = useMutation({
        mutationFn: () => inventario.contagem.abrir(Number(aAbrir!.warehouse_id), aAbrir!.notas),
        onSuccess: (r) => { feito(r.message); porAAbrir(null); },
        onError: porErro,
    });

    const contar = useMutation({
        mutationFn: ({ produto, valor }: { produto: number; valor: number | null }) =>
            inventario.contagem.contar(aberta!.id, produto, valor),
        onSuccess: () => void cache.invalidateQueries({ queryKey: ['inventario', 'contagem', 'linhas'] }),
        onError: porErro,
    });

    const fechar = useMutation({
        mutationFn: () => inventario.contagem.fechar(aberta!.id),
        onSuccess: (r) => { feito(r.message); porAFechar(false); },
        onError: porErro,
    });

    const cancelar = useMutation({
        mutationFn: () => inventario.contagem.cancelar(aberta!.id),
        onSuccess: (r) => { feito(r.message); porACancelar(false); },
        onError: porErro,
    });

    if (estado.isPending) return <Carregando linhas={8} />;
    if (estado.isError) return <AvisoDeErro erro={estado.error} />;

    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const progresso = linhas.data?.progresso;
    const meta = linhas.data?.meta;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Contagem Física')}
                subtitulo={t('Contar a prateleira, comparar com o sistema, acertar')}
                icone="fa-clipboard-check"
                cor="teal"
                accoes={
                    <>
                        {!aberta && (
                            <button type="button" className={ACCAO_DA_FAIXA}
                                onClick={() => porAAbrir({
                                    warehouse_id: estado.data.armazens[0]?.valor ?? '', notas: '',
                                })}>
                                <i className="fas fa-play" aria-hidden="true" />
                                {t('Abrir contagem')}
                            </button>
                        )}
                        <a href="/inventario/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chart-pie" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                {aberta && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-warehouse">{aberta.armazem ?? '—'}</EstadoNaFaixa>
                        <EstadoNaFaixa icone="fa-clock">
                            {t('Aberta em :quando', { quando: aberta.aberta_em ?? '—' })}
                        </EstadoNaFaixa>
                    </div>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {aberta ? (
                <>
                    {progresso && (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <CartaoNumero aspecto="claro" rotulo={t('Artigos a contar')} valor={numero(progresso.total)}
                                icone="fa-boxes-stacked" tom="teal" />
                            <CartaoNumero
                                aspecto="claro" rotulo={t('Já contados')} valor={numero(progresso.contados)}
                                icone="fa-clipboard-check" tom="verde"
                                nota={progresso.total > 0
                                    ? t(':n% do armazém', { n: String(Math.round(progresso.contados / progresso.total * 100)) })
                                    : undefined}
                            />
                            <CartaoNumero
                                aspecto="claro" rotulo={t('Com diferença')} valor={numero(progresso.diferencas)}
                                icone="fa-scale-unbalanced" tom={progresso.diferencas > 0 ? 'ambar' : 'verde'}
                                nota={t('Estas viram movimentos ao fechar')}
                            />
                        </div>
                    )}

                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input type="search" value={filtros.procura ?? ''}
                                    onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                                    placeholder={t('Nome, código ou código de barras…')} className={cls(entrada, 'pl-9')} />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Mostrar')} className="w-48">
                            <select value={filtros.filtro ?? 'todos'}
                                onChange={(e) => porFiltros({ ...filtros, filtro: e.target.value, page: 1 })}
                                className={entrada}>
                                <option value="todos">{t('Todos')}</option>
                                <option value="por_contar">{t('Por contar')}</option>
                                <option value="com_diferenca">{t('Com diferença')}</option>
                            </select>
                        </Campo>

                        <Botao cor="bom" tom="solida" icone="fa-flag-checkered" onClick={() => porAFechar(true)}>
                            {t('Fechar contagem')}
                        </Botao>

                        <Botao cor="perigo" icone="fa-ban" onClick={() => porACancelar(true)}>
                            {t('Cancelar')}
                        </Botao>
                    </div>

                    {linhas.isPending ? (
                        <Carregando linhas={8} />
                    ) : linhas.isError ? (
                        <AvisoDeErro erro={linhas.error} />
                    ) : linhas.data.data.length === 0 ? (
                        <SemNada icone="fa-clipboard-check" titulo={t('Nenhum artigo')}
                            frase={t('Limpe os filtros — ou o armazém não tem artigos geridos a stock.')} />
                    ) : (
                        <div className={cls('overflow-x-auto', CARTAO)}>
                            <table className="w-full min-w-[44rem] text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3 text-left">{t('Artigo')}</th>
                                        <th className="px-4 py-3 text-right">{t('O sistema diz')}</th>
                                        <th className="px-4 py-3 text-right">{t('Contei')}</th>
                                        <th className="px-4 py-3 text-right">{t('Diferença')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {linhas.data.data.map((l, i) => (
                                        <tr key={l.id} style={cascata(Math.min(i, 15))}
                                            className={cls('entra transition',
                                                l.diferenca !== null && l.diferenca !== 0 ? 'bg-amber-50/50' : 'hover:bg-slate-50/70')}>
                                            <td className="px-4 py-2">
                                                <p className="font-medium text-slate-800">{l.artigo}</p>
                                                {l.codigo && <p className="font-mono text-xs text-slate-400">{l.codigo}</p>}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums text-slate-500">
                                                {numero(l.esperado)} {l.unidade}
                                            </td>
                                            <td className="w-36 px-4 py-2">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    defaultValue={l.contado ?? ''}
                                                    onBlur={(e) => {
                                                        const cru = e.target.value.trim();
                                                        const valor = cru === '' ? null : Number(cru);

                                                        // Só se grava o que mudou: a lista tem centenas de
                                                        // linhas, e um pedido por cada saída de campo
                                                        // afogava o servidor sem mudar nada.
                                                        if (valor !== l.contado) {
                                                            contar.mutate({ produto: l.product_id, valor });
                                                        }
                                                    }}
                                                    className={cls(entrada, 'text-right tabular-nums')}
                                                />
                                            </td>
                                            <td className={cls('px-4 py-2 text-right font-bold tabular-nums',
                                                l.diferenca === null ? 'text-slate-300'
                                                    : l.diferenca === 0 ? 'text-emerald-600'
                                                        : l.diferenca > 0 ? 'text-blue-600' : 'text-red-600')}>
                                                {l.diferenca === null ? '—'
                                                    : l.diferenca > 0 ? `+${numero(l.diferenca)}` : numero(l.diferenca)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {meta && meta.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-slate-500">
                                {t('A mostrar :de a :ate de :total', {
                                    de: String(meta.from ?? 0), ate: String(meta.to ?? 0), total: String(meta.total),
                                })}
                            </p>
                            <div className="flex items-center gap-2">
                                <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
                                <Botao altura="pequeno" icone="fa-chevron-left" disabled={meta.current_page <= 1}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page - 1 })}
                                    aria-label={t('Página anterior')} />
                                <span className="text-xs font-semibold tabular-nums text-slate-600">
                                    {meta.current_page}/{meta.last_page}
                                </span>
                                <Botao altura="pequeno" icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page + 1 })}
                                    aria-label={t('Página seguinte')} />
                            </div>
                        </div>
                    )}
                </>
            ) : (
                <SemNada
                    icone="fa-clipboard-check"
                    titulo={t('Nenhuma contagem aberta')}
                    frase={t('Abrir uma contagem congela o que o sistema diz ter — e é contra esse retrato que a prateleira se compara.')}
                    accao={
                        <Botao cor="primaria" tom="solida" icone="fa-play"
                            onClick={() => porAAbrir({ warehouse_id: estado.data.armazens[0]?.valor ?? '', notas: '' })}>
                            {t('Abrir contagem')}
                        </Botao>
                    }
                />
            )}

            <Cartao
                titulo={t('As contagens anteriores')}
                subtitulo={t('Fechadas e canceladas — e o que cada uma acertou')}
                icone="fa-clock-rotate-left"
                semPadding
            >
                {estado.data.historico.length === 0 ? (
                    <div className="p-5">
                        <SemNada icone="fa-clock-rotate-left" titulo={t('Ainda não se contou nada')}
                            frase={t('A primeira contagem é a que transforma o valor do stock numa verdade.')} />
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {estado.data.historico.map((c, i) => (
                            <li key={c.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                <span className={cls('grid h-9 w-9 flex-none place-items-center rounded-xl',
                                    c.estado === 'closed' ? 'bg-teal-50 text-teal-600' : 'bg-slate-100 text-slate-400')}>
                                    <i className={`fas ${c.estado === 'closed' ? 'fa-flag-checkered' : 'fa-ban'}`} aria-hidden="true" />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{c.armazem ?? '—'}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {[c.fechada_em ?? c.aberta_em, c.quem_abriu].filter(Boolean).join(' · ')}
                                    </p>
                                </div>

                                {c.estado === 'closed' && (
                                    <>
                                        <Etiqueta cor={c.acertos > 0 ? 'aviso' : 'bom'} icone="fa-scale-unbalanced">
                                            {t(':n acertos', { n: numero(c.acertos) })}
                                        </Etiqueta>
                                        <span className="flex-none text-sm font-bold tabular-nums text-slate-900">
                                            {kz(c.custo)}
                                        </span>
                                        <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(c.id)}
                                            aria-label={t('Ver as diferenças')} />
                                    </>
                                )}

                                {c.estado === 'cancelled' && (
                                    <Etiqueta cor="neutra" icone="fa-ban">{t('Cancelada')}</Etiqueta>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Cartao>

            {/* ─── Abrir ──────────────────────────────────────────────── */}

            <Modal
                aberto={aAbrir !== null}
                aoFechar={() => porAAbrir(null)}
                titulo={t('Abrir contagem')}
                subtitulo={t('O esperado fica congelado no momento em que abrir')}
                icone="fa-play"
                cor="teal"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAAbrir(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-play" aTrabalhar={abrir.isPending}
                            disabled={!aAbrir?.warehouse_id} onClick={() => abrir.mutate()}>
                            {t('Abrir')}
                        </Botao>
                    </>
                }
            >
                {aAbrir && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={abrir.error} />

                        <p className={cls('border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800', RAIO)}>
                            <i className="fas fa-camera mr-2" aria-hidden="true" />
                            {t('O que o sistema diz ter fica gravado linha a linha agora. É contra esse retrato que a contagem se compara — e não contra um stock que continua a mexer-se enquanto se conta.')}
                        </p>

                        <Campo etiqueta={t('Armazém')} obrigatorio>
                            <select value={aAbrir.warehouse_id}
                                onChange={(e) => porAAbrir({ ...aAbrir, warehouse_id: e.target.value })}
                                className={entrada}>
                                {estado.data.armazens.map((a) => (
                                    <option key={a.valor} value={a.valor}>{a.rotulo}</option>
                                ))}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Notas')} ajuda={t('Quem contou, porquê, que prateleiras.')}>
                            <textarea rows={2} value={aAbrir.notas}
                                onChange={(e) => porAAbrir({ ...aAbrir, notas: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>

            {/* ─── Fechar ─────────────────────────────────────────────── */}

            <Modal
                aberto={aFechar}
                aoFechar={() => porAFechar(false)}
                titulo={t('Fechar a contagem')}
                subtitulo={t('As diferenças viram movimentos de stock')}
                icone="fa-flag-checkered"
                cor="bom"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAFechar(false)}>{t('Ainda não')}</Botao>
                        <Botao cor="bom" tom="solida" icone="fa-flag-checkered" aTrabalhar={fechar.isPending}
                            onClick={() => fechar.mutate()}>
                            {t('Fechar')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-4">
                    <AvisoDeErro erro={fechar.error} />

                    {resumoDoFecho.isPending ? (
                        <Carregando linhas={3} />
                    ) : resumoDoFecho.data ? (
                        <>
                            {/*
                              * O RESUMO ANTES DO BOTÃO. Ninguém deve fechar um
                              * inventário sem saber quantos acertos vai fazer e
                              * quanto é que eles custam.
                              */}
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Numero rotulo={t('Contados')} valor={numero(resumoDoFecho.data.contados)} tom="slate" />
                                <Numero rotulo={t('Acertos')} valor={numero(resumoDoFecho.data.acertos)}
                                    tom={resumoDoFecho.data.acertos > 0 ? 'amber' : 'emerald'} />
                                <Numero rotulo={t('Diferença')} valor={`${kz(resumoDoFecho.data.custo)} Kz`}
                                    tom={resumoDoFecho.data.custo > 0 ? 'red' : 'emerald'} />
                            </div>

                            <p className="text-sm text-slate-600">
                                {t('Ao fechar, cada diferença vira um movimento de ajuste e o stock passa a dizer o que a prateleira diz. Isto não se desfaz.')}
                            </p>
                        </>
                    ) : null}
                </div>
            </Modal>

            <Modal
                aberto={aCancelar}
                aoFechar={() => porACancelar(false)}
                titulo={t('Cancelar a contagem')}
                icone="fa-ban"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porACancelar(false)}>{t('Voltar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-ban" aTrabalhar={cancelar.isPending}
                            onClick={() => cancelar.mutate()}>
                            {t('Cancelar contagem')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={cancelar.error} />
                <p className="text-sm text-slate-600">
                    {t('O que já se contou perde-se, e NADA é mexido no stock. A contagem fica no histórico como cancelada.')}
                </p>
            </Modal>

            <Diferencas id={aVer} aoFechar={() => porAVer(null)} />
        </div>
    );
}

function Numero({ rotulo, valor, tom }: { rotulo: string; valor: string; tom: 'slate' | 'amber' | 'red' | 'emerald' }) {
    const cores = {
        slate: 'border-slate-200 bg-slate-50 text-slate-700',
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
        red: 'border-red-200 bg-red-50 text-red-800',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    }[tom];

    return (
        <div className={cls('border px-3 py-2 text-center', RAIO, cores)}>
            <p className="text-[11px] font-semibold uppercase tracking-wider opacity-80">{rotulo}</p>
            <p className="text-base font-bold tabular-nums">{valor}</p>
        </div>
    );
}

/* ─── As diferenças de uma contagem fechada ───────────────────────────── */

function Diferencas({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const detalhe = useQuery({
        queryKey: ['inventario', 'contagem', 'diferencas', id],
        queryFn: () => inventario.contagem.diferencas(id as number),
        enabled: id !== null,
    });

    const c: ContagemDoServidor | undefined = detalhe.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={t('O que se acertou')}
            subtitulo={c ? `${c.armazem ?? '—'} · ${c.fechada_em ?? ''}` : undefined}
            icone="fa-scale-unbalanced"
            cor="teal"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {detalhe.isPending ? (
                <Carregando linhas={5} />
            ) : detalhe.isError ? (
                <AvisoDeErro erro={detalhe.error} />
            ) : detalhe.data ? (
                detalhe.data.ajustadas.length === 0 ? (
                    <SemNada icone="fa-circle-check" titulo={t('Sem diferenças')}
                        frase={t('A prateleira dizia exactamente o que o sistema dizia. É o melhor resultado possível.')} />
                ) : (
                    <div className={cls('overflow-x-auto', CARTAO)}>
                        <table className="w-full min-w-[36rem] text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left">{t('Artigo')}</th>
                                    <th className="px-3 py-2 text-right">{t('O sistema dizia')}</th>
                                    <th className="px-3 py-2 text-right">{t('Estava')}</th>
                                    <th className="px-3 py-2 text-right">{t('Diferença')}</th>
                                    <th className="px-3 py-2 text-right">{t('Custo')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {detalhe.data.ajustadas.map((a) => (
                                    <tr key={a.id}>
                                        <td className="px-3 py-2 font-medium text-slate-800">{a.artigo}</td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-500">
                                            {a.esperado} {a.unidade}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-800">{a.contado}</td>
                                        <td className={cls('px-3 py-2 text-right font-bold tabular-nums',
                                            a.diferenca > 0 ? 'text-blue-600' : 'text-red-600')}>
                                            {a.diferenca > 0 ? `+${a.diferenca}` : a.diferenca}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-600">
                                            {kz(Math.abs(a.diferenca) * a.custo)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )
            ) : null}
        </Modal>
    );
}
