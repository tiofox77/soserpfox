import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { quebras, type Quebra } from '@/api/quebras';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * AS QUEBRAS DE STOCK — expirado, estragado, partido, perdido.
 *
 * Registo e relatório no mesmo sítio: o registo é uma linha; o relatório
 * responde às duas perguntas que justificam registar — QUANTO se perdeu no
 * período, e PORQUÊ. Registar faz o stock descer e anular fá-lo voltar pelo
 * movimento contrário; é o `QuebraDeStock` que o garante, o mesmo que o
 * ecrã Livewire chama.
 */
export default function Quebras() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState({ de: primeiroDoMes(), ate: hoje(), motivo: 'todos', page: 1 });
    const [recado, porRecado] = useState('');

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

            <Cartao titulo={t('O período')}>
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
                    <div className={cls('border border-slate-200 bg-white p-4', RAIO)}>
                        <p className="text-xs uppercase tracking-wider text-slate-500">{t('Perdido no período')}</p>
                        <p className="text-2xl font-bold tabular-nums text-red-700" data-custo>{kz(resumo.custo)} <span className="text-sm font-normal text-slate-400">Kz</span></p>
                        <p className="text-sm text-slate-500">{t(':quantos registo(s)', { quantos: resumo.registos })}</p>
                    </div>
                    <Cartao titulo={t('Porquê')}>
                        {resumo.por_motivo.length === 0 ? <p className="text-sm text-slate-400">{t('Nada perdido neste período.')}</p> : (
                            <GraficoDeBarras titulo={t('Custo perdido por motivo')} dados={resumo.por_motivo.map((m) => ({ rotulo: m.rotulo, valor: m.custo }))} />
                        )}
                    </Cartao>
                    <Cartao titulo={t('Onde')}>
                        {resumo.por_produto.length === 0 ? <p className="text-sm text-slate-400">—</p> : (
                            <ul className="divide-y divide-slate-100 text-sm">
                                {resumo.por_produto.map((p, i) => (
                                    <li key={i} className="flex items-center justify-between py-1.5">
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
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">{t('Quando')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Artigo')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Armazém')}</th>
                                <th className="px-4 py-3 text-right font-semibold">{t('Qtd.')}</th>
                                <th className="px-4 py-3 text-right font-semibold">{t('Custo')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Motivo')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Quem')}</th>
                                <th className="w-24 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={8} className="px-4 py-10 text-center text-slate-400">{lista.isPending ? t('A carregar…') : t('Nenhuma quebra neste período.')}</td></tr>}
                            {linhas.map((q) => (
                                <tr key={q.id} className={cls(q.anulada && 'text-slate-400 line-through')}>
                                    <td className="px-4 py-2 tabular-nums">{q.quando}</td>
                                    <td className="px-4 py-2">{q.artigo}{q.notas && <span className="block text-xs no-underline text-slate-400">{q.notas}</span>}</td>
                                    <td className="px-4 py-2">{q.armazem}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{q.quantidade.toLocaleString('pt-PT')} {q.unidade}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(q.custo)}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={q.anulada ? 'neutra' : 'aviso'}>{q.motivo_rotulo}</Etiqueta></td>
                                    <td className="px-4 py-2">{q.quem}</td>
                                    <td className="px-4 py-2 text-right">
                                        {!q.anulada && o.permissoes.pode_registar && (
                                            <button type="button" onClick={() => anular.mutate(q)} title={t('Anular')} aria-label={t('Anular quebra de :artigo', { artigo: q.artigo ?? '' })} className={cls('p-2 text-slate-400 hover:text-red-600', RAIO, FOCO)}><i className="fas fa-rotate-left" aria-hidden="true" /></button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>{t('Página :pagina de :ultima', { pagina: contas.current_page, ultima: contas.last_page })}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>{t('Anterior')}</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>{t('Seguinte')}</Botao>
                        </span>
                    </div>
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
        <Cartao titulo={t('Registar uma quebra')}>
            <AvisoDeErro erro={registar.error} />
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div className="relative lg:col-span-2">
                    <Campo etiqueta={t('Artigo')} erro={erros.product_id} obrigatorio>
                        <input value={artigo ? artigo.name : procura} onChange={(e) => { porArtigo(null); porProcura(e.target.value); }} placeholder={t('Nome, código ou código de barras')} className={entrada} />
                    </Campo>
                    {sugestoes.length > 0 && (
                        <ul className={cls('absolute z-10 mt-1 w-full border border-slate-200 bg-white shadow-lg', RAIO)} role="listbox">
                            {sugestoes.map((s) => (
                                <li key={s.id}><button type="button" onClick={() => { porArtigo(s); porSugestoes([]); }} className={cls('block w-full px-3 py-2 text-left text-sm hover:bg-slate-50', FOCO)}>{s.name}{s.code && <span className="ml-2 font-mono text-xs text-slate-400">{s.code}</span>}</button></li>
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
