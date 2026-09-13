import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { salao, type CategoriaDeServicos, type ServicoDoSalao } from '@/api/salao';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { EscolherIcone } from '@/ui/EscolherIcone';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * OS SERVIÇOS DO SALÃO — e as categorias, no mesmo ecrã.
 *
 * ESTAVAM EM DOIS ECRÃS e são a mesma decisão: a ordem das categorias é a ordem
 * por que os serviços aparecem no balcão e na página de marcação.
 *
 * O SERVIÇO É UM ARTIGO DA FACTURAÇÃO: é assim que ele entra numa factura com
 * imposto e vai ao SAFT. A duração, a comissão e a categoria são do salão.
 *
 * A DURAÇÃO É O CAMPO QUE MAIS CONTA. É ela que enche a agenda: um corte de 30
 * minutos que leva 50 atrasa o dia inteiro a partir do meio da manhã — e é isso
 * que o relatório de tempos vai dizer.
 */

const servicoVazio = () => ({
    category_id: '', name: '', text_description: '', duration: '30',
    price: '0', cost: '0', commission_percent: '0', is_active: true, online_booking: true,
});

const categoriaVazia = () => ({
    name: '', description: '', icon: 'fa-spa', color: '#6366F1', order: '0', is_active: true,
});

export default function Servicos({ separador }: { separador?: string }) {
    const cache = useQueryClient();

    const [aba, porAba] = useState(separador === 'categorias' ? 'categorias' : 'servicos');
    const [filtros, porFiltros] = useState<{ procura: string; categoria: number | ''; por_pagina: number; page: number }>({
        procura: '', categoria: '', por_pagina: 15, page: 1,
    });
    const [procura, porProcura] = useState('');

    const [formulario, porFormulario] = useState<ReturnType<typeof servicoVazio> | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<ServicoDoSalao | null>(null);

    const [categoria, porCategoria] = useState<{ id: number | null; dados: ReturnType<typeof categoriaVazia> } | null>(null);
    const [aApagarCategoria, porAApagarCategoria] = useState<CategoriaDeServicos | null>(null);

    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    useEffect(() => {
        const id = setTimeout(() => porFiltros((f) => ({ ...f, procura, page: 1 })), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const opcoes = useQuery({
        queryKey: ['salao', 'servicos', 'opcoes'],
        queryFn: salao.servicos.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['salao', 'servicos', 'lista', filtros],
        queryFn: () => salao.servicos.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['salao', 'servicos'] });
    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };
    const feito = (r: { message: string }) => { porErros({}); porErro(null); porRecado(r.message); refrescar(); };

    const guardar = useMutation({
        mutationFn: () => salao.servicos.guardar(aEditar, {
            ...formulario,
            category_id: Number(formulario!.category_id),
            duration: Number(formulario!.duration),
            price: Number(formulario!.price),
            cost: Number(formulario!.cost),
            commission_percent: Number(formulario!.commission_percent),
        }),
        onSuccess: (r) => { porFormulario(null); porAEditar(null); feito(r); },
        onError: falhou,
    });

    const alternar = useMutation({
        mutationFn: (id: number) => salao.servicos.alternar(id),
        onSuccess: feito, onError: falhou,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => salao.servicos.apagar(id),
        onSuccess: (r) => { porAApagar(null); feito(r); },
        onError: (e) => { porAApagar(null); falhou(e); },
    });

    const guardarCategoria = useMutation({
        mutationFn: () => salao.servicos.guardarCategoria(categoria!.id, {
            ...categoria!.dados, order: Number(categoria!.dados.order),
        }),
        onSuccess: (r) => { porCategoria(null); feito(r); },
        onError: falhou,
    });

    const moverCategoria = useMutation({
        mutationFn: ({ id, direccao }: { id: number; direccao: 'cima' | 'baixo' }) =>
            salao.servicos.moverCategoria(id, direccao),
        onSuccess: () => refrescar(), onError: falhou,
    });

    const apagarCategoria = useMutation({
        mutationFn: (id: number) => salao.servicos.apagarCategoria(id),
        onSuccess: (r) => { porAApagarCategoria(null); feito(r); },
        onError: (e) => { porAApagarCategoria(null); falhou(e); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const categorias = lista.data?.categorias ?? o.categorias;
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Serviços')}
                subtitulo={t('O que o salão faz — e quanto tempo leva')}
                icone="fa-scissors"
                cor="roxo"
                accoes={
                    <>
                        {o.permissoes.pode_criar && (
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                onClick={() => { porAEditar(null); porErros({}); porFormulario(servicoVazio()); }}
                            >
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo serviço')}
                            </button>
                        )}
                        <a href="/salon/professionals" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-user-group" aria-hidden="true" />
                            {t('Profissionais')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <EstadoNaFaixa icone="fa-layer-group">
                        {t(':s serviços em :c categorias', { s: String(resumo.total), c: String(resumo.categorias) })}
                    </EstadoNaFaixa>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <CartaoNumero aspecto="claro" rotulo={t('Serviços')} valor={resumo.total} icone="fa-scissors" tom="roxo" />
                    <CartaoNumero aspecto="claro" rotulo={t('Activos')} valor={resumo.activos} icone="fa-circle-check" tom="verde" />
                    <CartaoNumero aspecto="claro" rotulo={t('Categorias')} valor={resumo.categorias} icone="fa-layer-group" tom="indigo" />
                </div>
            )}

            <Separadores
                activa={aba}
                aoMudar={porAba}
                abas={[
                    { chave: 'servicos', rotulo: t('Serviços'), icone: 'fa-scissors' },
                    { chave: 'categorias', rotulo: t('Categorias'), icone: 'fa-layer-group' },
                ]}
            />

            <PainelDoSeparador chave="servicos" activa={aba}>
                <div className="space-y-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    type="search"
                                    value={procura}
                                    onChange={(e) => porProcura(e.target.value)}
                                    placeholder={t('Nome do serviço…')}
                                    className={cls(entrada, 'pl-9')}
                                />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Categoria')} className="w-52">
                            <select
                                value={filtros.categoria}
                                onChange={(e) => porFiltros({ ...filtros, categoria: e.target.value ? Number(e.target.value) : '', page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todas')}</option>
                                {categorias.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
                            </select>
                        </Campo>
                    </div>

                    <div className={cls(CARTAO, 'overflow-hidden')}>
                        {lista.isPending ? (
                            <div className="p-5"><Carregando linhas={6} /></div>
                        ) : (lista.data?.data.length ?? 0) === 0 ? (
                            <SemNada
                                icone="fa-scissors"
                                titulo={t('Ainda não há serviços')}
                                frase={t('Sem serviços não há marcações nem página pública — é por aqui que o salão começa.')}
                                accao={o.permissoes.pode_criar && (
                                    <Botao
                                        cor="primaria" tom="solida" icone="fa-plus"
                                        onClick={() => { porAEditar(null); porErros({}); porFormulario(servicoVazio()); }}
                                    >
                                        {t('Novo serviço')}
                                    </Botao>
                                )}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[48rem] text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50/60 text-left">
                                            <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Serviço')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Categoria')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Duração')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Preço')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Margem')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Acções')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {lista.data?.data.map((s, i) => (
                                            <tr key={s.id} style={cascata(i)} className={cls('entra', !s.activo && 'bg-slate-50/60')}>
                                                <td className="px-4 py-2.5">
                                                    <p className="font-semibold text-slate-800">{s.nome}</p>
                                                    <p className="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                                        {s.comissao > 0 && (
                                                            <span>{t('comissão :n%', { n: String(s.comissao) })}</span>
                                                        )}
                                                        {s.marcacao_online && (
                                                            <Etiqueta cor="primaria" icone="fa-globe">{t('na página')}</Etiqueta>
                                                        )}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-2.5 text-slate-600">{s.categoria ?? '—'}</td>
                                                <td className="px-4 py-2.5 text-right tabular-nums text-slate-700">{s.duracao_rotulo}</td>
                                                <td className="px-4 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(s.preco)}</td>
                                                <td className="px-4 py-2.5 text-right">
                                                    {s.margem === null ? (
                                                        <span className="text-xs text-slate-400">—</span>
                                                    ) : (
                                                        <Etiqueta cor={s.margem >= 60 ? 'bom' : s.margem >= 30 ? 'aviso' : 'perigo'}>
                                                            {s.margem}%
                                                        </Etiqueta>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="flex justify-end gap-1">
                                                        {o.permissoes.pode_editar && (
                                                            <>
                                                                <Botao
                                                                    altura="pequeno" icone="fa-pen"
                                                                    onClick={() => {
                                                                        porAEditar(s.id);
                                                                        porErros({});
                                                                        porFormulario({
                                                                            category_id: String(s.category_id ?? ''),
                                                                            name: s.nome,
                                                                            text_description: s.descricao ?? '',
                                                                            duration: String(s.duracao),
                                                                            price: String(s.preco),
                                                                            cost: String(s.custo),
                                                                            commission_percent: String(s.comissao),
                                                                            is_active: s.activo,
                                                                            online_booking: s.marcacao_online,
                                                                        });
                                                                    }}
                                                                    aria-label={t('Editar')}
                                                                />
                                                                <Botao
                                                                    altura="pequeno"
                                                                    cor={s.activo ? 'aviso' : 'bom'}
                                                                    icone={s.activo ? 'fa-eye-slash' : 'fa-eye'}
                                                                    onClick={() => alternar.mutate(s.id)}
                                                                    aria-label={s.activo ? t('Desligar') : t('Ligar')}
                                                                />
                                                            </>
                                                        )}
                                                        {o.permissoes.pode_apagar && (
                                                            <Botao
                                                                altura="pequeno" cor="perigo" icone="fa-trash"
                                                                onClick={() => porAApagar(s)}
                                                                aria-label={t('Eliminar')}
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {meta && meta.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-slate-500">
                                {t('A mostrar :de a :ate de :total', {
                                    de: String(meta.from ?? 0), ate: String(meta.to ?? 0), total: String(meta.total),
                                })}
                            </p>
                            <div className="flex items-center gap-2">
                                <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
                                <Botao
                                    altura="pequeno" icone="fa-chevron-left"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page - 1 })}
                                    aria-label={t('Página anterior')}
                                />
                                <span className="text-xs font-semibold tabular-nums text-slate-600">
                                    {meta.current_page}/{meta.last_page}
                                </span>
                                <Botao
                                    altura="pequeno" icone="fa-chevron-right"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page + 1 })}
                                    aria-label={t('Página seguinte')}
                                />
                            </div>
                        </div>
                    )}
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="categorias" activa={aba}>
                <Cartao
                    titulo={t('Categorias')}
                    subtitulo={t('A ordem daqui é a ordem por que os serviços aparecem')}
                    icone="fa-layer-group"
                    semPadding
                    accoes={o.permissoes.pode_gerir_categorias && (
                        <Botao
                            altura="pequeno" cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => { porErros({}); porCategoria({ id: null, dados: categoriaVazia() }); }}
                        >
                            {t('Nova')}
                        </Botao>
                    )}
                >
                    {categorias.length === 0 ? (
                        <SemNada icone="fa-layer-group" frase={t('Ainda não há categorias — e um serviço precisa de uma.')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {categorias.map((c, i) => (
                                <li key={c.id} style={cascata(i)} className="entra flex items-center gap-3 px-4 py-3">
                                    <span
                                        className="grid h-10 w-10 flex-none place-items-center rounded-xl text-white shadow-sm"
                                        style={{ backgroundColor: c.cor }}
                                    >
                                        <i className={`fas ${c.icone}`} aria-hidden="true" />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">{c.nome}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {t(':n serviços', { n: String(c.servicos) })}
                                            {c.descricao && ` · ${c.descricao}`}
                                        </p>
                                    </div>

                                    <Etiqueta cor={c.activa ? 'bom' : 'neutra'} ponto>
                                        {c.activa ? t('Activa') : t('Desligada')}
                                    </Etiqueta>

                                    {o.permissoes.pode_gerir_categorias && (
                                        <div className="flex flex-none gap-1">
                                            <Botao
                                                altura="pequeno" icone="fa-arrow-up"
                                                disabled={i === 0}
                                                onClick={() => moverCategoria.mutate({ id: c.id, direccao: 'cima' })}
                                                aria-label={t('Subir')}
                                            />
                                            <Botao
                                                altura="pequeno" icone="fa-arrow-down"
                                                disabled={i === categorias.length - 1}
                                                onClick={() => moverCategoria.mutate({ id: c.id, direccao: 'baixo' })}
                                                aria-label={t('Descer')}
                                            />
                                            <Botao
                                                altura="pequeno" icone="fa-pen"
                                                onClick={() => {
                                                    porErros({});
                                                    porCategoria({
                                                        id: c.id,
                                                        dados: {
                                                            name: c.nome, description: c.descricao ?? '',
                                                            icon: c.icone, color: c.cor,
                                                            order: String(c.ordem), is_active: c.activa,
                                                        },
                                                    });
                                                }}
                                                aria-label={t('Editar')}
                                            />
                                            <Botao
                                                altura="pequeno" cor="perigo" icone="fa-trash"
                                                disabled={c.servicos > 0}
                                                onClick={() => porAApagarCategoria(c)}
                                                aria-label={t('Eliminar')}
                                            />
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </PainelDoSeparador>

            {/* ─── O serviço ─── */}
            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={aEditar ? t('Editar serviço') : t('Novo serviço')}
                subtitulo={t('A duração é o que enche a agenda')}
                icone={aEditar ? 'fa-pen' : 'fa-plus'}
                cor="roxo"
                rodape={
                    <>
                        <Botao onClick={() => porFormulario(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                                <input
                                    value={formulario.name}
                                    onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                    className={entrada}
                                    autoFocus
                                />
                            </Campo>

                            <Campo etiqueta={t('Categoria')} obrigatorio erro={erros.category_id}>
                                <select
                                    value={formulario.category_id}
                                    onChange={(e) => porFormulario({ ...formulario, category_id: e.target.value })}
                                    className={entrada}
                                >
                                    <option value="">{t('Escolher…')}</option>
                                    {categorias.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Descrição')} erro={erros.text_description}>
                            <textarea
                                value={formulario.text_description}
                                onChange={(e) => porFormulario({ ...formulario, text_description: e.target.value })}
                                rows={2}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-4">
                            <Campo
                                etiqueta={t('Duração (min)')}
                                obrigatorio
                                erro={erros.duration}
                                ajuda={t('É ela que enche a agenda.')}
                            >
                                <input
                                    type="number" min="5" max="600" step="5"
                                    value={formulario.duration}
                                    onChange={(e) => porFormulario({ ...formulario, duration: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Preço')} obrigatorio erro={erros.price}>
                                <input
                                    type="number" step="0.01" min="0"
                                    value={formulario.price}
                                    onChange={(e) => porFormulario({ ...formulario, price: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Custo')} erro={erros.cost} ajuda={t('Produto gasto por serviço.')}>
                                <input
                                    type="number" step="0.01" min="0"
                                    value={formulario.cost}
                                    onChange={(e) => porFormulario({ ...formulario, cost: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Comissão %')} erro={erros.commission_percent}>
                                <input
                                    type="number" step="0.01" min="0" max="100"
                                    value={formulario.commission_percent}
                                    onChange={(e) => porFormulario({ ...formulario, commission_percent: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        </div>

                        <div className="flex flex-wrap gap-6">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={formulario.is_active}
                                    onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                    className="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500"
                                />
                                {t('Activo')}
                            </label>

                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={formulario.online_booking}
                                    onChange={(e) => porFormulario({ ...formulario, online_booking: e.target.checked })}
                                    className="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500"
                                />
                                {t('Aparece na página de marcação')}
                            </label>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── A categoria ─── */}
            <Modal
                aberto={categoria !== null}
                aoFechar={() => porCategoria(null)}
                titulo={categoria?.id ? t('Editar categoria') : t('Nova categoria')}
                icone={categoria?.id ? 'fa-pen' : 'fa-plus'}
                cor="roxo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porCategoria(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardarCategoria.isPending} onClick={() => guardarCategoria.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {categoria && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input
                                value={categoria.dados.name}
                                onChange={(e) => porCategoria({ ...categoria, dados: { ...categoria.dados, name: e.target.value } })}
                                className={entrada}
                                autoFocus
                            />
                        </Campo>

                        <Campo etiqueta={t('Descrição')} erro={erros.description}>
                            <textarea
                                value={categoria.dados.description}
                                onChange={(e) => porCategoria({ ...categoria, dados: { ...categoria.dados, description: e.target.value } })}
                                rows={2}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Ícone')} erro={erros.icon}>
                                <EscolherIcone
                                    valor={categoria.dados.icon}
                                    aoMudar={(v) => porCategoria({ ...categoria, dados: { ...categoria.dados, icon: v } })}
                                    etiqueta={t('Ícone')}
                                    galeria={o.galeria_de_icones ?? []}
                                />
                            </Campo>

                            <Campo etiqueta={t('Cor')} erro={erros.color}>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        value={categoria.dados.color}
                                        onChange={(e) => porCategoria({ ...categoria, dados: { ...categoria.dados, color: e.target.value } })}
                                        className="h-10 w-14 flex-none cursor-pointer rounded-xl border border-slate-300 bg-white p-1"
                                        aria-label={t('Cor')}
                                    />
                                    <input
                                        value={categoria.dados.color}
                                        onChange={(e) => porCategoria({ ...categoria, dados: { ...categoria.dados, color: e.target.value } })}
                                        className={cls(entrada, 'font-mono uppercase')}
                                    />
                                </div>
                            </Campo>
                        </div>

                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={categoria.dados.is_active}
                                onChange={(e) => porCategoria({ ...categoria, dados: { ...categoria.dados, is_active: e.target.checked } })}
                                className="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500"
                            />
                            {t('Activa')}
                        </label>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar serviço')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Um serviço que já esteve em marcações não se apaga — desligue-o. Apagá-lo deixava documentos a apontar para o vazio.')}
                </p>
            </Modal>

            <Modal
                aberto={aApagarCategoria !== null}
                aoFechar={() => porAApagarCategoria(null)}
                titulo={t('Eliminar categoria')}
                subtitulo={aApagarCategoria?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagarCategoria(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-trash"
                            aTrabalhar={apagarCategoria.isPending}
                            onClick={() => aApagarCategoria && apagarCategoria.mutate(aApagarCategoria.id)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Só se elimina uma categoria sem serviços. Se tiver, mova-os primeiro.')}
                </p>
            </Modal>
        </div>
    );
}
