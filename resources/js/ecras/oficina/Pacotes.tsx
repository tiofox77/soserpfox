import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { ordens, pacotesDeServico, type ArtigoParaEscolher, type LinhaDoPacote, type PacoteDeServico } from '@/api/oficina';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, kz } from '@/ui/tokens';

/**
 * OS PACOTES DE SERVIÇO (15/09/2026, OF-07).
 *
 * «Revisão 10 000 km» montada uma vez — mão-de-obra, óleo, filtros — e posta
 * numa ordem com um clique. Cada cartão diz o que leva, quanto custa, quantas
 * horas vende e quantas vezes já foi usado.
 */

type Rascunho = { id: number | null; nome: string; descricao: string; activo: boolean; linhas: LinhaDoPacote[] };

const subtotal = (l: LinhaDoPacote) => l.quantidade * l.preco * (1 - (l.desconto || 0) / 100);

export default function Pacotes() {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['oficina', 'pacotes'], queryFn: pacotesDeServico.lista });
    const [aEditar, porAEditar] = useState<Rascunho | null>(null);
    const [aApagar, porAApagar] = useState<PacoteDeServico | null>(null);

    const refazer = () => void cache.invalidateQueries({ queryKey: ['oficina', 'pacotes'] });
    const apagar = useMutation({ mutationFn: (p: PacoteDeServico) => pacotesDeServico.apagar(p.id), onSuccess: () => { porAApagar(null); refazer(); } });
    const activar = useMutation({
        mutationFn: (p: PacoteDeServico) => pacotesDeServico.guardar(p.id, { nome: p.nome, descricao: p.descricao ?? '', activo: !p.activo, linhas: p.linhas }),
        onSuccess: refazer,
    });

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const { data: pacotes, pode_gerir: podeGerir } = q.data;
    const novo = (): Rascunho => ({ id: null, nome: '', descricao: '', activo: true, linhas: [] });

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Pacotes de Serviço')} subtitulo={t('Mão-de-obra e peças que entram juntas numa ordem, com um clique')} icone="fa-box-open" cor="roxo"
                accoes={podeGerir && (
                    <button type="button" onClick={() => porAEditar(novo())} className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />{t('Novo pacote')}
                    </button>
                )} />

            {pacotes.length === 0 ? (
                <SemNada icone="fa-box-open" titulo={t('Ainda sem pacotes')} frase={t('Monte aqui os trabalhos que se repetem — uma revisão, uma troca de travões — ou guarde uma ordem já feita como pacote.')}
                    accao={podeGerir ? <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porAEditar(novo())}>{t('Novo pacote')}</Botao> : undefined} />
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {pacotes.map((p, i) => {
                        const servicos = p.linhas.filter((l) => l.tipo === 'service').length;
                        const pecas = p.linhas.length - servicos;
                        return (
                            <li key={p.id} style={cascata(i)} className={cls('entra group card-hover flex flex-col border border-slate-200 bg-white p-4 shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:-translate-y-1 hover:shadow-xl', !p.activo && 'opacity-60')}>
                                <div className="flex items-start gap-3">
                                    <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-lg text-white shadow">
                                        <i className="fas fa-box-open icon-float" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-bold text-slate-900">{p.nome}</p>
                                        {p.descricao && <p className="line-clamp-2 text-xs text-slate-500">{p.descricao}</p>}
                                    </div>
                                    {!p.activo && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">{t('Inactivo')}</span>}
                                </div>
                                <ul className="mt-3 flex-1 space-y-1 text-xs text-slate-600">
                                    {p.linhas.slice(0, 4).map((l, n) => (
                                        <li key={n} className="flex items-center gap-2">
                                            <i className={cls('fas w-3 text-center', l.tipo === 'service' ? 'fa-screwdriver-wrench text-indigo-400' : 'fa-gear text-emerald-500')} aria-hidden="true" />
                                            <span className="min-w-0 flex-1 truncate">{l.quantidade !== 1 && `${l.quantidade}× `}{l.nome}</span>
                                            <span className="tabular-nums text-slate-400">{kz(subtotal(l))}</span>
                                        </li>
                                    ))}
                                    {p.linhas.length > 4 && <li className="text-slate-400">{tn('+ :n linha|+ :n linhas', p.linhas.length - 4, { n: p.linhas.length - 4 })}</li>}
                                </ul>
                                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-xs">
                                    <span className="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">{tn(':n serviço|:n serviços', servicos, { n: servicos })}</span>
                                    <span className="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">{tn(':n peça|:n peças', pecas, { n: pecas })}</span>
                                    {p.horas > 0 && <span className="text-slate-500"><i className="fas fa-clock mr-1" aria-hidden="true" />{p.horas} h</span>}
                                    <span className="ml-auto text-base font-black tabular-nums text-slate-900">{kz(p.total)} <span className="text-xs font-normal text-slate-400">Kz</span></span>
                                </div>
                                <div className="mt-2 flex items-center gap-1 text-xs text-slate-400">
                                    <i className="fas fa-chart-simple" aria-hidden="true" />{tn('Usado :n vez|Usado :n vezes', p.usado, { n: p.usado })}
                                    {podeGerir && (
                                        <span className="ml-auto flex gap-1 opacity-70 transition-opacity group-hover:opacity-100">
                                            <button type="button" onClick={() => activar.mutate(p)} title={p.activo ? t('Desactivar') : t('Activar')} aria-label={p.activo ? t('Desactivar') : t('Activar')}
                                                className={cls('p-1.5 hover:text-amber-600', RAIO, FOCO)}><i className={cls('fas', p.activo ? 'fa-toggle-on text-emerald-500' : 'fa-toggle-off')} aria-hidden="true" /></button>
                                            <button type="button" onClick={() => porAEditar({ id: null, nome: t(':nome (cópia)', { nome: p.nome }), descricao: p.descricao ?? '', activo: true, linhas: p.linhas })} title={t('Duplicar')} aria-label={t('Duplicar')}
                                                className={cls('p-1.5 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-copy" aria-hidden="true" /></button>
                                            <button type="button" onClick={() => porAEditar({ id: p.id, nome: p.nome, descricao: p.descricao ?? '', activo: p.activo, linhas: p.linhas })} title={t('Editar')} aria-label={t('Editar')}
                                                className={cls('p-1.5 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-pen" aria-hidden="true" /></button>
                                            <button type="button" onClick={() => porAApagar(p)} title={t('Apagar')} aria-label={t('Apagar')}
                                                className={cls('p-1.5 hover:text-red-600', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>
                                        </span>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {aEditar && <EditorDoPacote r={aEditar} aoFechar={() => porAEditar(null)} aoGravar={() => { porAEditar(null); refazer(); }} />}

            <Modal aberto={aApagar !== null} aoFechar={() => porAApagar(null)} titulo={t('Apagar o pacote?')} subtitulo={aApagar?.nome} icone="fa-trash" cor="perigo" largura="sm"
                rodape={<><Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Apagar')}</Botao></>}>
                <p className="text-sm text-slate-600">{t('As ordens onde o pacote já entrou não mudam.')}</p>
            </Modal>
        </div>
    );
}

function EditorDoPacote({ r, aoFechar, aoGravar }: { r: Rascunho; aoFechar: () => void; aoGravar: () => void }) {
    const [d, porD] = useState<Rascunho>(r);
    const [procura, porProcura] = useState('');
    const [artigos, porArtigos] = useState<ArtigoParaEscolher[]>([]);
    const opcoes = useQuery({ queryKey: ['oficina', 'ordens', 'opcoes'], queryFn: ordens.opcoes, staleTime: 5 * 60_000 });

    useEffect(() => {
        if (procura.trim().length < 2) { porArtigos([]); return; }
        const tempo = window.setTimeout(() => { void ordens.artigos(procura).then((x) => porArtigos(x.data)); }, 300);
        return () => window.clearTimeout(tempo);
    }, [procura]);

    const gravar = useMutation({
        mutationFn: () => (d.id ? pacotesDeServico.guardar(d.id, d) : pacotesDeServico.criar(d)),
        onSuccess: aoGravar,
    });
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    const juntar = (l: LinhaDoPacote) => porD({ ...d, linhas: [...d.linhas, l] });
    const mudar = (n: number, novo: Partial<LinhaDoPacote>) => porD({ ...d, linhas: d.linhas.map((l, i) => (i === n ? { ...l, ...novo } : l)) });
    const total = d.linhas.reduce((s, l) => s + subtotal(l), 0);
    const caixa = cls('w-full border border-slate-300 bg-white px-2 py-1.5 text-sm', RAIO, FOCO);

    return (
        <Modal aberto aoFechar={aoFechar} titulo={d.id ? t('Editar pacote') : t('Novo pacote')} icone="fa-box-open" cor="roxo" largura="xl"
            rodape={
                <>
                    <span className="mr-auto text-sm text-slate-600">{t('Total do pacote')}: <b className="text-lg tabular-nums text-slate-900">{kz(total)} Kz</b></span>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao>
                </>
            }>
            <div className="space-y-4">
                <AvisoDeErro erro={gravar.error} />
                <div className="grid gap-3 sm:grid-cols-[2fr_3fr_auto]">
                    <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Nome')}</span>
                        <input value={d.nome} onChange={(e) => porD({ ...d, nome: e.target.value })} placeholder={t('Ex.: Revisão 10 000 km')} className={caixa} />
                        {erros.nome && <span className="mt-1 block text-xs text-red-600">{erros.nome[0]}</span>}
                    </label>
                    <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Descrição')}</span>
                        <input value={d.descricao} onChange={(e) => porD({ ...d, descricao: e.target.value })} className={caixa} />
                    </label>
                    <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                        <input type="checkbox" checked={d.activo} onChange={(e) => porD({ ...d, activo: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-indigo-600" />{t('Activo')}
                    </label>
                </div>

                {/* JUNTAR LINHAS */}
                <div className={cls('grid gap-3 border border-slate-200 bg-slate-50 p-3 lg:grid-cols-3', RAIO_GRANDE)}>
                    <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700"><i className="fas fa-screwdriver-wrench mr-1.5 text-indigo-500" aria-hidden="true" />{t('Serviço do catálogo')}</span>
                        <select value="" onChange={(e) => {
                            const s = opcoes.data?.servicos.find((x) => x.valor === e.target.value);
                            if (s) juntar({ tipo: 'service', service_id: Number(s.valor), product_id: null, codigo: s.codigo, nome: s.rotulo, quantidade: 1, preco: s.preco, desconto: 0, horas: s.horas });
                        }} className={caixa}>
                            <option value="">{t('— escolher —')}</option>
                            {opcoes.data?.servicos.map((s) => <option key={s.valor} value={s.valor}>{s.rotulo} · {kz(s.preco)}</option>)}
                        </select>
                    </label>
                    <div className="relative text-sm">
                        <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-gear mr-1.5 text-emerald-500" aria-hidden="true" />{t('Peça do catálogo')}</span>
                        <input value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Nome, código ou código de barras')} className={caixa} />
                        {artigos.length > 0 && (
                            <ul className="absolute inset-x-0 top-full z-20 mt-1 max-h-56 overflow-y-auto border border-slate-200 bg-white shadow-xl">
                                {artigos.map((a) => (
                                    <li key={a.valor}>
                                        <button type="button" onClick={() => { juntar({ tipo: 'part', service_id: null, product_id: Number(a.valor), codigo: a.codigo, nome: a.rotulo, quantidade: 1, preco: a.preco, desconto: 0, horas: 0 }); porProcura(''); }}
                                            className={cls('flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-emerald-50', FOCO)}>
                                            <span className="min-w-0 flex-1 truncate">{a.rotulo}</span>
                                            <span className="text-xs tabular-nums text-slate-500">{kz(a.preco)}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <div className="flex items-end">
                        <Botao icone="fa-pen" className="w-full" onClick={() => juntar({ tipo: 'service', service_id: null, product_id: null, codigo: null, nome: '', quantidade: 1, preco: 0, desconto: 0, horas: 0 })}>{t('Linha escrita à mão')}</Botao>
                    </div>
                </div>

                {erros.linhas && <p className="text-sm text-red-600">{erros.linhas[0]}</p>}

                {d.linhas.length === 0 ? (
                    <SemNada icone="fa-list" frase={t('Junte serviços e peças ao pacote.')} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[44rem] text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-2 py-2 text-left">{t('Tipo')}</th>
                                    <th scope="col" className="px-2 py-2 text-left">{t('Descrição')}</th>
                                    <th scope="col" className="w-20 px-2 py-2 text-right">{t('Qtd')}</th>
                                    <th scope="col" className="w-28 px-2 py-2 text-right">{t('Preço')}</th>
                                    <th scope="col" className="w-20 px-2 py-2 text-right">{t('Desc. %')}</th>
                                    <th scope="col" className="w-20 px-2 py-2 text-right">{t('Horas')}</th>
                                    <th scope="col" className="w-28 px-2 py-2 text-right">{t('Subtotal')}</th>
                                    <th scope="col" className="w-10 px-2 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.linhas.map((l, n) => (
                                    <tr key={n} style={cascata(n)} className="entra">
                                        <td className="px-2 py-1.5">
                                            <select value={l.tipo} onChange={(e) => mudar(n, { tipo: e.target.value as LinhaDoPacote['tipo'], service_id: null, product_id: null })} disabled={Boolean(l.service_id || l.product_id)}
                                                className={cls(caixa, 'w-24')}>
                                                <option value="service">{t('Serviço')}</option>
                                                <option value="part">{t('Peça')}</option>
                                            </select>
                                        </td>
                                        <td className="px-2 py-1.5">
                                            <input value={l.nome} onChange={(e) => mudar(n, { nome: e.target.value })} className={caixa} aria-label={t('Descrição')} />
                                            {(l.service_id || l.product_id) && <span className="text-[10px] text-slate-400"><i className="fas fa-link mr-1" aria-hidden="true" />{t('Do catálogo')}</span>}
                                        </td>
                                        <td className="px-2 py-1.5"><input type="number" min={0.01} step="0.01" value={l.quantidade} onChange={(e) => mudar(n, { quantidade: Number(e.target.value) })} className={cls(caixa, 'text-right tabular-nums')} aria-label={t('Qtd')} /></td>
                                        <td className="px-2 py-1.5"><input type="number" min={0} step="0.01" value={l.preco} onChange={(e) => mudar(n, { preco: Number(e.target.value) })} className={cls(caixa, 'text-right tabular-nums')} aria-label={t('Preço')} /></td>
                                        <td className="px-2 py-1.5"><input type="number" min={0} max={100} step="0.5" value={l.desconto} onChange={(e) => mudar(n, { desconto: Number(e.target.value) })} className={cls(caixa, 'text-right tabular-nums')} aria-label={t('Desc. %')} /></td>
                                        <td className="px-2 py-1.5"><input type="number" min={0} step="0.25" value={l.horas} disabled={l.tipo === 'part'} onChange={(e) => mudar(n, { horas: Number(e.target.value) })} className={cls(caixa, 'text-right tabular-nums disabled:bg-slate-50')} aria-label={t('Horas')} /></td>
                                        <td className="px-2 py-1.5 text-right font-bold tabular-nums">{kz(subtotal(l))}</td>
                                        <td className="px-2 py-1.5 text-right">
                                            <button type="button" onClick={() => porD({ ...d, linhas: d.linhas.filter((_, i) => i !== n) })} aria-label={t('Tirar a linha')}
                                                className={cls('p-1.5 text-slate-400 hover:scale-110 hover:text-red-600', RAIO, TRANSICAO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </Modal>
    );
}
