import { useEffect, useState, type CSSProperties } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { quebras, type Quebra } from '@/api/quebras';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { Paginacao } from '@/ui/Paginacao';
import { CORES, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * AS QUEBRAS DE STOCK — expirado, estragado, partido, perdido.
 *
 * Registo e relatório no mesmo sítio: o registo é uma linha; o relatório
 * responde às duas perguntas que justificam registar — QUANTO se perdeu no
 * período, e PORQUÊ. Registar faz o stock descer e anular fá-lo voltar pelo
 * movimento contrário; é o `QuebraDeStock` que o garante, o mesmo que o
 * ecrã Livewire chama.
 *
 * O ASPECTO É O DE SEMPRE: o dinheiro perdido no período era o número grande
 * do cabeçalho rosa deste ecrã, e volta como cartão de gradiente — vermelho,
 * porque é uma perda. A lista recupera a cascata, o passar do rato e o
 * estado vazio desenhado.
 */

/** O atraso da linha `i` na entrada em cascata (ver `.entra` no layout). */
const cascata = (i: number) => ({ '--i': i }) as CSSProperties;

export default function Quebras() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState({ de: primeiroDoMes(), ate: hoje(), motivo: 'todos', page: 1 });
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['quebras', 'opcoes'], queryFn: quebras.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['quebras', filtros], queryFn: () => quebras.lista(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['quebras'] }); };

    const anular = useMutation({ mutationFn: (q: Quebra) => quebras.anular(q.id), onSuccess: (r) => feito(r.message) });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as quebras')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={anular.error} />

            {o.permissoes.pode_registar && <Registo o={o} aoFeito={feito} />}

            <Cartao titulo={t('O período')} icone="fa-calendar-days">
                <div className="flex flex-wrap items-end gap-3">
                    <Campo etiqueta={t('De')}><input type="date" value={filtros.de} onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))} className={entrada} /></Campo>
                    <Campo etiqueta={t('Até')}><input type="date" value={filtros.ate} onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value, page: 1 }))} className={entrada} /></Campo>
                    <Campo etiqueta={t('Motivo')}>
                        <select value={filtros.motivo} onChange={(e) => porFiltros((f) => ({ ...f, motivo: e.target.value, page: 1 }))} className={entrada}>
                            <option value="todos">{t('Todos')}</option>
                            {o.motivos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                        </select>
                    </Campo>
                </div>
            </Cartao>

            {resumo && (
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* O número que este ecrã existe para dar. Vermelho porque é
                        dinheiro que se perdeu, e com o caixote a arder ao lado
                        para quem não distingue o vermelho do resto. */}
                    <CartaoNumero
                        rotulo={t('Perdido no período')}
                        valor={<span data-custo>{kz(resumo.custo)}</span>}
                        sufixo="Kz"
                        icone="fa-dumpster-fire"
                        tom={resumo.custo > 0 ? 'vermelho' : 'cinza'}
                        nota={t(':quantos registo(s)', { quantos: resumo.registos })}
                    />
                    <Cartao titulo={t('Porquê')} icone="fa-chart-pie">
                        {resumo.por_motivo.length === 0 ? <p className="text-sm text-slate-400">{t('Nada perdido neste período.')}</p> : (
                            <GraficoDeBarras titulo={t('Custo perdido por motivo')} dados={resumo.por_motivo.map((m) => ({ rotulo: m.rotulo, valor: m.custo }))} />
                        )}
                    </Cartao>
                    <Cartao titulo={t('Onde')} icone="fa-boxes-stacked">
                        {resumo.por_produto.length === 0 ? <p className="text-sm text-slate-400">—</p> : (
                            <ul className="divide-y divide-slate-100 text-sm">
                                {resumo.por_produto.map((p, i) => (
                                    <li key={i} style={cascata(i)} className="entra -mx-2 flex items-center justify-between rounded-lg px-2 py-1.5 transition-all duration-200 hover:bg-rose-50/60">
                                        <span>{p.artigo} <span className="text-xs text-slate-400">{p.quantidade.toLocaleString('pt-PT')} {p.unidade}</span></span>
                                        <span className="tabular-nums font-semibold">{kz(p.custo)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Cartao>
                </div>
            )}

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                                <th className="px-4 py-3 font-bold"><i className="fas fa-clock mr-1.5 text-slate-400" aria-hidden="true" />{t('Quando')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-box mr-1.5 text-indigo-500" aria-hidden="true" />{t('Artigo')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-warehouse mr-1.5 text-blue-500" aria-hidden="true" />{t('Armazém')}</th>
                                <th className="px-4 py-3 text-right font-bold">{t('Qtd.')}</th>
                                <th className="px-4 py-3 text-right font-bold">{t('Custo')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-circle-question mr-1.5 text-rose-500" aria-hidden="true" />{t('Motivo')}</th>
                                <th className="px-4 py-3 font-bold">{t('Quem')}</th>
                                <th className="w-24 px-4 py-3 text-right font-bold">{t('Acções')}</th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-6 py-16">
                                        {lista.isPending ? (
                                            <p className="text-center text-slate-400">{t('A carregar…')}</p>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                    <i className="fas fa-dumpster-fire text-3xl text-slate-400" aria-hidden="true" />
                                                </div>
                                                <p className="text-lg font-semibold text-slate-500">{t('Nenhuma quebra neste período.')}</p>
                                                <p className="mt-2 max-w-md text-sm text-slate-400">{t('Quando um artigo expirar, se estragar ou partir, registe-o acima — sai do stock e fica no relatório.')}</p>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((q, i) => (
                                <tr key={q.id} style={cascata(i)} className={cls('entra transition-all duration-200 hover:bg-rose-50/60', q.anulada && 'text-slate-400 line-through opacity-60')}>
                                    <td className="px-4 py-2 tabular-nums">{q.quando}</td>
                                    <td className="px-4 py-2 font-medium text-slate-800">
                                        {q.artigo}
                                        {/* A anulada continua a dizer POR QUE foi
                                            registada; o que muda é ganhar aqui a
                                            marca de anulada, como no ecrã de
                                            sempre — o risco por cima do texto
                                            sozinho não chega a quem lê letra a
                                            letra. */}
                                        {q.anulada && <span className="ml-2 inline-block no-underline"><Etiqueta cor="neutra" icone="fa-rotate-left">{t('Anulada')}</Etiqueta></span>}
                                        {q.notas && <span className="block text-xs font-normal text-slate-400 no-underline">{q.notas}</span>}
                                    </td>
                                    <td className="px-4 py-2">{q.armazem ? <Etiqueta cor="neutra" icone="fa-warehouse">{q.armazem}</Etiqueta> : <span className="text-slate-300">—</span>}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{q.quantidade.toLocaleString('pt-PT')} <span className="text-xs text-slate-400">{q.unidade}</span></td>
                                    <td className={cls('px-4 py-2 text-right font-bold tabular-nums', !q.anulada && 'text-red-600')}>{kz(q.custo)}</td>
                                    <td className="px-4 py-2">
                                        <Etiqueta cor={q.anulada ? 'neutra' : 'aviso'} icone="fa-triangle-exclamation" ponto>
                                            {q.motivo_rotulo}
                                        </Etiqueta>
                                    </td>
                                    <td className="px-4 py-2">{q.quem}</td>
                                    <td className="px-4 py-2 text-right">
                                        {!q.anulada && o.permissoes.pode_registar && (
                                            <button type="button" onClick={() => anular.mutate(q)} title={t('Anular')} aria-label={t('Anular quebra de :artigo', { artigo: q.artigo ?? '' })} className={cls('grid h-9 w-9 place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES.aviso.suave, FOCO)}><i className="fas fa-rotate-left" aria-hidden="true" /></button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && (
                    <Paginacao
                        pagina={contas.current_page}
                        ultima={contas.last_page}
                        total={contas.total}
                        aCarregar={lista.isFetching}
                        aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                    />
                )}
            </Cartao>
        </div>
    );
}

/* ─── O registo ─────────────────────────────────────────────────────── */

function Registo({ o, aoFeito }: { o: { motivos: Array<{ valor: string; rotulo: string }>; armazens: Array<{ id: number; name: string }>; armazem_padrao: number | null }; aoFeito: (m: string) => void }) {
    const [procura, porProcura] = useState('');
    const [sugestoes, porSugestoes] = useState<Array<{ id: number; name: string; code: string | null; unit: string | null }>>([]);
    const [artigo, porArtigo] = useState<{ id: number; name: string } | null>(null);
    const [quantidade, porQuantidade] = useState('');
    const [armazem, porArmazem] = useState(o.armazem_padrao ? String(o.armazem_padrao) : '');
    const [motivo, porMotivo] = useState('estragado');
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    useEffect(() => {
        const termo = procura.trim();
        if (termo === '' || artigo) { porSugestoes([]); return; }
        let cancelado = false;
        const h = setTimeout(() => {
            quebras.artigos(termo).then((r) => { if (!cancelado) porSugestoes(r.data); }).catch(() => { if (!cancelado) porSugestoes([]); });
        }, 300);
        return () => { cancelado = true; clearTimeout(h); };
    }, [procura, artigo]);

    const registar = useMutation({
        mutationFn: () => quebras.registar({ product_id: artigo?.id ?? null, warehouse_id: Number(armazem) || null, quantity: Number(String(quantidade).replace(',', '.')) || 0, reason: motivo, notes: notas || null }),
        onSuccess: (r) => { aoFeito(r.message); porArtigo(null); porProcura(''); porQuantidade(''); porNotas(''); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Cartao titulo={t('Registar uma quebra')} icone="fa-dumpster-fire">
            <AvisoDeErro erro={registar.error} />
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div className="relative lg:col-span-2">
                    <Campo etiqueta={t('Artigo')} erro={erros.product_id} obrigatorio>
                        <input value={artigo ? artigo.name : procura} onChange={(e) => { porArtigo(null); porProcura(e.target.value); }} placeholder={t('Nome, código ou código de barras')} className={entrada} />
                    </Campo>
                    {sugestoes.length > 0 && (
                        <ul className={cls('absolute z-10 mt-1 w-full overflow-hidden border border-slate-200 bg-white shadow-xl', RAIO)} role="listbox">
                            {sugestoes.map((s, i) => (
                                <li key={s.id} style={cascata(i)} className="entra border-b border-slate-100 last:border-0"><button type="button" onClick={() => { porArtigo(s); porSugestoes([]); }} className={cls('block w-full px-3 py-2 text-left text-sm transition-all duration-200 hover:bg-rose-50/60', FOCO)}><span className="font-semibold text-slate-900">{s.name}</span>{s.code && <span className="ml-2 font-mono text-xs text-slate-400">{s.code}</span>}{s.unit && <span className="ml-1 text-xs text-slate-400">· {s.unit}</span>}</button></li>
                            ))}
                        </ul>
                    )}
                </div>
                <Campo etiqueta={t('Quantidade')} erro={erros.quantity} obrigatorio>
                    <input type="number" min="0.001" step="0.001" value={quantidade} onChange={(e) => porQuantidade(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id} obrigatorio>
                    <select value={armazem} onChange={(e) => porArmazem(e.target.value)} className={entrada}>
                        <option value="">{t('Escolher…')}</option>
                        {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Motivo')} erro={erros.reason} obrigatorio>
                    <select value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada}>
                        {o.motivos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Notas')} erro={erros.notes} className="lg:col-span-4">
                    <input value={notas} onChange={(e) => porNotas(e.target.value)} className={entrada} />
                </Campo>
                <div className="flex items-end">
                    <Botao cor="perigo" tom="solida" icone="fa-dumpster-fire" aTrabalhar={registar.isPending} onClick={() => registar.mutate()}>{t('Registar quebra')}</Botao>
                </div>
            </div>
        </Cartao>
    );
}

const hoje = () => new Date().toISOString().slice(0, 10);
const primeiroDoMes = () => hoje().slice(0, 8) + '01';
