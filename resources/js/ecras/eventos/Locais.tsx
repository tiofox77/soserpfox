import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { eventos, type Local } from '@/api/eventos';
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
import { CARTAO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS LOCAIS — onde os eventos acontecem.
 *
 * A CAPACIDADE PASSOU A VALER ALGUMA COISA: marcar um evento de 500 pessoas
 * numa sala de 80 é agora recusado à cara, com o número dos dois lados. Antes
 * entrava, e descobria-se no dia com as pessoas à porta.
 *
 * E A PROCURA DEIXOU DE ATRAVESSAR EMPRESAS. Era `where(nome)->orWhere(cidade)`
 * encostado ao filtro da empresa — um `or` solto rompe o `and` que está antes,
 * e procurar «Luanda» trazia os locais de toda a gente.
 */

const VAZIO = {
    name: '', address: '', city: '', phone: '', contact_person: '',
    capacity: '', notes: '', is_active: true,
};

export default function Locais() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ procura?: string; por_pagina?: number; page?: number }>({
        por_pagina: 15, page: 1,
    });
    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<Local | null>(null);

    const lista = useQuery({
        queryKey: ['eventos', 'locais', filtros],
        queryFn: () => eventos.locais.lista(filtros),
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['eventos'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => eventos.locais.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const alternar = useMutation({
        mutationFn: (id: number) => eventos.locais.alternar(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => eventos.locais.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: porErro,
    });

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <AvisoDeErro erro={lista.error} />;

    const { data, meta, resumo, permissoes } = lista.data;
    const pode = permissoes.pode_gerir;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Locais')}
                subtitulo={t('Onde os eventos acontecem — e quantos cabem')}
                icone="fa-map-marker-alt"
                cor="perigo"
                accoes={
                    pode && (
                        <button
                            type="button"
                            className={ACCAO_DA_FAIXA}
                            onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}
                        >
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Novo local')}
                        </button>
                    )
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-users">
                        {t(':n lugares no total', { n: numero(resumo.lugares) })}
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
                <CartaoNumero aspecto="claro" rotulo={t('Locais')} valor={numero(resumo.total)} icone="fa-map-marker-alt" tom="vermelho" />
                <CartaoNumero aspecto="claro" rotulo={t('Activos')} valor={numero(resumo.activos)} icone="fa-circle-check" tom="verde" />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Lugares')} valor={numero(resumo.lugares)}
                    icone="fa-users" tom="indigo" nota={t('Só os locais activos contam')}
                />
            </div>

            <Campo etiqueta={t('Procurar')} className="max-w-md">
                <div className="relative">
                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        type="search"
                        value={filtros.procura ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                        placeholder={t('Nome, cidade ou morada…')}
                        className={cls(entrada, 'pl-9')}
                    />
                </div>
            </Campo>

            {data.length === 0 ? (
                <SemNada
                    icone="fa-map-marker-alt"
                    titulo={t('Ainda não há locais')}
                    frase={t('Um local guarda a morada, o contacto e quantas pessoas leva — e é a capacidade que impede marcar um evento que não cabe.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}>
                            {t('Novo local')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {data.map((l, i) => (
                        <article key={l.id} style={cascata(i)}
                            className={cls('entra flex flex-col gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md', CARTAO)}>
                            <div className="flex items-start gap-3">
                                <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-red-50 text-red-600">
                                    <i className="fas fa-location-dot" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold text-slate-800">{l.nome}</p>
                                    <p className="truncate text-xs text-slate-500">{l.cidade ?? t('Sem cidade')}</p>
                                </div>
                                <Etiqueta cor={l.activo ? 'bom' : 'neutra'} ponto>
                                    {l.activo ? t('Activo') : t('Desligado')}
                                </Etiqueta>
                            </div>

                            <dl className="space-y-1 text-xs text-slate-600">
                                {l.morada && (
                                    <div className="flex items-start gap-2">
                                        <i className="fas fa-map w-4 flex-none pt-0.5 text-slate-400" aria-hidden="true" />
                                        <span className="min-w-0">{l.morada}</span>
                                    </div>
                                )}
                                {l.telefone && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-phone w-4 text-slate-400" aria-hidden="true" />
                                        <span className="truncate">{l.telefone}</span>
                                    </div>
                                )}
                                {l.contacto && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-user w-4 text-slate-400" aria-hidden="true" />
                                        <span className="truncate">{l.contacto}</span>
                                    </div>
                                )}
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-users w-4 text-slate-400" aria-hidden="true" />
                                    <span className="tabular-nums">
                                        {l.capacidade > 0
                                            ? t(':n lugares', { n: numero(l.capacidade) })
                                            : t('Capacidade não dita')}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-calendar w-4 text-slate-400" aria-hidden="true" />
                                    <span className="tabular-nums">{t(':n eventos', { n: numero(l.eventos) })}</span>
                                </div>
                            </dl>

                            {l.notas && <p className="text-xs italic text-slate-500">{l.notas}</p>}

                            {pode && (
                                <div className="mt-auto flex items-center gap-1.5 border-t border-slate-100 pt-3">
                                    <Botao altura="pequeno" icone={l.activo ? 'fa-toggle-on' : 'fa-toggle-off'}
                                        onClick={() => alternar.mutate(l.id)}
                                        aria-label={l.activo ? t('Desligar local') : t('Ligar local')} />
                                    <Botao altura="pequeno" icone="fa-pen"
                                        onClick={() => {
                                            porAEditar(l.id);
                                            porFormulario({
                                                name: l.nome, address: l.morada ?? '', city: l.cidade ?? '',
                                                phone: l.telefone ?? '', contact_person: l.contacto ?? '',
                                                capacity: l.capacidade ? String(l.capacidade) : '',
                                                notes: l.notas ?? '', is_active: l.activo,
                                            });
                                        }}
                                        aria-label={t('Editar local')} />
                                    <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                        onClick={() => porAApagar(l)} aria-label={t('Eliminar local')} />
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
                titulo={aEditar ? t('Editar local') : t('Novo local')}
                subtitulo={t('A capacidade é o que impede um evento que não cabe')}
                icone="fa-map-marker-alt"
                cor="perigo"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                capacity: formulario.capacity ? Number(formulario.capacity) : null,
                            })}>
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
                                placeholder={t('Ex.: Centro de Convenções')} className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Morada')} erro={erros.address}>
                            <input type="text" value={formulario.address}
                                onChange={(e) => porFormulario({ ...formulario, address: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Cidade')} erro={erros.city}>
                                <input type="text" value={formulario.city}
                                    onChange={(e) => porFormulario({ ...formulario, city: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Capacidade')} erro={erros.capacity}
                                ajuda={t('Quantas pessoas leva. O evento não passa daqui.')}>
                                <input type="number" min="0" value={formulario.capacity}
                                    onChange={(e) => porFormulario({ ...formulario, capacity: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                                <input type="text" value={formulario.phone}
                                    onChange={(e) => porFormulario({ ...formulario, phone: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Pessoa de contacto')} erro={erros.contact_person}>
                                <input type="text" value={formulario.contact_person}
                                    onChange={(e) => porFormulario({ ...formulario, contact_person: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Notas')} erro={erros.notes}
                            ajuda={t('Onde é a entrada de carga, a que horas abre, com quem se fala.')}>
                            <textarea rows={3} value={formulario.notes}
                                onChange={(e) => porFormulario({ ...formulario, notes: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <label className="flex items-center gap-3">
                            <input type="checkbox" checked={formulario.is_active}
                                onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                className="h-5 w-5 rounded text-red-600" />
                            <span className="text-sm font-medium text-slate-700">{t('Activo')}</span>
                        </label>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar local')}
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
                    {t('Eliminar «:nome»? Um local com eventos não se apaga — desligue-o, que as fichas ficam com o sítio.', {
                        nome: aApagar?.nome ?? '',
                    })}
                </p>
            </Modal>
        </div>
    );
}
