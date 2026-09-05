import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    produtos,
    type Artigo,
    type ArtigoParaGravar,
    type FiltrosDeArtigos,
    type OpcoesDosArtigos,
} from '@/api/produtos';
import { ErroDaApi } from '@/api/cliente';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Modal } from '@/ui/Modal';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * OS ARTIGOS.
 *
 * A regra que este ecrã tem de respeitar e que o Livewire aprendeu à sua
 * custa: **o stock não se edita aqui**. É um agregado das linhas de stock, e
 * escrevê-lo numa ficha devolvia o valor que estava no ecrã quando ele abriu —
 * revertendo as vendas que aconteceram entretanto. Por isso a quantidade só
 * aparece na CRIAÇÃO; a editar, o campo nem existe e o servidor ignora-o.
 *
 * O que este ecrã ainda NÃO faz, dito à cara para ninguém o descobrir tarde:
 * imagens e os campos de sector (medicamento, vestuário, cosmética). Para
 * isso, o ecrã de sempre continua na morada de sempre.
 */

const VAZIO: ArtigoParaGravar = {
    name: '',
    type: 'produto',
    description: '',
    sku: '',
    barcode: '',
    price: '',
    cost: '',
    unit: 'un',
    category_id: '',
    tax_type: 'iva',
    tax_rate_id: '',
    exemption_reason: '',
    manage_stock: true,
    stock_min: '',
    stock_max: '',
    is_active: true,
    stock_quantity: 0,
};

