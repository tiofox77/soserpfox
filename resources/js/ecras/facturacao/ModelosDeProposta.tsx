import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { modelos, type Modelo } from '@/api/propostas';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { CARTAO, FOCO, RAIO, RAIO_GRANDE, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';
import { Faixa, SemNada, cascata } from './faixa';

/**
 * OS MODELOS DE PROPOSTA da empresa, e a porta para os criar — de um
 * modelo de arranque ou vazio. Cada cartão abre o editor.
 */
export default function ModelosDeProposta() {
    const cache = useQueryClient();
    const [procura, porProcura] = useState('');
    const [pagina, porPagina] = useState(1);
    const [novo, porNovo] = useState(false);
    const [aEliminar, porAEliminar] = useState<Modelo | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['modelos', 'opcoes'], queryFn: modelos.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['modelos', procura, pagina], queryFn: () => modelos.lista({ procura, page: pagina }), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['modelos'] }); };
    const abrirEditor = (m: Modelo) => window.location.assign(m.editor);

    const criar = useMutation({ mutationFn: (arranque?: string) => modelos.criar(arranque ? { arranque } : {}), onSuccess: (r) => abrirEditor(r.data) });
    const duplicar = useMutation({ mutationFn: (id: number) => modelos.duplicar(id), onSuccess: (r) => abrirEditor(r.data) });
    const padrao = useMutation({ mutationFn: (id: number) => modelos.tornarPadrao(id), onSuccess: (r) => feito(r.message) });
    const eliminar = useMutation({ mutationFn: (id: number) => modelos.eliminar(id), onSuccess: (r) => { feito(r.message); porAEliminar(null); } });

    if (opcoes.isPending || lista.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os modelos')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const { data: itens, meta } = lista.data;

    return (
        <div className="space-y-4" data-modelos>
            {recado && <p role="status" className={cls('border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</p>}
            <AvisoDeErro erro={criar.error ?? duplicar.error ?? padrao.error ?? eliminar.error} />

            <Faixa
                icone="fa-file-signature"
                titulo={t('Modelos de Proposta')}
                subtitulo={t('O desenho com que os orçamentos saem em papel')}
            />

            <Cartao
                titulo={t('Filtros')}
                icone="fa-filter"
                accoes={o.permissoes.pode_criar && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porNovo(true)}>{t('Novo modelo')}</Botao>}
            >
                <label className="block text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span><input value={procura} onChange={(e) => { porProcura(e.target.value); porPagina(1); }} placeholder={t('Nome do modelo')} className={entrada} /></label>
            </Cartao>

            <div className={cls('grid gap-4 sm:grid-cols-2 lg:grid-cols-3', lista.isFetching && 'opacity-60')}>
                {itens.length === 0 && (
                    <div className={cls(CARTAO, 'sm:col-span-2 lg:col-span-3')}>
                        <SemNada
                            icone="fa-file-invoice"
                            titulo={t('Ainda não há modelos')}
                            frase={t('Comece por um pronto a usar — depois muda o que quiser.')}
                            accao={o.permissoes.pode_criar ? <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porNovo(true)}>{t('Criar o primeiro')}</Botao> : undefined}
                        />
                    </div>
                )}
                {itens.map((m, i) => (
                    /* A FITA DE COR NO TOPO é a do próprio modelo — vem da
                       ficha, não da paleta — e é por ela que se reconhece o
                       modelo antes de se ler o nome. */
                    <article
                        key={m.id}
                        data-modelo={m.id}
                        style={cascata(i)}
                        className={cls('entra flex flex-col overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO_GRANDE, 'transition-all duration-200 hover:-translate-y-1 hover:shadow-lg')}
                    >
                        <div className="h-2" style={{ background: m.cor }} />
                        <div className="flex-1 p-4">
                            <div className="mb-1 flex items-start justify-between gap-2">
                                <h3 className="font-bold text-slate-900">{m.nome}</h3>
                                {m.is_default && <Etiqueta cor="aviso" icone="fa-star">{t('Padrão')}</Etiqueta>}
                            </div>
                            {m.descricao && <p className="text-sm text-slate-500">{m.descricao}</p>}
                            <p className="mt-2 text-xs text-slate-400">{t(':blocos blocos · :orcamentos orçamentos · :actualizado', { blocos: m.blocos_n, orcamentos: m.orcamentos_n, actualizado: m.actualizado ?? '' })}</p>
                        </div>
                        <div className="flex flex-wrap gap-1 border-t border-slate-100 bg-slate-50/60 p-2">
                            {o.permissoes.pode_editar && <a href={m.editor} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50', RAIO, FOCO)}><i className="fas fa-pen" aria-hidden="true" />{t('Editar')}</a>}
                            <a href={m.previa} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50', RAIO, FOCO)}><i className="fas fa-eye" aria-hidden="true" />{t('Pré-visualizar')}</a>
                            {o.permissoes.pode_criar && <button type="button" onClick={() => duplicar.mutate(m.id)} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50', RAIO, FOCO)}><i className="fas fa-copy" aria-hidden="true" />{t('Duplicar')}</button>}
                            {o.permissoes.pode_editar && !m.is_default && <button type="button" onClick={() => padrao.mutate(m.id)} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50', RAIO, FOCO)}><i className="fas fa-star" aria-hidden="true" />{t('Tornar padrão')}</button>}
                            {o.permissoes.pode_eliminar && <button type="button" onClick={() => porAEliminar(m)} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" />{t('Eliminar')}</button>}
                        </div>
                    </article>
                ))}
            </div>

            <Paginacao
                emCartao
                pagina={meta.current_page}
                ultima={meta.last_page}
                total={meta.total}
                aCarregar={lista.isFetching}
                aMudar={porPagina}
            />

            <Modal aberto={novo} aoFechar={() => porNovo(false)} titulo={t('Novo modelo de proposta')} largura="lg" rodape={<Botao onClick={() => porNovo(false)}>{t('Cancelar')}</Botao>}>
                <div className="grid gap-3 sm:grid-cols-2" data-arranque>
                    {o.arranque.map((a, i) => (
                        <button key={a.chave} type="button" disabled={criar.isPending} onClick={() => criar.mutate(a.chave)} style={cascata(i)} className={cls('entra flex items-start gap-3 border border-slate-200 p-4 text-left', RAIO, FOCO, 'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-indigo-50/40 hover:shadow-md')}>
                            <span className={cls('inline-flex h-10 w-10 shrink-0 items-center justify-center text-white shadow-sm', RAIO)} style={{ background: a.cor }}><i className={cls('fas', a.icone)} aria-hidden="true" /></span>
                            <span><span className="block font-semibold text-slate-900">{a.nome}</span><span className="block text-xs text-slate-500">{a.descricao}</span></span>
                        </button>
                    ))}
                    <button type="button" disabled={criar.isPending} onClick={() => criar.mutate(undefined)} className={cls('flex items-start gap-3 border border-dashed border-slate-300 p-4 text-left', RAIO, FOCO, 'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-slate-50')}>
                        <span className={cls('inline-flex h-10 w-10 shrink-0 items-center justify-center bg-slate-100 text-slate-500', RAIO)}><i className="fas fa-file" aria-hidden="true" /></span>
                        <span><span className="block font-semibold text-slate-900">{t('Modelo vazio')}</span><span className="block text-xs text-slate-500">{t('Só cliente, itens e totais. Acrescenta o resto no editor.')}</span></span>
                    </button>
                </div>
            </Modal>

            <Modal aberto={aEliminar !== null} aoFechar={() => porAEliminar(null)} titulo={t('Eliminar o modelo?')} rodape={<><Botao onClick={() => porAEliminar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={eliminar.isPending} onClick={() => aEliminar && eliminar.mutate(aEliminar.id)}>{t('Eliminar')}</Botao></>}>
                <p className="text-sm text-slate-700">{t('«:nome» deixa de aparecer. Os orçamentos já feitos com ele continuam a saber com que desenho foram impressos.', { nome: aEliminar?.nome ?? '' })}</p>
            </Modal>
        </div>
    );
}
