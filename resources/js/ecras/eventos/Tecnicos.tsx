import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { eventos, type Tecnico } from '@/api/eventos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS TÉCNICOS — quem monta, quem opera, e em quê.
 *
 * AS ESPECIALIDADES SÃO O QUE INTERESSA: um evento com transmissão precisa de
 * alguém que faça streaming, e escalar um técnico de áudio para isso é dar por
 * ela no dia. Pelo menos uma é obrigatória, e o ecrã filtra por elas — que era
 * o que faltava para a lista servir para alguma coisa com trinta pessoas.
 *
 * A IMPORTAÇÃO DO RH tinha um furo silencioso: a consulta que procurava quem
 * ainda não era técnico usava `whereNotIn` sobre colunas que podem ser nulas, e
 * em SQL `NULL NOT IN (…)` não é verdadeiro — TODO o funcionário sem e-mail
 * desaparecia da lista. Eram justamente os que faltava importar.
 */

const VAZIO = {
    name: '', email: '', phone: '', document: '', address: '',
    specialties: [] as string[], level: 'pleno',
    hourly_rate: '0', daily_rate: '0', birth_date: '', hire_date: '',
    notes: '', is_active: true, is_available: true,
};

const ICONE_DA_ESPECIALIDADE: Record<string, string> = {
    audio: 'fa-microphone',
    video: 'fa-video',
    iluminacao: 'fa-lightbulb',
    streaming: 'fa-satellite-dish',
};

