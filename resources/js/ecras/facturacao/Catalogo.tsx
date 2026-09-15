import { useEffect, useState, type ReactNode } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { catalogos, type Campo, type Coluna, type FiltrosDoCatalogo, type Linha, type OpcoesDoCatalogo } from '@/api/catalogos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo as CampoDoFormulario, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { EscolherIcone } from '@/ui/EscolherIcone';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { IntervaloDeDatas, PorPagina } from '@/ui/FiltrosComuns';
import { ACCAO_DA_FAIXA, Faixa, type TomDaFaixa } from './faixa';
import { Dado, JanelaDoExtrato, Seccao } from './ExtratoDaParte';
import { FichaDaViatura } from '../oficina/FichaDaViatura';
import { CabecaDaViatura } from '../oficina/CabecaDaViatura';
import { ChapaDaMatricula } from '../oficina/ChapaDaMatricula';
import { FotografiasDaViatura, type ListasDasFotos, type LoteParaSubir } from '../oficina/FotografiasDaViatura';
import { fotografiasDaViatura } from '@/api/oficina';
import { avisar } from '@/casca/avisos';
import { Separadores } from '@/ui/Separadores';
import { FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t, tn, tPartes } from '@/i18n';

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

/* As LISTAS: os dias da semana de um turno (`number[]`) e as especialidades de
   um mecânico (`string[]`). Tudo o resto guarda um valor só. */
type Valor = string | number | boolean | number[] | string[] | null;
type Valores = Record<string, Valor>;

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

/**
 * A PORTA DO ECRÃ — um catálogo, ou vários com separadores.
 *
 * A maioria das moradas serve UM catálogo. Duas não: as tarifas do hotel são
 * as épocas mais os preços por tipo de quarto, e os pacotes são os pacotes
 * mais os códigos promocionais. São listas com a mesma forma que vivem na
 * mesma entrada do menu — e o ecrã em Blade separava-as por abas.
 *
 * Cada separador é um catálogo INTEIRO e independente: filtros, formulário,
 * permissões. A `key` faz com que trocar de aba comece do princípio em vez de
 * herdar a procura do anterior.
 */
export default function Catalogo({ tipo, tipos }: { tipo?: string; tipos?: Array<{ tipo: string; rotulo: string; icone?: string }> }) {
    const lista = tipos ?? (tipo ? [{ tipo, rotulo: '' }] : []);
    const [aberto, porAberto] = useState(lista[0]?.tipo ?? '');

    if (lista.length === 0) return null;

    if (lista.length === 1) return <UmCatalogo tipo={lista[0]!.tipo} />;

    return (
        <div className="space-y-4">
            <div role="tablist" aria-label={t('Listas')} className="flex flex-wrap gap-2">
                {lista.map((x) => {
                    const activo = x.tipo === aberto;

                    return (
                        <button
                            key={x.tipo}
                            type="button"
                            role="tab"
                            aria-selected={activo}
                            onClick={() => porAberto(x.tipo)}
                            className={cls(
                                'inline-flex items-center gap-2 border px-3.5 py-2 text-sm font-semibold transition-all duration-200',
                                'hover:-translate-y-0.5 active:translate-y-0',
                                RAIO, FOCO,
                                activo
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            )}
                        >
                            {x.icone && <i className={cls('fas', x.icone)} aria-hidden="true" />}
                            {t(x.rotulo)}
                        </button>
                    );
                })}
            </div>

            <UmCatalogo key={aberto} tipo={aberto} />
        </div>
    );
}

