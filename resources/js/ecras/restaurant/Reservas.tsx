import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type ReservaDeMesa, type ReservaParaGravar } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * AS RESERVAS DE MESA — um dia de cada vez.
 *
 * O DIA É A UNIDADE porque é assim que se trabalha: quem chega de manhã quer
 * saber o que a casa tem hoje, não uma lista de tudo o que alguma vez foi
 * reservado.
 *
 * OS ESTADOS SÃO BOTÕES e não um selector: confirmar uma reserva põe a mesa em
 * «reservada», cancelar devolve-a a «livre». Cada transição faz duas coisas, e
 * por isso cada uma tem o seu botão — com o que é possível a partir de onde a
 * reserva está, e mais nada.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    confirmed: 'primaria',
    seated: 'bom',
    completed: 'neutra',
    cancelled: 'perigo',
    no_show: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    pending: 'fa-hourglass-half',
    confirmed: 'fa-circle-check',
    seated: 'fa-chair',
    completed: 'fa-flag-checkered',
    cancelled: 'fa-xmark',
    no_show: 'fa-user-slash',
};

const horaLocal = (d: Date) => {
    const p = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
};

const formularioVazio = (venue: string): ReservaParaGravar => ({
    venue_id: venue, table_id: '', guest_name: '', phone: '', email: '',
    guest_count: '2', reserved_at: horaLocal(new Date(Date.now() + 3600_000)),
    duration_minutes: '120', notes: '',
});