export default function Tecnicos() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{
        procura?: string; especialidade?: string; nivel?: string;
        por_pagina?: number; page?: number;
    }>({ por_pagina: 15, page: 1 });

    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<Tecnico | null>(null);
    const [aImportar, porAImportar] = useState(false);
    const [escolhidos, porEscolhidos] = useState<number[]>([]);

    const opcoes = useQuery({ queryKey: ['eventos', 'tecnicos', 'opcoes'], queryFn: () => eventos.tecnicos.opcoes() });

    const lista = useQuery({
        queryKey: ['eventos', 'tecnicos', filtros],
        queryFn: () => eventos.tecnicos.lista(filtros),
    });

    const doRh = useQuery({
        queryKey: ['eventos', 'tecnicos', 'do-rh'],
        queryFn: () => eventos.tecnicos.doRh(),
        enabled: aImportar,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['eventos'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => eventos.tecnicos.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const alternar = useMutation({
        mutationFn: (id: number) => eventos.tecnicos.alternar(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => eventos.tecnicos.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: porErro,
    });

    const importar = useMutation({
        mutationFn: () => eventos.tecnicos.importarDoRh(escolhidos),
        onSuccess: (r) => { feito(r.message); porAImportar(false); porEscolhidos([]); },
        onError: porErro,
    });

    if (opcoes.isPending || lista.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;
    if (lista.isError) return <AvisoDeErro erro={lista.error} />;

    const o = opcoes.data;
    const { data, meta, resumo, permissoes } = lista.data;
    const pode = permissoes.pode_gerir;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const alternarEspecialidade = (f: typeof VAZIO, chave: string) => ({
        ...f,
        specialties: f.specialties.includes(chave)
            ? f.specialties.filter((x) => x !== chave)
            : [...f.specialties, chave],
    });

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Técnicos')}
                subtitulo={t('Quem monta, quem opera, e em quê')}
                icone="fa-user-tie"
                cor="ciano"
                accoes={
                    pode && (
                        <>
                            <button type="button" className={ACCAO_DA_FAIXA}
                                onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo técnico')}
                            </button>
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porAImportar(true)}>
                                <i className="fas fa-file-import" aria-hidden="true" />
                                {t('Importar do RH')}
                            </button>
                        </>
                    )
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-user-check">
                        {t(':n activos', { n: numero(resumo.activos) })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-id-badge">
                        {t(':n vindos do RH', { n: numero(resumo.do_rh) })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="grid gap-4 sm:grid-cols-3">
                <CartaoNumero aspecto="claro" rotulo={t('Técnicos')} valor={numero(resumo.total)} icone="fa-user-tie" tom="teal" />
                <CartaoNumero aspecto="claro" rotulo={t('Activos')} valor={numero(resumo.activos)} icone="fa-user-check" tom="verde" />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Vindos do RH')} valor={numero(resumo.do_rh)}
                    icone="fa-id-badge" tom="indigo"
                    nota={t('Não se escreve a mesma pessoa duas vezes')}
                />
            </div>

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Nome, telefone ou e-mail…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Especialidade')} className="w-48">
                    <select value={filtros.especialidade ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, especialidade: e.target.value || undefined, page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todas')}</option>
                        {o.especialidades.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Nível')} className="w-40">
                    <select value={filtros.nivel ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, nivel: e.target.value || undefined, page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {o.niveis.map((n) => <option key={n.valor} value={n.valor}>{n.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {data.length === 0 ? (
                <SemNada
                    icone="fa-user-tie"
                    titulo={t('Ainda não há técnicos')}
                    frase={t('Sem técnicos não há escala — e um evento com transmissão precisa de quem a faça.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-file-import" onClick={() => porAImportar(true)}>
                            {t('Importar do RH')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {data.map((x, i) => (
                        <article key={x.id} style={cascata(i)}
                            className={cls('entra flex flex-col gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md', CARTAO)}>
                            <div className="flex items-start gap-3">
                                <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-cyan-50 text-cyan-600">
                                    <i className="fas fa-user-tie" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold text-slate-800">{x.nome}</p>
                                    <p className="truncate text-xs text-slate-500">{x.nivel_rotulo}</p>
                                </div>
                                <Etiqueta cor={x.activo ? 'bom' : 'neutra'} ponto>
                                    {x.activo ? t('Activo') : t('Desligado')}
                                </Etiqueta>
                            </div>

                            {x.especialidades.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {x.especialidades.map((e, j) => (
                                        <Etiqueta key={e} cor="primaria" icone={ICONE_DA_ESPECIALIDADE[e]}>
                                            {x.especialidades_rotulos[j] ?? e}
                                        </Etiqueta>
                                    ))}
                                </div>
                            )}

                            <dl className="space-y-1 text-xs text-slate-600">
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-phone w-4 text-cyan-600" aria-hidden="true" />
                                    <span className="truncate">{x.telefone ?? t('Sem telefone')}</span>
                                </div>
                                {x.email && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-envelope w-4 text-slate-400" aria-hidden="true" />
                                        <span className="truncate">{x.email}</span>
                                    </div>
                                )}
                                {x.documento && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-id-card w-4 text-slate-400" aria-hidden="true" />
                                        <span className="truncate">{x.documento}</span>
                                    </div>
                                )}
                                {(x.preco_hora > 0 || x.preco_dia > 0) && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-money-bill w-4 text-slate-400" aria-hidden="true" />
                                        <span className="tabular-nums">
                                            {x.preco_hora > 0 && t(':v Kz/hora', { v: kz(x.preco_hora) })}
                                            {x.preco_hora > 0 && x.preco_dia > 0 && ' · '}
                                            {x.preco_dia > 0 && t(':v Kz/dia', { v: kz(x.preco_dia) })}
                                        </span>
                                    </div>
                                )}
                            </dl>

                            {pode && (
                                <div className="mt-auto flex items-center gap-1.5 border-t border-slate-100 pt-3">
                                    <Botao altura="pequeno" icone={x.activo ? 'fa-toggle-on' : 'fa-toggle-off'}
                                        onClick={() => alternar.mutate(x.id)}
                                        aria-label={x.activo ? t('Desligar técnico') : t('Ligar técnico')} />
                                    <Botao altura="pequeno" icone="fa-pen"
                                        onClick={() => {
                                            porAEditar(x.id);
                                            porFormulario({
                                                name: x.nome, email: x.email ?? '', phone: x.telefone ?? '',
                                                document: x.documento ?? '', address: x.morada ?? '',
                                                specialties: x.especialidades, level: x.nivel,
                                                hourly_rate: String(x.preco_hora), daily_rate: String(x.preco_dia),
                                                birth_date: x.nascimento ?? '', hire_date: x.admissao ?? '',
                                                notes: x.notas ?? '', is_active: x.activo, is_available: x.disponivel,
                                            });
                                        }}
                                        aria-label={t('Editar técnico')} />
                                    <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                        onClick={() => porAApagar(x)} aria-label={t('Eliminar técnico')} />
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            )}

            {meta.last_page > 1 && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-slate-500">
                        {t('A mostrar :de a :ate de :total', {
                            de: String(meta.from ?? 0), ate: String(meta.to ?? 0), total: String(meta.total),
                        })}
                    </p>
                    <div className="flex items-center gap-2">
                        <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
                        <Botao altura="pequeno" icone="fa-chevron-left" disabled={meta.current_page <= 1}
                            onClick={() => porFiltros({ ...filtros, page: meta.current_page - 1 })}
                            aria-label={t('Página anterior')} />
                        <span className="text-xs font-semibold tabular-nums text-slate-600">
                            {meta.current_page}/{meta.last_page}
                        </span>
                        <Botao altura="pequeno" icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page}
                            onClick={() => porFiltros({ ...filtros, page: meta.current_page + 1 })}
                            aria-label={t('Página seguinte')} />
                    </div>
                </div>
            )}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar técnico') : t('Novo técnico')}
                subtitulo={t('Pelo menos uma especialidade — é por ela que se escala')}
                icone="fa-user-tie"
                cor="ciano"
                largura="lg"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                hourly_rate: Number(formulario.hourly_rate || 0),
                                daily_rate: Number(formulario.daily_rate || 0),
                                birth_date: formulario.birth_date || null,
                                hire_date: formulario.hire_date || null,
                            })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                                <input type="text" value={formulario.name}
                                    onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Telefone')} obrigatorio erro={erros.phone}
                                ajuda={t('É por aqui que se chama alguém à pressa.')}>
                                <input type="text" value={formulario.phone}
                                    onChange={(e) => porFormulario({ ...formulario, phone: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('E-mail')} erro={erros.email}>
                                <input type="email" value={formulario.email}
                                    onChange={(e) => porFormulario({ ...formulario, email: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Documento')} erro={erros.document}>
                                <input type="text" value={formulario.document}
                                    onChange={(e) => porFormulario({ ...formulario, document: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <Campo
                            etiqueta={t('Especialidades')} obrigatorio erro={erros.specialties}
                            ajuda={t('Um evento com transmissão precisa de quem faça transmissão.')}
                        >
                            <div className="grid gap-2 sm:grid-cols-2">
                                {o.especialidades.map((e) => {
                                    const ligada = formulario.specialties.includes(e.valor);

                                    return (
                                        <button
                                            key={e.valor}
                                            type="button"
                                            onClick={() => porFormulario(alternarEspecialidade(formulario, e.valor))}
                                            className={cls(
                                                'flex items-center gap-3 border-2 px-3 py-2.5 text-sm font-semibold transition', RAIO, FOCO,
                                                ligada ? 'border-cyan-500 bg-cyan-50 text-cyan-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                                            )}
                                        >
                                            <i className={`fas ${e.icone}`} aria-hidden="true" />
                                            {e.rotulo}
                                            {ligada && <i className="fas fa-check ml-auto text-cyan-600" aria-hidden="true" />}
                                        </button>
                                    );
                                })}
                            </div>
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Nível')} obrigatorio erro={erros.level}>
                                <select value={formulario.level}
                                    onChange={(e) => porFormulario({ ...formulario, level: e.target.value })}
                                    className={entrada}>
                                    {o.niveis.map((n) => <option key={n.valor} value={n.valor}>{n.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('Preço/hora')} erro={erros.hourly_rate}>
                                <input type="number" step="0.01" min="0" value={formulario.hourly_rate}
                                    onChange={(e) => porFormulario({ ...formulario, hourly_rate: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta={t('Preço/dia')} erro={erros.daily_rate}>
                                <input type="number" step="0.01" min="0" value={formulario.daily_rate}
                                    onChange={(e) => porFormulario({ ...formulario, daily_rate: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nascimento')} erro={erros.birth_date}>
                                <input type="date" value={formulario.birth_date}
                                    onChange={(e) => porFormulario({ ...formulario, birth_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Admissão')} erro={erros.hire_date}>
                                <input type="date" value={formulario.hire_date}
                                    onChange={(e) => porFormulario({ ...formulario, hire_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Morada')} erro={erros.address}>
                            <input type="text" value={formulario.address}
                                onChange={(e) => porFormulario({ ...formulario, address: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Notas')} erro={erros.notes}>
                            <textarea rows={2} value={formulario.notes}
                                onChange={(e) => porFormulario({ ...formulario, notes: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <div className="flex flex-wrap gap-5">
                            <label className="flex items-center gap-3">
                                <input type="checkbox" checked={formulario.is_active}
                                    onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                    className="h-5 w-5 rounded text-cyan-600" />
                                <span className="text-sm font-medium text-slate-700">{t('Activo')}</span>
                            </label>
                            <label className="flex items-center gap-3">
                                <input type="checkbox" checked={formulario.is_available}
                                    onChange={(e) => porFormulario({ ...formulario, is_available: e.target.checked })}
                                    className="h-5 w-5 rounded text-cyan-600" />
                                <span className="text-sm font-medium text-slate-700">{t('Disponível para escalas')}</span>
                            </label>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── A importação do RH ─────────────────────────────────── */}

            <Modal
                aberto={aImportar}
                aoFechar={() => { porAImportar(false); porEscolhidos([]); }}
                titulo={t('Importar do RH')}
                subtitulo={t('Quem já está no pessoal e ainda não é técnico')}
                icone="fa-file-import"
                cor="ciano"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porAImportar(false); porEscolhidos([]); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-file-import" aTrabalhar={importar.isPending}
                            disabled={escolhidos.length === 0} onClick={() => importar.mutate()}>
                            {t('Importar :n', { n: String(escolhidos.length) })}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-3">
                    <AvisoDeErro erro={importar.error} />

                    {doRh.isPending ? (
                        <Carregando linhas={4} />
                    ) : doRh.isError ? (
                        <AvisoDeErro erro={doRh.error} />
                    ) : doRh.data.data.length === 0 ? (
                        <SemNada
                            icone="fa-user-check"
                            titulo={t('Não há ninguém por importar')}
                            frase={t('A importação recusa quem já cá está, por e-mail ou por telefone.')}
                        />
                    ) : (
                        <>
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-xs text-slate-500">
                                    {t('Entram com nível «Pleno» e a especialidade de áudio — muda-se depois na ficha.')}
                                </p>
                                <Botao
                                    altura="pequeno"
                                    onClick={() => porEscolhidos(
                                        escolhidos.length === doRh.data.data.length ? [] : doRh.data.data.map((f) => f.id),
                                    )}
                                >
                                    {escolhidos.length === doRh.data.data.length ? t('Nenhum') : t('Escolher todos')}
                                </Botao>
                            </div>

                            <ul className="max-h-80 space-y-1.5 overflow-y-auto">
                                {doRh.data.data.map((f) => (
                                    <li key={f.id}>
                                        <label className={cls(
                                            'flex cursor-pointer items-center gap-3 border px-3 py-2 transition hover:bg-slate-50', RAIO,
                                            escolhidos.includes(f.id) ? 'border-cyan-400 bg-cyan-50/50' : 'border-slate-200 bg-white',
                                        )}>
                                            <input
                                                type="checkbox"
                                                checked={escolhidos.includes(f.id)}
                                                onChange={() => porEscolhidos(
                                                    escolhidos.includes(f.id)
                                                        ? escolhidos.filter((x) => x !== f.id)
                                                        : [...escolhidos, f.id],
                                                )}
                                                className="h-4 w-4 rounded text-cyan-600"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-slate-800">{f.nome}</span>
                                                <span className="block truncate text-xs text-slate-500">
                                                    {[f.cargo, f.email, f.telefone].filter(Boolean).join(' · ')}
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar técnico')}
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
                    {t('Eliminar «:nome»? Quem tem equipamento por devolver não se apaga — o material ficava perdido.', {
                        nome: aApagar?.nome ?? '',
                    })}
                </p>
            </Modal>
        </div>
    );
}