export default function Produtos() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDeArtigos>({ procura: '', page: 1 });
    const [aEditar, porAEditar] = useState<Artigo | null>(null);
    const [formulario, porFormulario] = useState<ArtigoParaGravar | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Artigo | null>(null);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({
        queryKey: ['produtos', 'opcoes'],
        queryFn: produtos.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['produtos', filtros],
        queryFn: () => produtos.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const gravar = useMutation({
        mutationFn: (d: ArtigoParaGravar) =>
            aEditar ? produtos.guardar(aEditar.id, d) : produtos.criar(d),
        onSuccess: () => {
            void cache.invalidateQueries({ queryKey: ['produtos'] });
            porFormulario(null);
            porAEditar(null);
            porErros({});
            porRecado(aEditar ? 'Artigo guardado.' : 'Artigo criado.');
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const apagar = useMutation({
        mutationFn: (a: Artigo) => produtos.apagar(a.id),
        onSuccess: (r) => {
            void cache.invalidateQueries({ queryKey: ['produtos'] });
            porAApagar(null);
            // O servidor diz o que fez: apagou, ou desactivou porque já foi
            // vendido. São coisas diferentes e a pessoa tem de saber qual.
            porRecado(r.message);
        },
    });

    const permissoes = opcoes.data?.permissoes;

    if (lista.isError) {
        return <Falhou erro={lista.error} />;
    }

    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    function abrirNovo() {
        porAEditar(null);
        porErros({});
        porFormulario({ ...VAZIO });
    }

    function abrirEdicao(a: Artigo) {
        porAEditar(a);
        porErros({});
        porFormulario({
            name: a.name,
            type: a.type,
            description: a.description ?? '',
            sku: a.sku ?? '',
            barcode: a.barcode ?? '',
            price: a.price,
            cost: a.cost ?? '',
            unit: a.unit,
            category_id: a.category_id ?? '',
            tax_type: a.tax_type,
            tax_rate_id: a.tax_rate_id ?? '',
            exemption_reason: a.exemption_reason ?? '',
            manage_stock: a.manage_stock,
            stock_min: a.stock_min ?? '',
            stock_max: a.stock_max ?? '',
            is_active: a.is_active,
        });
    }

    return (
        <div className="space-y-4">
            {recado && (
                <div
                    role="status"
                    className={cls('flex items-start justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}
                >
                    <span>
                        <i className="fas fa-circle-check mr-2" aria-hidden="true" />
                        {recado}
                    </span>
                    <button type="button" onClick={() => porRecado('')} aria-label="Fechar aviso">
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <Cartao
                titulo="Artigos"
                accoes={
                    permissoes?.pode_criar && (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                            Novo artigo
                        </Botao>
                    )
                }
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block lg:col-span-2">
                        <Rotulo>Procurar</Rotulo>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder="Nome, código, SKU ou código de barras"
                            className={entrada}
                        />
                    </label>

                    <label className="block">
                        <Rotulo>Tipo</Rotulo>
                        <select
                            value={filtros.tipo ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, tipo: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">Todos</option>
                            <option value="produto">Produto</option>
                            <option value="servico">Serviço</option>
                        </select>
                    </label>

                    <label className="block">
                        <Rotulo>Categoria</Rotulo>
                        <select
                            value={filtros.categoria ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, categoria: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">Todas</option>
                            {opcoes.data?.categorias.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {contas ? `${contas.total.toLocaleString('pt-PT')} artigo(s)` : 'A contar…'}
                        {lista.isFetching && <span className="ml-2 text-xs">a actualizar…</span>}
                    </p>
                    <div className="flex items-center gap-2">
                        <Botao
                            altura="pequeno"
                            cor={filtros.so_em_falta === '1' ? 'aviso' : 'neutra'}
                            tom={filtros.so_em_falta === '1' ? 'solida' : 'suave'}
                            icone="fa-triangle-exclamation"
                            onClick={() =>
                                porFiltros((f) => ({
                                    ...f,
                                    so_em_falta: f.so_em_falta === '1' ? '' : '1',
                                    page: 1,
                                }))
                            }
                        >
                            Só os que estão em falta
                        </Botao>
                        <Botao
                            altura="pequeno"
                            icone="fa-eraser"
                            onClick={() => porFiltros({ procura: '', page: 1 })}
                        >
                            Limpar
                        </Botao>
                    </div>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'px-6 py-14 text-center')}>
                    <i className="fas fa-box-open mb-3 text-4xl text-slate-300" aria-hidden="true" />
                    <p className="font-semibold text-slate-700">Nenhum artigo com estes filtros</p>
                </div>
            ) : (
                <Cartao semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">Artigo</th>
                                    <th className="px-4 py-3 font-semibold">Categoria</th>
                                    <th className="px-4 py-3 font-semibold">Imposto</th>
                                    <th className="px-4 py-3 text-right font-semibold">Preço</th>
                                    <th className="px-4 py-3 text-right font-semibold">Stock</th>
                                    <th className="px-4 py-3 font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Acções</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((a) => (
                                    <tr key={a.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-slate-800">{a.name}</div>
                                            <div className="text-xs text-slate-400">
                                                {[a.code, a.sku].filter(Boolean).join(' · ') || '—'}
                                                {a.type === 'servico' && ' · serviço'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {a.category?.name ?? <span className="text-slate-300">—</span>}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {a.tax_type === 'isento' ? (
                                                <span className="text-xs">Isento · {a.exemption_reason ?? '—'}</span>
                                            ) : (
                                                <span className="tabular-nums">{a.taxa ?? '—'}%</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                                            {kz(a.price)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {/* Um serviço não tem stock. Mostrar «0» seria mentira. */}
                                            {a.stock === null ? (
                                                <span className="text-slate-300">—</span>
                                            ) : (
                                                <span
                                                    className={cls(
                                                        a.esgotado
                                                            ? 'font-bold text-red-600'
                                                            : a.em_falta
                                                              ? 'font-bold text-amber-600'
                                                              : 'text-slate-700',
                                                    )}
                                                >
                                                    {kz(a.stock, 0)}
                                                    {a.stock_min ? (
                                                        <span className="ml-1 text-xs text-slate-400">
                                                            /{a.stock_min}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.is_active ? (
                                                <Etiqueta cor="bom">Activo</Etiqueta>
                                            ) : (
                                                <Etiqueta cor="neutra">Desactivado</Etiqueta>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {permissoes?.pode_editar && (
                                                    <button
                                                        type="button"
                                                        onClick={() => abrirEdicao(a)}
                                                        title="Editar"
                                                        aria-label={`Editar ${a.name}`}
                                                        className={cls('p-2 text-slate-500 transition hover:bg-slate-100', RAIO, FOCO)}
                                                    >
                                                        <i className="fas fa-pen" aria-hidden="true" />
                                                    </button>
                                                )}
                                                {permissoes?.pode_apagar && (
                                                    <button
                                                        type="button"
                                                        onClick={() => porAApagar(a)}
                                                        title="Apagar"
                                                        aria-label={`Apagar ${a.name}`}
                                                        className={cls('p-2 text-red-500 transition hover:bg-red-50', RAIO, FOCO)}
                                                    >
                                                        <i className="fas fa-trash" aria-hidden="true" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {contas && contas.last_page > 1 && (
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label="Páginas">
                    <Botao
                        icone="fa-chevron-left"
                        altura="pequeno"
                        disabled={contas.current_page <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}
                    >
                        Anterior
                    </Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        Página {contas.current_page} de {contas.last_page}
                    </span>
                    <Botao
                        icone="fa-chevron-right"
                        altura="pequeno"
                        disabled={contas.current_page >= contas.last_page}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}
                    >
                        Seguinte
                    </Botao>
                </nav>
            )}

            <Formulario
                dados={formulario}
                aEditar={aEditar}
                erros={erros}
                aGravar={gravar.isPending}
                erroDeGravar={gravar.error}
                opcoes={opcoes.data}
                aoMudar={porFormulario}
                aoFechar={() => {
                    porFormulario(null);
                    porAEditar(null);
                    porErros({});
                }}
                aoGravar={(d) => gravar.mutate(d)}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo="Apagar artigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>Cancelar</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}
                        >
                            Apagar
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    Apagar <strong>{aApagar?.name}</strong>?
                </p>
                {/* Um artigo já vendido não desaparece: fica desactivado, porque
                    a linha da factura aponta para ele. Dizer isto ANTES evita a
                    surpresa de carregar em «Apagar» e ver o artigo continuar lá. */}
                <p className="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Se já tiver sido vendido, é <strong>desactivado</strong> em vez de apagado — os
                    documentos antigos apontam para ele e não podem ficar sem artigo.
                </p>
                {apagar.isError && (
                    <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800">
                        {apagar.error instanceof ErroDaApi ? apagar.error.message : 'Não foi possível apagar.'}
                    </p>
                )}
            </Modal>
        </div>
    );
}

/* ─── O formulário ────────────────────────────────────────────────────── */

function Formulario({
    dados,
    aEditar,
    erros,
    aGravar,
    erroDeGravar,
    opcoes,
    aoMudar,
    aoFechar,
    aoGravar,
}: {
    dados: ArtigoParaGravar | null;
    aEditar: Artigo | null;
    erros: Record<string, string[]>;
    aGravar: boolean;
    erroDeGravar: unknown;
    opcoes?: OpcoesDosArtigos;
    aoMudar: (d: ArtigoParaGravar) => void;
    aoFechar: () => void;
    aoGravar: (d: ArtigoParaGravar) => void;
}) {
    if (!dados) {
        return null;
    }

    const campo = <K extends keyof ArtigoParaGravar>(chave: K, valor: ArtigoParaGravar[K]) =>
        aoMudar({ ...dados, [chave]: valor });

    const eServico = dados.type === 'servico';

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={aEditar ? `Editar ${aEditar.name}` : 'Novo artigo'}
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>Cancelar</Botao>
                    <Botao
                        cor="primaria"
                        tom="solida"
                        icone="fa-check"
                        aTrabalhar={aGravar}
                        onClick={() => aoGravar(dados)}
                    >
                        Guardar
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroDeGravar} />

            <form
                className="grid gap-4 sm:grid-cols-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    aoGravar(dados);
                }}
            >
                <Campo etiqueta="Nome" erro={erros.name} obrigatorio className="sm:col-span-2">
                    <input value={dados.name} onChange={(e) => campo('name', e.target.value)} className={entrada} />
                </Campo>

                <Campo etiqueta="Tipo" erro={erros.type} obrigatorio>
                    <select
                        value={dados.type}
                        onChange={(e) => {
                            const t = e.target.value as ArtigoParaGravar['type'];
                            // Um serviço não gere stock. Muda-se aqui para o
                            // ecrã dizer a verdade antes de o servidor a impor.
                            aoMudar({ ...dados, type: t, manage_stock: t === 'produto' });
                        }}
                        className={entrada}
                    >
                        <option value="produto">Produto</option>
                        <option value="servico">Serviço</option>
                    </select>
                </Campo>

                <Campo etiqueta="Categoria" erro={erros.category_id} obrigatorio>
                    <select
                        value={String(dados.category_id ?? '')}
                        onChange={(e) => campo('category_id', e.target.value)}
                        className={entrada}
                    >
                        <option value="">Escolher…</option>
                        {opcoes?.categorias.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </Campo>

                <Campo etiqueta="Unidade" erro={erros.unit} obrigatorio>
                    <select value={dados.unit} onChange={(e) => campo('unit', e.target.value)} className={entrada}>
                        {opcoes?.unidades.map((u) => (
                            <option key={u} value={u}>
                                {u}
                            </option>
                        ))}
                    </select>
                </Campo>

                <Campo etiqueta="SKU" erro={erros.sku}>
                    <input value={dados.sku ?? ''} onChange={(e) => campo('sku', e.target.value)} className={entrada} />
                </Campo>

                <Campo etiqueta="Preço" erro={erros.price} obrigatorio>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={dados.price}
                        onChange={(e) => campo('price', e.target.value)}
                        className={cls(entrada, 'text-right tabular-nums')}
                    />
                </Campo>

                <Campo etiqueta="Custo" erro={erros.cost}>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={dados.cost ?? ''}
                        onChange={(e) => campo('cost', e.target.value)}
                        className={cls(entrada, 'text-right tabular-nums')}
                    />
                </Campo>

                <Campo etiqueta="Código de barras" erro={erros.barcode}>
                    <input
                        value={dados.barcode ?? ''}
                        onChange={(e) => campo('barcode', e.target.value)}
                        className={entrada}
                    />
                </Campo>

                {/* O imposto sai do catálogo da empresa, nunca escrito à mão:
                    o regime fiscal já afinou as taxas. */}
                <Campo etiqueta="Imposto" erro={erros.tax_type} obrigatorio>
                    <select
                        value={dados.tax_type}
                        onChange={(e) => campo('tax_type', e.target.value as ArtigoParaGravar['tax_type'])}
                        className={entrada}
                    >
                        <option value="iva">IVA</option>
                        <option value="isento">Isento</option>
                    </select>
                </Campo>

                {dados.tax_type === 'iva' ? (
                    <Campo etiqueta="Taxa" erro={erros.tax_rate_id} obrigatorio className="sm:col-span-2">
                        <select
                            value={String(dados.tax_rate_id ?? '')}
                            onChange={(e) => campo('tax_rate_id', e.target.value)}
                            className={entrada}
                        >
                            <option value="">Escolher…</option>
                            {opcoes?.taxas.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name} — {t.rate}%
                                </option>
                            ))}
                        </select>
                    </Campo>
                ) : (
                    <Campo
                        etiqueta="Motivo da isenção"
                        erro={erros.exemption_reason}
                        obrigatorio
                        className="sm:col-span-2"
                    >
                        <input
                            value={dados.exemption_reason ?? ''}
                            onChange={(e) => campo('exemption_reason', e.target.value)}
                            placeholder="Ex.: M99"
                            className={entrada}
                        />
                    </Campo>
                )}

                {!eServico && (
                    <>
                        <Campo etiqueta="Stock mínimo" erro={erros.stock_min}>
                            <input
                                type="number"
                                min="0"
                                value={dados.stock_min ?? ''}
                                onChange={(e) => campo('stock_min', e.target.value)}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                        </Campo>

                        <Campo etiqueta="Stock máximo" erro={erros.stock_max}>
                            <input
                                type="number"
                                min="0"
                                value={dados.stock_max ?? ''}
                                onChange={(e) => campo('stock_max', e.target.value)}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                        </Campo>

                        {/* A QUANTIDADE SÓ EXISTE AO CRIAR.
                            A editar, o stock é o que as linhas dizem — mexer nele
                            aqui revertia vendas feitas entretanto. Ajusta-se na
                            Gestão de Stock, com movimento registado. */}
                        {aEditar ? (
                            <div className="sm:col-span-1">
                                <Rotulo>Stock actual</Rotulo>
                                <p className="flex h-10 items-center rounded-xl bg-slate-50 px-3 text-sm tabular-nums text-slate-600">
                                    {aEditar.stock ?? '—'}
                                    <span className="ml-2 text-xs text-slate-400">
                                        ajusta-se na Gestão de Stock
                                    </span>
                                </p>
                            </div>
                        ) : (
                            <Campo etiqueta="Quantidade inicial" erro={erros.stock_quantity}>
                                <input
                                    type="number"
                                    min="0"
                                    value={dados.stock_quantity ?? 0}
                                    onChange={(e) => campo('stock_quantity', e.target.value)}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        )}
                    </>
                )}

                <Campo etiqueta="Descrição" erro={erros.description} className="sm:col-span-3">
                    <textarea
                        rows={2}
                        value={dados.description ?? ''}
                        onChange={(e) => campo('description', e.target.value)}
                        className={cls(entrada, 'h-auto py-2')}
                    />
                </Campo>

                <label className="flex items-center gap-2 text-sm text-slate-700 sm:col-span-3">
                    <input
                        type="checkbox"
                        checked={dados.is_active}
                        onChange={(e) => campo('is_active', e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                    />
                    Activo — aparece no POS e nas listas
                </label>
            </form>
        </Modal>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */




function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">A sessão expirou</h2>
                <p className="mb-4 text-sm text-amber-900">Entre outra vez para continuar. Nada se perdeu.</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    Voltar a entrar
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível carregar os artigos</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? 'Verifique a ligação e tente outra vez.'}</p>
        </div>
    );
}
