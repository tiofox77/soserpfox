import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { catalogos, type Campo, type Coluna, type FiltrosDoCatalogo, type Linha, type OpcoesDoCatalogo } from '@/api/catalogos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo as CampoDoFormulario, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { IntervaloDeDatas, PorPagina } from '@/ui/FiltrosComuns';
import { ACCAO_DA_FAIXA, Faixa, type TomDaFaixa } from './faixa';
import { Dado, JanelaDoExtrato, Seccao } from './ExtratoDaParte';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t, tPartes } from '@/i18n';

/**
 * UM CATÁLOGO — fornecedores, categorias, marcas, armazéns, condições de
 * pagamento ou impostos. O `tipo` vem nas props, do Blade; tudo o resto vem
 * do servidor como esquema: as colunas, os campos do formulário, os filtros,
 * as acções. Este ecrã não sabe o que é um fornecedor — sabe desenhar uma
 * tabela e um formulário a partir de um esquema, e é isso que faz com que
 * seis ecrãs Livewire caibam num só.
 *
 * QUEM DECIDE CONTINUA A SER O SERVIDOR: a permissão de escrever vem nas
 * opções, o `pode_apagar` de cada linha vem contado de lá, e a validação é a
 * mesma do Livewire. Esconder um botão aqui é conveniência, nunca segurança.
 */

type Valores = Record<string, string | number | boolean | null>;

/**
 * A cor da faixa, traduzida para o tom do cartão de número.
 *
 * São duas paletas com nomes próprios: a faixa é um gradiente largo, o cartão é
 * um quadrado de ícone. A correspondência fica aqui e não interpolada — sem
 * build, o Tailwind do browser não gera uma classe montada em tempo de
 * execução, e uma cor desconhecida cai no índigo em vez de sair sem cor.
 */
const TOM_DO_CARTAO: Record<string, TomDoCartao> = {
    primaria: 'indigo',
    neutra: 'cinza',
    bom: 'verde',
    aviso: 'ambar',
    perigo: 'vermelho',
    laranja: 'laranja',
    roxo: 'roxo',
    ciano: 'azul',
    rosa: 'roxo',
};

