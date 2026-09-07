import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { catalogos, type Campo, type Coluna, type FiltrosDoCatalogo, type Linha, type OpcoesDoCatalogo } from '@/api/catalogos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo as CampoDoFormulario, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
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

export default function Catalogo({ tipo }: { tipo: string }) {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDoCatalogo>({ procura: '', page: 1 });
    const [aEditar, porAEditar] = useState<Linha | null>(null);
    const [formulario, porFormulario] = useState<Valores | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Linha | null>(null);
    const [recado, porRecado] = useState('');

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
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={apagar.error ?? accao.error ?? logotipo.error} />

            {/* OS CARTÕES DO TOPO. A contagem é a do servidor, com os filtros
                postos; o que está contado nas linhas à vista di-lo no cartão. */}
            <div className={cls('grid gap-3 sm:grid-cols-2', o.accoes.activar ? 'lg:grid-cols-3' : 'lg:grid-cols-2', lista.isFetching && 'opacity-70')}>
                <CartaoNumero
                    rotulo={o.titulo}
                    tom="indigo"
                    icone={o.icone}
                    nota={t('com os filtros actuais')}
                    valor={contas === undefined ? <span className="text-white/50">—</span> : contas.total.toLocaleString(etiquetaIntl())}
                />
                <CartaoNumero
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
                        rotulo={t('Activos nesta página')}
                        tom="verde"
                        icone="fa-toggle-on"
                        valor={linhas.filter((l) => l.is_active !== false).length.toLocaleString(etiquetaIntl())}
                        nota={t(':quantos nesta página', { quantos: linhas.length })}
                    />
                )}
            </div>

            <Cartao
                titulo={o.titulo}
                icone={o.icone}
                accoes={o.permissoes.pode_escrever && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>{t('Novo(a) :nome', { nome: o.singular.toLowerCase() })}</Botao>}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="flex-1 min-w-[16rem] text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input value={filtros.procura ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={o.pesquisa} className={entrada} />
                    </label>
                    {o.filtros.map((f) => (
                        <label key={f.chave} className="text-sm">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{f.rotulo}</span>
                            <select value={String(filtros[f.chave] ?? '')} onChange={(e) => porFiltros((x) => ({ ...x, [f.chave]: e.target.value, page: 1 }))} className={entrada}>
                                <option value="">{t('Todos')}</option>
                                {f.opcoes.map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
                            </select>
                        </label>
                    ))}
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
                                                            {t('Novo(a) :nome', { nome: o.singular.toLowerCase() })}
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
                                        {o.permissoes.pode_escrever && (
                                            <span className="flex justify-end gap-1">
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
                                            </span>
                                        )}
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
                    titulo={aEditar ? t('Editar :nome', { nome: o.singular.toLowerCase() }) : t('Novo(a) :nome', { nome: o.singular.toLowerCase() })}
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

function Formulario({ o, valores, erros, titulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDoCatalogo;
    valores: Valores;
    erros: Record<string, string[]>;
    titulo: string;
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

function Falhou({ erro }: { erro: unknown }) {
    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o catálogo')}</h2>
            <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
        </div>
    );
}