function UmCatalogo({ tipo }: { tipo: string }) {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDoCatalogo>({ procura: '', page: 1 });
    const [aEditar, porAEditar] = useState<Linha | null>(null);
    const [formulario, porFormulario] = useState<Valores | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    // As fotografias de uma viatura NOVA: sobem assim que ela é criada.
    const [fotosPorSubir, porFotosPorSubir] = useState<LoteParaSubir[]>([]);
    const [aApagar, porAApagar] = useState<Linha | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    /** A linha cuja FICHA está aberta — só nos catálogos que têm extrato. */
    const [aVer, porAVer] = useState<Linha | null>(null);
    /** A linha a quem se está a atribuir gente — só onde o esquema o oferece. */
    const [aAtribuir, porAAtribuir] = useState<Linha | null>(null);
    /** Se o modal de IMPORTAR está aberto — não pende de linha nenhuma. */
    const [aImportar, porAImportar] = useState(false);
    /** A linha cuja GALERIA está aberta — só nos catálogos que a têm. */
    const [aGaleria, porAGaleria] = useState<Linha | null>(null);

    const opcoes = useQuery({ queryKey: ['catalogo', tipo, 'opcoes'], queryFn: () => catalogos.opcoes(tipo), staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['catalogo', tipo, filtros], queryFn: () => catalogos.lista(tipo, filtros), placeholderData: keepPreviousData });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['catalogo', tipo] });

    const gravar = useMutation({
        mutationFn: (dados: Valores) => (aEditar ? catalogos.guardar(tipo, aEditar.id, dados) : catalogos.criar(tipo, dados)),
        onSuccess: (r) => {
            if (!aEditar && o.ficha === 'viatura' && fotosPorSubir.length > 0 && r.data?.id) void subirFotos(Number(r.data.id), fotosPorSubir);
            invalidar(); porFormulario(null); porAEditar(null); porErros({}); porFotosPorSubir([]); porRecado(r.message);
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    /** As fotografias juntadas a uma viatura nova, lote a lote, depois de ela existir. */
    async function subirFotos(id: number, lotes: LoteParaSubir[]) {
        let subidas = 0;

        for (const l of lotes) {
            try {
                await fotografiasDaViatura.juntar(id, l.ficheiros, l.dados);
                subidas += l.ficheiros.length;
            } catch (e) {
                avisar(e instanceof ErroDaApi ? e.message : t('Não foi possível enviar as fotografias.'), 'erro');
            }
        }

        if (subidas > 0) avisar(tn(':n fotografia juntada à viatura.|:n fotografias juntadas à viatura.', subidas, { n: subidas }), 'ok');
        cache.invalidateQueries({ queryKey: ['oficina', 'viatura', id] });
    }

    const apagar = useMutation({
        mutationFn: (l: Linha) => catalogos.apagar(tipo, l.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    const accao = useMutation({
        mutationFn: ({ l, qual }: { l: Linha; qual: 'activar' | 'padrao' }) => catalogos.accao(tipo, l.id, qual),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    /* MUDAR NA TABELA — o estado da viatura sem abrir a ficha. */
    const rapido = useMutation({
        mutationFn: ({ l, chave, valor }: { l: Linha; chave: string; valor: string }) => catalogos.campo(tipo, l.id, chave, valor),
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

    /**
     * COMO SE CHAMA ESTA LINHA — quase sempre pelo `name`, mas não sempre.
     *
     * Uma viatura chama-se pela MATRÍCULA: sem isto, a pergunta de apagar
     * dizia «Vai apagar . Não há volta.» e o título da ficha vinha vazio.
     */
    const nomeDe = (l: Linha | null | undefined): string =>
        l ? String(l[o.nome] ?? l.name ?? l.id) : '';

    const vazio = (): Valores => Object.fromEntries(o.campos.map((c) => {
        if (c.tipo === 'dias' || c.tipo === 'multi' || c.tipo === 'etiquetas') return [c.chave, Array.isArray(c.omissao) ? c.omissao : []];

        return [c.chave, c.omissao ?? (c.tipo === 'booleano' ? false : '')];
    }));

    const abrirNovo = () => { porAEditar(null); porErros({}); porFormulario(vazio()); };
    const abrirEdicao = (l: Linha) => {
        porAEditar(l); porErros({});
        porFormulario(Object.fromEntries(o.campos.map((c) => {
            const v = l[c.chave];
            if (c.tipo === 'booleano') return [c.chave, Boolean(v)];
            // Uma LISTA fica lista: passá-la por `String()` dava «1,2,3» na
            // caixa e um erro de validação ao gravar.
            if (c.tipo === 'dias') return [c.chave, Array.isArray(v) ? v.map(Number) : []];
            if (c.tipo === 'multi' || c.tipo === 'etiquetas') return [c.chave, Array.isArray(v) ? v.map(String) : []];
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
                        <>
                            {/* IMPORTAR DE OUTRO SÍTIO — «Importar de RH», nos
                                mecânicos. Só onde o esquema o declara. */}
                            {o.importar && (
                                <button type="button" onClick={() => porAImportar(true)} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                    <i
                                        className="fas fa-file-import transition-transform duration-300 group-hover:-translate-y-0.5"
                                        aria-hidden="true"
                                    />
                                    {o.importar.botao}
                                </button>
                            )}
                            <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                <i
                                    className="fas fa-plus transition-transform duration-300 group-hover:rotate-90"
                                    aria-hidden="true"
                                />
                                {o.novo}
                            </button>
                        </>
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
                    titulo={nomeDe(aVer)}
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
                    moradasDoDocumento={(id) => ({ preview: `/invoicing/purchases/invoices/${id}/preview`, pdf: `/invoicing/purchases/invoices/${id}/pdf` })}
                    podeEditar={o.permissoes.pode_escrever}
                    aoEditar={() => {
                        const l = aVer;
                        porAVer(null);
                        if (l) abrirEdicao(l);
                    }}
                    ficha={aVer && <FichaDaLinha linha={aVer} colunas={o.campos} />}
                />
            )}

            {/* A FICHA DA VIATURA — os dados e as folhas de obra com as facturas. */}
            {o.ficha === 'viatura' && (
                <FichaDaViatura
                    aberto={aVer !== null}
                    id={aVer ? Number(aVer.id) : null}
                    titulo={nomeDe(aVer)}
                    subtitulo={aVer ? [aVer.brand, aVer.model, aVer.owner_name].filter(Boolean).join(' · ') : undefined}
                    cor={(o.cor as TomDaFaixa) ?? 'primaria'}
                    ficha={aVer && <FichaDaLinha linha={aVer} colunas={o.campos} />}
                    fotografias={aVer && <FotografiasDaViatura id={Number(aVer.id)} podeEditar={o.permissoes.pode_escrever} listas={listasDasFotos(o)} />}
                    podeEditar={o.permissoes.pode_escrever}
                    aoEditar={() => {
                        const l = aVer;
                        porAVer(null);
                        if (l) abrirEdicao(l);
                    }}
                    aoFechar={() => porAVer(null)}
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
                                    {o.colunas.map((c) => (
                                        <td key={c.chave} className={cls('px-4 py-2', c.alinhar === 'direita' && 'text-right tabular-nums')}>
                                            {c.rapido && o.permissoes.pode_escrever
                                                ? <EscolhaRapida c={c} l={l} aTrabalhar={rapido.isPending && rapido.variables?.l.id === l.id} aoMudar={(valor) => rapido.mutate({ l, chave: c.chave, valor })} />
                                                : <Celula c={c} l={l} />}
                                        </td>
                                    ))}
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            {/* VER A FICHA — só onde há uma para ver, e
                                                basta a permissão de VER: são as contas
                                                do fornecedor, não uma edição. Fica FORA
                                                do bloco de escrever, que é o que separa
                                                consultar de mexer. */}
                                            {(o.extrato || o.ficha) && (
                                                <button
                                                    type="button"
                                                    onClick={() => porAVer(l)}
                                                    title={t('Ver')}
                                                    aria-label={t('Ver: :nome', { nome: nomeDe(l) })}
                                                    className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO, FOCO)}
                                                >
                                                    <i className="fas fa-eye" aria-hidden="true" />
                                                </button>
                                            )}

                                            {o.permissoes.pode_escrever && (
                                            <>
                                                {o.accoes.padrao && !l.is_default && (
                                                    <button type="button" onClick={() => accao.mutate({ l, qual: 'padrao' })} title={t('Tornar padrão')} aria-label={t('Tornar padrão: :nome', { nome: nomeDe(l) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-amber-500', RAIO, FOCO)}><i className="fas fa-star" aria-hidden="true" /></button>
                                                )}
                                                {o.accoes.activar && (
                                                    <button type="button" onClick={() => accao.mutate({ l, qual: 'activar' })} title={l.is_active ? t('Desactivar') : t('Activar')} aria-label={t(l.is_active ? 'Desactivar: :nome' : 'Activar: :nome', { nome: nomeDe(l) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-slate-700', RAIO, FOCO)}><i className={cls('fas', l.is_active ? 'fa-toggle-on text-emerald-500' : 'fa-toggle-off')} aria-hidden="true" /></button>
                                                )}
                                                {/* ATRIBUIR EM LOTE — pôr trinta
                                                    pessoas neste turno de uma
                                                    vez, em vez de uma a uma
                                                    pela ficha de cada uma. */}
                                                {o.accoes.atribuir && (
                                                    <button
                                                        type="button"
                                                        onClick={() => porAAtribuir(l)}
                                                        title={t('Atribuir')}
                                                        aria-label={t('Atribuir a :nome', { nome: nomeDe(l) })}
                                                        className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-emerald-600', RAIO, FOCO)}
                                                    >
                                                        <i className="fas fa-user-plus" aria-hidden="true" />
                                                    </button>
                                                )}
                                                {o.accoes.logotipo && (
                                                    <label title={o.imagem?.rotulo ?? t('Logótipo')} className={cls('cursor-pointer p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO)}>
                                                        <i className="fas fa-image" aria-hidden="true" />
                                                        {/* O RÓTULO É DO CATÁLOGO: «Logótipo» num fornecedor,
                                                            «Imagem de destaque» num tipo de quarto. Dizia
                                                            sempre «Logótipo», que numa lista de quartos não
                                                            quer dizer nada. */}
                                                        <span className="sr-only">{t(':que de :nome', { que: o.imagem?.rotulo ?? t('Logótipo'), nome: nomeDe(l) })}</span>
                                                        <input type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) logotipo.mutate({ l, ficheiro: f }); e.target.value = ''; }} />
                                                    </label>
                                                )}
                                                {o.galeria && (
                                                    <button
                                                        type="button"
                                                        onClick={() => porAGaleria(l)}
                                                        title={o.galeria.rotulo}
                                                        aria-label={t(':que de :nome', { que: o.galeria.rotulo, nome: nomeDe(l) })}
                                                        className={cls('relative p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-purple-600', RAIO, FOCO)}
                                                    >
                                                        <i className="fas fa-images" aria-hidden="true" />
                                                        {Array.isArray(l.galeria) && l.galeria.length > 0 && (
                                                            <span className="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-purple-600 px-1 text-[9px] font-bold text-white">
                                                                {l.galeria.length}
                                                            </span>
                                                        )}
                                                    </button>
                                                )}
                                                <button type="button" onClick={() => abrirEdicao(l)} aria-label={t('Editar: :nome', { nome: nomeDe(l) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-pen" aria-hidden="true" /></button>
                                                {o.accoes.apagar && (
                                                    <button type="button" disabled={!l.pode_apagar} onClick={() => porAApagar(l)} title={l.pode_apagar ? t('Apagar') : t('Em uso — não se pode apagar')} aria-label={t('Apagar: :nome', { nome: nomeDe(l) })} className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>
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
                {contas && (
                    <Paginacao
                        pagina={contas.current_page}
                        ultima={contas.last_page}
                        aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                        total={contas.total}
                        de={contas.from}
                        ate={contas.to}
                        aCarregar={lista.isFetching}
                    />
                )}
            </Cartao>

            {formulario && (
                <Formulario
                    o={o}
                    valores={formulario}
                    erros={erros}
                    titulo={aEditar ? t('Editar :nome', { nome: o.singular.toLowerCase() }) : o.novo}
                    subtitulo={aEditar ? nomeDe(aEditar) : undefined}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    cabeca={o.ficha === 'viatura' ? <CabecaDaViatura valores={formulario} o={o} fotos={fotosPorSubir.reduce((n, l) => n + l.ficheiros.length, 0)} /> : undefined}
                    abaExtra={o.ficha === 'viatura' ? {
                        chave: 'fotografias',
                        rotulo: t('Fotografias'),
                        icone: 'fa-camera',
                        conteudo: (
                            <FotografiasDaViatura
                                id={aEditar ? Number(aEditar.id) : null}
                                podeEditar={o.permissoes.pode_escrever}
                                listas={listasDasFotos(o)}
                                pendentes={fotosPorSubir}
                                aoMudarPendentes={porFotosPorSubir}
                            />
                        ),
                    } : undefined}
                    aoFechar={() => { porFormulario(null); porAEditar(null); porFotosPorSubir([]); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar :nome?', { nome: o.singular.toLowerCase() })}
                rodape={<><Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Apagar')}</Botao></>}
            >
                <p className="text-sm text-slate-700">{tPartes('Vai apagar :nome. Não há volta.', { nome: <strong>{nomeDe(aApagar)}</strong> })}</p>
            </Modal>

            {/* ATRIBUIR EM LOTE — o modal que o ecrã dos turnos em Blade tinha. */}
            {o.atribuir && aAtribuir && (
                <Atribuicao
                    tipo={tipo}
                    modo="atribuir"
                    linha={aAtribuir}
                    nome={nomeDe(aAtribuir)}
                    descricao={o.atribuir}
                    aoFechar={() => porAAtribuir(null)}
                    aoGravar={(m) => { porAAtribuir(null); porRecado(m); invalidar(); }}
                />
            )}

            {/* A GALERIA — várias imagens, só onde o esquema as tem. */}
            {o.galeria && aGaleria && (
                <Galeria
                    tipo={tipo}
                    linha={linhas.find((l) => l.id === aGaleria.id) ?? aGaleria}
                    nome={nomeDe(aGaleria)}
                    rotulo={o.galeria.rotulo}
                    aoFechar={() => porAGaleria(null)}
                    aoMudar={(m) => { porRecado(m); invalidar(); }}
                />
            )}

            {/* IMPORTAR EM LOTE — o mesmo modal, outro verbo. */}
            {o.importar && aImportar && (
                <Atribuicao
                    tipo={tipo}
                    modo="importar"
                    descricao={o.importar}
                    aoFechar={() => porAImportar(false)}
                    aoGravar={(m) => { porAImportar(false); porRecado(m); invalidar(); }}
                />
            )}
        </div>
    );
}

/* ─── Atribuir em lote ──────────────────────────────────────────────── */

/* ─── A galeria ─────────────────────────────────────────────────────── */

/**
 * AS IMAGENS DE UM REGISTO — as que o site de reservas mostra.
 *
 * Um tipo de quarto tem uma imagem de destaque (o botão da lista) e uma
 * GALERIA: é ela que faz alguém escolher um quarto em vez de outro, e o ecrã
 * em Blade tinha-a.
 *
 * APAGA-SE PELO CAMINHO DO FICHEIRO e não pela posição: pela posição, apagar a
 * segunda e depois a terceira apagava a quarta, porque os índices mudam assim
 * que a lista encolhe.
 */
function Galeria({ tipo, linha, nome, rotulo, aoFechar, aoMudar }: {
    tipo: string;
    linha: Linha;
    nome: string;
    rotulo: string;
    aoFechar: () => void;
    aoMudar: (mensagem: string) => void;
}) {
    const imagens = Array.isArray(linha.galeria) ? linha.galeria : [];

    const juntar = useMutation({
        mutationFn: (ficheiros: File[]) => catalogos.juntarAGaleria(tipo, linha.id, ficheiros),
        onSuccess: (r) => aoMudar(r.message),
    });

    const tirar = useMutation({
        mutationFn: (caminho: string) => catalogos.tirarDaGaleria(tipo, linha.id, caminho),
        onSuccess: (r) => aoMudar(r.message),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={rotulo}
            subtitulo={nome}
            icone="fa-images"
            cor="roxo"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            <AvisoDeErro erro={juntar.error ?? tirar.error} />

            <div className="space-y-4">
                <label className={cls(
                    'flex cursor-pointer flex-col items-center gap-2 border-2 border-dashed border-slate-300 px-4 py-8 text-center transition-colors hover:border-indigo-400 hover:bg-indigo-50/50',
                    RAIO,
                )}>
                    <i className="fas fa-cloud-arrow-up text-2xl text-slate-400" aria-hidden="true" />
                    <span className="text-sm font-semibold text-slate-700">{t('Escolher imagens')}</span>
                    <span className="text-xs text-slate-400">{t('Até 10 de cada vez, 2 MB cada.')}</span>
                    <input
                        type="file"
                        multiple
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => {
                            const ficheiros = Array.from(e.target.files ?? []);
                            if (ficheiros.length > 0) juntar.mutate(ficheiros);
                            e.target.value = '';
                        }}
                    />
                </label>

                {juntar.isPending && <p className="text-center text-sm text-slate-500">{t('A enviar…')}</p>}

                {imagens.length === 0 ? (
                    <p className="py-6 text-center text-sm text-slate-400">{t('Ainda não há imagens.')}</p>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-3">
                        {imagens.map((img, i) => (
                            <li key={img.caminho} className={cls('entra group relative overflow-hidden border border-slate-200', RAIO)}
                                style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                <img src={img.url} alt="" className="h-28 w-full object-cover transition-transform duration-300 group-hover:scale-105" />
                                <button
                                    type="button"
                                    onClick={() => tirar.mutate(img.caminho)}
                                    aria-label={t('Remover imagem :n', { n: i + 1 })}
                                    className={cls(
                                        'absolute right-1.5 top-1.5 grid h-7 w-7 place-items-center bg-white/90 text-slate-500 shadow-sm transition-all hover:scale-110 hover:text-red-600',
                                        RAIO, FOCO,
                                    )}
                                >
                                    <i className="fas fa-trash text-xs" aria-hidden="true" />
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Modal>
    );
}

/**
 * ESCOLHER MUITOS DE UMA VEZ — e fazer-lhes a mesma coisa.
 *
 * Serve os dois trabalhos em lote que os ecrãs em Blade tinham, porque são o
 * mesmo modal com verbos diferentes:
 *
 *  · ATRIBUIR — trinta pessoas no turno da manhã. Grava-se a LISTA COMPLETA de
 *    quem fica, e por isso desmarcar alguém tira-o mesmo: mandar só os novos
 *    deixava sem maneira de tirar uma pessoa do turno sem ir à ficha dela, que
 *    é precisamente o trabalho que este modal existe para poupar.
 *  · IMPORTAR — os mecânicos a partir do pessoal do RH. Aqui só se CRIA: quem
 *    já cá está vem marcado e trancado, e desmarcá-lo não o apagaria — apagar
 *    um mecânico é decisão que se toma na lista, com a sua guarda.
 *
 * A frase por baixo da lista diz qual dos dois é, porque a diferença importa e
 * não se adivinha olhando.
 */
function Atribuicao({ tipo, modo, linha, nome, descricao, aoFechar, aoGravar }: {
    tipo: string;
    modo: 'atribuir' | 'importar';
    /** Só na atribuição: o registo a quem se atribui. Importar não pende de um. */
    linha?: Linha;
    nome?: string;
    descricao: { titulo: string; nada: string; pesquisa_ajuda: string };
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const aImportar = modo === 'importar';
    const [procura, porProcura] = useState('');
    /* `null` enquanto a lista não chegou: só então se sabe quem já lá está. */
    const [escolhidos, porEscolhidos] = useState<Set<number> | null>(null);

    const q = useQuery({
        queryKey: ['catalogo', tipo, modo, linha?.id ?? 0, procura],
        queryFn: () => (aImportar
            ? catalogos.importaveis(tipo, procura)
            : catalogos.atribuiveis(tipo, linha!.id, procura)),
        placeholderData: keepPreviousData,
    });

    const candidatos = q.data?.data ?? [];

    /*
     * A ESCOLHA NASCE DE QUEM JÁ LÁ ESTÁ — e uma vez só.
     *
     * Refazê-la a cada resposta apagava o que a pessoa tinha acabado de
     * marcar assim que escrevesse na procura.
     */
    if (escolhidos === null && q.data) {
        porEscolhidos(new Set(candidatos.filter((c) => c.atribuido).map((c) => c.id)));
    }

    const marcados = escolhidos ?? new Set<number>();

    /* Quem já cá está não se desmarca: importá-lo outra vez não faz nada, e
       desmarcá-lo não o apaga — deixá-lo mexer prometia o que não acontece. */
    const trancado = (id: number) => candidatos.some((c) => c.id === id && c.bloqueado);

    const alternar = (id: number) => porEscolhidos((antes) => {
        if (trancado(id)) return antes ?? new Set<number>();

        const novo = new Set(antes ?? []);
        novo.has(id) ? novo.delete(id) : novo.add(id);

        return novo;
    });

    /* «Todos» é todos OS QUE SE VÊEM: com a procura posta, marca só esses. */
    const livres = candidatos.filter((c) => !c.bloqueado);
    const todosAVista = livres.length > 0 && livres.every((c) => marcados.has(c.id));

    const alternarTodos = () => porEscolhidos((antes) => {
        const novo = new Set(antes ?? []);
        livres.forEach((c) => (todosAVista ? novo.delete(c.id) : novo.add(c.id)));

        return novo;
    });

    /* Na importação, os que já cá estão vieram marcados e não são trabalho. */
    const aFazer = aImportar ? [...marcados].filter((id) => !trancado(id)) : [...marcados];

    const gravar = useMutation({
        mutationFn: () => (aImportar
            ? catalogos.importar(tipo, aFazer)
            : catalogos.atribuir(tipo, linha!.id, aFazer)),
        onSuccess: (r) => aoGravar(r.message),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={descricao.titulo}
            subtitulo={nome}
            icone={aImportar ? 'fa-file-import' : 'fa-user-plus'}
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone={aImportar ? 'fa-file-import' : 'fa-check'}
                        aTrabalhar={gravar.isPending}
                        onClick={() => gravar.mutate()}
                        disabled={aImportar && aFazer.length === 0}
                    >
                        {aImportar ? t('Importar escolhidos') : t('Gravar atribuição')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="space-y-3">
                <div className="relative">
                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true" />
                    <input
                        type="search"
                        value={procura}
                        onChange={(e) => porProcura(e.target.value)}
                        placeholder={descricao.pesquisa_ajuda}
                        aria-label={descricao.pesquisa_ajuda}
                        className={cls(entrada, 'pl-9')}
                    />
                </div>

                <div className="flex items-center justify-between text-sm">
                    <button
                        type="button"
                        onClick={alternarTodos}
                        disabled={candidatos.length === 0}
                        className={cls('inline-flex items-center gap-2 px-2 py-1 font-semibold text-indigo-600 transition-colors hover:bg-indigo-50 disabled:opacity-40', RAIO, FOCO)}
                    >
                        <i className={cls('fas', todosAVista ? 'fa-square-check' : 'fa-square')} aria-hidden="true" />
                        {todosAVista ? t('Desmarcar todos') : t('Marcar todos')}
                    </button>

                    <span className="tabular-nums text-slate-500">
                        {t(':quantos escolhido(s)', { quantos: marcados.size })}
                    </span>
                </div>

                <div className={cls('max-h-72 overflow-y-auto border border-slate-200 divide-y divide-slate-100', RAIO)}>
                    {q.isPending ? (
                        <Carregando linhas={4} />
                    ) : candidatos.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-400">
                            {procura ? t('Nada encontrado.') : descricao.nada}
                        </p>
                    ) : (
                        candidatos.map((c, i) => {
                            const marcado = marcados.has(c.id);

                            return (
                                <label
                                    key={c.id}
                                    style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                                    className={cls(
                                        'entra flex cursor-pointer items-center gap-3 px-3 py-2.5 text-sm transition-colors duration-150',
                                        marcado ? 'bg-emerald-50/70' : 'hover:bg-slate-50',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        checked={marcado}
                                        disabled={c.bloqueado}
                                        onChange={() => alternar(c.id)}
                                        className="h-4 w-4 rounded border-slate-300 text-emerald-600 disabled:opacity-50"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-semibold text-slate-800">{c.nome}</span>
                                        {c.nota && <span className="block font-mono text-xs text-slate-400">{c.nota}</span>}
                                    </span>
                                    {/* QUEM JÁ CÁ ESTÁ di-lo, em vez de desaparecer da
                                        lista: escondê-lo fazia quem procurasse um nome
                                        já importado julgar que ele saíra do RH. */}
                                    {c.bloqueado && <Etiqueta cor="bom" icone="fa-check">{t('Já importado')}</Etiqueta>}
                                    {!aImportar && c.atribuido && !marcado && (
                                        <Etiqueta cor="aviso" icone="fa-arrow-right-from-bracket">{t('Vai sair')}</Etiqueta>
                                    )}
                                </label>
                            );
                        })
                    )}
                </div>

                <p className="text-xs text-slate-500">
                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                    {aImportar
                        ? t('Importar só acrescenta: quem já cá está não muda, e nada se apaga.')
                        : t('Grava-se a lista completa: quem estiver aqui e for desmarcado sai.')}
                </p>
            </div>
        </Modal>
    );
}

/**
 * UMA DATA NA LISTA — e, sendo validade, o aviso de que passou.
 *
 * A inspecção de uma viatura caduca num dia certo, e é isso que faz alguém
 * abrir a lista. Escrita a preto no meio de vinte outras, não se vê: a que já
 * passou sai a vermelho, e a que está quase sai a âmbar, com o ícone a dizer
 * o mesmo a quem não distingue as cores.
 */
function DataDaCelula({ valor, validade }: { valor: unknown; validade: boolean }) {
    if (valor === null || valor === undefined || valor === '') {
        return null;
    }

    const quando = new Date(String(valor));

    if (Number.isNaN(quando.getTime())) {
        return <span className="tabular-nums">{String(valor)}</span>;
    }

    const dias = Math.ceil((quando.getTime() - Date.now()) / 86_400_000);
    const passou = validade && dias < 0;
    const perto = validade && dias >= 0 && dias <= 30;

    return (
        <span className={cls('inline-flex items-center gap-1.5 tabular-nums',
            passou ? 'font-bold text-red-600' : perto ? 'font-semibold text-amber-700' : '')}>
            {(passou || perto) && (
                <i className={cls('fas text-xs', passou ? 'fa-triangle-exclamation' : 'fa-clock')} aria-hidden="true" />
            )}
            {data(String(valor))}
            {passou && <span className="text-[10px] font-bold uppercase">{t('caducou')}</span>}
        </span>
    );
}

/* ─── Mudar na tabela ─────────────────────────────────────────────── */

/** As cores das escolhas que a têm (os estados de viatura), em classes que o Tailwind conhece. */
const COR_DA_ESCOLHA: Record<string, string> = {
    verde: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    azul: 'bg-blue-50 text-blue-800 ring-blue-200',
    ambar: 'bg-amber-50 text-amber-800 ring-amber-200',
    laranja: 'bg-orange-50 text-orange-800 ring-orange-200',
    teal: 'bg-teal-50 text-teal-800 ring-teal-200',
    roxo: 'bg-purple-50 text-purple-800 ring-purple-200',
    vermelho: 'bg-red-50 text-red-800 ring-red-200',
    cinza: 'bg-slate-100 text-slate-700 ring-slate-200',
};

const PONTO_DA_ESCOLHA: Record<string, string> = {
    verde: 'bg-emerald-500', azul: 'bg-blue-500', ambar: 'bg-amber-500', laranja: 'bg-orange-500',
    teal: 'bg-teal-500', roxo: 'bg-purple-500', vermelho: 'bg-red-500', cinza: 'bg-slate-400',
};

/**
 * UMA LISTA NA CÉLULA — o estado da viatura muda-se aqui (15/09/2026).
 *
 * Parece a etiqueta de sempre, com a cor do estado e uma seta; carregar abre a
 * lista. Grava logo ao escolher, com o aviso no canto, e a linha volta a
 * desenhar-se com o valor que o servidor gravou.
 */
function EscolhaRapida({ c, l, aTrabalhar, aoMudar }: { c: Coluna; l: Linha; aTrabalhar: boolean; aoMudar: (valor: string) => void }) {
    const valor = l[c.chave] === null || l[c.chave] === undefined ? '' : String(l[c.chave]);
    const actual = (c.opcoes ?? []).find((op) => op.valor === valor);
    const cor = actual?.cor ?? 'cinza';

    return (
        <span className={cls('relative inline-flex items-center rounded-full ring-1 ring-inset transition-all duration-200 hover:shadow-sm', COR_DA_ESCOLHA[cor] ?? COR_DA_ESCOLHA.cinza)}>
            <span aria-hidden="true" className={cls('pointer-events-none absolute left-2.5 h-1.5 w-1.5 rounded-full', aTrabalhar ? 'animate-ping' : '', PONTO_DA_ESCOLHA[cor] ?? PONTO_DA_ESCOLHA.cinza)} />
            <select
                value={valor}
                disabled={aTrabalhar}
                onChange={(e) => { if (e.target.value !== valor) aoMudar(e.target.value); }}
                aria-label={t(':campo de :nome', { campo: c.rotulo, nome: String(l.plate ?? l.name ?? l.id) })}
                className="cursor-pointer appearance-none rounded-full border-0 bg-transparent py-1 pl-6 pr-7 text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-wait"
            >
                {!actual && valor && <option value={valor}>{l.rotulos[c.chave] ?? valor}</option>}
                {(c.opcoes ?? []).map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
            </select>
            <i aria-hidden="true" className={cls('fas pointer-events-none absolute right-2.5 text-[10px]', aTrabalhar ? 'fa-spinner fa-spin' : 'fa-chevron-down')} />
        </span>
    );
}

/* ─── Uma célula, pelo formato da coluna ────────────────────────────── */

function Celula({ c, l }: { c: Coluna; l: Linha }) {
    const v = l[c.chave];

    switch (c.formato) {
        case 'matricula':
            return v ? <ChapaDaMatricula matricula={String(v)} tamanho="pequeno" /> : <span className="text-slate-300">—</span>;
        case 'escolha': {
            const cor = (c.opcoes ?? []).find((op) => op.valor === String(v ?? ''))?.cor;

            return cor
                ? <span className={cls('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', COR_DA_ESCOLHA[cor] ?? COR_DA_ESCOLHA.cinza)}>{l.rotulos[c.chave] ?? String(v ?? '')}</span>
                : <>{l.rotulos[c.chave] ?? (v === null || v === undefined ? '' : String(v))}</>;
        }
        /*
         * UMA REFERÊNCIA MOSTRA O NOME, não o id.
         *
         * Faltava o caso e caía no `default`, que escreve o valor cru: a coluna
         * «Banco» da lista de contas bancárias mostrava `7`, e a do «Centro-mãe»
         * mostrava `106`. O rótulo vem no `rotulos`, resolvido no servidor a
         * partir das mesmas referências que o formulário usa.
         */
        case 'referencia':
            return l.rotulos[c.chave]
                ? <>{l.rotulos[c.chave]}</>
                : <span className="text-slate-300">—</span>;
        case 'booleano':
            return v ? <Etiqueta cor="bom">{t('Sim')}</Etiqueta> : <Etiqueta>{t('Não')}</Etiqueta>;
        case 'padrao':
            return v ? <Etiqueta cor="aviso" icone="fa-star">{t('Padrão')}</Etiqueta> : null;
        case 'numero':
            return <span className="tabular-nums">{v === null || v === undefined ? '' : String(v)}</span>;
        case 'percentagem':
            return <span className="tabular-nums">{Number(v ?? 0).toLocaleString('pt-PT', { maximumFractionDigits: 2 })}%</span>;
        /* UM VALOR POR PREENCHER NÃO É ZERO. «0,00» num preço opcional lê-se
           como «de graça»; o traço diz «não está definido». */
        case 'dinheiro':
            return v === null || v === undefined
                ? <span className="text-slate-300">—</span>
                : <span className="tabular-nums">{kz(Number(v))}</span>;
        case 'cor':
            return v ? <span className="inline-flex items-center gap-2"><span className="inline-block h-4 w-4 rounded border border-slate-200" style={{ background: String(v) }} aria-hidden="true" /><span className="font-mono text-xs">{String(v)}</span></span> : null;
        case 'icone':
            return v ? <span className="inline-flex items-center gap-2"><i className={cls('fas', String(v), 'text-slate-500')} aria-hidden="true" /><span className="font-mono text-xs">{String(v)}</span></span> : null;
        /* A HORA sai sempre `08:00`, mesmo que a coluna seja um `datetime`. */
        case 'hora':
            return v ? <span className="inline-flex items-center gap-1.5 tabular-nums"><i className="fas fa-clock text-xs text-slate-400" aria-hidden="true" />{String(v).slice(0, 5)}</span> : null;
        /* A DATA, e a de VALIDADE a dizer que passou.
           Numa lista de viaturas, a inspecção caducada é o que faz alguém
           pegar no ecrã — e uma data escrita a preto no meio de vinte não se
           vê. Por isso o formato `validade` pinta o que já lá vai. */
        case 'data':
        case 'validade':
            return <DataDaCelula valor={v} validade={c.formato === 'validade'} />;
        /* AS ESCOLHAS MÚLTIPLAS EM CRACHÁS — «Motor», «Chapa». A lista de
           mecânicos serve para achar quem faz aquilo, e `["Motor","Chapa"]`
           não é uma resposta a essa pergunta. */
        /* AS ETIQUETAS escritas à mão — o valor já é o que se lê. */
        case 'etiquetas':
        case 'multi':
            return Array.isArray(v) && v.length > 0 ? (
                <span className="inline-flex flex-wrap gap-1">
                    {(v as unknown[]).map((x) => (
                        <span key={String(x)} className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                            {/* O QUE ESTÁ GRAVADO PODE SER UMA CHAVE (`sea_view`):
                                o rótulo vem das opções da coluna. Sem isso, a
                                lista de quartos dizia «balcony sea_view». */}
                            {c.opcoes?.find((op) => op.valor === String(x))?.rotulo ?? String(x)}
                        </span>
                    ))}
                </span>
            ) : null;
        /* OS DIAS EM CRACHÁS, e não «1, 2, 3, 4, 5»: quem olha a lista quer
           ver de relance que o turno não trabalha ao sábado. */
        case 'dias':
            return (
                /* SEM QUEBRA: os sete crachás são uma semana e lêem-se em
                   linha. A envolver, empilhavam-se um por cima do outro e a
                   linha da tabela crescia sete vezes — a tabela é que rola. */
                <span className="inline-flex flex-nowrap gap-0.5">
                    {DIAS_DA_SEMANA.map((d) => {
                        const marcado = Array.isArray(v) && (v as unknown[]).some((x) => Number(x) === d.valor);

                        return (
                            <span
                                key={d.valor}
                                title={d.nome}
                                className={cls(
                                    'inline-grid h-5 w-5 place-items-center rounded text-[9px] font-bold uppercase transition-colors duration-200',
                                    marcado ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-300',
                                )}
                            >
                                {/* A letra só se lê com a posição: a ordem é
                                    sempre de segunda a domingo. Quem ouve o
                                    ecrã ouve o dia por extenso. */}
                                <span aria-hidden="true">{t(d.curto).slice(0, 1)}</span>
                                <span className="sr-only">
                                    {marcado ? t(':dia: sim', { dia: t(d.nome) }) : t(':dia: não', { dia: t(d.nome) })}
                                </span>
                            </span>
                        );
                    })}
                </span>
            );
        default:
            return <>{v === null || v === undefined ? '' : String(v)}</>;
    }
}

/* ─── O formulário, campo a campo, pelo esquema ─────────────────────── */

/** As listas das fotografias (fase, serviço, zona) vêm nas referências do catálogo das viaturas. */
const listasDasFotos = (o: OpcoesDoCatalogo): ListasDasFotos => ({
    fases: o.referencias.fotos_fases ?? [],
    servicos: o.referencias.fotos_servicos ?? [],
    zonas: o.referencias.fotos_zonas ?? [],
});

function Formulario({ o, valores, erros, titulo, subtitulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar, cabeca, abaExtra }: {
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
    /** O que vai por cima dos campos — o cartão vivo da viatura. */
    cabeca?: ReactNode;
    /** Um separador que não é de campos — as fotografias da viatura. */
    abaExtra?: { chave: string; rotulo: string; icone: string; conteudo: ReactNode };
}) {
    const paisPadrao = o.geografia?.pais_padrao ?? 'AO';
    const emAngola = String(valores.country ?? paisPadrao) === paisPadrao;

    const mudar = (chave: string, valor: Valor) => {
        const novo: Valores = { ...valores, [chave]: valor };

        // A morada concorda consigo própria: mudar de país limpa a província
        // e o município; mudar de província limpa o município; em Angola a
        // cidade é o município. A mesma regra do trait ConcordaComAMorada.
        if (chave === 'country') { novo.province = ''; novo.municipality = ''; novo.neighbourhood = ''; novo.city = ''; }
        if (chave === 'province') { novo.municipality = ''; novo.neighbourhood = ''; if (String(valor) === '' || emAngola) novo.city = ''; }
        if (chave === 'municipality' && emAngola) { novo.city = String(valor ?? ''); novo.neighbourhood = ''; }

        /*
         * ESCOLHER O CLIENTE PREENCHE O DONO (`preencher`). Só escreve por cima
         * do que está vazio ou do que veio do cliente anterior: o que alguém
         * escreveu à mão não se perde por mudar de cliente.
         */
        const campo = o.campos.find((x) => x.chave === chave);
        if (campo?.preencher && campo.referencia) {
            const lista = o.referencias[campo.referencia] ?? [];
            const doAnterior = lista.find((op) => op.valor === String(valores[chave] ?? ''))?.dados ?? {};
            const doNovo = lista.find((op) => op.valor === String(valor ?? ''))?.dados;

            if (doNovo) {
                Object.entries(campo.preencher).forEach(([destino, origem]) => {
                    const actual = String(valores[destino] ?? '');
                    if (actual === '' || actual === String(doAnterior[origem] ?? '')) novo[destino] = doNovo[origem] ?? '';
                });
            }
        }

        aoMudar(novo);
    };

    /*
     * OS SEPARADORES — onde o esquema os declara (`grupos`). Um campo que não
     * esteja em grupo nenhum vai para o primeiro: nunca fica um campo sem sítio.
     * Cada aba conta os erros que tem, e gravar com erros abre a primeira delas.
     */
    const grupos = (o.grupos ?? []).map((g, i) => ({
        ...g,
        campos: i === 0
            ? [...g.campos, ...o.campos.map((c) => c.chave).filter((k) => !(o.grupos ?? []).some((x) => x.campos.includes(k)))]
            : g.campos,
    }));
    const [aba, porAba] = useState(grupos[0]?.chave ?? '');

    useEffect(() => {
        const comErro = grupos.find((g) => g.campos.some((k) => erros[k]));
        if (comErro) porAba(comErro.chave);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [erros]);

    const grupoActivo = grupos.find((g) => g.chave === aba);
    const camposAVer = grupoActivo
        ? grupoActivo.campos.map((k) => o.campos.find((c) => c.chave === k)).filter((c): c is Campo => Boolean(c))
        : o.campos;

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
            {cabeca}
            <AvisoDeErro erro={erroGeral} />
            {grupos.length > 0 && (
                <div className="mb-4">
                    <Separadores
                        abas={[
                            ...grupos.map((g) => ({ chave: g.chave, rotulo: g.rotulo, icone: g.icone, erros: g.campos.filter((k) => erros[k]).length })),
                            ...(abaExtra ? [{ chave: abaExtra.chave, rotulo: abaExtra.rotulo, icone: abaExtra.icone }] : []),
                        ]}
                        activa={aba}
                        aoMudar={porAba}
                    />
                </div>
            )}
            {abaExtra && aba === abaExtra.chave ? (
                <div key={abaExtra.chave} className="animate-fade-in">{abaExtra.conteudo}</div>
            ) : (
                <div key={aba} className="grid gap-4 sm:grid-cols-2">
                    {camposAVer.filter(visivel).map((c, i) => (
                        <div key={c.chave} style={grupos.length > 0 ? { animationDelay: `${Math.min(i, 10) * 25}ms` } : undefined}
                            className={cls(c.largura === 'inteira' && 'sm:col-span-2', grupos.length > 0 && 'animate-fade-in')}>
                            <CampoDeEsquema c={c} valor={valores[c.chave]} erro={erros[c.chave]} o={o} valores={valores} aoMudar={(v) => mudar(c.chave, v)} />
                        </div>
                    ))}
                </div>
            )}
        </Modal>
    );
}

/**
 * OS DIAS DA SEMANA, como o modelo os guarda: 1 = Segunda … 7 = Domingo.
 *
 * A ordem é a portuguesa — a semana começa à segunda — e é a mesma do
 * acessor `work_days_formatted` do `Shift`. Mudar aqui sem mudar lá punha o
 * ecrã a dizer «Seg» e o papel a dizer «Dom».
 */
const DIAS_DA_SEMANA = [
    { valor: 1, curto: 'Seg', nome: 'Segunda-feira' },
    { valor: 2, curto: 'Ter', nome: 'Terça-feira' },
    { valor: 3, curto: 'Qua', nome: 'Quarta-feira' },
    { valor: 4, curto: 'Qui', nome: 'Quinta-feira' },
    { valor: 5, curto: 'Sex', nome: 'Sexta-feira' },
    { valor: 6, curto: 'Sáb', nome: 'Sábado' },
    { valor: 7, curto: 'Dom', nome: 'Domingo' },
] as const;

/**
 * ESCOLHER OS DIAS EM QUE O TURNO TRABALHA.
 *
 * Sete botões que se ligam e desligam, e não uma caixa onde se escreve
 * «1,2,3,4,5» — que é o que a base guarda e ninguém devia ter de saber. O dia
 * aceso levanta-se um pouco; o apagado fica cinzento e continua a ler-se.
 *
 * Cada botão é um `aria-pressed` a sério, para quem navega por teclado ouvir
 * o estado em vez de o adivinhar pela cor.
 */
function EscolherDias({ valor, aoMudar, etiqueta }: {
    valor: Valor | undefined;
    aoMudar: (v: number[]) => void;
    etiqueta: string;
}) {
    const escolhidos = Array.isArray(valor) ? valor.map(Number) : [];

    const alternar = (dia: number) =>
        aoMudar(escolhidos.includes(dia)
            ? escolhidos.filter((d) => d !== dia)
            : [...escolhidos, dia].sort((a, b) => a - b));

    return (
        <div role="group" aria-label={etiqueta} className="flex flex-wrap gap-1.5">
            {DIAS_DA_SEMANA.map((d) => {
                const aceso = escolhidos.includes(d.valor);

                return (
                    <button
                        key={d.valor}
                        type="button"
                        onClick={() => alternar(d.valor)}
                        aria-pressed={aceso}
                        title={d.nome}
                        className={cls(
                            'min-w-[3.25rem] border px-2.5 py-1.5 text-xs font-bold transition-all duration-200',
                            'hover:-translate-y-0.5 active:translate-y-0',
                            RAIO,
                            FOCO,
                            aceso
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-400 hover:border-slate-300 hover:text-slate-600',
                        )}
                    >
                        <i
                            className={cls(
                                'fas mr-1 text-[10px] transition-opacity duration-200',
                                aceso ? 'fa-check opacity-100' : 'fa-minus opacity-40',
                            )}
                            aria-hidden="true"
                        />
                        {t(d.curto)}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * MARCAR VÁRIAS DE UMA LISTA FECHADA — as especialidades de um mecânico.
 *
 * O mesmo desenho dos dias da semana, e pela mesma razão: seis caixas de
 * verificação em coluna ocupam meio formulário e lêem-se uma a uma; seis
 * botões que se acendem lêem-se de relance e cabem numa linha.
 *
 * O que muda é que aqui o valor é o RÓTULO — «Motor», «Chapa» — porque é assim
 * que a coluna `specialties` está gravada desde sempre, e traduzir a chave ao
 * gravar mudava o que já lá está.
 */
function EscolherVarios({ valor, opcoes, aoMudar, etiqueta }: {
    valor: Valor | undefined;
    opcoes: Array<{ valor: string; rotulo: string }>;
    aoMudar: (v: string[]) => void;
    etiqueta: string;
}) {
    const escolhidos = Array.isArray(valor) ? valor.map(String) : [];

    const alternar = (v: string) =>
        aoMudar(escolhidos.includes(v) ? escolhidos.filter((x) => x !== v) : [...escolhidos, v]);

    return (
        <div role="group" aria-label={etiqueta} className="flex flex-wrap gap-1.5">
            {opcoes.map((op) => {
                const aceso = escolhidos.includes(op.valor);

                return (
                    <button
                        key={op.valor}
                        type="button"
                        onClick={() => alternar(op.valor)}
                        aria-pressed={aceso}
                        className={cls(
                            'border px-2.5 py-1.5 text-xs font-bold transition-all duration-200',
                            'hover:-translate-y-0.5 active:translate-y-0',
                            RAIO,
                            FOCO,
                            aceso
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-400 hover:border-slate-300 hover:text-slate-600',
                        )}
                    >
                        <i
                            className={cls('fas mr-1 text-[10px] transition-opacity duration-200',
                                aceso ? 'fa-check opacity-100' : 'fa-plus opacity-40')}
                            aria-hidden="true"
                        />
                        {op.rotulo}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * UMA LISTA ESCRITA À MÃO — os serviços incluídos num pacote.
 *
 * Ao contrário das especialidades do mecânico, aqui NÃO há lista de onde
 * escolher: cada casa inclui no pacote o que quer, e escrever «Transfer do
 * aeroporto» não pode obrigar a mexer no código.
 *
 * ENTER JUNTA, e não submete o formulário — que é o defeito clássico desta
 * peça: quem escreve o primeiro serviço e carrega em Enter grava o registo a
 * meio sem perceber porquê.
 */
function EscreverEtiquetas({ valor, aoMudar, etiqueta }: {
    valor: Valor | undefined;
    aoMudar: (v: string[]) => void;
    etiqueta: string;
}) {
    const [aEscrever, porAEscrever] = useState('');
    const lista = Array.isArray(valor) ? valor.map(String) : [];

    const juntar = () => {
        const nova = aEscrever.trim();

        // Repetida não entra: duas linhas iguais no pacote não dizem nada.
        if (nova !== '' && !lista.includes(nova)) aoMudar([...lista, nova]);

        porAEscrever('');
    };

    return (
        <div className="space-y-2">
            <span className="flex gap-2">
                <input
                    value={aEscrever}
                    onChange={(e) => porAEscrever(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') { e.preventDefault(); juntar(); }
                    }}
                    aria-label={etiqueta}
                    placeholder={t('Escreva e carregue em Enter')}
                    className={entrada}
                />
                <Botao icone="fa-plus" onClick={juntar} disabled={aEscrever.trim() === ''}>{t('Juntar')}</Botao>
            </span>

            {lista.length > 0 && (
                <ul className="flex flex-wrap gap-1.5">
                    {lista.map((x) => (
                        <li key={x} className="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                            {x}
                            <button
                                type="button"
                                onClick={() => aoMudar(lista.filter((y) => y !== x))}
                                aria-label={t('Remover: :nome', { nome: x })}
                                className={cls('text-indigo-400 transition-colors hover:text-red-600', FOCO, RAIO)}
                            >
                                <i className="fas fa-xmark text-[10px]" aria-hidden="true" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function CampoDeEsquema({ c, valor, erro, o, valores, aoMudar }: {
    c: Campo;
    valor: Valor | undefined;
    erro?: string[];
    o: OpcoesDoCatalogo;
    valores: Valores;
    aoMudar: (v: Valor) => void;
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
        /* A DATA e a VALIDADE escrevem-se do mesmo modo — o que muda é como se
           LÊEM na lista, onde a que passou sai a vermelho. */
        case 'data':
        case 'validade':
            controlo = <input type="date" value={texto.slice(0, 10)} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'tabular-nums')} />;
            break;
        case 'cor':
            controlo = (
                <span className="flex items-center gap-2">
                    <input type="color" value={texto || '#3B82F6'} onChange={(e) => aoMudar(e.target.value)} aria-label={c.rotulo} className="h-10 w-12 cursor-pointer rounded border border-slate-200" />
                    <input value={texto} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'font-mono')} />
                </span>
            );
            break;
        /*
         * O ÍCONE ESCOLHE-SE DE UMA GALERIA, e não escrevendo o código.
         *
         * Era uma caixa de texto onde se escrevia `fa-money-bill` e se
         * esperava pelo melhor. Um código mal escrito não dá erro nenhum —
         * dá um quadrado vazio na lista, e só se descobre depois.
         */
        case 'icone':
            controlo = <EscolherIcone valor={texto} aoMudar={aoMudar} etiqueta={c.rotulo} galeria={o.galeria_de_icones ?? []} />;
            break;
        /*
         * A HORA, com o relógio à esquerda.
         *
         * `type="time"` traz o selector do sistema e a máscara certa em cada
         * língua — escrever «8h» numa caixa de texto e esperar que o servidor
         * adivinhe era o que havia antes.
         */
        case 'hora':
            controlo = (
                <span className="relative block">
                    <i className="fas fa-clock pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true" />
                    <input
                        type="time"
                        value={texto.slice(0, 5)}
                        onChange={(e) => aoMudar(e.target.value)}
                        aria-label={c.rotulo}
                        className={cls(entrada, 'pl-9 tabular-nums')}
                    />
                </span>
            );
            break;
        /* OS DIAS DA SEMANA: sete botões que se acendem — ver `EscolherDias`. */
        case 'dias':
            controlo = <EscolherDias valor={valor} aoMudar={aoMudar} etiqueta={c.rotulo} />;
            break;
        /* VÁRIAS DE UMA LISTA FECHADA — as especialidades do mecânico. */
        case 'multi':
            controlo = <EscolherVarios valor={valor} opcoes={c.opcoes ?? []} aoMudar={aoMudar} etiqueta={c.rotulo} />;
            break;
        /* UMA LISTA ESCRITA À MÃO — os serviços incluídos num pacote. */
        case 'etiquetas':
            controlo = <EscreverEtiquetas valor={valor} aoMudar={aoMudar} etiqueta={c.rotulo} />;
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
        // Uma lista lê-se «Motor, Chapa» e não «Motor,Chapa»: o `String()` de
        // um array cola tudo sem espaço e a ficha ficava a parecer código.
        if (Array.isArray(v)) return v.length > 0 ? v.join(', ') : null;
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
