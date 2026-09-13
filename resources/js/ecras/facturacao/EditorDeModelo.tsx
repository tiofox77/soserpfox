import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { modelos, type Bloco, type EstadoDoEditor } from '@/api/propostas';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';
import { ACCAO_DA_FAIXA, Faixa, SemNada, cascata } from './faixa';

/**
 * O EDITOR DE UM MODELO DE PROPOSTA.
 *
 * Três colunas: à esquerda as peças e a ordem, ao centro a folha como vai
 * sair, à direita as opções do bloco escolhido. A pré-visualização é o
 * MESMO renderizador que faz o PDF — um editor que mostra uma coisa e
 * imprime outra é pior do que não ter editor. Cada alteração vai ao
 * servidor (`EdicaoDeModelo`), que valida, grava e devolve a folha nova.
 */
export default function EditorDeModelo({ id }: { id: number }) {
    const q = useQuery({ queryKey: ['modelos', 'editor', id], queryFn: () => modelos.editor(id) });

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o modelo')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    return <Editor id={id} inicial={q.data.estado} previaInicial={q.data.previa} catalogo={q.data.catalogo} variaveis={q.data.variaveis} />;
}

type Catalogo = Array<{ tipo: string; nome: string; icone: string; ajuda: string; padroes: Record<string, unknown> }>;

