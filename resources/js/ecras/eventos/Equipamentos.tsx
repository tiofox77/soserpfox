import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { eventos, type CategoriaDeEquipamentos, type Conjunto, type Equipamento } from '@/api/eventos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS EQUIPAMENTOS — o parque, os conjuntos e as categorias.
 *
 * O DEFEITO QUE ISTO FECHA era um erro 500 a sério: gravar um equipamento COM
 * NÚMERO DE SÉRIE rebentava, porque a regra de unicidade apontava para uma
 * tabela que não existe. Quem tem material caro escreve sempre o número de
 * série — ou seja, toda a gente que usava este ecrã a sério.
 *
 * TRÊS PÁGINAS PASSARAM A SER TRÊS SEPARADORES: o parque, os conjuntos e as
 * categorias. Eram três moradas com a mesma barra de navegação copiada no topo
 * de cada uma — e a barra era a prova de que pertenciam ao mesmo ecrã.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    disponivel: 'bom',
    reservado: 'primaria',
    em_uso: 'primaria',
    emprestado: 'aviso',
    manutencao: 'aviso',
    avariado: 'perigo',
    descartado: 'neutra',
};

const cor = (e: string) => COR_DO_ESTADO[e] ?? 'neutra';

const VAZIO = {
    name: '', category_id: '', serial_number: '', location: '', description: '',
    status: 'disponivel', acquisition_date: '', purchase_price: '', current_value: '',
    last_maintenance_date: '', next_maintenance_date: '', maintenance_notes: '', is_active: true,
};