export default function Catalogo({ tipo }: { tipo: string }) {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDoCatalogo>({ procura: '', page: 1 });
    const [aEditar, porAEditar] = useState<Linha | null>(null);
    const [formulario, porFormulario] = useState<Valores | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Linha | null>(null);
    const [recado, porRecado] = useState('');
    /** A linha cuja FICHA está aberta — só nos catálogos que têm extrato. */
    const [aVer, porAVer] = useState<Linha | null>(null);

    const opcoes = useQuery({ queryKey: ['catalogo', tipo, 'opcoes'], queryFn: () => catalogos.opcoes(tipo), staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['catalogo', tipo, filtros], queryFn: () => catalogos.lista(tipo, filtros), placeholderData: keepPreviousData });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['catalogo', tipo] });

    const gravar = useMutation({
        mutationFn: (dados: Valores) => (aEditar ? catalogos.guardar(tipo, aEditar.id, dados) : catalogos.criar(tipo, dados)),
        onSuccess: (r) => { invalidar(); porFormulario(null); porAEditar(null); porErros({}); porRecado(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const apagar = useMutation({
        mutationFn: (l: Linha) => catalogos.apagar(tipo, l.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    const accao = useMutation({
        mutationFn: ({ l, qual }: { l: Linha; qual: 'activar' | 'padrao' }) => catalogos.accao(tipo, l.id, qual),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const logotipo = useMutation({
        mutationFn: ({ l, ficheiro }: { l: Linha; ficheiro: File }) => catalogos.logotipo(tipo, l.id, ficheiro),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) return <Falhou erro={opcoes.error ?? lista.error} />;

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    const vazio = (): Valores => Object.fromEntries(o.campos.map((c) => [c.chave, c.omissao ?? (c.tipo === 'booleano' ? false : '')]));

    const abrirNovo = () => { porAEditar(null); porErros({}); porFormulario(vazio()); };
    const abrirEdicao = (l: Linha) => {
        porAEditar(l); porErros({});
        porFormulario(Object.fromEntries(o.campos.map((c) => {
            const v = l[c.chave];
            if (c.tipo === 'booleano') return [c.chave, Boolean(v)];
            return [c.chave, v === null || v === undefined ? '' : String(v)];
        })));
    };

    return (
        <div className="space-y-4">
            {/* O CABEÇALHO DE SEMPRE, com a COR DESTE catálogo: os fornecedores
                laranja, as categorias ciano, as marcas rosa. Vinha do servidor
                porque o esquema é que sabe de que lista se trata — o ecrã é o
                mesmo para as seis. */}
            <Faixa
                titulo={o.titulo}
                subtitulo={o.descricao || undefined}
                icone={o.icone}
                cor={(o.cor as TomDaFaixa) ?? 'primaria'}
                accoes={
                    o.permissoes.pode_escrever && (
                        <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i
                                className="fas fa-plus transition-transform duration-300 group-hover:rotate-90"
                                aria-hidden="true"
                            />
                            {o.novo}
                        </button>
                    )
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={apagar.error ?? accao.error ?? logotipo.error} />

            {/* A FICHA DO FORNECEDOR — o modal de ver que o ecrã em Blade tinha
                e a migração não trouxe: as contas do que já lhe comprámos, as
                últimas facturas, o que mais lhe compramos e a frequência. */}
            {o.extrato && (
                <JanelaDoExtrato
                    aberto={aVer !== null}
                    aoFechar={() => porAVer(null)}
                    titulo={String(aVer?.name ?? '')}
                    subtitulo={aVer?.nif ? String(aVer.nif) : undefined}
                    icone={o.icone}
                    cor={(o.cor as TomDaFaixa) ?? 'primaria'}
                    caminho={`/catalogos/${tipo}/${aVer?.id}/extrato`}
                    chave={['catalogo', tipo, 'extrato', aVer?.id]}
                    rotulos={{
                        facturado: t('Comprado'),
                        documentos: t('Faturas'),
                        artigos: t('Top 10 Produtos Comprados a Este Fornecedor'),
                        semDocumentos: t('Sem faturas registadas'),
                    }}
                    moradaDoDocumento={(id) => `/invoicing/purchases/invoices/${id}`}
                    podeEditar={o.permissoes.pode_escrever}
                    aoEditar={() => {
                        const l = aVer;
                        porAVer(null);
                        if (l) abrirEdicao(l);
                    }}
                    ficha={aVer && <FichaDaLinha linha={aVer} colunas={o.campos} />}
                />
            )}

            {/* OS CARTÕES DO TOPO. A contagem é a do servidor, com os filtros
                postos; o que está contado nas linhas à vista di-lo no cartão. */}
            <div className={cls('grid gap-3 sm:grid-cols-2', o.accoes.activar ? 'lg:grid-cols-3' : 'lg:grid-cols-2', lista.isFetching && 'opacity-70')}>
                <CartaoNumero
                    aspecto="claro"
                    rotulo={o.titulo}
                    tom={TOM_DO_CARTAO[o.cor] ?? 'indigo'}
                    icone={o.icone}
                    nota={t('com os filtros actuais')}
                    valor={contas === undefined ? '—' : contas.total.toLocaleString(etiquetaIntl())}
                />
                <CartaoNumero
                    aspecto="claro"
                    rotulo={t('Nesta página')}
                    tom="cinza"
                    icone="fa-list"
                    nota={contas ? t('Página :actual de :total', { actual: contas.current_page, total: contas.last_page }) : undefined}
                    valor={linhas.length.toLocaleString(etiquetaIntl())}
                />
                {/* Só onde o activar/desactivar existe: nos outros a coluna não
                    é sequer oferecida, e um cartão a dizer sempre o mesmo é ruído. */}
                {o.accoes.activar && (
                    <CartaoNumero
                        aspecto="claro"
                        rotulo={t('Activos nesta página')}
                        tom="verde"
                        icone="fa-toggle-on"
                        valor={linhas.filter((l) => l.is_active !== false).length.toLocaleString(etiquetaIntl())}
                        nota={t(':quantos nesta página', { quantos: linhas.length })}
                    />
                )}
            </div>

            {/* Sem botão de criar aqui: ele vive NA FAIXA, como no ecrã de
                sempre — dois botões iguais na mesma página não ajudam. */}
            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="flex flex-wrap items-end gap-3">
                    <label className="flex-1 min-w-[16rem] text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input value={filtros.procura ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={o.pesquisa} className={entrada} />
                    </label>
                    {o.filtros.map((f) => (
                        <label key={f.chave} className="text-sm">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{f.rotulo}</span>
                            {/* UM FILTRO ESCRITO À MÃO ou uma lista fechada — é o
                                esquema do servidor que o diz. A cidade dos
                                fornecedores era um campo de texto no ecrã de
                                sempre, e uma lista de cidades não existe. */}
                            {f.tipo === 'texto' ? (
                                <input
                                    type="search"
                                    value={String(filtros[f.chave] ?? '')}
                                    onChange={(e) => porFiltros((x) => ({ ...x, [f.chave]: e.target.value, page: 1 }))}
                                    placeholder={f.ajuda ?? ''}
                                    className={entrada}
                                />
                            ) : (
                                <select value={String(filtros[f.chave] ?? '')} onChange={(e) => porFiltros((x) => ({ ...x, [f.chave]: e.target.value, page: 1 }))} className={entrada}>
                                    <option value="">{t('Todos')}</option>
                                    {(f.opcoes ?? []).map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
                                </select>
                            )}
                        </label>
                    ))}

                    {/* «Quem entrou este mês» — só nos catálogos onde a pergunta
                        faz sentido, e é o servidor que o declara. */}
                    {o.datas && (
                        <IntervaloDeDatas
                            de={filtros.de}
                            ate={filtros.ate}
                            aoMudar={(campo, valor) => porFiltros((f) => ({ ...f, [campo]: valor, page: 1 }))}
                            className="text-sm"
                        />
                    )}
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {contas
                            ? t(':quantos registo(s)', { quantos: contas.total.toLocaleString(etiquetaIntl()) })
                            : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <PorPagina
                            valor={filtros.por_pagina}
                            aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))}
                        />
                        <Botao altura="pequeno" icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>
                            {t('Limpar')}
                        </Botao>
                    </div>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        {/* O cabeçalho de sempre: fundo cinzento claro e
                            maiúsculas pequenas. Sem ícone por coluna — aqui as
                            colunas vêm do servidor e mudam de catálogo para
                            catálogo, e um ícone adivinhado é pior do que nenhum. */}
                        <thead className="bg-slate-50">
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                {o.accoes.logotipo && <th className="w-12 px-4 py-3"></th>}
                                {o.colunas.map((c) => <th key={c.chave} className={cls('px-4 py-3 font-bold', c.alinhar === 'direita' && 'text-right')}>{c.rotulo}</th>)}
                                <th className="w-40 px-4 py-3 text-right font-bold"><i className="fas fa-gear mr-1.5 text-slate-400" aria-hidden="true" />{t('Acções')}</th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={o.colunas.length + 2} className="px-6 py-16 text-center">
                                        {lista.isPending ? (
                                            <span className="text-slate-400">{t('A carregar…')}</span>
                                        ) : (
                                            /* O ESTADO VAZIO COM DESENHO: o círculo de
                                               80px com o ícone do catálogo lá dentro, e
                                               uma frase que diz o que fazer a seguir —
                                               o «Nada para mostrar.» que aqui estava não
                                               dizia nem o que faltava nem por onde ir. */
                                            <div className="animate-fade-in">
                                                <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                    <i className={cls('fas', o.icone, 'text-4xl text-slate-300')} aria-hidden="true" />
                                                </div>
                                                <p className="text-lg font-bold text-slate-800">{t('Nada para mostrar.')}</p>
                                                <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                                                    {o.permissoes.pode_escrever
                                                        ? t('Limpe a procura, ou crie o(a) primeiro(a) :nome.', { nome: o.singular.toLowerCase() })
                                                        : t('Limpe a procura para ver mais.')}
                                                </p>
                                                {o.permissoes.pode_escrever && (
                                                    <div className="mt-5 flex justify-center">
                                                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                                                            {o.novo}
                                                        </Botao>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((l, i) => (
                                <tr
                                    key={l.id}
                                    /* A entrada em cascata (`--i`) e o realce ao passar
                                       vêm do ecrã de sempre; a guarda de
                                       prefers-reduced-motion está no layout. */
                                    className={cls('entra transition-all duration-200 hover:bg-indigo-50/60', l.is_active === false && 'text-slate-400')}
                                    style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                                >
                                    {o.accoes.logotipo && (
                                        <td className="px-4 py-2">
                                            {l.logo ? <img src={l.logo} alt="" className="h-8 w-8 rounded object-contain" /> : <span className="inline-block h-8 w-8 rounded bg-slate-100" aria-hidden="true" />}
                                        </td>
                                    )}
                                    {o.colunas.map((c) => <td key={c.chave} className={cls('px-4 py-2', c.alinhar === 'direita' && 'text-right tabular-nums')}><Celula c={c} l={l} /></td>)}
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            {/* VER A FICHA — só onde há uma para ver, e
                                                basta a permissão de VER: são as contas
                                                do fornecedor, não uma edição. Fica FORA
                                                do bloco de escrever, que é o que separa
                                                consultar de mexer. */}
                                            {o.extrato && (
                                                <button
                                                    type="button"
                                                    onClick={() => porAVer(l)}
                                                    title={t('Ver')}
                                                    aria-label={t('Ver: :nome', { nome: String(l.name ?? l.id) })}
                                                    className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO, FOCO)}
                                                >
                                                    <i className="fas fa-eye" aria-hidden="true" />
                                                </button>
                                            )}

                                            {o.permissoes.pode_escrever && (
                                            <>
                                                {o.accoes.padrao && !l.is_default && (
                                                    <button type="button" onClick={() => accao.mutate({ l, qual: 'padrao' })} title={t('Tornar padrão')} aria-label={t('Tornar padrão: :nome', { nome: String(l.name ?? l.id) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-amber-500', RAIO, FOCO)}><i className="fas fa-star" aria-hidden="true" /></button>
                                                )}
                                                {o.accoes.activar && (
                                                    <button type="button" onClick={() => accao.mutate({ l, qual: 'activar' })} title={l.is_active ? t('Desactivar') : t('Activar')} aria-label={t(l.is_active ? 'Desactivar: :nome' : 'Activar: :nome', { nome: String(l.name ?? l.id) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-slate-700', RAIO, FOCO)}><i className={cls('fas', l.is_active ? 'fa-toggle-on text-emerald-500' : 'fa-toggle-off')} aria-hidden="true" /></button>
                                                )}
                                                {o.accoes.logotipo && (
                                                    <label title={t('Logótipo')} className={cls('cursor-pointer p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO)}>
                                                        <i className="fas fa-image" aria-hidden="true" />
                                                        <span className="sr-only">{t('Logótipo de :nome', { nome: String(l.name ?? l.id) })}</span>
                                                        <input type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) logotipo.mutate({ l, ficheiro: f }); e.target.value = ''; }} />
                                                    </label>
                                                )}
                                                <button type="button" onClick={() => abrirEdicao(l)} aria-label={t('Editar: :nome', { nome: String(l.name ?? l.id) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-pen" aria-hidden="true" /></button>
                                                {o.accoes.apagar && (
                                                    <button type="button" disabled={!l.pode_apagar} onClick={() => porAApagar(l)} title={l.pode_apagar ? t('Apagar') : t('Em uso — não se pode apagar')} aria-label={t('Apagar: :nome', { nome: String(l.name ?? l.id) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>
                                                )}
                                            </>
                                            )}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>{t(':inicio–:fim de :total', { inicio: contas.from ?? '', fim: contas.to ?? '', total: contas.total })}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>{t('Anterior')}</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>{t('Seguinte')}</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {formulario && (
                <Formulario
                    o={o}
                    valores={formulario}
                    erros={erros}
                    titulo={aEditar ? t('Editar :nome', { nome: o.singular.toLowerCase() }) : o.novo}
                    subtitulo={aEditar ? String(aEditar.name ?? '') : undefined}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    aoFechar={() => { porFormulario(null); porAEditar(null); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar :nome?', { nome: o.singular.toLowerCase() })}
                rodape={<><Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Apagar')}</Botao></>}
            >
                <p className="text-sm text-slate-700">{tPartes('Vai apagar :nome. Não há volta.', { nome: <strong>{String(aApagar?.name ?? '')}</strong> })}</p>
            </Modal>
        </div>
    );
}

/* ─── Uma célula, pelo formato da coluna ────────────────────────────── */

function Celula({ c, l }: { c: Coluna; l: Linha }) {
    const v = l[c.chave];

    switch (c.formato) {
        case 'escolha':
            return <>{l.rotulos[c.chave] ?? (v === null || v === undefined ? '' : String(v))}</>;
        case 'booleano':
            return v ? <Etiqueta cor="bom">{t('Sim')}</Etiqueta> : <Etiqueta>{t('Não')}</Etiqueta>;
        case 'padrao':
            return v ? <Etiqueta cor="aviso" icone="fa-star">{t('Padrão')}</Etiqueta> : null;
        case 'numero':
            return <span className="tabular-nums">{v === null || v === undefined ? '' : String(v)}</span>;
        case 'percentagem':
            return <span className="tabular-nums">{Number(v ?? 0).toLocaleString('pt-PT', { maximumFractionDigits: 2 })}%</span>;
        case 'dinheiro':
            return <span className="tabular-nums">{kz(Number(v ?? 0))}</span>;
        case 'cor':
            return v ? <span className="inline-flex items-center gap-2"><span className="inline-block h-4 w-4 rounded border border-slate-200" style={{ background: String(v) }} aria-hidden="true" /><span className="font-mono text-xs">{String(v)}</span></span> : null;
        case 'icone':
            return v ? <span className="inline-flex items-center gap-2"><i className={cls('fas', String(v), 'text-slate-500')} aria-hidden="true" /><span className="font-mono text-xs">{String(v)}</span></span> : null;
        default:
            return <>{v === null || v === undefined ? '' : String(v)}</>;
    }
}

/* ─── O formulário, campo a campo, pelo esquema ─────────────────────── */

function Formulario({ o, valores, erros, titulo, subtitulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDoCatalogo;
    valores: Valores;
    erros: Record<string, string[]>;
    titulo: string;
    subtitulo?: string;
    aGravar: boolean;
    erroGeral: unknown;
    aoMudar: (v: Valores) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const paisPadrao = o.geografia?.pais_padrao ?? 'AO';
    const emAngola = String(valores.country ?? paisPadrao) === paisPadrao;

    const mudar = (chave: string, valor: string | number | boolean | null) => {
        const novo: Valores = { ...valores, [chave]: valor };

        // A morada concorda consigo própria: mudar de país limpa a província
        // e o município; mudar de província limpa o município; em Angola a
        // cidade é o município. A mesma regra do trait ConcordaComAMorada.
        if (chave === 'country') { novo.province = ''; novo.municipality = ''; novo.neighbourhood = ''; novo.city = ''; }
        if (chave === 'province') { novo.municipality = ''; novo.neighbourhood = ''; if (String(valor) === '' || emAngola) novo.city = ''; }
        if (chave === 'municipality' && emAngola) { novo.city = String(valor ?? ''); novo.neighbourhood = ''; }

        aoMudar(novo);
    };

    const visivel = (c: Campo) => {
        if (c.tipo === 'provincia' || c.tipo === 'municipio') return true;
        if (c.tipo === 'cidade') return !emAngola;
        return true;
    };

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={titulo}
            subtitulo={subtitulo}
            // A janela leva a COR e o ÍCONE do catálogo, como a faixa lá em
            // cima: é o que a faz ler-se como parte da página.
            cor={(o.cor as TomDaFaixa) ?? 'primaria'}
            icone={o.icone}
            largura="lg"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={aGravar} onClick={aoGravar}>{t('Guardar')}</Botao></>}
        >
            <AvisoDeErro erro={erroGeral} />
            <div className="grid gap-4 sm:grid-cols-2">
                {o.campos.filter(visivel).map((c) => (
                    <div key={c.chave} className={cls(c.largura === 'inteira' && 'sm:col-span-2')}>
                        <CampoDeEsquema c={c} valor={valores[c.chave]} erro={erros[c.chave]} o={o} valores={valores} aoMudar={(v) => mudar(c.chave, v)} />
                    </div>
                ))}
            </div>
        </Modal>
    );
}

function CampoDeEsquema({ c, valor, erro, o, valores, aoMudar }: {
    c: Campo;
    valor: string | number | boolean | null | undefined;
    erro?: string[];
    o: OpcoesDoCatalogo;
    valores: Valores;
    aoMudar: (v: string | number | boolean | null) => void;
}) {
    const texto = valor === null || valor === undefined ? '' : String(valor);

    if (c.tipo === 'booleano') {
        return (
            <label className={cls('flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50', RAIO)}>
                <input type="checkbox" checked={Boolean(valor)} onChange={(e) => aoMudar(e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                {c.rotulo}
                {erro?.[0] && <span className="text-xs text-red-600">{erro[0]}</span>}
            </label>
        );
    }

    let controlo: React.ReactNode;

    switch (c.tipo) {
        case 'escolha':
            controlo = (
                <select value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                    {!c.obrigatorio && <option value="">—</option>}
                    {(c.opcoes ?? []).map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
                </select>
            );
            break;
        case 'referencia':
            controlo = (
                <select value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                    <option value="">—</option>
                    {(o.referencias[c.referencia ?? ''] ?? []).map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
                </select>
            );
            break;
        case 'pais':
            controlo = (
                <select value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                    {(o.geografia?.paises ?? []).map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
                </select>
            );
            break;
        case 'provincia': {
            const emAngola = String(valores.country ?? o.geografia?.pais_padrao ?? 'AO') === (o.geografia?.pais_padrao ?? 'AO');
            controlo = emAngola ? (
                <select value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                    <option value="">—</option>
                    {(o.geografia?.provincias ?? []).map((p) => <option key={p} value={p}>{p}</option>)}
                </select>
            ) : (
                <input value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada} />
            );
            break;
        }
        case 'municipio': {
            const emAngola = String(valores.country ?? o.geografia?.pais_padrao ?? 'AO') === (o.geografia?.pais_padrao ?? 'AO');
            const municipios = o.geografia?.municipios[String(valores.province ?? '')] ?? [];
            controlo = emAngola && municipios.length > 0 ? (
                <select value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                    <option value="">—</option>
                    {municipios.map((m) => <option key={m} value={m}>{m}</option>)}
                </select>
            ) : (
                <input value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada} />
            );
            break;
        }
        case 'textarea':
            controlo = <textarea rows={2} value={texto} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'h-auto py-2')} />;
            break;
        case 'numero':
            controlo = <input type="number" value={texto} step={c.passo ?? 1} min={c.min} max={c.max} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />;
            break;
        case 'cor':
            controlo = (
                <span className="flex items-center gap-2">
                    <input type="color" value={texto || '#3B82F6'} onChange={(e) => aoMudar(e.target.value)} aria-label={c.rotulo} className="h-10 w-12 cursor-pointer rounded border border-slate-200" />
                    <input value={texto} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'font-mono')} />
                </span>
            );
            break;
        default:
            controlo = <input type={c.tipo === 'email' ? 'email' : c.tipo === 'url' ? 'url' : 'text'} value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada} />;
    }

    return (
        <CampoDoFormulario etiqueta={c.rotulo} erro={erro} obrigatorio={c.obrigatorio}>
            {controlo}
            {c.ajuda && <p className="mt-1 text-xs text-slate-400">{c.ajuda}</p>}
        </CampoDoFormulario>
    );
}

/**
 * A FICHA DE UMA LINHA DO CATÁLOGO — o primeiro separador da janela de ver.
 *
 * Desenha-se a partir dos CAMPOS DO ESQUEMA, e não de uma lista escrita à mão:
 * é o mesmo princípio do resto deste ecrã. Um campo novo no fornecedor aparece
 * aqui sem ninguém tocar neste ficheiro — e nenhum fica esquecido.
 *
 * As caixas de escolha lêem-se «Sim»/«Não» e não `true`/`false`: quem lê a
 * ficha não está a ler JSON.
 */
function FichaDaLinha({ linha, colunas }: { linha: Linha; colunas: Campo[] }) {
    // Os campos de morada vão juntos numa secção própria, como o formulário os
    // agrupa: soltos entre o NIF e o email, ninguém os lê como uma morada.
    const daMorada = ['country', 'province', 'municipality', 'neighbourhood', 'city', 'postal_code', 'address'];

    const identificacao = colunas.filter((c) => !daMorada.includes(c.chave));
    const morada = colunas.filter((c) => daMorada.includes(c.chave));

    const valorDe = (c: Campo) => {
        const v = linha[c.chave];

        if (typeof v === 'boolean') return v ? t('Sim') : t('Não');
        // O rótulo já resolvido do servidor (o país por extenso, a
        // categoria-mãe pelo nome) ganha ao número cru.
        return linha.rotulos?.[c.chave] || (v === null || v === undefined ? null : String(v));
    };

    return (
        <>
            <Seccao titulo={t('Identificação')} icone="fa-id-card">
                {identificacao.map((c) => (
                    <Dado key={c.chave} rotulo={`${c.rotulo}:`} valor={valorDe(c)} />
                ))}
            </Seccao>

            {morada.length > 0 && (
                <Seccao titulo={t('Localização')} icone="fa-location-dot">
                    {morada.map((c) => (
                        <Dado key={c.chave} rotulo={`${c.rotulo}:`} valor={valorDe(c)} />
                    ))}
                </Seccao>
            )}

            {linha.logo && (
                <Seccao titulo={t('Logótipo')} icone="fa-image">
                    <img
                        src={String(linha.logo)}
                        alt=""
                        className="h-24 w-24 rounded-xl object-contain shadow-md ring-1 ring-slate-200"
                    />
                </Seccao>
            )}
        </>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o catálogo')}</h2>
            <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
        </div>
    );
}
