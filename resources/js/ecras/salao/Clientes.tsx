import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { salao, type ClienteDoSalao } from '@/api/salao';
import { ErroDaApi } from '@/api/cliente';
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
import { CARTAO, RAIO, cls, data, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { COR_DO_ESTADO, ICONE_DO_ESTADO } from './Painel';

/**
 * AS CLIENTES DO SALÃO.
 *
 * SÃO AS CLIENTES DA FACTURAÇÃO — a mesma ficha que recebe a factura. O que é
 * do salão (visitas, gasto, pontos, alergias, VIP) viaja no mesmo registo.
 *
 * AS ALERGIAS NÃO SÃO UM CAMPO QUALQUER. Num salão, uma tinta no couro
 * cabeludo de quem é alérgica é uma ida ao hospital: aparecem na LISTA, a
 * vermelho, e não escondidas atrás de um separador da ficha.
 */

const vazia = () => ({
    name: '', email: '', phone: '', whatsapp: '', birth_date: '', gender: '',
    address: '', country: 'AO', province: '', city: '', postal_code: '',
    is_vip: false, allergies: [] as string[],
});

export default function Clientes() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState({ procura: '', vip: '', por_pagina: 15, page: 1 });
    const [procura, porProcura] = useState('');

    const [formulario, porFormulario] = useState<ReturnType<typeof vazia> | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [alergia, porAlergia] = useState('');
    const [aVer, porAVer] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<ClienteDoSalao | null>(null);

    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useState('');

    useEffect(() => {
        const id = setTimeout(() => porFiltros((f) => ({ ...f, procura, page: 1 })), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const opcoes = useQuery({
        queryKey: ['salao', 'clientes', 'opcoes'],
        queryFn: salao.clientes.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['salao', 'clientes', 'lista', filtros],
        queryFn: () => salao.clientes.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const ficha = useQuery({
        queryKey: ['salao', 'clientes', 'ficha', aVer],
        queryFn: () => salao.clientes.ficha(aVer!),
        enabled: aVer !== null,
    });

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['salao', 'clientes'] });
    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };
    const feito = (r: { message: string }) => { porErros({}); porErro(null); porRecado(r.message); refrescar(); };

    const guardar = useMutation({
        mutationFn: () => salao.clientes.guardar(aEditar, {
            ...formulario, birth_date: formulario!.birth_date || null, gender: formulario!.gender || null,
        }),
        onSuccess: (r) => { porFormulario(null); porAEditar(null); feito(r); },
        onError: falhou,
    });

    const vip = useMutation({
        mutationFn: (id: number) => salao.clientes.vip(id),
        onSuccess: feito, onError: falhou,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => salao.clientes.apagar(id),
        onSuccess: (r) => { porAApagar(null); feito(r); },
        onError: (e) => { porAApagar(null); falhou(e); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;

    const abrirNova = () => { porAEditar(null); porErros({}); porAlergia(''); porFormulario(vazia()); };

    const abrirEdicao = (c: ClienteDoSalao) => {
        porAEditar(c.id);
        porErros({});
        porAlergia('');
        porFormulario({
            name: c.nome, email: c.email ?? '', phone: c.telefone ?? '', whatsapp: c.whatsapp ?? '',
            birth_date: c.nascimento ?? '', gender: c.genero ?? '',
            address: c.morada ?? '', country: c.pais ?? 'AO', province: c.provincia ?? '',
            city: c.cidade ?? '', postal_code: c.codigo_postal ?? '',
            is_vip: c.vip, allergies: c.alergias ?? [],
        });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Clientes do Salão')}
                subtitulo={t('A mesma ficha que recebe a factura')}
                icone="fa-users"
                cor="rosa"
                accoes={
                    <>
                        {o.permissoes.pode_criar && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNova}>
                                <i className="fas fa-user-plus" aria-hidden="true" />
                                {t('Nova cliente')}
                            </button>
                        )}
                        <a href="/salon/appointments" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-calendar-check" aria-hidden="true" />
                            {t('Marcações')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <EstadoNaFaixa icone="fa-crown">
                        {t(':n VIP', { n: String(resumo.vip) })}
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
                    <CartaoNumero aspecto="claro" rotulo={t('Clientes')} valor={resumo.total} icone="fa-users" tom="roxo" />
                    <CartaoNumero
                        aspecto="claro" rotulo={t('VIP')} valor={resumo.vip} icone="fa-crown" tom="ambar"
                        nota={t('Dez visitas ou 100.000 Kz tornam VIP sozinhas')}
                    />
                    <CartaoNumero aspecto="claro" rotulo={t('Normais')} valor={resumo.normais} icone="fa-user" tom="teal" />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input
                            type="search"
                            value={procura}
                            onChange={(e) => porProcura(e.target.value)}
                            placeholder={t('Nome, telefone ou e-mail…')}
                            className={cls(entrada, 'pl-9')}
                        />
                    </div>
                </Campo>

                <Campo etiqueta={t('Tipo')} className="w-40">
                    <select
                        value={filtros.vip}
                        onChange={(e) => porFiltros({ ...filtros, vip: e.target.value, page: 1 })}
                        className={entrada}
                    >
                        <option value="">{t('Todas')}</option>
                        <option value="vip">{t('Só VIP')}</option>
                        <option value="normal">{t('Sem VIP')}</option>
                    </select>
                </Campo>
            </div>

            <div className={cls(CARTAO, 'overflow-hidden')}>
                {lista.isPending ? (
                    <div className="p-5"><Carregando linhas={6} /></div>
                ) : (lista.data?.data.length ?? 0) === 0 ? (
                    <SemNada
                        icone="fa-users"
                        titulo={t('Nenhuma cliente')}
                        frase={t('Crie a primeira, ou limpe os filtros.')}
                        accao={o.permissoes.pode_criar && (
                            <Botao cor="primaria" tom="solida" icone="fa-user-plus" onClick={abrirNova}>
                                {t('Nova cliente')}
                            </Botao>
                        )}
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {lista.data?.data.map((c, i) => (
                            <li key={c.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-4 py-3">
                                <span className={cls(
                                    'grid h-11 w-11 flex-none place-items-center rounded-xl',
                                    c.vip ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-500',
                                )}>
                                    <i className={`fas ${c.vip ? 'fa-crown' : 'fa-user'}`} aria-hidden="true" />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{c.nome}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {c.telefone ?? t('Sem telefone')}
                                        {c.email && ` · ${c.email}`}
                                    </p>

                                    {/* AS ALERGIAS À VISTA — não escondidas na ficha. */}
                                    {c.alergias.length > 0 && (
                                        <p className="mt-1 flex flex-wrap items-center gap-1">
                                            {c.alergias.map((a) => (
                                                <span key={a} className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-inset ring-red-200">
                                                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                                                    {a}
                                                </span>
                                            ))}
                                        </p>
                                    )}
                                </div>

                                <div className="flex-none text-right text-xs text-slate-500">
                                    <p>{t(':n visitas', { n: String(c.visitas) })}</p>
                                    <p className="font-semibold tabular-nums text-slate-700">{kz(c.gasto, 0)} Kz</p>
                                </div>

                                <div className="flex flex-none gap-1">
                                    <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(c.id)} aria-label={t('Ver')} />
                                    {o.permissoes.pode_editar && (
                                        <>
                                            <Botao altura="pequeno" icone="fa-pen" onClick={() => abrirEdicao(c)} aria-label={t('Editar')} />
                                            <Botao
                                                altura="pequeno"
                                                cor={c.vip ? 'aviso' : 'neutra'}
                                                icone="fa-crown"
                                                onClick={() => vip.mutate(c.id)}
                                                aria-label={c.vip ? t('Retirar VIP') : t('Tornar VIP')}
                                                title={c.vip ? t('Retirar VIP') : t('Tornar VIP')}
                                            />
                                        </>
                                    )}
                                    {o.permissoes.pode_apagar && (
                                        <Botao altura="pequeno" cor="perigo" icone="fa-trash" onClick={() => porAApagar(c)} aria-label={t('Eliminar')} />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
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

            {/* ─── A ficha ─── */}
            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={aEditar ? t('Editar cliente') : t('Nova cliente')}
                subtitulo={t('É a mesma ficha que recebe a factura')}
                icone={aEditar ? 'fa-pen' : 'fa-user-plus'}
                cor="rosa"
                largura="lg"
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
                                <input value={formulario.name} onChange={(e) => porFormulario({ ...formulario, name: e.target.value })} className={entrada} autoFocus />
                            </Campo>
                            <Campo etiqueta={t('E-mail')} erro={erros.email}>
                                <input type="email" value={formulario.email} onChange={(e) => porFormulario({ ...formulario, email: e.target.value })} className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-4">
                            <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                                <input value={formulario.phone} onChange={(e) => porFormulario({ ...formulario, phone: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('WhatsApp')} erro={erros.whatsapp}>
                                <input value={formulario.whatsapp} onChange={(e) => porFormulario({ ...formulario, whatsapp: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Nascimento')} erro={erros.birth_date} ajuda={t('Para os parabéns.')}>
                                <input type="date" value={formulario.birth_date} onChange={(e) => porFormulario({ ...formulario, birth_date: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Género')} erro={erros.gender}>
                                <select value={formulario.gender} onChange={(e) => porFormulario({ ...formulario, gender: e.target.value })} className={entrada}>
                                    <option value="">{t('Não dito')}</option>
                                    {o.generos.map((g) => <option key={g.valor} value={g.valor}>{g.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Morada')} erro={erros.address}>
                            <input value={formulario.address} onChange={(e) => porFormulario({ ...formulario, address: e.target.value })} className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-4">
                            <Campo etiqueta={t('País')} erro={erros.country}>
                                <select value={formulario.country} onChange={(e) => porFormulario({ ...formulario, country: e.target.value })} className={entrada}>
                                    {o.paises.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('Província')} erro={erros.province}>
                                <select value={formulario.province} onChange={(e) => porFormulario({ ...formulario, province: e.target.value })} className={entrada}>
                                    <option value="">{t('Escolher…')}</option>
                                    {o.provincias.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('Cidade')} erro={erros.city}>
                                <input value={formulario.city} onChange={(e) => porFormulario({ ...formulario, city: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Código postal')} erro={erros.postal_code}>
                                <input value={formulario.postal_code} onChange={(e) => porFormulario({ ...formulario, postal_code: e.target.value })} className={entrada} />
                            </Campo>
                        </div>

                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('Alergias')}
                            </legend>

                            <p className="mb-2 text-xs text-slate-500">
                                {t('Aparecem na lista, a vermelho. Uma tinta no couro cabeludo de quem é alérgica é uma ida ao hospital.')}
                            </p>

                            <div className="flex gap-2">
                                <input
                                    value={alergia}
                                    onChange={(e) => porAlergia(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key !== 'Enter' || alergia.trim() === '') return;

                                        e.preventDefault();
                                        porFormulario({ ...formulario, allergies: [...formulario.allergies, alergia.trim()] });
                                        porAlergia('');
                                    }}
                                    placeholder={t('Amoníaco, latex, perfume…')}
                                    aria-label={t('Alergia')}
                                    className={entrada}
                                />
                                <Botao
                                    icone="fa-plus"
                                    disabled={alergia.trim() === ''}
                                    onClick={() => {
                                        porFormulario({ ...formulario, allergies: [...formulario.allergies, alergia.trim()] });
                                        porAlergia('');
                                    }}
                                >
                                    {t('Juntar')}
                                </Botao>
                            </div>

                            {formulario.allergies.length > 0 && (
                                <ul className="mt-2 flex flex-wrap gap-1.5">
                                    {formulario.allergies.map((a, i) => (
                                        <li key={`${a}-${i}`} className="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-200">
                                            <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                                            {a}
                                            <button
                                                type="button"
                                                onClick={() => porFormulario({
                                                    ...formulario,
                                                    allergies: formulario.allergies.filter((_, j) => j !== i),
                                                })}
                                                aria-label={t('Retirar :nome', { nome: a })}
                                                className="rounded p-0.5 hover:bg-red-100"
                                            >
                                                <i className="fas fa-xmark" aria-hidden="true" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </fieldset>

                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={formulario.is_vip}
                                onChange={(e) => porFormulario({ ...formulario, is_vip: e.target.checked })}
                                className="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                            />
                            {t('VIP')}
                        </label>
                    </div>
                )}
            </Modal>

            {/* ─── Ver ─── */}
            <Modal
                aberto={aVer !== null}
                aoFechar={() => porAVer(null)}
                titulo={ficha.data?.data.nome ?? t('Cliente')}
                icone="fa-user"
                cor="rosa"
                rodape={<Botao onClick={() => porAVer(null)}>{t('Fechar')}</Botao>}
            >
                {ficha.isPending ? (
                    <Carregando linhas={4} />
                ) : ficha.data ? (
                    <div className="space-y-4">
                        {ficha.data.data.alergias.length > 0 && (
                            <div className={cls('border border-red-200 bg-red-50 px-3 py-2', RAIO)}>
                                <p className="text-xs font-bold uppercase tracking-wider text-red-700">
                                    <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                                    {t('Alergias')}
                                </p>
                                <p className="text-sm text-red-900">{ficha.data.data.alergias.join(', ')}</p>
                            </div>
                        )}

                        <div className="grid grid-cols-3 gap-2 text-center">
                            <div className="rounded-xl bg-slate-50 px-2 py-2">
                                <p className="text-xs text-slate-500">{t('Visitas')}</p>
                                <p className="text-lg font-bold tabular-nums text-slate-900">{ficha.data.data.visitas}</p>
                            </div>
                            <div className="rounded-xl bg-slate-50 px-2 py-2">
                                <p className="text-xs text-slate-500">{t('Gasto')}</p>
                                <p className="text-lg font-bold tabular-nums text-slate-900">{kz(ficha.data.data.gasto, 0)}</p>
                            </div>
                            <div className="rounded-xl bg-slate-50 px-2 py-2">
                                <p className="text-xs text-slate-500">{t('Pontos')}</p>
                                <p className="text-lg font-bold tabular-nums text-slate-900">{ficha.data.data.pontos}</p>
                            </div>
                        </div>

                        <div>
                            <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('Últimas marcações')}
                            </p>

                            {ficha.data.marcacoes.length === 0 ? (
                                <p className="text-sm text-slate-500">{t('Ainda não veio ao salão.')}</p>
                            ) : (
                                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                                    {ficha.data.marcacoes.map((m) => (
                                        <li key={m.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                                            <span className="flex-none tabular-nums text-slate-500">
                                                {m.dia ? data(m.dia) : '—'} {m.inicio}
                                            </span>
                                            <span className="min-w-0 flex-1 truncate text-slate-800">
                                                {m.servicos.join(', ') || t('Sem serviços')}
                                            </span>
                                            <Etiqueta cor={COR_DO_ESTADO[m.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[m.estado]}>
                                                {m.estado_rotulo}
                                            </Etiqueta>
                                            <span className="w-20 flex-none text-right font-semibold tabular-nums text-slate-900">
                                                {kz(m.total)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                ) : null}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar cliente')}
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
                    {t('Uma cliente com marcações por atender não se apaga — as marcações ficariam sem dono.')}
                </p>
            </Modal>
        </div>
    );
}
