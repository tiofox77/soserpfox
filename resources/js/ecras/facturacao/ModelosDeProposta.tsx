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
import { FOCO, RAIO, cls } from '@/ui/tokens';

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
    const [recado, porRecado] = useState('');

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
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir os modelos</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const { data: itens, meta } = lista.data;

    return (
        <div className="space-y-4" data-modelos>
            {recado && <p role="status" className={cls('border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</p>}
            <AvisoDeErro erro={criar.error ?? duplicar.error ?? padrao.error ?? eliminar.error} />

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-file-signature text-slate-400" aria-hidden="true" />Modelos de Proposta</span>}
                accoes={o.permissoes.pode_criar && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porNovo(true)}>Novo modelo</Botao>}
            >
                <label className="block text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Procurar</span><input value={procura} onChange={(e) => { porProcura(e.target.value); porPagina(1); }} placeholder="Nome do modelo" className={entrada} /></label>
            </Cartao>

            <div className={cls('grid gap-4 sm:grid-cols-2 lg:grid-cols-3', lista.isFetching && 'opacity-60')}>
                {itens.length === 0 && <p className="text-sm text-slate-400 sm:col-span-3">Ainda não há modelos. Crie um de arranque: já vem com capa, itens e condições.</p>}
                {itens.map((m) => (
                    <article key={m.id} className={cls('flex flex-col border border-slate-200 bg-white', RAIO)} data-modelo={m.id}>
                        <div className="h-2 rounded-t-lg" style={{ background: m.cor }} />
                        <div className="flex-1 p-4">
                            <div className="mb-1 flex items-start justify-between gap-2">
                                <h3 className="font-bold text-slate-900">{m.nome}</h3>
                                {m.is_default && <Etiqueta cor="aviso" icone="fa-star">Padrão</Etiqueta>}
                            </div>
                            {m.descricao && <p className="text-sm text-slate-500">{m.descricao}</p>}
                            <p className="mt-2 text-xs text-slate-400">{m.blocos_n} blocos · {m.orcamentos_n} orçamentos · {m.actualizado}</p>
                        </div>
                        <div className="flex flex-wrap gap-1 border-t border-slate-100 p-2">
                            {o.permissoes.pode_editar && <a href={m.editor} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50', RAIO, FOCO)}><i className="fas fa-pen" aria-hidden="true" />Editar</a>}
                            <a href={m.previa} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50', RAIO, FOCO)}><i className="fas fa-eye" aria-hidden="true" />Pré-visualizar</a>
                            {o.permissoes.pode_criar && <button type="button" onClick={() => duplicar.mutate(m.id)} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50', RAIO, FOCO)}><i className="fas fa-copy" aria-hidden="true" />Duplicar</button>}
                            {o.permissoes.pode_editar && !m.is_default && <button type="button" onClick={() => padrao.mutate(m.id)} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50', RAIO, FOCO)}><i className="fas fa-star" aria-hidden="true" />Tornar padrão</button>}
                            {o.permissoes.pode_eliminar && <button type="button" onClick={() => porAEliminar(m)} className={cls('inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" />Eliminar</button>}
                        </div>
                    </article>
                ))}
            </div>

            {meta.last_page > 1 && (
                <div className="flex items-center justify-between text-sm text-slate-500">
                    <span>Página {meta.current_page} de {meta.last_page}</span>
                    <span className="flex gap-1">
                        <Botao icone="fa-chevron-left" disabled={meta.current_page <= 1} onClick={() => porPagina(meta.current_page - 1)}>Anterior</Botao>
                        <Botao icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page} onClick={() => porPagina(meta.current_page + 1)}>Seguinte</Botao>
                    </span>
                </div>
            )}

            <Modal aberto={novo} aoFechar={() => porNovo(false)} titulo="Novo modelo de proposta" largura="lg" rodape={<Botao onClick={() => porNovo(false)}>Cancelar</Botao>}>
                <div className="grid gap-3 sm:grid-cols-2" data-arranque>
                    {o.arranque.map((a) => (
                        <button key={a.chave} type="button" disabled={criar.isPending} onClick={() => criar.mutate(a.chave)} className={cls('flex items-start gap-3 border border-slate-200 p-4 text-left hover:border-indigo-300 hover:bg-indigo-50/40', RAIO, FOCO)}>
                            <span className={cls('inline-flex h-10 w-10 shrink-0 items-center justify-center text-white', RAIO)} style={{ background: a.cor }}><i className={cls('fas', a.icone)} aria-hidden="true" /></span>
                            <span><span className="block font-semibold text-slate-900">{a.nome}</span><span className="block text-xs text-slate-500">{a.descricao}</span></span>
                        </button>
                    ))}
                    <button type="button" disabled={criar.isPending} onClick={() => criar.mutate(undefined)} className={cls('flex items-start gap-3 border border-dashed border-slate-300 p-4 text-left hover:border-indigo-300', RAIO, FOCO)}>
                        <span className={cls('inline-flex h-10 w-10 shrink-0 items-center justify-center bg-slate-100 text-slate-500', RAIO)}><i className="fas fa-file" aria-hidden="true" /></span>
                        <span><span className="block font-semibold text-slate-900">Modelo vazio</span><span className="block text-xs text-slate-500">Só cliente, itens e totais. Acrescenta o resto no editor.</span></span>
                    </button>
                </div>
            </Modal>

            <Modal aberto={aEliminar !== null} aoFechar={() => porAEliminar(null)} titulo="Eliminar o modelo?" rodape={<><Botao onClick={() => porAEliminar(null)}>Cancelar</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={eliminar.isPending} onClick={() => aEliminar && eliminar.mutate(aEliminar.id)}>Eliminar</Botao></>}>
                <p className="text-sm text-slate-700">«{aEliminar?.nome}» deixa de aparecer. Os orçamentos já feitos com ele continuam a saber com que desenho foram impressos.</p>
            </Modal>
        </div>
    );
}
