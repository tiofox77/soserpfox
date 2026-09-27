import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import type { DadosDoDreIntegrado, LinhaDoDre } from '@/api/tesouraria';
import { relatoriosDaTesouraria } from '@/api/tesouraria';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * O DRE INTEGRADO — RESULTADO DO PERÍODO (27/09/2026).
 *
 * Responde a uma pergunta só: o lucro das vendas deste período cobriu as
 * despesas da empresa? Se não cobriu, o prejuízo aparece como prejuízo.
 *
 * As contas vivem no servidor (`App\Services\Treasury\DreIntegrado`), as
 * mesmas do PDF e do Excel. Este ecrã desenha e deixa ABRIR CADA VALOR: um
 * clique numa rubrica mostra os documentos e os movimentos que a compõem, cada
 * um com ligação para o abrir. E a CLASSIFICAÇÃO das categorias da tesouraria
 * (despesa, compra de stock, activo, dívida…) faz-se aqui, porque é aqui que
 * se vê o efeito dela.
 */
export function DreIntegrado({ d, filtros }: {
    d: Partial<DadosDoDreIntegrado>;
    filtros: { periodo: string; de: string; ate: string };
}) {
    const [aVer, porAVer] = useState<{ rubrica: string; rotulo: string } | null>(null);
    const [aClassificar, porAClassificar] = useState(false);

    if (!d.linhas) return <Carregando linhas={8} />;

    const liquido = d.resultado_liquido ?? 0;
    const prejuizo = liquido < 0;

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Receita líquida')} valor={kz(d.receita?.receita_liquida ?? 0)} sufixo="Kz" icone="fa-arrow-trend-up" tom="indigo" />
                <CartaoNumero aspecto="claro" rotulo={t('Lucro bruto')} valor={kz(d.lucro_bruto ?? 0)} sufixo="Kz" icone="fa-coins" tom="verde"
                    nota={t('Margem bruta :m%', { m: String(d.margem_bruta ?? 0) })} />
                <CartaoNumero aspecto="claro" rotulo={t('Despesas operacionais')} valor={kz(d.despesas?.total ?? 0)} sufixo="Kz" icone="fa-file-invoice-dollar" tom="ambar" />
                <CartaoNumero aspecto="claro" rotulo={prejuizo ? t('Prejuízo do período') : t('Lucro do período')} valor={kz(liquido)} sufixo="Kz"
                    icone={prejuizo ? 'fa-arrow-trend-down' : 'fa-scale-balanced'} tom={prejuizo ? 'vermelho' : 'teal'} />
            </div>

            {(d.por_classificar?.length ?? 0) > 0 && (
                <div role="alert" className={cls('flex flex-wrap items-start justify-between gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                    <p className="min-w-0 flex-1">
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('Categorias por classificar, contadas como despesa: :c.', { c: d.por_classificar!.map((c) => c.rotulo).join(', ') })}
                    </p>
                    <Botao altura="pequeno" icone="fa-tags" onClick={() => porAClassificar(true)}>{t('Classificar')}</Botao>
                </div>
            )}

            <div className={cls(CARTAO, 'overflow-hidden')}>
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
                    <p className="text-xs text-slate-500">{t('Sem IVA. Toque numa linha para ver os documentos e os movimentos que a compõem.')}</p>
                    <Botao altura="pequeno" icone="fa-tags" onClick={() => porAClassificar(true)}>{t('Classificação das categorias')}</Botao>
                </div>
                <table className="min-w-full text-sm">
                    <tbody className="divide-y divide-slate-100">
                        {d.linhas.map((l, i) => (
                            <LinhaDoResultado key={i} l={l} aoAbrir={() => l.rubrica && porAVer({ rubrica: l.rubrica, rotulo: l.rotulo })} />
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Despesas pagas pela tesouraria')} icone="fa-chart-pie">
                    {(d.despesas?.por_categoria.length ?? 0) === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">{t('Nada neste período.')}</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {d.despesas!.por_categoria.map((c, i) => (
                                <li key={c.categoria} style={cascata(i)} className="entra flex items-baseline justify-between gap-3">
                                    <span className="min-w-0 truncate text-slate-700">{c.rotulo}</span>
                                    <span className="shrink-0 font-semibold tabular-nums text-slate-900">{kz(c.valor)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                {/*
                  * O QUE NÃO ENTRA NO RESULTADO, à vista — para bater certo com
                  * a tesouraria e para se ver que não foi esquecido.
                  */}
                <Cartao titulo={t('Movimentos que não entram no resultado')} icone="fa-filter-circle-xmark">
                    {(d.fora_do_resultado?.length ?? 0) === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">{t('Nada neste período.')}</p>
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {d.fora_do_resultado!.map((f) => (
                                <li key={f.rubrica}>
                                    <button type="button" onClick={() => porAVer({ rubrica: f.rubrica, rotulo: f.rotulo })}
                                        className={cls('flex w-full items-baseline justify-between gap-3 py-2 text-left hover:bg-slate-50', FOCO)}>
                                        <span className="min-w-0 text-slate-700">{f.rotulo}</span>
                                        <span className="shrink-0 text-right tabular-nums">
                                            {f.saidas > 0 && <span className="block text-red-600">−{kz(f.saidas)}</span>}
                                            {f.entradas > 0 && <span className="block text-emerald-600">+{kz(f.entradas)}</span>}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>

            <Cartao titulo={t('Últimos seis meses')} icone="fa-calendar">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[36rem] text-sm">
                        <thead className="text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th className="px-3 py-2 text-left">{t('Mês')}</th>
                                <th className="px-3 py-2 text-right">{t('Receita líquida')}</th>
                                <th className="px-3 py-2 text-right">{t('CMV')}</th>
                                <th className="px-3 py-2 text-right">{t('Despesas')}</th>
                                <th className="px-3 py-2 text-right">{t('Resultado')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {(d.mensal ?? []).map((m) => (
                                <tr key={m.mes}>
                                    <td className="px-3 py-2 text-slate-700">{m.mes}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{kz(m.receita_liquida)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{kz(m.cmv)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{kz(m.despesas)}</td>
                                    <td className={cls('px-3 py-2 text-right font-semibold tabular-nums', m.resultado < 0 ? 'text-red-600' : 'text-emerald-700')}>
                                        {kz(m.resultado)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Cartao>

            <div className={cls('flex items-start gap-3 border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800', RAIO)}>
                <i className="fas fa-circle-info mt-0.5" aria-hidden="true" />
                <span>
                    {t('Receita pelas facturas e notas emitidas. CMV ao custo de compra à data da venda (as notas de crédito de devolução devolvem-no). Despesas: a parte de serviços das facturas de compra, pela data da factura, e os movimentos da tesouraria classificados como despesa. Comprar mercadoria, pagar facturas de compra, transferir entre contas e receber de clientes não entra no resultado.')}
                </span>
            </div>

            <Detalhe alvo={aVer} filtros={filtros} aoFechar={() => porAVer(null)} />
            <Classificacao aberta={aClassificar} aoFechar={() => porAClassificar(false)} />
        </div>
    );
}

function LinhaDoResultado({ l, aoAbrir }: { l: LinhaDoDre; aoAbrir: () => void }) {
    const conteudo = (
        <>
            <span className={cls('min-w-0', l.nivel > 0 && 'pl-5 text-slate-600', (l.total || l.final) && 'font-bold')}>
                {l.rotulo}
                {l.rubrica && <i className="fas fa-magnifying-glass ml-2 text-[10px] text-slate-400" aria-hidden="true" />}
            </span>
            <span className={cls('shrink-0 tabular-nums',
                l.final ? cls('text-lg font-bold', l.valor < 0 ? 'text-red-300' : 'text-emerald-300')
                    : l.total ? 'font-bold text-slate-900'
                        : l.valor < 0 ? 'text-red-600' : 'text-slate-700')}>
                {kz(l.valor)} <span className="text-xs font-normal opacity-60">Kz</span>
            </span>
        </>
    );

    return (
        <tr className={cls(l.final && 'bg-slate-900 text-white', l.total && !l.final && 'bg-slate-50')}>
            <td className="p-0">
                {l.rubrica ? (
                    <button type="button" onClick={aoAbrir} aria-label={t('Ver a origem de :r', { r: l.rotulo })}
                        className={cls('flex w-full items-baseline justify-between gap-3 px-5 py-3 text-left transition hover:bg-teal-50/60', FOCO)}>
                        {conteudo}
                    </button>
                ) : (
                    <div className="flex items-baseline justify-between gap-3 px-5 py-3">{conteudo}</div>
                )}
            </td>
        </tr>
    );
}

/* ─── A origem de um valor ────────────────────────────────────────────── */

function Detalhe({ alvo, filtros, aoFechar }: {
    alvo: { rubrica: string; rotulo: string } | null;
    filtros: { periodo: string; de: string; ate: string };
    aoFechar: () => void;
}) {
    const detalhe = useQuery({
        queryKey: ['tesouraria', 'dre-integrado', 'detalhe', alvo?.rubrica, filtros],
        queryFn: () => relatoriosDaTesouraria.detalhe({ rubrica: alvo!.rubrica, periodo: 'custom', de: filtros.de, ate: filtros.ate }),
        enabled: alvo !== null,
    });

    return (
        <Modal
            aberto={alvo !== null}
            aoFechar={aoFechar}
            titulo={detalhe.data?.titulo ?? alvo?.rotulo ?? ''}
            subtitulo={t('De :de a :ate', { de: filtros.de, ate: filtros.ate })}
            icone="fa-magnifying-glass-dollar"
            cor="teal"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {detalhe.isPending ? (
                <Carregando linhas={6} />
            ) : detalhe.isError ? (
                <AvisoDeErro erro={detalhe.error} />
            ) : detalhe.data.linhas.length === 0 ? (
                <p className="py-6 text-center text-sm text-slate-400">{t('Nada neste período.')}</p>
            ) : (
                <div className="space-y-3">
                    <div className={cls('overflow-x-auto', CARTAO)}>
                        <table className="w-full min-w-[36rem] text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left">{t('Data')}</th>
                                    <th className="px-3 py-2 text-left">{t('Documento ou movimento')}</th>
                                    <th className="px-3 py-2 text-left">{t('Descrição')}</th>
                                    <th className="px-3 py-2 text-right">{t('Valor')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {detalhe.data.linhas.map((l, i) => (
                                    <tr key={i}>
                                        <td className="px-3 py-2 tabular-nums text-slate-600">{l.data}</td>
                                        <td className="px-3 py-2">
                                            <a href={l.ligacao} target="_blank" rel="noopener"
                                                className={cls('font-mono text-xs font-semibold text-teal-700 underline-offset-2 hover:underline', FOCO)}>
                                                {l.documento}
                                            </a>
                                        </td>
                                        <td className="px-3 py-2 text-slate-600">{l.descricao ?? '—'}</td>
                                        <td className={cls('px-3 py-2 text-right font-semibold tabular-nums', l.valor < 0 ? 'text-red-600' : 'text-slate-900')}>{kz(l.valor)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-slate-50">
                                <tr>
                                    <td colSpan={3} className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Total')}</td>
                                    <td className="px-3 py-2 text-right font-bold tabular-nums text-slate-900">{kz(detalhe.data.total)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    {detalhe.data.limitado && (
                        <p className="text-xs text-slate-500">{t('Mostram-se as primeiras 500 linhas; o total conta todas.')}</p>
                    )}
                </div>
            )}
        </Modal>
    );
}

/* ─── A natureza das categorias ───────────────────────────────────────── */

/**
 * A CLASSIFICAÇÃO: cada categoria da tesouraria com a sua natureza. As de
 * sistema trazem a da casa; as da empresa começam como despesa até alguém
 * dizer o que são. Guardar muda o relatório na hora.
 */
function Classificacao({ aberta, aoFechar }: { aberta: boolean; aoFechar: () => void }) {
    const cache = useQueryClient();
    const lista = useQuery({
        queryKey: ['tesouraria', 'naturezas'],
        queryFn: () => relatoriosDaTesouraria.naturezas(),
        enabled: aberta,
    });
    const [mudancas, porMudancas] = useState<Record<string, string>>({});

    const guardar = useMutation({
        mutationFn: () => relatoriosDaTesouraria.guardarNaturezas(mudancas),
        onSuccess: () => {
            porMudancas({});
            void cache.invalidateQueries({ queryKey: ['tesouraria'] });
            aoFechar();
        },
    });

    const fechar = () => { porMudancas({}); aoFechar(); };
    const pode = lista.data?.pode_classificar ?? false;
    const noResultado = new Set((lista.data?.naturezas ?? []).filter((n) => n.no_resultado).map((n) => n.valor));

    return (
        <Modal
            aberto={aberta}
            aoFechar={fechar}
            titulo={t('Classificação das categorias')}
            subtitulo={t('O que cada categoria de movimento é para o resultado')}
            icone="fa-tags"
            cor="teal"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={fechar}>{pode ? t('Cancelar') : t('Fechar')}</Botao>
                    {pode && (
                        <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending}
                            disabled={Object.keys(mudancas).length === 0} onClick={() => guardar.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    )}
                </>
            }
        >
            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : (
                <div className="space-y-3">
                    <AvisoDeErro erro={guardar.error} />
                    <p className="text-sm text-slate-600">
                        {t('Só despesa, encargo financeiro, imposto e outro rendimento mexem no resultado. Compra de stock, activo, dívida, transferência, devolução e recebimento ficam fora — não são custo nem proveito do período.')}
                    </p>
                    <ul className="divide-y divide-slate-100">
                        {lista.data.categorias.map((c) => {
                            const valor = mudancas[c.categoria] ?? c.natureza;

                            return (
                                <li key={c.categoria} className="flex flex-wrap items-center justify-between gap-3 py-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-slate-800">{c.rotulo}</p>
                                        <p className="font-mono text-xs text-slate-400">
                                            {c.categoria}
                                            {noResultado.has(valor) ? ` · ${t('entra no resultado')}` : ` · ${t('fora do resultado')}`}
                                        </p>
                                    </div>
                                    <label className="w-72 max-w-full">
                                        <span className="sr-only">{t('Natureza de :c', { c: c.rotulo })}</span>
                                        <select id={`natureza-${c.categoria}`} value={valor} disabled={!pode}
                                            onChange={(e) => porMudancas({ ...mudancas, [c.categoria]: e.target.value })}
                                            className={entrada}>
                                            {lista.data.naturezas.map((n) => <option key={n.valor} value={n.valor}>{n.rotulo}</option>)}
                                        </select>
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </Modal>
    );
}