export default function Reservas() {
    const cache = useQueryClient();

    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [casa, porCasa] = useState<number | ''>('');
    const [estado, porEstado] = useState('');
    const [formulario, porFormulario] = useState<ReservaParaGravar | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({
        queryKey: ['restaurante', 'reservas', 'opcoes'],
        queryFn: restaurante.reservas.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['restaurante', 'reservas', 'lista', dia, casa, estado],
        queryFn: () => restaurante.reservas.lista({ dia, estabelecimento: casa, estado }),
        placeholderData: keepPreviousData,
    });

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['restaurante', 'reservas'] });

    const guardar = useMutation({
        mutationFn: () => restaurante.reservas.guardar(aEditar, {
            ...formulario,
            table_id: formulario!.table_id ? Number(formulario!.table_id) : null,
            venue_id: Number(formulario!.venue_id),
            guest_count: Number(formulario!.guest_count),
            duration_minutes: Number(formulario!.duration_minutes),
        }),
        onSuccess: (r) => { porFormulario(null); porAEditar(null); porErros({}); porErro(null); porRecado(r.message); refrescar(); },
        onError: (e) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); },
    });

    const mudarEstado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => restaurante.reservas.estado(id, estado),
        onSuccess: (r) => { porErro(null); porRecado(r.message); refrescar(); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const reservas = lista.data?.data ?? [];
    const resumo = lista.data?.resumo;
    const casaEscolhida = formulario
        ? o.estabelecimentos.find((v) => v.valor === formulario.venue_id)
        : undefined;

    const abrirNova = () => {
        porAEditar(null);
        porErros({});
        porFormulario(formularioVazio(String(casa || o.estabelecimentos[0]?.valor || '')));
    };

    const abrirEdicao = (r: ReservaDeMesa) => {
        porAEditar(r.id);
        porErros({});
        porFormulario({
            venue_id: String(r.venue_id ?? ''),
            table_id: String(r.table_id ?? ''),
            guest_name: r.nome,
            phone: r.telefone ?? '',
            email: r.email ?? '',
            guest_count: String(r.pessoas),
            reserved_at: r.quando ? horaLocal(new Date(r.quando)) : horaLocal(new Date()),
            duration_minutes: String(r.duracao),
            notes: r.observacoes ?? '',
        });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Reservas')}
                subtitulo={t('A agenda das mesas, dia a dia')}
                icone="fa-calendar-check"
                cor="ciano"
                accoes={o.permissoes.pode_criar && (
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNova}>
                        <i className="fas fa-plus" aria-hidden="true" />
                        {t('Nova reserva')}
                    </button>
                )}
            >
                <EstadoNaFaixa icone="fa-user-group">
                    {t(':n pessoas esperadas', { n: String(lista.data?.pessoas ?? 0) })}
                </EstadoNaFaixa>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero rotulo={t('Por confirmar')} valor={resumo.pending ?? 0} icone="fa-hourglass-half" tom="ambar" />
                    <CartaoNumero rotulo={t('Confirmadas')} valor={resumo.confirmed ?? 0} icone="fa-circle-check" tom="indigo" />
                    <CartaoNumero rotulo={t('Sentados')} valor={resumo.seated ?? 0} icone="fa-chair" tom="verde" />
                    <CartaoNumero
                        rotulo={t('Não compareceram')}
                        valor={resumo.no_show ?? 0}
                        icone="fa-user-slash"
                        tom="vermelho"
                        nota={t('É o número que mede as mesas perdidas')}
                    />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Dia')} className="w-44">
                    <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                </Campo>

                <div className="flex gap-1.5 pb-0.5">
                    <Botao icone="fa-chevron-left" onClick={() => porDia(new Date(new Date(dia).getTime() - 86400_000).toISOString().slice(0, 10))} aria-label={t('Dia anterior')} />
                    <Botao onClick={() => porDia(new Date().toISOString().slice(0, 10))}>{t('Hoje')}</Botao>
                    <Botao icone="fa-chevron-right" onClick={() => porDia(new Date(new Date(dia).getTime() + 86400_000).toISOString().slice(0, 10))} aria-label={t('Dia seguinte')} />
                </div>

                <Campo etiqueta={t('Estabelecimento')} className="w-52">
                    <select value={casa} onChange={(e) => porCasa(e.target.value ? Number(e.target.value) : '')} className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {o.estabelecimentos.map((v) => <option key={v.valor} value={v.valor}>{v.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-44">
                    <select value={estado} onChange={(e) => porEstado(e.target.value)} className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            <div className={cls(CARTAO, 'overflow-hidden')}>
                {lista.isPending ? (
                    <div className="p-5"><Carregando linhas={5} /></div>
                ) : reservas.length === 0 ? (
                    <SemNada
                        icone="fa-calendar-check"
                        titulo={t('Nenhuma reserva neste dia')}
                        frase={t('Marque a primeira, ou escolha outro dia.')}
                        accao={o.permissoes.pode_criar && (
                            <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>
                                {t('Nova reserva')}
                            </Botao>
                        )}
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {reservas.map((r, i) => (
                            <li key={r.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-4 py-3">
                                <span className="grid h-14 w-16 flex-none place-items-center rounded-xl bg-cyan-50 text-cyan-700">
                                    <span className="text-base font-bold tabular-nums">
                                        {r.quando ? new Date(r.quando).toLocaleTimeString(etiquetaIntl(), { hour: '2-digit', minute: '2-digit' }) : '—'}
                                    </span>
                                    <span className="text-[10px]">{t(':n min', { n: String(r.duracao) })}</span>
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">
                                        {r.nome}
                                        <span className="ml-2 font-normal text-slate-500">
                                            · {t(':n pessoas', { n: String(r.pessoas) })}
                                        </span>
                                    </p>
                                    <p className="truncate text-xs text-slate-500">
                                        {r.numero}
                                        {r.mesa && ` · ${t('Mesa :m', { m: r.mesa })}`}
                                        {r.telefone && ` · ${r.telefone}`}
                                    </p>
                                    {r.observacoes && <p className="truncate text-xs italic text-amber-700">« {r.observacoes} »</p>}
                                </div>

                                <Etiqueta cor={COR_DO_ESTADO[r.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[r.estado]}>
                                    {r.estado_rotulo}
                                </Etiqueta>

                                <div className="flex flex-none flex-wrap gap-1">
                                    {r.pode.map((p) => (
                                        <Botao
                                            key={p.valor}
                                            altura="pequeno"
                                            cor={['cancelled', 'no_show'].includes(p.valor) ? 'perigo' : p.valor === 'seated' ? 'bom' : 'primaria'}
                                            icone={ICONE_DO_ESTADO[p.valor]}
                                            onClick={() => mudarEstado.mutate({ id: r.id, estado: p.valor })}
                                        >
                                            {p.rotulo}
                                        </Botao>
                                    ))}

                                    {o.permissoes.pode_editar && (
                                        <Botao altura="pequeno" icone="fa-pen" onClick={() => abrirEdicao(r)} aria-label={t('Editar')} />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={aEditar ? t('Editar reserva') : t('Nova reserva')}
                subtitulo={t('A mesa tem de caber a gente toda, e não pode ter outra reserva à mesma hora')}
                icone={aEditar ? 'fa-pen' : 'fa-calendar-plus'}
                cor="ciano"
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
                            <Campo etiqueta={t('Estabelecimento')} obrigatorio erro={erros.venue_id}>
                                <select
                                    value={formulario.venue_id}
                                    onChange={(e) => porFormulario({ ...formulario, venue_id: e.target.value, table_id: '' })}
                                    className={entrada}
                                >
                                    {o.estabelecimentos.map((v) => <option key={v.valor} value={v.valor}>{v.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo
                                etiqueta={t('Mesa')}
                                erro={erros.table_id}
                                ajuda={t('Sem mesa, a reserva fica só no nome — atribui-se à chegada.')}
                            >
                                <select
                                    value={formulario.table_id}
                                    onChange={(e) => porFormulario({ ...formulario, table_id: e.target.value })}
                                    className={entrada}
                                >
                                    <option value="">{t('Sem mesa fixa')}</option>
                                    {casaEscolhida?.mesas.map((m) => (
                                        <option key={m.valor} value={m.valor} disabled={m.lugares < Number(formulario.guest_count)}>
                                            {m.rotulo}
                                        </option>
                                    ))}
                                </select>
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Nome de quem reserva')} obrigatorio erro={erros.guest_name}>
                            <input
                                value={formulario.guest_name}
                                onChange={(e) => porFormulario({ ...formulario, guest_name: e.target.value })}
                                className={entrada}
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                                <input
                                    value={formulario.phone}
                                    onChange={(e) => porFormulario({ ...formulario, phone: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('E-mail')} erro={erros.email}>
                                <input
                                    type="email"
                                    value={formulario.email}
                                    onChange={(e) => porFormulario({ ...formulario, email: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Pessoas')} obrigatorio erro={erros.guest_count}>
                                <input
                                    type="number" min="1" max="100"
                                    value={formulario.guest_count}
                                    onChange={(e) => porFormulario({ ...formulario, guest_count: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Quando')} obrigatorio erro={erros.reserved_at}>
                                <input
                                    type="datetime-local"
                                    value={formulario.reserved_at}
                                    onChange={(e) => porFormulario({ ...formulario, reserved_at: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Duração (min)')} obrigatorio erro={erros.duration_minutes}>
                                <input
                                    type="number" min="30" max="720" step="15"
                                    value={formulario.duration_minutes}
                                    onChange={(e) => porFormulario({ ...formulario, duration_minutes: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Observações')} erro={erros.notes}>
                            <textarea
                                value={formulario.notes}
                                onChange={(e) => porFormulario({ ...formulario, notes: e.target.value })}
                                rows={2}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>
                    </div>
                )}
            </Modal>
        </div>
    );
}