function Editor({ id, inicial, previaInicial, catalogo, variaveis }: { id: number; inicial: EstadoDoEditor; previaInicial: string; catalogo: Catalogo; variaveis: Record<string, Record<string, string>> }) {
    const [estado, porEstado] = useState<EstadoDoEditor>(inicial);
    const [previa, porPrevia] = useState(previaInicial);
    const [seleccionado, porSeleccionado] = useState<string | null>(inicial.seleccionado);
    const [mostrarVariaveis, porMostrarVariaveis] = useState(false);
    const [recado, porRecado] = useRecadoNoCanto('');
    // O bloco que se está a arrastar, e aquele por cima do qual paira.
    const [arrastado, porArrastado] = useState<string | null>(null);
    const [alvo, porAlvo] = useState<string | null>(null);

    const accao = useMutation({
        mutationFn: (corpo: Record<string, unknown>) => modelos.accao(id, { seleccionado, ...corpo }),
        // Corre a cada toque no editor: só avisa quando o servidor diz alguma coisa.
        meta: { aviso: 'so-com-mensagem' },
        onSuccess: (r) => { porEstado(r.estado); porPrevia(r.previa); porSeleccionado(r.estado.seleccionado); if (r.message) porRecado(r.message); },
    });
    const fazer = (corpo: Record<string, unknown>) => accao.mutate(corpo);

    /**
     * Larga-se o bloco: a ordem nova vai inteira, de uma vez.
     *
     * Quem decide é o servidor (`reordenar`), como em todas as outras acções
     * deste editor — aqui só se diz a ordem em que os blocos ficaram.
     */
    const largar = () => {
        if (!arrastado || !alvo || arrastado === alvo) {
            porArrastado(null);
            porAlvo(null);

            return;
        }

        const ids = estado.blocos.map((b) => b.id).filter((id) => id !== arrastado);
        const onde = ids.indexOf(alvo);

        ids.splice(onde < 0 ? ids.length : onde, 0, arrastado);

        porArrastado(null);
        porAlvo(null);
        fazer({ accao: 'reordenar', ids });
    };

    const bloco = estado.blocos.find((b) => b.id === seleccionado) ?? null;
    const nomeDoTipo = (tipo: string) => catalogo.find((c) => c.tipo === tipo)?.nome ?? tipo;
    const iconeDoTipo = (tipo: string) => catalogo.find((c) => c.tipo === tipo)?.icone ?? 'fa-square';

    return (
        <div className="space-y-3" data-editor-de-modelo>
            {/* A BARRA DE TOPO DO EDITOR, como no ecrã de sempre: o nome do
                modelo, e à direita o que se faz com ele. */}
            <Faixa
                icone="fa-pen-ruler"
                titulo={estado.nome || t('Modelo sem nome')}
                subtitulo={estado.descricao || undefined}
                accoes={
                    <>
                        <a href="/invoicing/sales/quote-templates" className={ACCAO_DA_FAIXA}><i className="fas fa-arrow-left" aria-hidden="true" />{t('Modelos')}</a>
                        <button type="button" onClick={() => porMostrarVariaveis((v) => !v)} aria-pressed={mostrarVariaveis} className={ACCAO_DA_FAIXA}><i className="fas fa-code" aria-hidden="true" />{t('Variáveis')}</button>
                        <button type="button" onClick={() => fazer({ accao: 'pagina' })} className={ACCAO_DA_FAIXA}><i className="fas fa-file-circle-plus" aria-hidden="true" />{t('Página (:n)', { n: estado.paginas })}</button>
                        <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={accao.isPending} onClick={() => fazer({ accao: 'guardar' })}>{t('Guardar')}</Botao>
                    </>
                }
            />

            {recado && <p role="status" className={cls('border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900', RAIO)}><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</p>}
            <AvisoDeErro erro={accao.error} />

            <Cartao titulo={t('Identificação')} icone="fa-tag">
                <div className="flex flex-wrap items-end gap-3">
                    <Campo etiqueta={t('Nome do modelo')} className="min-w-[16rem] flex-1"><input key={`nome-${estado.id}`} defaultValue={estado.nome} onBlur={(e) => e.target.value !== estado.nome && fazer({ accao: 'renomear', nome: e.target.value, descricao: estado.descricao })} className={entrada} /></Campo>
                    <Campo etiqueta={t('Descrição')} className="min-w-[16rem] flex-1"><input key={`desc-${estado.id}`} defaultValue={estado.descricao} onBlur={(e) => e.target.value !== estado.descricao && fazer({ accao: 'renomear', nome: estado.nome, descricao: e.target.value })} className={entrada} /></Campo>
                </div>
                {mostrarVariaveis && (
                    <div className="mt-4 grid gap-3 text-xs sm:grid-cols-3" data-variaveis>
                        {Object.entries(variaveis).map(([grupo, lista]) => (
                            <div key={grupo}><p className="mb-1 font-semibold uppercase tracking-wider text-slate-500">{grupo}</p><ul className="space-y-0.5">{Object.entries(lista).map(([v, r]) => <li key={v}><code className="rounded bg-slate-100 px-1 text-[11px] text-indigo-700">{v}</code> <span className="text-slate-600">{r}</span></li>)}</ul></div>
                        ))}
                    </div>
                )}
            </Cartao>

            <div className="grid gap-3 lg:grid-cols-[16rem_1fr_20rem]">
                <div className="space-y-3">
                    <Cartao titulo={t('Acrescentar')} icone="fa-plus">
                        <div className="grid grid-cols-2 gap-1">
                            {catalogo.map((c) => (
                                <button key={c.tipo} type="button" title={c.ajuda} onClick={() => fazer({ accao: 'adicionar', tipo: c.tipo })} className={cls('group flex items-center gap-2 border border-slate-200 px-2 py-1.5 text-left text-xs font-semibold text-slate-700', RAIO, FOCO, 'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-indigo-50/40 hover:shadow-sm')} data-adicionar={c.tipo}>
                                    <i className={cls('fas w-4 text-center text-slate-400 transition-colors duration-200 group-hover:text-indigo-600', c.icone)} aria-hidden="true" />{c.nome}
                                </button>
                            ))}
                        </div>
                    </Cartao>
                    <Cartao titulo={t('Blocos')} icone="fa-layer-group" semPadding>
                        {/* ARRASTAR PARA ORDENAR.
                            O editor em Blade reordenava a arrastar, e passar
                            para setas foi um passo atrás: mover um bloco do
                            fim para o princípio pedia doze cliques. As setas
                            ficam — são o caminho de quem usa teclado, e o
                            arrastar não é acessível. A ordem final é UMA
                            viagem ao servidor (`reordenar`), não uma por
                            passo. */}
                        {estado.blocos.length === 0 && (
                            <SemNada icone="fa-layer-group" titulo={t('Sem secções')} frase={t('Acrescente uma acima.')} />
                        )}
                        <ol className="divide-y divide-slate-100" data-blocos onDragOver={(e) => e.preventDefault()}>
                            {estado.blocos.map((b, i) => (
                                <li
                                    key={b.id}
                                    draggable
                                    style={cascata(i)}
                                    onDragStart={() => porArrastado(b.id)}
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        if (arrastado && arrastado !== b.id) porAlvo(b.id);
                                    }}
                                    onDragEnd={largar}
                                    onDrop={largar}
                                    className={cls(
                                        'entra group flex cursor-move items-center gap-1 px-2 py-1.5 text-sm transition-all duration-200',
                                        b.id === seleccionado ? 'bg-indigo-50 ring-1 ring-inset ring-indigo-200' : 'hover:bg-slate-50',
                                        b.id === alvo && arrastado !== b.id && 'border-t-2 border-indigo-400',
                                        b.id === arrastado && 'opacity-50',
                                    )}
                                >
                                    <i className="fas fa-grip-vertical cursor-grab text-xs text-slate-300" aria-hidden="true" />
                                    <i className={cls('fas w-4 text-center text-xs', iconeDoTipo(b.tipo), b.id === seleccionado ? 'text-indigo-500' : 'text-slate-400')} aria-hidden="true" />
                                    <button type="button" onClick={() => porSeleccionado(b.id)} className={cls('flex-1 truncate text-left', FOCO, RAIO)} aria-current={b.id === seleccionado}>
                                        <span className="mr-1 text-xs text-slate-400">{i + 1}.</span>{nomeDoTipo(b.tipo)}{typeof b.titulo === 'string' && b.titulo && <span className="ml-1 text-xs text-slate-500">· {b.titulo}</span>}
                                    </button>
                                    {/* Os botões da linha só aparecem ao passar
                                        — mas também ao chegar por TECLADO, que
                                        é o que o ecrã em Blade não fazia:
                                        `hidden group-hover:flex` deixava quem
                                        navega por Tab sem os alcançar. */}
                                    <span className="flex items-center gap-0.5 opacity-0 transition-opacity duration-200 focus-within:opacity-100 group-hover:opacity-100">
                                        <button type="button" onClick={() => fazer({ accao: 'mover', id: b.id, direccao: -1 })} aria-label={t('Subir')} className={cls('p-1 text-slate-400 hover:text-slate-800', FOCO, RAIO)}><i className="fas fa-chevron-up" aria-hidden="true" /></button>
                                        <button type="button" onClick={() => fazer({ accao: 'mover', id: b.id, direccao: 1 })} aria-label={t('Descer')} className={cls('p-1 text-slate-400 hover:text-slate-800', FOCO, RAIO)}><i className="fas fa-chevron-down" aria-hidden="true" /></button>
                                        <button type="button" onClick={() => fazer({ accao: 'duplicar', id: b.id })} aria-label={t('Duplicar')} className={cls('p-1 text-slate-400 hover:text-slate-800', FOCO, RAIO)}><i className="fas fa-copy" aria-hidden="true" /></button>
                                        <button type="button" onClick={() => fazer({ accao: 'remover', id: b.id })} aria-label={t('Remover')} className={cls('p-1 text-slate-400 hover:text-red-600', FOCO, RAIO)}><i className="fas fa-trash" aria-hidden="true" /></button>
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </Cartao>
                </div>

                <Cartao titulo={t('A folha, como vai sair')} icone="fa-file-lines" semPadding>
                    <iframe title={t('Pré-visualização do modelo')} srcDoc={previa} className={cls('h-[80vh] w-full bg-slate-100 transition-opacity duration-200', accao.isPending && 'opacity-60')} data-previa />
                </Cartao>

                <div className="space-y-3">
                    {bloco
                        ? <OpcoesDoBloco key={bloco.id} bloco={bloco} nome={nomeDoTipo(bloco.tipo)} icone={iconeDoTipo(bloco.tipo)} fazer={fazer} />
                        : <Cartao titulo={t('Bloco')} icone="fa-square"><SemNada icone="fa-hand-pointer" titulo={t('Nenhum bloco escolhido')} frase={t('Escolha um bloco na lista.')} /></Cartao>}
                    <Estilos estilos={estado.estilos} fazer={fazer} />
                </div>
            </div>
        </div>
    );
}

/** As opções do bloco, geradas das chaves que ele tem: cada tipo traz as suas. */
function OpcoesDoBloco({ bloco, nome, icone, fazer }: { bloco: Bloco; nome: string; icone: string; fazer: (c: Record<string, unknown>) => void }) {
    const campos = Object.entries(bloco).filter(([k]) => !['id', 'tipo', 'layout'].includes(k));
    const l = bloco.layout;
    const campo = (chave: string, valor: unknown) => fazer({ accao: 'campo', id: bloco.id, campo: chave, valor });
    const layout = (chave: string, valor: number | boolean) => l && fazer({ accao: 'layout', id: bloco.id, layout: { ...l, [chave]: valor } });

    return (
        <Cartao titulo={nome} icone={icone}>
            <div className="space-y-3" data-opcoes>
                {campos.length === 0 && <p className="text-sm text-slate-400">{t('Este bloco não tem opções.')}</p>}
                {campos.map(([k, v]) => {
                    const rotulo = k.replace(/_/g, ' ');
                    if (typeof v === 'boolean') {
                        return <label key={k} className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={v} onChange={(e) => campo(k, e.target.checked)} className="h-4 w-4 rounded border-slate-300" />{rotulo}</label>;
                    }
                    if (typeof v === 'number') {
                        return <Campo key={k} etiqueta={rotulo}><input type="number" defaultValue={v} onBlur={(e) => Number(e.target.value) !== v && campo(k, Number(e.target.value))} className={entrada} /></Campo>;
                    }
                    const texto = typeof v === 'string' ? v : JSON.stringify(v);
                    const longo = k === 'html' || k === 'texto' || k === 'linhas' || texto.length > 60;
                    return (
                        <Campo key={k} etiqueta={rotulo}>
                            {longo
                                ? <textarea defaultValue={texto} rows={4} onBlur={(e) => e.target.value !== texto && campo(k, e.target.value)} className={cls(entrada, 'font-mono text-xs')} />
                                : <input defaultValue={texto} onBlur={(e) => e.target.value !== texto && campo(k, e.target.value)} className={entrada} />}
                        </Campo>
                    );
                })}
                {l && (
                    <fieldset className="border-t border-slate-100 pt-3">
                        <legend className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Posição na folha')}</legend>
                        <div className="mt-2 grid grid-cols-3 gap-2">
                            {(['pagina', 'x', 'y', 'largura', 'altura', 'z'] as const).map((k) => (
                                <Campo key={k} etiqueta={k}><input type="number" defaultValue={l[k]} onBlur={(e) => Number(e.target.value) !== l[k] && layout(k, Number(e.target.value))} className={cls(entrada, 'tabular-nums')} /></Campo>
                            ))}
                        </div>
                        <label className="mt-2 flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={l.bloqueado} onChange={(e) => layout('bloqueado', e.target.checked)} className="h-4 w-4 rounded border-slate-300" />{t('Bloqueado')}</label>
                    </fieldset>
                )}
            </div>
        </Cartao>
    );
}

function Estilos({ estilos, fazer }: { estilos: Record<string, unknown>; fazer: (c: Record<string, unknown>) => void }) {
    const estilo = (chave: string, valor: unknown) => fazer({ accao: 'estilo', chave, valor });
    const s = (k: string) => String(estilos[k] ?? '');

    return (
        <Cartao titulo={t('Estilos')} icone="fa-palette">
            <div className="space-y-3" data-estilos>
                <Campo etiqueta={t('Cor principal')}><input type="color" defaultValue={s('cor_principal') || '#4f46e5'} onBlur={(e) => e.target.value !== s('cor_principal') && estilo('cor_principal', e.target.value)} className={cls(entrada, 'h-10 p-1')} /></Campo>
                <Campo etiqueta={t('Fonte')}><input defaultValue={s('fonte')} onBlur={(e) => e.target.value !== s('fonte') && estilo('fonte', e.target.value)} className={entrada} /></Campo>
                <Campo etiqueta={t('Tamanho base')}><input type="number" min={8} max={20} defaultValue={Number(estilos.tamanho_base ?? 12)} onBlur={(e) => Number(e.target.value) !== Number(estilos.tamanho_base) && estilo('tamanho_base', Number(e.target.value))} className={entrada} /></Campo>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={Boolean(estilos.mostrar_rodape)} onChange={(e) => estilo('mostrar_rodape', e.target.checked)} className="h-4 w-4 rounded border-slate-300" />{t('Mostrar rodapé')}</label>
                <Campo etiqueta={t('Texto do rodapé')}><input defaultValue={s('texto_rodape')} onBlur={(e) => e.target.value !== s('texto_rodape') && estilo('texto_rodape', e.target.value)} className={entrada} /></Campo>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={Boolean(estilos.numerar_paginas)} onChange={(e) => estilo('numerar_paginas', e.target.checked)} className="h-4 w-4 rounded border-slate-300" />{t('Numerar páginas')}</label>
            </div>
        </Cartao>
    );
}