export default function Equipamentos({
    separador, equipamento: abrirEquipamento,
}: {
    separador?: string;
    equipamento?: number;
}) {
    const cache = useQueryClient();

    const [aba, porAba] = useState(
        separador === 'conjuntos' ? 'conjuntos' : separador === 'categorias' ? 'categorias' : 'parque',
    );
    const [filtros, porFiltros] = useState<{
        procura?: string; categoria?: number | ''; estado?: string;
        local?: string; por_pagina?: number; page?: number;
    }>({ por_pagina: 12, page: 1 });

    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<number | null>(abrirEquipamento ?? null);
    const [aEmprestar, porAEmprestar] = useState<Equipamento | null>(null);
    const [aManutencao, porAManutencao] = useState<Equipamento | null>(null);
    const [aApagar, porAApagar] = useState<Equipamento | null>(null);

    const opcoes = useQuery({
        queryKey: ['eventos', 'equipamentos', 'opcoes'],
        queryFn: () => eventos.equipamentos.opcoes(),
    });

    const lista = useQuery({
        queryKey: ['eventos', 'equipamentos', 'lista', filtros],
        queryFn: () => eventos.equipamentos.lista(filtros),
        enabled: aba === 'parque',
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['eventos'] });
    };

    const guardar = useMutation({
        mutationFn: (dados: Record<string, unknown>) => eventos.equipamentos.guardar(aEditar, dados),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const devolver = useMutation({
        mutationFn: (id: number) => eventos.equipamentos.devolver(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => eventos.equipamentos.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.pode_gerir;
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;
    const avisos = lista.data?.avisos ?? [];
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const abrirNovo = () => { porAEditar(null); porFormulario({ ...VAZIO }); };

    const abrirEdicao = (e: Equipamento) => {
        porAEditar(e.id);
        porFormulario({
            name: e.nome,
            category_id: e.category_id ? String(e.category_id) : '',
            serial_number: e.numero_de_serie ?? '',
            location: e.local ?? '',
            description: e.descricao ?? '',
            status: e.estado,
            acquisition_date: e.aquisicao ?? '',
            purchase_price: e.preco_de_compra ? String(e.preco_de_compra) : '',
            current_value: e.valor_actual ? String(e.valor_actual) : '',
            last_maintenance_date: e.ultima_manutencao ?? '',
            next_maintenance_date: e.proxima_manutencao ?? '',
            maintenance_notes: e.notas_de_manutencao ?? '',
            is_active: e.activo,
        });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Equipamentos')}
                subtitulo={t('O que há, onde está, e com quem')}
                icone="fa-tools"
                cor="roxo"
                accoes={
                    <>
                        {pode && aba === 'parque' && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNovo}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo equipamento')}
                            </button>
                        )}
                        <a href="/events/equipment/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chart-pie" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-circle-check">
                            {t(':n disponíveis', { n: numero(resumo.disponivel) })}
                        </EstadoNaFaixa>
                        <EstadoNaFaixa icone="fa-gauge">
                            {t(':n% em serviço', { n: String(resumo.taxa_de_uso) })}
                        </EstadoNaFaixa>
                    </div>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {/*
              * OS AVISOS À CABEÇA. O material atrasado e a manutenção por fazer
              * são o que impede a montagem de sábado — não podem estar no fundo
              * da página, atrás de uma paginação.
              */}
            {avisos.length > 0 && (
                <ul className="space-y-2">
                    {avisos.slice(0, 5).map((a, i) => (
                        <li
                            key={`${a.equipamento_id}-${i}`}
                            style={cascata(i)}
                            className={cls(
                                'entra flex items-center gap-3 border px-4 py-2.5 text-sm font-medium', RAIO,
                                a.tipo === 'perigo'
                                    ? 'border-red-200 bg-red-50 text-red-800'
                                    : 'border-amber-200 bg-amber-50 text-amber-800',
                            )}
                        >
                            <i className={`fas ${a.icone}`} aria-hidden="true" />
                            {a.mensagem}
                        </li>
                    ))}
                </ul>
            )}

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero aspecto="claro" rotulo={t('No parque')} valor={numero(resumo.total)} icone="fa-boxes-stacked" tom="roxo" />
                    <CartaoNumero aspecto="claro" rotulo={t('Disponível')} valor={numero(resumo.disponivel)} icone="fa-circle-check" tom="verde" />
                    <CartaoNumero
                        aspecto="claro" rotulo={t('Fora de casa')} valor={numero(resumo.emprestado + resumo.em_uso)}
                        icone="fa-hand-holding" tom="ambar"
                        nota={t(':m em manutenção · :a avariados', { m: numero(resumo.manutencao), a: numero(resumo.avariado) })}
                    />
                    <CartaoNumero
                        aspecto="claro" rotulo={t('Valor do parque')} valor={kz(resumo.valor)} sufixo="Kz"
                        icone="fa-sack-dollar" tom="indigo"
                    />
                </div>
            )}

            <Separadores
                activa={aba}
                aoMudar={porAba}
                abas={[
                    { chave: 'parque', rotulo: t('Equipamentos'), icone: 'fa-boxes-stacked' },
                    { chave: 'conjuntos', rotulo: t('Conjuntos'), icone: 'fa-layer-group' },
                    { chave: 'categorias', rotulo: t('Categorias'), icone: 'fa-tags' },
                ]}
            />

            <PainelDoSeparador chave="parque" activa={aba}>
                <div className="space-y-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    type="search"
                                    value={filtros.procura ?? ''}
                                    onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                                    placeholder={t('Nome, número de série ou sítio…')}
                                    className={cls(entrada, 'pl-9')}
                                />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Categoria')} className="w-48">
                            <select
                                value={filtros.categoria ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, categoria: e.target.value ? Number(e.target.value) : '', page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todas')}</option>
                                {o.categorias.map((c) => (
                                    <option key={c.id} value={c.id}>{c.icone} {c.nome}</option>
                                ))}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Estado')} className="w-44">
                            <select
                                value={filtros.estado ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined, page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Sítio')} className="w-44">
                            <select
                                value={filtros.local ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, local: e.target.value || undefined, page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {o.locais.map((l) => <option key={l} value={l}>{l}</option>)}
                            </select>
                        </Campo>
                    </div>

                    {lista.isPending ? (
                        <Carregando linhas={6} />
                    ) : lista.isError ? (
                        <AvisoDeErro erro={lista.error} />
                    ) : lista.data.data.length === 0 ? (
                        <SemNada
                            icone="fa-boxes-stacked"
                            titulo={t('Ainda não há equipamentos')}
                            frase={t('Sem parque não há reserva de material nem lista de carga para a montagem.')}
                            accao={pode ? (
                                <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                                    {t('Novo equipamento')}
                                </Botao>
                            ) : undefined}
                        />
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {lista.data.data.map((e, i) => (
                                <article
                                    key={e.id}
                                    style={cascata(i)}
                                    className={cls('entra flex flex-col gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md', CARTAO)}
                                >
                                    <div className="flex items-start gap-3">
                                        {e.imagem ? (
                                            <img src={e.imagem} alt="" className="h-14 w-14 flex-none rounded-xl object-cover" />
                                        ) : (
                                            <span
                                                className="grid h-14 w-14 flex-none place-items-center rounded-xl text-2xl"
                                                style={{ backgroundColor: `${e.categoria_cor ?? '#6366f1'}22` }}
                                            >
                                                {e.categoria_icone ?? '📦'}
                                            </span>
                                        )}

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-bold text-slate-800">{e.nome}</p>
                                            <p className="truncate text-xs text-slate-500">
                                                {e.categoria ?? t('Sem categoria')}
                                            </p>
                                            {e.numero_de_serie && (
                                                <p className="truncate font-mono text-[11px] text-slate-400">{e.numero_de_serie}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <Etiqueta cor={cor(e.estado)} icone={e.estado_icone}>{e.estado_rotulo}</Etiqueta>
                                        {e.atrasado && (
                                            <Etiqueta cor="perigo" icone="fa-clock">
                                                {t(':n dias', { n: String(e.dias_de_atraso) })}
                                            </Etiqueta>
                                        )}
                                    </div>

                                    <dl className="space-y-1 text-xs text-slate-600">
                                        {e.local && (
                                            <div className="flex items-center gap-2">
                                                <i className="fas fa-location-dot w-4 text-slate-400" aria-hidden="true" />
                                                <span className="truncate">{e.local}</span>
                                            </div>
                                        )}
                                        {e.emprestado_a && (
                                            <div className="flex items-center gap-2">
                                                <i className="fas fa-hand-holding w-4 text-amber-500" aria-hidden="true" />
                                                <span className="truncate">
                                                    {t('Com :quem, até :dia', { quem: e.emprestado_a, dia: e.devolver_em ?? '—' })}
                                                </span>
                                            </div>
                                        )}
                                        {e.valor_actual > 0 && (
                                            <div className="flex items-center gap-2">
                                                <i className="fas fa-tag w-4 text-slate-400" aria-hidden="true" />
                                                <span className="tabular-nums">{kz(e.valor_actual)} Kz</span>
                                            </div>
                                        )}
                                    </dl>

                                    <div className="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                                        <Botao altura="pequeno" icone="fa-clock-rotate-left" onClick={() => porAVer(e.id)}>
                                            {t('Historial')}
                                        </Botao>

                                        {pode && (
                                            <>
                                                {e.estado === 'emprestado' ? (
                                                    <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-rotate-left"
                                                        aTrabalhar={devolver.isPending}
                                                        onClick={() => devolver.mutate(e.id)}>
                                                        {t('Devolver')}
                                                    </Botao>
                                                ) : (
                                                    <Botao altura="pequeno" icone="fa-hand-holding" onClick={() => porAEmprestar(e)}>
                                                        {t('Emprestar')}
                                                    </Botao>
                                                )}

                                                <Botao altura="pequeno" icone="fa-screwdriver-wrench"
                                                    onClick={() => porAManutencao(e)} aria-label={t('Manutenção')} />
                                                <Botao altura="pequeno" icone="fa-pen"
                                                    onClick={() => abrirEdicao(e)} aria-label={t('Editar equipamento')} />
                                                <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                                    onClick={() => porAApagar(e)} aria-label={t('Eliminar equipamento')} />
                                            </>
                                        )}

                                        <a
                                            href={`/events/equipment/${e.id}/qrcode/print`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className={cls('grid h-8 w-8 place-items-center border border-slate-200 bg-white text-slate-600 transition hover:border-purple-300 hover:text-purple-600', RAIO, FOCO)}
                                            aria-label={t('Imprimir o QR')}
                                        >
                                            <i className="fas fa-qrcode text-xs" aria-hidden="true" />
                                        </a>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}

                    {meta && meta.last_page > 1 && (
                        <Paginacao
                            pagina={meta.current_page}
                            ultima={meta.last_page}
                            aMudar={(p) => porFiltros({ ...filtros, page: p })}
                            total={meta.total}
                            de={meta.from}
                            ate={meta.to}
                            aCarregar={lista.isFetching}
                            emCartao
                            extra={<PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />}
                        />
                    )}
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="conjuntos" activa={aba}>
                <Conjuntos pode={pode} categorias={o.categorias} aoFeito={feito} aoErro={porErro} />
            </PainelDoSeparador>

            <PainelDoSeparador chave="categorias" activa={aba}>
                <Categorias pode={pode} categorias={o.categorias} emojis={o.emojis} aoFeito={feito} aoErro={porErro} />
            </PainelDoSeparador>

            {/* ─── A janela do equipamento ────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar equipamento') : t('Novo equipamento')}
                subtitulo={t('O número de série é único dentro da empresa')}
                icone="fa-box"
                cor="roxo"
                largura="lg"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                category_id: formulario.category_id ? Number(formulario.category_id) : null,
                                purchase_price: formulario.purchase_price ? Number(formulario.purchase_price) : null,
                                current_value: formulario.current_value ? Number(formulario.current_value) : null,
                            })}
                        >
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input type="text" value={formulario.name}
                                onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                placeholder={t('Ex.: Mesa de som 32 canais')} className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Categoria')} obrigatorio erro={erros.category_id}>
                                <select value={formulario.category_id}
                                    onChange={(e) => porFormulario({ ...formulario, category_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Escolher…')}</option>
                                    {o.categorias.map((c) => (
                                        <option key={c.id} value={c.id}>{c.icone} {c.nome}</option>
                                    ))}
                                </select>
                            </Campo>

                            <Campo
                                etiqueta={t('Número de série')} erro={erros.serial_number}
                                ajuda={t('Único dentro da empresa — duas casas podem ter o mesmo aparelho.')}
                            >
                                <input type="text" value={formulario.serial_number}
                                    onChange={(e) => porFormulario({ ...formulario, serial_number: e.target.value })}
                                    className={cls(entrada, 'font-mono')} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Sítio')} erro={erros.location} ajuda={t('Onde é que ele vive quando não está fora.')}>
                                <input type="text" value={formulario.location}
                                    onChange={(e) => porFormulario({ ...formulario, location: e.target.value })}
                                    placeholder={t('Ex.: Armazém A, prateleira 3')} className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Estado')} obrigatorio erro={erros.status}>
                                <select value={formulario.status}
                                    onChange={(e) => porFormulario({ ...formulario, status: e.target.value })}
                                    className={entrada}>
                                    {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Data de aquisição')} erro={erros.acquisition_date}>
                                <input type="date" value={formulario.acquisition_date}
                                    onChange={(e) => porFormulario({ ...formulario, acquisition_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Preço de compra')} erro={erros.purchase_price}>
                                <input type="number" step="0.01" min="0" value={formulario.purchase_price}
                                    onChange={(e) => porFormulario({ ...formulario, purchase_price: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta={t('Valor actual')} erro={erros.current_value}
                                ajuda={t('É este que soma no valor do parque.')}>
                                <input type="number" step="0.01" min="0" value={formulario.current_value}
                                    onChange={(e) => porFormulario({ ...formulario, current_value: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Última manutenção')} erro={erros.last_maintenance_date}>
                                <input type="date" value={formulario.last_maintenance_date}
                                    onChange={(e) => porFormulario({ ...formulario, last_maintenance_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Próxima manutenção')} erro={erros.next_maintenance_date}
                                ajuda={t('Aparece nos avisos 15 dias antes.')}>
                                <input type="date" value={formulario.next_maintenance_date}
                                    onChange={(e) => porFormulario({ ...formulario, next_maintenance_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Descrição')} erro={erros.description}>
                            <textarea rows={3} value={formulario.description}
                                onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Notas de manutenção')} erro={erros.maintenance_notes}>
                            <textarea rows={2} value={formulario.maintenance_notes}
                                onChange={(e) => porFormulario({ ...formulario, maintenance_notes: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <label className="flex items-center gap-3">
                            <input type="checkbox" checked={formulario.is_active}
                                onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                className="h-5 w-5 rounded text-purple-600" />
                            <span className="text-sm font-medium text-slate-700">{t('Activo')}</span>
                        </label>
                    </div>
                )}
            </Modal>

            <Emprestimo
                equipamento={aEmprestar}
                clientes={o.clientes}
                tecnicos={o.tecnicos}
                aoFechar={() => porAEmprestar(null)}
                aoFeito={(m) => { feito(m); porAEmprestar(null); }}
            />

            <Manutencao
                equipamento={aManutencao}
                aoFechar={() => porAManutencao(null)}
                aoFeito={(m) => { feito(m); porAManutencao(null); }}
            />

            <Historial id={aVer} aoFechar={() => porAVer(null)} />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar equipamento')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />
                <p className="text-sm text-slate-600">
                    {t('Eliminar «:nome»? O que está fora de casa não se apaga — dê-o por devolvido primeiro.', {
                        nome: aApagar?.nome ?? '',
                    })}
                </p>
            </Modal>
        </div>
    );
}

/* ─── O empréstimo ────────────────────────────────────────────────────── */

/**
 * EMPRESTAR É A DOIS: um cliente ou um técnico da casa.
 *
 * E o técnico NÃO PAGA aluguer do material da casa — o preço por dia só aparece
 * quando o destinatário é um cliente.
 */
function Emprestimo({
    equipamento, clientes, tecnicos, aoFechar, aoFeito,
}: {
    equipamento: Equipamento | null;
    clientes: Array<{ valor: string; rotulo: string }>;
    tecnicos: Array<{ valor: string; rotulo: string }>;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const hoje = new Date().toISOString().slice(0, 10);

    const [f, porF] = useState({
        para: 'cliente',
        borrowed_to_client_id: '',
        borrowed_to_technician_id: '',
        borrow_date: hoje,
        return_due_date: '',
        rental_price_per_day: '',
        notes: '',
    });

    const gravar = useMutation({
        mutationFn: () => eventos.equipamentos.emprestar(equipamento!.id, {
            ...f,
            borrowed_to_client_id: f.borrowed_to_client_id ? Number(f.borrowed_to_client_id) : null,
            borrowed_to_technician_id: f.borrowed_to_technician_id ? Number(f.borrowed_to_technician_id) : null,
            rental_price_per_day: f.rental_price_per_day ? Number(f.rental_price_per_day) : null,
        }),
        onSuccess: (r) => aoFeito(r.message),
    });

    const erros = (gravar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    return (
        <Modal
            aberto={equipamento !== null}
            aoFechar={aoFechar}
            titulo={t('Emprestar equipamento')}
            subtitulo={equipamento?.nome}
            icone="fa-hand-holding"
            cor="aviso"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={gravar.isPending}
                        onClick={() => gravar.mutate()}>
                        {t('Emprestar')}
                    </Botao>
                </>
            }
        >
            <div className="space-y-4">
                <AvisoDeErro erro={gravar.error} />

                <div className={cls('inline-flex overflow-hidden border border-slate-200 bg-white', RAIO)}>
                    {(['cliente', 'tecnico'] as const).map((v) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => porF({ ...f, para: v })}
                            className={cls(
                                'px-4 py-2 text-sm font-semibold transition', FOCO,
                                f.para === v ? 'bg-purple-600 text-white' : 'text-slate-600 hover:bg-slate-50',
                            )}
                        >
                            <i className={`fas ${v === 'cliente' ? 'fa-user' : 'fa-user-tie'} mr-1.5`} aria-hidden="true" />
                            {v === 'cliente' ? t('A um cliente') : t('A um técnico')}
                        </button>
                    ))}
                </div>

                {f.para === 'cliente' ? (
                    <Campo etiqueta={t('Cliente')} obrigatorio erro={erros.borrowed_to_client_id}>
                        <select value={f.borrowed_to_client_id}
                            onChange={(e) => porF({ ...f, borrowed_to_client_id: e.target.value })}
                            className={entrada}>
                            <option value="">{t('Escolher…')}</option>
                            {clientes.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                        </select>
                    </Campo>
                ) : (
                    <Campo etiqueta={t('Técnico')} obrigatorio erro={erros.borrowed_to_technician_id}>
                        <select value={f.borrowed_to_technician_id}
                            onChange={(e) => porF({ ...f, borrowed_to_technician_id: e.target.value })}
                            className={entrada}>
                            <option value="">{t('Escolher…')}</option>
                            {tecnicos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Sai em')} obrigatorio erro={erros.borrow_date}>
                        <input type="date" value={f.borrow_date}
                            onChange={(e) => porF({ ...f, borrow_date: e.target.value })} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Devolve em')} obrigatorio erro={erros.return_due_date}
                        ajuda={t('Passado este dia, entra nos atrasos do painel.')}>
                        <input type="date" value={f.return_due_date}
                            onChange={(e) => porF({ ...f, return_due_date: e.target.value })} className={entrada} />
                    </Campo>
                </div>

                {f.para === 'cliente' && (
                    <Campo etiqueta={t('Preço por dia')} erro={erros.rental_price_per_day}
                        ajuda={t('Um técnico da casa não paga aluguer do material da casa.')}>
                        <input type="number" step="0.01" min="0" value={f.rental_price_per_day}
                            onChange={(e) => porF({ ...f, rental_price_per_day: e.target.value })}
                            className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                )}

                <Campo etiqueta={t('Notas')} erro={erros.notes}>
                    <textarea rows={2} value={f.notes}
                        onChange={(e) => porF({ ...f, notes: e.target.value })} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── A manutenção ────────────────────────────────────────────────────── */

function Manutencao({
    equipamento, aoFechar, aoFeito,
}: {
    equipamento: Equipamento | null;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const [f, porF] = useState({ maintenance_notes: '', next_maintenance_date: '', terminada: false });

    const gravar = useMutation({
        mutationFn: () => eventos.equipamentos.manutencao(equipamento!.id, f),
        onSuccess: (r) => aoFeito(r.message),
    });

    return (
        <Modal
            aberto={equipamento !== null}
            aoFechar={aoFechar}
            titulo={t('Manutenção')}
            subtitulo={equipamento?.nome}
            icone="fa-screwdriver-wrench"
            cor="aviso"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={gravar.isPending}
                        onClick={() => gravar.mutate()}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <div className="space-y-4">
                <AvisoDeErro erro={gravar.error} />

                <Campo etiqueta={t('O que se fez')}>
                    <textarea rows={3} value={f.maintenance_notes}
                        onChange={(e) => porF({ ...f, maintenance_notes: e.target.value })} className={entrada} />
                </Campo>

                <Campo etiqueta={t('Próxima manutenção')} ajuda={t('Aparece nos avisos 15 dias antes.')}>
                    <input type="date" value={f.next_maintenance_date}
                        onChange={(e) => porF({ ...f, next_maintenance_date: e.target.value })} className={entrada} />
                </Campo>

                <label className="flex items-center gap-3">
                    <input type="checkbox" checked={f.terminada}
                        onChange={(e) => porF({ ...f, terminada: e.target.checked })}
                        className="h-5 w-5 rounded text-purple-600" />
                    <span className="text-sm font-medium text-slate-700">
                        {t('Já está feita — o equipamento volta a disponível')}
                    </span>
                </label>
            </div>
        </Modal>
    );
}

/* ─── O historial ─────────────────────────────────────────────────────── */

function Historial({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const historial = useQuery({
        queryKey: ['eventos', 'equipamentos', 'historial', id],
        queryFn: () => eventos.equipamentos.historial(id as number),
        enabled: id !== null,
    });

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={t('Historial do equipamento')}
            subtitulo={t('Por onde andou, e com quem')}
            icone="fa-clock-rotate-left"
            cor="roxo"
            largura="md"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {historial.isPending ? (
                <Carregando linhas={5} />
            ) : historial.isError ? (
                <AvisoDeErro erro={historial.error} />
            ) : historial.data.data.length === 0 ? (
                <SemNada icone="fa-clock-rotate-left" titulo={t('Sem movimentos')}
                    frase={t('Este equipamento ainda não saiu nem entrou em manutenção.')} />
            ) : (
                <ol className="space-y-2">
                    {historial.data.data.map((h, i) => (
                        <li key={h.id} style={cascata(i)}
                            className={cls('entra border border-slate-200 bg-white px-3 py-2', RAIO)}>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-semibold capitalize text-slate-800">{h.accao}</span>
                                <span className="text-xs tabular-nums text-slate-500">{h.quando}</span>
                            </div>
                            {(h.cliente || h.evento || h.utilizador) && (
                                <p className="text-xs text-slate-500">
                                    {[h.cliente, h.evento, h.utilizador].filter(Boolean).join(' · ')}
                                </p>
                            )}
                            {h.notas && <p className="mt-0.5 text-xs text-slate-600">{h.notas}</p>}
                        </li>
                    ))}
                </ol>
            )}
        </Modal>
    );
}

/* ─── Os conjuntos ────────────────────────────────────────────────────── */

/**
 * UM CONJUNTO é o material que sai sempre junto — a mesa de som com os cabos e
 * os microfones. Serve para não montar a lista peça a peça de cada vez, e para
 * não esquecer a peça pequena que faz falta no sítio.
 */
function Conjuntos({
    pode, categorias, aoFeito, aoErro,
}: {
    pode: boolean;
    categorias: CategoriaDeEquipamentos[];
    aoFeito: (m: string) => void;
    aoErro: (e: unknown) => void;
}) {
    const [procura, porProcura] = useState('');
    const [formulario, porFormulario] = useState<{ name: string; description: string; category_id: string } | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aItens, porAItens] = useState<Conjunto | null>(null);
    const [novoItem, porNovoItem] = useState({ equipment_id: '', quantity: '1' });

    const lista = useQuery({
        queryKey: ['eventos', 'equipamentos', 'conjuntos', procura],
        queryFn: () => eventos.equipamentos.conjuntos.lista({ procura, por_pagina: 30 }),
    });

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => eventos.equipamentos.conjuntos.guardar(aEditar, d),
        onSuccess: (r) => { aoFeito(r.message); porFormulario(null); porAEditar(null); },
        onError: aoErro,
    });

    const juntar = useMutation({
        mutationFn: () => eventos.equipamentos.conjuntos.juntar(
            aItens!.id, Number(novoItem.equipment_id), Number(novoItem.quantity),
        ),
        onSuccess: (r) => { aoFeito(r.message); porNovoItem({ equipment_id: '', quantity: '1' }); },
        onError: aoErro,
    });

    const tirar = useMutation({
        mutationFn: ({ conjunto, item }: { conjunto: number; item: number }) =>
            eventos.equipamentos.conjuntos.tirar(conjunto, item),
        onSuccess: (r) => aoFeito(r.message),
        onError: aoErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => eventos.equipamentos.conjuntos.apagar(id),
        onSuccess: (r) => aoFeito(r.message),
        onError: aoErro,
    });

    if (lista.isPending) return <Carregando linhas={5} />;
    if (lista.isError) return <AvisoDeErro erro={lista.error} />;

    // A janela dos itens tem de ler o conjunto SEMPRE FRESCO da lista: senão,
    // juntar uma peça não a mostrava até se fechar e reabrir a janela.
    const aberto = aItens ? lista.data.data.find((c) => c.id === aItens.id) ?? aItens : null;

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={procura} onChange={(e) => porProcura(e.target.value)}
                            placeholder={t('Nome do conjunto…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                {pode && (
                    <Botao cor="primaria" tom="solida" icone="fa-plus"
                        onClick={() => { porAEditar(null); porFormulario({ name: '', description: '', category_id: '' }); }}>
                        {t('Novo conjunto')}
                    </Botao>
                )}
            </div>

            {lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-layer-group"
                    titulo={t('Ainda não há conjuntos')}
                    frase={t('Um conjunto é o material que sai sempre junto — e é o que evita esquecer o cabo pequeno no armazém.')}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {lista.data.data.map((c, i) => (
                        <article key={c.id} style={cascata(i)}
                            className={cls('entra flex flex-col gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md', CARTAO)}>
                            <div className="flex items-start gap-3">
                                <span className="grid h-11 w-11 flex-none place-items-center rounded-xl text-xl"
                                    style={{ backgroundColor: `${c.categoria_cor ?? '#6366f1'}22` }}>
                                    {c.categoria_icone ?? '📦'}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold text-slate-800">{c.nome}</p>
                                    <p className="truncate text-xs text-slate-500">{c.categoria ?? t('Sem categoria')}</p>
                                </div>
                            </div>

                            {c.descricao && <p className="text-xs text-slate-600">{c.descricao}</p>}

                            <ul className="space-y-1">
                                {c.itens.slice(0, 4).map((x) => (
                                    <li key={x.id} className="flex items-center justify-between gap-2 text-xs">
                                        <span className="truncate text-slate-600">{x.nome}</span>
                                        <span className="flex-none font-semibold tabular-nums text-slate-500">×{x.quantidade}</span>
                                    </li>
                                ))}
                                {c.itens.length === 0 && (
                                    <li className="text-xs italic text-slate-400">{t('Sem material atribuído.')}</li>
                                )}
                                {c.itens.length > 4 && (
                                    <li className="text-xs font-semibold text-slate-400">
                                        {t('+:n', { n: String(c.itens.length - 4) })}
                                    </li>
                                )}
                            </ul>

                            {pode && (
                                <div className="mt-auto flex items-center gap-1.5 border-t border-slate-100 pt-3">
                                    <Botao altura="pequeno" icone="fa-boxes-packing" onClick={() => porAItens(c)}>
                                        {t('Material')}
                                    </Botao>
                                    <Botao altura="pequeno" icone="fa-pen"
                                        onClick={() => {
                                            porAEditar(c.id);
                                            porFormulario({
                                                name: c.nome, description: c.descricao ?? '',
                                                category_id: c.category_id ? String(c.category_id) : '',
                                            });
                                        }}
                                        aria-label={t('Editar conjunto')} />
                                    <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                        onClick={() => apagar.mutate(c.id)} aria-label={t('Eliminar conjunto')} />
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            )}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar conjunto') : t('Novo conjunto')}
                icone="fa-layer-group"
                cor="roxo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                category_id: formulario.category_id ? Number(formulario.category_id) : null,
                            })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Nome')} obrigatorio>
                            <input type="text" value={formulario.name}
                                onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                placeholder={t('Ex.: Kit de som pequeno')} className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Categoria')} obrigatorio>
                            <select value={formulario.category_id}
                                onChange={(e) => porFormulario({ ...formulario, category_id: e.target.value })}
                                className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {categorias.map((c) => <option key={c.id} value={c.id}>{c.icone} {c.nome}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Descrição')}>
                            <textarea rows={3} value={formulario.description}
                                onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aberto !== null}
                aoFechar={() => porAItens(null)}
                titulo={t('Material do conjunto')}
                subtitulo={aberto?.nome}
                icone="fa-boxes-packing"
                cor="roxo"
                largura="md"
                rodape={<Botao onClick={() => porAItens(null)}>{t('Fechar')}</Botao>}
            >
                {aberto && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={juntar.error ?? tirar.error} />

                        <div className="flex flex-wrap items-end gap-3">
                            <Campo etiqueta={t('Equipamento')} className="min-w-[12rem] flex-1">
                                <select value={novoItem.equipment_id}
                                    onChange={(e) => porNovoItem({ ...novoItem, equipment_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Escolher…')}</option>
                                    {lista.data.equipamentos.map((x) => (
                                        <option key={x.valor} value={x.valor}>{x.rotulo}</option>
                                    ))}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Quantidade')} className="w-28">
                                <input type="number" min="1" value={novoItem.quantity}
                                    onChange={(e) => porNovoItem({ ...novoItem, quantity: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>

                            <Botao cor="primaria" tom="solida" icone="fa-plus" aTrabalhar={juntar.isPending}
                                disabled={!novoItem.equipment_id} onClick={() => juntar.mutate()}>
                                {t('Juntar')}
                            </Botao>
                        </div>

                        {aberto.itens.length === 0 ? (
                            <p className="text-sm text-slate-500">{t('Sem material atribuído.')}</p>
                        ) : (
                            <ul className="space-y-1.5">
                                {aberto.itens.map((x) => (
                                    <li key={x.id} className={cls('flex items-center gap-3 border border-slate-200 bg-white px-3 py-2', RAIO)}>
                                        <span className="min-w-0 flex-1 truncate text-sm text-slate-700">{x.nome}</span>
                                        <Etiqueta cor={cor(x.estado)}>{x.estado_rotulo}</Etiqueta>
                                        <span className="flex-none text-sm font-semibold tabular-nums text-slate-600">×{x.quantidade}</span>
                                        <Botao altura="pequeno" cor="perigo" icone="fa-xmark"
                                            onClick={() => tirar.mutate({ conjunto: aberto.id, item: x.id })}
                                            aria-label={t('Tirar do conjunto')} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── As categorias ───────────────────────────────────────────────────── */

function Categorias({
    pode, categorias, emojis, aoFeito, aoErro,
}: {
    pode: boolean;
    categorias: CategoriaDeEquipamentos[];
    emojis: Array<{ valor: string; rotulo: string }>;
    aoFeito: (m: string) => void;
    aoErro: (e: unknown) => void;
}) {
    const [formulario, porFormulario] = useState<{ name: string; icon: string; color: string } | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => eventos.equipamentos.categorias.guardar(aEditar, d),
        onSuccess: (r) => { aoFeito(r.message); porFormulario(null); porAEditar(null); },
        onError: aoErro,
    });

    const alternar = useMutation({
        mutationFn: (id: number) => eventos.equipamentos.categorias.alternar(id),
        onSuccess: (r) => aoFeito(r.message),
        onError: aoErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => eventos.equipamentos.categorias.apagar(id),
        onSuccess: (r) => aoFeito(r.message),
        onError: aoErro,
    });

    return (
        <div className="space-y-3">
            {pode && (
                <Botao cor="primaria" tom="solida" icone="fa-plus"
                    onClick={() => { porAEditar(null); porFormulario({ name: '', icon: '📦', color: '#6366f1' }); }}>
                    {t('Nova categoria')}
                </Botao>
            )}

            {categorias.length === 0 ? (
                <SemNada
                    icone="fa-tags"
                    titulo={t('Ainda não há categorias')}
                    frase={t('Um equipamento precisa de uma categoria — é por ela que se acha o material no armazém.')}
                />
            ) : (
                <Cartao semPadding>
                    <ul className="divide-y divide-slate-100">
                        {categorias.map((c, i) => (
                            <li key={c.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                <span className="grid h-10 w-10 flex-none place-items-center rounded-xl text-xl"
                                    style={{ backgroundColor: `${c.cor}22` }}>
                                    {c.icone}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{c.nome}</p>
                                    <p className="text-xs text-slate-500">
                                        {t(':n equipamentos', { n: String(c.equipamentos) })}
                                    </p>
                                </div>

                                <Etiqueta cor={c.activa ? 'bom' : 'neutra'} ponto>
                                    {c.activa ? t('Activa') : t('Desligada')}
                                </Etiqueta>

                                {pode && (
                                    <div className="flex items-center gap-1.5">
                                        <Botao altura="pequeno" icone={c.activa ? 'fa-toggle-on' : 'fa-toggle-off'}
                                            onClick={() => alternar.mutate(c.id)}
                                            aria-label={c.activa ? t('Desligar categoria') : t('Ligar categoria')} />
                                        <Botao altura="pequeno" icone="fa-pen"
                                            onClick={() => {
                                                porAEditar(c.id);
                                                porFormulario({ name: c.nome, icon: c.icone, color: c.cor });
                                            }}
                                            aria-label={t('Editar categoria')} />
                                        <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                            onClick={() => apagar.mutate(c.id)} aria-label={t('Eliminar categoria')} />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </Cartao>
            )}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar categoria') : t('Nova categoria')}
                icone="fa-tags"
                cor="roxo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate(formulario)}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Nome')} obrigatorio>
                            <input type="text" value={formulario.name}
                                onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Ícone')}>
                            <div className="flex flex-wrap gap-2">
                                {emojis.map((e) => (
                                    <button key={e.valor} type="button" title={e.rotulo}
                                        onClick={() => porFormulario({ ...formulario, icon: e.valor })}
                                        className={cls(
                                            'grid h-10 w-10 place-items-center rounded-xl border-2 text-lg transition hover:-translate-y-0.5', FOCO,
                                            formulario.icon === e.valor ? 'border-purple-500 bg-purple-50' : 'border-slate-200 bg-white',
                                        )}>
                                        {e.valor}
                                    </button>
                                ))}
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Cor')} obrigatorio>
                            <input type="color" value={formulario.color}
                                onChange={(e) => porFormulario({ ...formulario, color: e.target.value })}
                                className="h-11 w-full cursor-pointer rounded-xl border border-slate-200" />
                        </Campo>
                    </div>
                )}
            </Modal>
        </div>
    );
}
