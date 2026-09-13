import { useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    reservas,
    tarifas,
    type FiltrosDasReservas,
    type HospedeDaProcura,
    type OpcoesDasReservas,
    type Reserva,
    type ReservaParaGravar,
} from '@/api/hotel';
import { catalogos } from '@/api/catalogos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t, tPartes } from '@/i18n';

/**
 * AS RESERVAS — o centro do módulo do hotel.
 *
 * Uma estada nasce pendente, confirma-se, dá entrada e sai pelo ecrã de
 * check-out. Cancelar e «não compareceu» são os dois fins que não passam pela
 * porta.
 *
 * O QUE SE PODE FAZER A CADA RESERVA VEM DO SERVIDOR (`pode`), da tabela de
 * transições do modelo. O ecrã de sempre repetia as condições em `@if`s —
 * `status === 'pending'`, `in_array($status, ['pending','confirmed'])` — e
 * quando a tabela mudou os botões não mudaram com ela.
 *
 * O CHECK-OUT ENCAMINHA para o ecrã que factura, e não fecha a estada aqui:
 * fechá-la sem facturar deixava o hóspede sair sem documento e, com a reserva
 * já em «checked_out», o caminho fiscal deixava de estar acessível.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    confirmed: 'primaria',
    checked_in: 'bom',
    checked_out: 'neutra',
    cancelled: 'perigo',
    no_show: 'aviso',
};

const ICONE_DA_FONTE: Record<string, string> = {
    direct: 'fa-handshake',
    website: 'fa-globe',
    booking: 'fa-b',
    airbnb: 'fa-house',
    phone: 'fa-phone',
    email: 'fa-envelope',
    walk_in: 'fa-person-walking',
    other: 'fa-ellipsis',
};

const hoje = () => new Date().toISOString().slice(0, 10);
const amanha = () => {
    const d = new Date();

    d.setDate(d.getDate() + 1);

    return d.toISOString().slice(0, 10);
};

/** As noites entre duas datas — o mesmo cálculo que o servidor faz ao gravar. */
function noitesEntre(de: string, ate: string): number {
    if (!de || !ate) return 0;

    const ms = new Date(`${ate}T12:00:00`).getTime() - new Date(`${de}T12:00:00`).getTime();

    return Math.max(0, Math.round(ms / 86_400_000));
}

const formularioVazio = (): ReservaParaGravar => ({
    client_id: '', room_type_id: '', room_id: '',
    check_in_date: hoje(), check_out_date: amanha(),
    adults: '1', children: '0', extra_beds: '0',
    source: 'direct', room_rate: '0', discount: '0',
    special_requests: '', internal_notes: '', payment_method: '', paid_amount: '0',
});

const HOSPEDE_VAZIO = {
    name: '', phone: '', email: '', nif: '', address: '', city: '', province: 'Luanda',
};

type HospedeRapido = typeof HOSPEDE_VAZIO;

export default function Reservas() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDasReservas>({ procura: '', page: 1 });
    const [formulario, porFormulario] = useState<ReservaParaGravar | null>(null);
    const [aEditar, porAEditar] = useState<Reserva | null>(null);
    const [hospedeEscolhido, porHospedeEscolhido] = useState<HospedeDaProcura | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aVer, porAVer] = useState<Reserva | null>(null);
    const [aDarEntrada, porADarEntrada] = useState<Reserva | null>(null);
    const [aReceber, porAReceber] = useState<Reserva | null>(null);
    const [aCancelar, porACancelar] = useState<Reserva | null>(null);
    const [aMarcarFalta, porAMarcarFalta] = useState<Reserva | null>(null);
    const [novoHospede, porNovoHospede] = useState<HospedeRapido | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aviso, porAviso] = useState('');

    const opcoes = useQuery({ queryKey: ['hotel', 'reservas', 'opcoes'], queryFn: reservas.opcoes, staleTime: 5 * 60_000 });

    const lista = useQuery({
        queryKey: ['hotel', 'reservas', 'lista', filtros],
        queryFn: () => reservas.lista(filtros),
        placeholderData: keepPreviousData,
        enabled: opcoes.isSuccess,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['hotel', 'reservas'] });

    const aplicar = (r: { data: Reserva; message?: string; aviso?: string | null }) => {
        invalidar();
        porRecado(r.message ?? '');
        porAviso(r.aviso ?? '');
        porAVer((x) => (x && x.id === r.data.id ? r.data : x));
    };

    const gravar = useMutation({
        mutationFn: (dados: ReservaParaGravar) => (aEditar ? reservas.guardar(aEditar.id, dados) : reservas.criar(dados)),
        onSuccess: (r) => {
            invalidar(); porFormulario(null); porAEditar(null); porErros({});
            porHospedeEscolhido(null); porRecado(r.message ?? '');
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const mudarEstado = useMutation({
        mutationFn: ({ id, accao, extra }: { id: number; accao: 'confirmar' | 'entrada' | 'nao-compareceu' | 'cancelar'; extra?: { quarto?: number; motivo?: string } }) =>
            reservas.estado(id, accao, extra),
        onSuccess: (r) => { aplicar(r); porADarEntrada(null); porACancelar(null); porAMarcarFalta(null); },
    });

    const receber = useMutation({
        mutationFn: ({ id, valor, meio, facturar }: { id: number; valor: string; meio: string; facturar: boolean }) =>
            reservas.receber(id, valor, meio, facturar),
        onSuccess: (r) => { aplicar(r); porAReceber(null); },
    });

    const criarHospede = useMutation({
        // PELA PORTA DE SEMPRE — o catálogo dos hóspedes, com as mesmas
        // validações e a mesma permissão. O formulário rápido do ecrã em Blade
        // escrevia directamente na tabela dos clientes, sem passar por nenhuma.
        mutationFn: (dados: HospedeRapido) => catalogos.criar('hospedes', dados),
        onSuccess: (r) => {
            porNovoHospede(null);
            porHospedeEscolhido({
                id: r.data.id, nome: String(r.data.name ?? ''), telefone: null, email: null,
                nif: null, vip: false, lista_negra: false,
            });
            porFormulario((f) => (f ? { ...f, client_id: String(r.data.id) } : f));
            porRecado(r.message);
        },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const resumo = lista.data?.resumo;
    const contas = lista.data?.meta;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    const abrirNovo = () => {
        porAEditar(null); porErros({}); porHospedeEscolhido(null); porFormulario(formularioVazio());
    };

    const abrirEdicao = (r: Reserva) => {
        porAEditar(r); porErros({}); porAVer(null);
        porHospedeEscolhido(r.client_id ? {
            id: r.client_id, nome: r.hospede, telefone: r.telefone, email: r.email,
            nif: null, vip: false, lista_negra: false,
        } : null);
        porFormulario({
            client_id: String(r.client_id ?? ''),
            room_type_id: String(r.room_type_id ?? ''),
            room_id: String(r.room_id ?? ''),
            check_in_date: r.entrada ?? hoje(),
            check_out_date: r.saida ?? amanha(),
            adults: String(r.adultos),
            children: String(r.criancas),
            extra_beds: String(r.camas_extra),
            source: r.fonte,
            room_rate: String(r.taxa),
            discount: String(r.desconto ?? 0),
            special_requests: r.pedidos ?? '',
            internal_notes: r.notas ?? '',
            payment_method: r.meio_de_pagamento ?? '',
            paid_amount: String(r.pago),
        });
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Reservas')}
                subtitulo={t('Quem vem, quem está e quem sai')}
                icone="fa-calendar-check"
                cor="primaria"
                accoes={
                    <>
                        <a href="/hotel/calendar" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-calendar-days" aria-hidden="true" />
                            {t('Calendário')}
                        </a>
                        {o.permissoes.pode_criar && (
                            <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                                {t('Nova Reserva')}
                            </button>
                        )}
                    </>
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} className={cls('p-1', FOCO, RAIO)} aria-label={t('Fechar')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            )}

            {/* O AVISO DAS FACTURAS POR REGULARIZAR fica noutra cor e não se
                mistura com o «feito»: cancelar uma reserva não anula o
                documento fiscal, e é preciso que alguém emita a nota. */}
            {aviso && (
                <div role="alert" className={cls('flex items-start justify-between gap-3 border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                    <span><i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{aviso}</span>
                    <button type="button" onClick={() => porAviso('')} className={cls('p-1', FOCO, RAIO)} aria-label={t('Fechar')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            )}

            <AvisoDeErro erro={mudarEstado.error ?? receber.error} />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Entram hoje')} tom="verde" icone="fa-right-to-bracket"
                    nota={t('nesta casa')} valor={resumo ? numero(resumo.entram_hoje) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Saem hoje')} tom="laranja" icone="fa-right-from-bracket"
                    valor={resumo ? numero(resumo.saem_hoje) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Hospedados')} tom="azul" icone="fa-bed"
                    valor={resumo ? numero(resumo.hospedados) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Por confirmar')}
                    tom={resumo && resumo.pendentes > 0 ? 'ambar' : 'cinza'} icone="fa-clock"
                    valor={resumo ? numero(resumo.pendentes) : '—'} />
            </div>

            <div className={cls(CARTAO, 'grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5')}>
                <Campo etiqueta={t('Procurar')}>
                    <input type="search" value={filtros.procura ?? ''} className={entrada}
                        placeholder={t('Nº, código, nome, email ou telefone')}
                        onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} />
                </Campo>
                <Campo etiqueta={t('Estado')}>
                    <select value={filtros.estado ?? ''} className={entrada}
                        onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))}>
                        <option value="">{t('Todos')}</option>
                        {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Origem')}>
                    <select value={filtros.fonte ?? ''} className={entrada}
                        onChange={(e) => porFiltros((f) => ({ ...f, fonte: e.target.value, page: 1 }))}>
                        <option value="">{t('Todas')}</option>
                        {o.fontes.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Quando')}>
                    <select value={filtros.quando ?? ''} className={entrada}
                        onChange={(e) => porFiltros((f) => ({ ...f, quando: e.target.value, page: 1 }))}>
                        <option value="">{t('Sem limite de datas')}</option>
                        {o.filtros_de_data.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <PorPagina
                    valor={filtros.por_pagina ?? 15}
                    aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))}
                />
            </div>

            <div className={cls(CARTAO, 'overflow-hidden', lista.isFetching && 'opacity-70 transition-opacity')}>
                {lista.isPending ? (
                    <Carregando linhas={8} />
                ) : lista.isError ? (
                    <Falhou erro={lista.error} />
                ) : (lista.data?.data.length ?? 0) === 0 ? (
                    <SemNada
                        icone="fa-calendar-xmark"
                        titulo={t('Nenhuma reserva')}
                        frase={t('Limpe os filtros, ou faça a primeira reserva.')}
                        accao={o.permissoes.pode_criar && (
                            <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>{t('Nova Reserva')}</Botao>
                        )}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-4 py-3 text-left">{t('Reserva')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Hóspede')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Quarto')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Período')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Valor')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Estado')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {(lista.data?.data ?? []).map((r, i) => (
                                    <tr key={r.id} className="entra transition-colors duration-150 hover:bg-indigo-50/40"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="px-4 py-2.5">
                                            <span className="block font-mono text-xs font-bold text-indigo-600">{r.numero}</span>
                                            <span className="block text-xs text-slate-400">
                                                <i className={cls('fas mr-1', ICONE_DA_FONTE[r.fonte] ?? 'fa-ellipsis')} aria-hidden="true" />
                                                {r.fonte_rotulo}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="flex items-center gap-2">
                                                <Inicial nome={r.hospede} />
                                                <span className="min-w-0">
                                                    <span className="block truncate font-semibold text-slate-800">{r.hospede}</span>
                                                    {r.telefone && <span className="block text-xs text-slate-400">{r.telefone}</span>}
                                                </span>
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="block text-slate-700">{r.tipo_de_quarto ?? '—'}</span>
                                            {r.quarto ? (
                                                <span className="block text-xs text-emerald-600">
                                                    <i className="fas fa-door-open mr-1" aria-hidden="true" />
                                                    {t('Quarto :n', { n: r.quarto })}
                                                </span>
                                            ) : (
                                                <span className="block text-xs text-amber-500">
                                                    <i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />
                                                    {t('Sem quarto')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="block tabular-nums text-slate-700">
                                                {r.entrada ? data(r.entrada) : '—'} → {r.saida ? data(r.saida) : '—'}
                                            </span>
                                            <span className="block text-xs text-slate-400">{t(':n noite(s)', { n: r.noites })}</span>
                                        </td>
                                        <td className="px-4 py-2.5 text-right">
                                            <span className="block font-bold tabular-nums text-slate-800">{kz(r.total)} Kz</span>
                                            <span className={cls('block text-xs', r.estado_de_pagamento === 'paid' ? 'text-emerald-600' : 'text-amber-600')}>
                                                {r.estado_de_pagamento_rotulo}
                                            </span>
                                            {r.por_receber > 0 && (
                                                <span className="block text-xs font-semibold tabular-nums text-red-600">
                                                    {t('Falta :v Kz', { v: kz(r.por_receber) })}
                                                </span>
                                            )}
                                            {r.invoice_id && r.factura && (
                                                <a href={`/invoicing/sales/invoices/${r.invoice_id}/preview`}
                                                    className={cls('block text-xs font-semibold text-indigo-600 hover:underline', FOCO, RAIO)}>
                                                    <i className="fas fa-file-invoice mr-1" aria-hidden="true" />{r.factura}
                                                </a>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <Etiqueta cor={COR_DO_ESTADO[r.estado] ?? 'neutra'}>{r.estado_rotulo}</Etiqueta>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <Accoes
                                                r={r}
                                                o={o}
                                                aTrabalhar={mudarEstado.isPending}
                                                aoVer={() => porAVer(r)}
                                                aoConfirmar={() => mudarEstado.mutate({ id: r.id, accao: 'confirmar' })}
                                                aoDarEntrada={() => porADarEntrada(r)}
                                                aoEditar={() => abrirEdicao(r)}
                                                aoFalta={() => porAMarcarFalta(r)}
                                                aoCancelar={() => porACancelar(r)}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm">
                        <span className="text-slate-500">
                            {t('Página :actual de :total', { actual: contas.current_page, total: contas.last_page })}
                        </span>
                        <span className="flex gap-2">
                            <Botao disabled={contas.current_page <= 1} icone="fa-chevron-left"
                                onClick={() => porFiltros((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}>
                                {t('Anterior')}
                            </Botao>
                            <Botao disabled={contas.current_page >= contas.last_page} icone="fa-chevron-right"
                                onClick={() => porFiltros((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}>
                                {t('Seguinte')}
                            </Botao>
                        </span>
                    </div>
                )}
            </div>

            {formulario && (
                <FormularioDaReserva
                    o={o}
                    valores={formulario}
                    erros={erros}
                    hospede={hospedeEscolhido}
                    titulo={aEditar ? t('Editar Reserva') : t('Nova Reserva')}
                    subtitulo={aEditar?.numero}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    aoEscolherHospede={(h) => {
                        porHospedeEscolhido(h);
                        porFormulario((f) => (f ? { ...f, client_id: h ? String(h.id) : '' } : f));
                    }}
                    aoCriarHospede={() => porNovoHospede({ ...HOSPEDE_VAZIO })}
                    aoFechar={() => { porFormulario(null); porAEditar(null); porErros({}); porHospedeEscolhido(null); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            {aVer && (
                <FichaDaReserva
                    reserva={aVer}
                    aTrabalhar={mudarEstado.isPending}
                    aoFechar={() => porAVer(null)}
                    aoReceber={() => porAReceber(aVer)}
                    aoEditar={() => abrirEdicao(aVer)}
                    aoDarEntrada={() => porADarEntrada(aVer)}
                />
            )}

            <DarEntrada
                reserva={aDarEntrada}
                aTrabalhar={mudarEstado.isPending}
                erro={mudarEstado.error}
                aoFechar={() => porADarEntrada(null)}
                aoEntrar={(quarto) => aDarEntrada && mudarEstado.mutate({ id: aDarEntrada.id, accao: 'entrada', extra: { quarto } })}
            />

            <Receber
                reserva={aReceber}
                meios={o.meios_de_pagamento}
                aTrabalhar={receber.isPending}
                erro={receber.error}
                aoFechar={() => porAReceber(null)}
                aoReceber={(valor, meio, facturar) => aReceber && receber.mutate({ id: aReceber.id, valor, meio, facturar })}
            />

            <Modal
                aberto={aCancelar !== null}
                aoFechar={() => porACancelar(null)}
                titulo={t('Cancelar reserva')}
                subtitulo={aCancelar?.numero}
                icone="fa-ban"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porACancelar(null)}>{t('Voltar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-ban" aTrabalhar={mudarEstado.isPending}
                            onClick={() => aCancelar && mudarEstado.mutate({ id: aCancelar.id, accao: 'cancelar' })}>
                            {t('Cancelar reserva')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {tPartes('Vai cancelar :nome. O quarto fica livre.', {
                        nome: <strong>{aCancelar?.numero ?? ''}</strong>,
                    })}
                </p>
                {(aCancelar?.invoice_id || (aCancelar?.pago ?? 0) > 0) && (
                    <p className={cls('mt-3 border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('Já há dinheiro recebido nesta reserva. Uma factura emitida não desaparece com o cancelamento — só uma nota de crédito a anula.')}
                    </p>
                )}
            </Modal>

            <Modal
                aberto={aMarcarFalta !== null}
                aoFechar={() => porAMarcarFalta(null)}
                titulo={t('Não compareceu')}
                subtitulo={aMarcarFalta?.numero}
                icone="fa-user-slash"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAMarcarFalta(null)}>{t('Voltar')}</Botao>
                        <Botao cor="aviso" tom="solida" icone="fa-user-slash" aTrabalhar={mudarEstado.isPending}
                            onClick={() => aMarcarFalta && mudarEstado.mutate({ id: aMarcarFalta.id, accao: 'nao-compareceu' })}>
                            {t('Marcar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('O hóspede não apareceu. O quarto fica livre para novas reservas.')}
                </p>
            </Modal>

            {novoHospede && (
                <NovoHospede
                    o={o}
                    valores={novoHospede}
                    aGravar={criarHospede.isPending}
                    erro={criarHospede.error}
                    aoMudar={porNovoHospede}
                    aoFechar={() => porNovoHospede(null)}
                    aoGravar={() => criarHospede.mutate(novoHospede)}
                />
            )}
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

function Accoes({ r, o, aTrabalhar, aoVer, aoConfirmar, aoDarEntrada, aoEditar, aoFalta, aoCancelar }: {
    r: Reserva;
    o: OpcoesDasReservas;
    aTrabalhar: boolean;
    aoVer: () => void;
    aoConfirmar: () => void;
    aoDarEntrada: () => void;
    aoEditar: () => void;
    aoFalta: () => void;
    aoCancelar: () => void;
}) {
    const botao = 'p-2 text-slate-400 transition-all hover:scale-110 disabled:opacity-40';

    return (
        <div className="flex justify-end gap-0.5">
            <button type="button" onClick={aoVer} title={t('Ver')}
                aria-label={t('Ver: :nome', { nome: r.numero })}
                className={cls(botao, 'hover:text-indigo-600', RAIO, FOCO)}>
                <i className="fas fa-eye" aria-hidden="true" />
            </button>

            <a href={`/hotel/reservations/${r.id}/folio`} title={t('Folio — consumos da estada')}
                className={cls(botao, 'inline-block hover:text-purple-600', RAIO, FOCO)}>
                <i className="fas fa-list-ul" aria-hidden="true" />
            </a>

            <a href={`/hotel/reservations/${r.id}/voucher`} target="_blank" rel="noreferrer" title={t('Voucher')}
                className={cls(botao, 'inline-block hover:text-pink-600', RAIO, FOCO)}>
                <i className="fas fa-ticket" aria-hidden="true" />
            </a>

            {o.permissoes.pode_editar && r.pode.confirmar && (
                <button type="button" onClick={aoConfirmar} disabled={aTrabalhar} title={t('Confirmar')}
                    className={cls(botao, 'hover:text-blue-600', RAIO, FOCO)}>
                    <i className="fas fa-check" aria-hidden="true" />
                </button>
            )}

            {o.permissoes.pode_editar && r.pode.entrada && (
                <button type="button" onClick={aoDarEntrada} disabled={aTrabalhar} title={t('Dar entrada')}
                    className={cls(botao, 'hover:text-emerald-600', RAIO, FOCO)}>
                    <i className="fas fa-right-to-bracket" aria-hidden="true" />
                </button>
            )}

            {/* O CHECK-OUT ENCAMINHA para o ecrã que factura. Fechá-lo aqui
                deixava o hóspede sair sem documento. */}
            {o.permissoes.pode_editar && r.pode.saida && (
                <a href={`/hotel/checkout/${r.id}`} title={t('Check-out')}
                    className={cls(botao, 'inline-block hover:text-orange-600', RAIO, FOCO)}>
                    <i className="fas fa-right-from-bracket" aria-hidden="true" />
                </a>
            )}

            {o.permissoes.pode_editar && r.pode.editar && (
                <button type="button" onClick={aoEditar} title={t('Editar')}
                    className={cls(botao, 'hover:text-slate-700', RAIO, FOCO)}>
                    <i className="fas fa-pen" aria-hidden="true" />
                </button>
            )}

            {o.permissoes.pode_editar && r.pode.nao_compareceu && (
                <button type="button" onClick={aoFalta} disabled={aTrabalhar} title={t('Não compareceu')}
                    className={cls(botao, 'hover:text-amber-600', RAIO, FOCO)}>
                    <i className="fas fa-user-slash" aria-hidden="true" />
                </button>
            )}

            {o.permissoes.pode_editar && r.pode.cancelar && (
                <button type="button" onClick={aoCancelar} disabled={aTrabalhar} title={t('Cancelar')}
                    className={cls(botao, 'hover:text-red-600', RAIO, FOCO)}>
                    <i className="fas fa-xmark" aria-hidden="true" />
                </button>
            )}
        </div>
    );
}

function FormularioDaReserva({ o, valores, erros, hospede, titulo, subtitulo, aGravar, erroGeral, aoMudar, aoEscolherHospede, aoCriarHospede, aoFechar, aoGravar }: {
    o: OpcoesDasReservas;
    valores: ReservaParaGravar;
    erros: Record<string, string[]>;
    hospede: HospedeDaProcura | null;
    titulo: string;
    subtitulo?: string;
    aGravar: boolean;
    erroGeral: unknown;
    aoMudar: (v: ReservaParaGravar) => void;
    aoEscolherHospede: (h: HospedeDaProcura | null) => void;
    aoCriarHospede: () => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const mudar = (campo: keyof ReservaParaGravar, valor: string) => aoMudar({ ...valores, [campo]: valor });

    const noites = noitesEntre(valores.check_in_date, valores.check_out_date);
    const taxa = Number(valores.room_rate) || 0;
    const desconto = Number(valores.discount) || 0;

    /*
     * O PREÇO QUE AS TARIFAS DIZEM — época, dia da semana e dias especiais.
     *
     * A conta existia e não era aplicada a lado nenhum: o formulário propunha
     * o `base_price` do tipo, e definir uma época alta não mudava uma reserva.
     * Aqui ela é uma PROPOSTA — quem está ao balcão vê-a e pode mudá-la, que é
     * como funciona um hotel.
     */
    const sugerido = useQuery({
        queryKey: ['hotel', 'tarifas', 'preco', valores.room_type_id, valores.check_in_date, valores.check_out_date],
        queryFn: () => tarifas.preco(valores.room_type_id, valores.check_in_date, valores.check_out_date),
        enabled: Boolean(valores.room_type_id) && noites > 0,
        staleTime: 60_000,
    });

    const proposta = sugerido.data;
    const difereDaTarifa = Boolean(proposta && Math.abs(proposta.media - taxa) > 0.5);

    /* Os quartos do tipo escolhido — o resto não interessa a esta reserva. */
    const quartos = useMemo(
        () => (valores.room_type_id ? o.quartos.filter((q) => q.tipo === valores.room_type_id) : o.quartos),
        [o.quartos, valores.room_type_id],
    );

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={titulo}
            subtitulo={subtitulo}
            icone="fa-calendar-check"
            cor="primaria"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroGeral} />

            <div className="space-y-4">
                <EscolherHospede
                    escolhido={hospede}
                    erro={erros.client_id}
                    podeCriar={o.permissoes.pode_criar_hospede}
                    aoEscolher={aoEscolherHospede}
                    aoCriar={aoCriarHospede}
                />

                <div className="grid gap-3 sm:grid-cols-2">
                    <Campo etiqueta={t('Tipo de quarto')} obrigatorio erro={erros.room_type_id}>
                        <select value={valores.room_type_id} className={entrada}
                            onChange={(e) => {
                                const tipo = o.tipos_de_quarto.find((x) => x.valor === e.target.value);

                                // ESCOLHER O TIPO PREENCHE A TAXA, como o ecrã
                                // de sempre fazia — e limpa o quarto, que podia
                                // ser de outro tipo.
                                aoMudar({
                                    ...valores,
                                    room_type_id: e.target.value,
                                    room_id: '',
                                    room_rate: tipo ? String(tipo.preco) : valores.room_rate,
                                });
                            }}>
                            <option value="">{t('Escolha…')}</option>
                            {o.tipos_de_quarto.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Quarto')} erro={erros.room_id}
                        ajuda={t('Em branco marca o tipo e atribui-se o quarto à entrada.')}>
                        <select value={valores.room_id} className={entrada}
                            onChange={(e) => mudar('room_id', e.target.value)}>
                            <option value="">{t('Atribuir depois')}</option>
                            {quartos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Check-in')} obrigatorio erro={erros.check_in_date}>
                        <input type="date" value={valores.check_in_date} className={cls(entrada, 'tabular-nums')}
                            onChange={(e) => mudar('check_in_date', e.target.value)} />
                    </Campo>
                    <Campo etiqueta={t('Check-out')} obrigatorio erro={erros.check_out_date}>
                        <input type="date" value={valores.check_out_date} className={cls(entrada, 'tabular-nums')}
                            onChange={(e) => mudar('check_out_date', e.target.value)} />
                    </Campo>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    <Campo etiqueta={t('Adultos')} obrigatorio erro={erros.adults}>
                        <input type="number" min={1} max={10} value={valores.adults}
                            className={cls(entrada, 'text-right tabular-nums')}
                            onChange={(e) => mudar('adults', e.target.value)} />
                    </Campo>
                    <Campo etiqueta={t('Crianças')} obrigatorio erro={erros.children}>
                        <input type="number" min={0} max={10} value={valores.children}
                            className={cls(entrada, 'text-right tabular-nums')}
                            onChange={(e) => mudar('children', e.target.value)} />
                    </Campo>
                    <Campo etiqueta={t('Camas extra')} obrigatorio erro={erros.extra_beds}>
                        <input type="number" min={0} max={5} value={valores.extra_beds}
                            className={cls(entrada, 'text-right tabular-nums')}
                            onChange={(e) => mudar('extra_beds', e.target.value)} />
                    </Campo>
                    <Campo etiqueta={t('Origem')} obrigatorio erro={erros.source}>
                        <select value={valores.source} className={entrada}
                            onChange={(e) => mudar('source', e.target.value)}>
                            {o.fontes.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                </div>

                {/* OS VALORES, com a conta à vista. */}
                <section className={cls('border border-amber-200 bg-amber-50 p-4', RAIO)}>
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-amber-900">
                        <i className="fas fa-calculator" aria-hidden="true" />
                        {t('Valores')}
                    </h3>

                    <div className="grid gap-3 sm:grid-cols-4">
                        <Campo etiqueta={t('Taxa/noite (Kz)')} obrigatorio erro={erros.room_rate}>
                            <input type="number" min={0} step="0.01" value={valores.room_rate}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('room_rate', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Desconto (Kz)')} obrigatorio erro={erros.discount}>
                            <input type="number" min={0} step="0.01" value={valores.discount}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('discount', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Valor pago (Kz)')} obrigatorio erro={erros.paid_amount}>
                            <input type="number" min={0} step="0.01" value={valores.paid_amount}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('paid_amount', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Meio de pagamento')} erro={erros.payment_method}>
                            <input value={valores.payment_method} className={entrada}
                                placeholder={t('Ex.: Multicaixa')}
                                onChange={(e) => mudar('payment_method', e.target.value)} />
                        </Campo>
                    </div>

                    {noites > 0 && taxa > 0 && (
                        <p className="mt-3 border-t border-amber-200 pt-3 text-sm text-amber-900">
                            {t(':noites noite(s) × :taxa Kz', { noites, taxa: kz(taxa) })}
                            {desconto > 0 && <> − {t(':v Kz de desconto', { v: kz(desconto) })}</>}
                            {' = '}
                            <strong className="text-lg tabular-nums">{kz(noites * taxa - desconto)} Kz</strong>
                            <span className="ml-2 text-xs text-amber-700">{t('(o imposto entra ao gravar)')}</span>
                        </p>
                    )}

                    {/* A TARIFA DA CASA, quando difere do que está escrito. */}
                    {difereDaTarifa && proposta && (
                        <p className={cls('mt-3 flex flex-wrap items-center justify-between gap-2 border border-purple-200 bg-purple-50 p-3 text-sm text-purple-900', RAIO)}>
                            <span>
                                <i className="fas fa-tags mr-2" aria-hidden="true" />
                                {t('As tarifas da casa dão :media Kz/noite para estas datas (:total Kz no total).', {
                                    media: kz(proposta.media), total: kz(proposta.total),
                                })}
                            </span>
                            <button type="button" onClick={() => mudar('room_rate', String(proposta.media))}
                                className={cls('inline-flex items-center gap-1.5 border border-purple-300 bg-white px-3 py-1.5 text-xs font-semibold text-purple-700 transition-all hover:-translate-y-0.5', RAIO, FOCO)}>
                                <i className="fas fa-wand-magic-sparkles" aria-hidden="true" />
                                {t('Aplicar')}
                            </button>
                        </p>
                    )}
                </section>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Campo etiqueta={t('Pedidos especiais')} erro={erros.special_requests}
                        ajuda={t('Preferências do hóspede — chega à limpeza e à recepção.')}>
                        <textarea rows={2} value={valores.special_requests} className={cls(entrada, 'h-auto py-2')}
                            onChange={(e) => mudar('special_requests', e.target.value)} />
                    </Campo>
                    <Campo etiqueta={t('Notas internas')} erro={erros.internal_notes}
                        ajuda={t('Só a equipa vê — não sai no voucher nem na factura.')}>
                        <textarea rows={2} value={valores.internal_notes} className={cls(entrada, 'h-auto py-2')}
                            onChange={(e) => mudar('internal_notes', e.target.value)} />
                    </Campo>
                </div>
            </div>
        </Modal>
    );
}

/**
 * A CAIXA DE PROCURA DO HÓSPEDE.
 *
 * Escreve-se e o servidor devolve dez; escolhido, mostra-se quem é. A LISTA
 * NEGRA aparece aqui, que é onde importa — o ecrã de sempre só a mostrava na
 * ficha do hóspede, e ninguém a ia lá ver antes de dar o quarto.
 */
function EscolherHospede({ escolhido, erro, podeCriar, aoEscolher, aoCriar }: {
    escolhido: HospedeDaProcura | null;
    erro?: string[];
    podeCriar: boolean;
    aoEscolher: (h: HospedeDaProcura | null) => void;
    aoCriar: () => void;
}) {
    const [procura, porProcura] = useState('');
    const [aberto, porAberto] = useState(false);
    const [atraso, porAtraso] = useState('');

    useEffect(() => {
        const id = setTimeout(() => porAtraso(procura), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const achados = useQuery({
        queryKey: ['hotel', 'reservas', 'hospedes', atraso],
        queryFn: () => reservas.hospedes(atraso),
        enabled: aberto,
        staleTime: 30_000,
    });

    if (escolhido) {
        return (
            <Campo etiqueta={t('Hóspede')} obrigatorio erro={erro}>
                <div className={cls('flex items-center justify-between gap-3 border border-indigo-200 bg-indigo-50 p-3', RAIO)}>
                    <span className="flex min-w-0 items-center gap-2">
                        <Inicial nome={escolhido.nome} grande />
                        <span className="min-w-0">
                            <span className="block truncate font-bold text-indigo-900">{escolhido.nome}</span>
                            <span className="block truncate text-xs text-indigo-600">
                                {[escolhido.telefone, escolhido.email, escolhido.nif].filter(Boolean).join(' · ') || t('Sem contactos')}
                            </span>
                        </span>
                        {escolhido.vip && <Etiqueta cor="aviso">{t('VIP')}</Etiqueta>}
                        {escolhido.lista_negra && <Etiqueta cor="perigo">{t('Lista negra')}</Etiqueta>}
                    </span>
                    <button type="button" onClick={() => { aoEscolher(null); porProcura(''); }}
                        className={cls('p-2 text-indigo-400 transition-colors hover:text-indigo-700', RAIO, FOCO)}
                        aria-label={t('Trocar de hóspede')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>

                {escolhido.lista_negra && (
                    <p className={cls('mt-2 border border-red-200 bg-red-50 p-2 text-xs text-red-800', RAIO)}>
                        <i className="fas fa-ban mr-1.5" aria-hidden="true" />
                        {t('Este hóspede está na lista negra da casa.')}
                    </p>
                )}
            </Campo>
        );
    }

    return (
        <Campo etiqueta={t('Hóspede')} obrigatorio erro={erro}
            ajuda={t('Procure por nome, telefone, email ou NIF.')}>
            <div className="relative">
                <div className="flex gap-2">
                    <input
                        type="search"
                        value={procura}
                        className={entrada}
                        placeholder={t('Procurar hóspede…')}
                        onChange={(e) => { porProcura(e.target.value); porAberto(true); }}
                        onFocus={() => porAberto(true)}
                    />
                    {podeCriar && (
                        <Botao icone="fa-user-plus" onClick={aoCriar}>{t('Novo')}</Botao>
                    )}
                </div>

                {aberto && (
                    <div className={cls('absolute z-20 mt-1 max-h-64 w-full overflow-y-auto border border-slate-200 bg-white shadow-lg', RAIO)}>
                        {achados.isPending ? (
                            <p className="px-3 py-4 text-center text-sm text-slate-400">{t('A procurar…')}</p>
                        ) : (achados.data?.data.length ?? 0) === 0 ? (
                            <p className="px-3 py-4 text-center text-sm text-slate-400">{t('Ninguém com esse nome.')}</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {(achados.data?.data ?? []).map((h) => (
                                    <li key={h.id}>
                                        <button
                                            type="button"
                                            onClick={() => { aoEscolher(h); porAberto(false); }}
                                            className={cls('flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-indigo-50', FOCO)}
                                        >
                                            <Inicial nome={h.nome} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold text-slate-800">{h.nome}</span>
                                                <span className="block truncate text-xs text-slate-400">
                                                    {[h.telefone, h.email, h.nif].filter(Boolean).join(' · ')}
                                                </span>
                                            </span>
                                            {h.vip && <Etiqueta cor="aviso">{t('VIP')}</Etiqueta>}
                                            {h.lista_negra && <Etiqueta cor="perigo">{t('Lista negra')}</Etiqueta>}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </Campo>
    );
}

function NovoHospede({ o, valores, aGravar, erro, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDasReservas;
    valores: HospedeRapido;
    aGravar: boolean;
    erro: unknown;
    aoMudar: (v: HospedeRapido) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const mudar = (campo: keyof HospedeRapido, valor: string) => aoMudar({ ...valores, [campo]: valor });
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Novo Hóspede')}
            icone="fa-user-plus"
            cor="roxo"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {t('Criar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Nome')} obrigatorio erro={daApi?.erros.name} className="sm:col-span-2">
                    <input value={valores.name} className={entrada}
                        onChange={(e) => mudar('name', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Telefone')} erro={daApi?.erros.phone}>
                    <input value={valores.phone} className={entrada}
                        onChange={(e) => mudar('phone', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Email')} erro={daApi?.erros.email}>
                    <input type="email" value={valores.email} className={entrada}
                        onChange={(e) => mudar('email', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('NIF')} erro={daApi?.erros.nif}
                    ajuda={t('Único nesta empresa — é a identidade fiscal.')}>
                    <input value={valores.nif} className={entrada}
                        onChange={(e) => mudar('nif', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Província')} erro={daApi?.erros.province}>
                    <select value={valores.province} className={entrada}
                        onChange={(e) => mudar('province', e.target.value)}>
                        <option value="">—</option>
                        {o.provincias.map((p) => <option key={p} value={p}>{p}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Cidade')} erro={daApi?.erros.city}>
                    <input value={valores.city} className={entrada}
                        onChange={(e) => mudar('city', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Morada')} erro={daApi?.erros.address}>
                    <input value={valores.address} className={entrada}
                        onChange={(e) => mudar('address', e.target.value)} />
                </Campo>
            </div>

            <p className="mt-3 text-xs text-slate-500">
                <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                {t('A ficha completa — documento, nacionalidade, VIP — abre-se em Hóspedes.')}
            </p>
        </Modal>
    );
}

function FichaDaReserva({ reserva, aTrabalhar, aoFechar, aoReceber, aoEditar, aoDarEntrada }: {
    reserva: Reserva;
    aTrabalhar: boolean;
    aoFechar: () => void;
    aoReceber: () => void;
    aoEditar: () => void;
    aoDarEntrada: () => void;
}) {
    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={reserva.numero}
            subtitulo={reserva.hospede}
            icone="fa-calendar-check"
            cor="primaria"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Etiqueta cor={COR_DO_ESTADO[reserva.estado] ?? 'neutra'}>{reserva.estado_rotulo}</Etiqueta>
                    <Etiqueta cor={reserva.estado_de_pagamento === 'paid' ? 'bom' : 'aviso'}>
                        {reserva.estado_de_pagamento_rotulo}
                    </Etiqueta>
                    {reserva.codigo && (
                        <span className="font-mono text-xs text-slate-400">
                            {t('Código: :c', { c: reserva.codigo })}
                        </span>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h3 className="mb-3 text-sm font-bold text-slate-900">{t('A estada')}</h3>
                        <dl className="space-y-1.5">
                            <Dado rotulo={t('Hóspede')} valor={reserva.hospede} />
                            <Dado rotulo={t('Telefone')} valor={reserva.telefone} />
                            <Dado rotulo={t('Email')} valor={reserva.email} />
                            <Dado rotulo={t('Tipo de quarto')} valor={reserva.tipo_de_quarto} />
                            <Dado rotulo={t('Quarto')} valor={reserva.quarto} />
                            <Dado rotulo={t('Check-in')} valor={reserva.entrada ? data(reserva.entrada) : null} />
                            <Dado rotulo={t('Check-out')} valor={reserva.saida ? data(reserva.saida) : null} />
                            <Dado rotulo={t('Noites')} valor={String(reserva.noites)} />
                            <Dado rotulo={t('Pessoas')}
                                valor={t(':a adulto(s) + :c criança(s)', { a: reserva.adultos, c: reserva.criancas })} />
                            <Dado rotulo={t('Camas extra')} valor={reserva.camas_extra > 0 ? String(reserva.camas_extra) : null} />
                            <Dado rotulo={t('Origem')} valor={reserva.fonte_rotulo} />
                        </dl>
                    </section>

                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h3 className="mb-3 text-sm font-bold text-slate-900">{t('A conta')}</h3>
                        <dl className="space-y-1.5">
                            <Dado rotulo={t('Taxa/noite')} valor={`${kz(reserva.taxa)} Kz`} />
                            <Dado rotulo={t('Subtotal')} valor={reserva.subtotal === undefined ? null : `${kz(reserva.subtotal)} Kz`} />
                            <Dado rotulo={t('Desconto')} valor={reserva.desconto ? `−${kz(reserva.desconto)} Kz` : null} />
                            <Dado rotulo={t('Extras')} valor={reserva.extras ? `${kz(reserva.extras)} Kz` : null} />
                            <Dado rotulo={t('Imposto')} valor={reserva.imposto === undefined ? null : `${kz(reserva.imposto)} Kz`} />
                            <div className="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-1.5 text-sm">
                                <dt className="font-bold text-slate-700">{t('Total')}</dt>
                                <dd className="text-lg font-bold tabular-nums text-slate-900">{kz(reserva.total)} Kz</dd>
                            </div>
                            <Dado rotulo={t('Pago')} valor={`${kz(reserva.pago)} Kz`} />
                            {reserva.por_receber > 0 && (
                                <div className="flex items-baseline justify-between gap-3 text-sm">
                                    <dt className="text-red-600">{t('Por receber')}</dt>
                                    <dd className="font-bold tabular-nums text-red-600">{kz(reserva.por_receber)} Kz</dd>
                                </div>
                            )}
                            <Dado rotulo={t('Meio de pagamento')} valor={reserva.meio_de_pagamento} />
                        </dl>
                    </section>
                </div>

                {reserva.pedidos && (
                    <section className={cls('flex items-start gap-2 border border-amber-200 bg-amber-50 p-3', RAIO)}>
                        <i className="fas fa-star mt-0.5 text-amber-500" aria-hidden="true" />
                        <span>
                            <span className="block text-xs font-bold uppercase tracking-wide text-amber-700">{t('Pedidos especiais')}</span>
                            <p className="whitespace-pre-line text-sm text-amber-900">{reserva.pedidos}</p>
                        </span>
                    </section>
                )}

                {reserva.notas && (
                    <section className={cls('flex items-start gap-2 border border-slate-200 bg-slate-50 p-3', RAIO)}>
                        <i className="fas fa-lock mt-0.5 text-slate-400" aria-hidden="true" />
                        <span>
                            <span className="block text-xs font-bold uppercase tracking-wide text-slate-500">{t('Notas internas')}</span>
                            <p className="whitespace-pre-line text-sm text-slate-700">{reserva.notas}</p>
                        </span>
                    </section>
                )}

                {reserva.motivo_do_cancelamento && (
                    <section className={cls('flex items-start gap-2 border border-red-200 bg-red-50 p-3', RAIO)}>
                        <i className="fas fa-ban mt-0.5 text-red-500" aria-hidden="true" />
                        <p className="text-sm text-red-800">{reserva.motivo_do_cancelamento}</p>
                    </section>
                )}

                {reserva.invoice_id && reserva.factura && (
                    <section className={cls('flex flex-wrap items-center justify-between gap-3 border border-indigo-200 bg-indigo-50 p-3', RAIO)}>
                        <span className="text-sm text-indigo-900">
                            <i className="fas fa-file-invoice mr-2" aria-hidden="true" />
                            {t('Factura :n', { n: reserva.factura })}
                        </span>
                        <span className="flex gap-2">
                            <a href={`/invoicing/sales/invoices/${reserva.invoice_id}/preview`} target="_blank" rel="noreferrer"
                                className={cls('inline-flex items-center gap-1.5 border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-all hover:-translate-y-0.5', RAIO, FOCO)}>
                                <i className="fas fa-eye" aria-hidden="true" />{t('Ver')}
                            </a>
                            <a href={`/invoicing/sales/invoices/${reserva.invoice_id}/pdf`} target="_blank" rel="noreferrer"
                                className={cls('inline-flex items-center gap-1.5 border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-all hover:-translate-y-0.5', RAIO, FOCO)}>
                                <i className="fas fa-file-pdf" aria-hidden="true" />{t('PDF')}
                            </a>
                        </span>
                    </section>
                )}

                <div className="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                    {reserva.pode.receber && (
                        <Botao cor="bom" tom="solida" icone="fa-money-bill-wave" onClick={aoReceber}>
                            {t('Receber')}
                        </Botao>
                    )}
                    {reserva.pode.entrada && (
                        <Botao cor="primaria" tom="solida" icone="fa-right-to-bracket" aTrabalhar={aTrabalhar} onClick={aoDarEntrada}>
                            {t('Dar entrada')}
                        </Botao>
                    )}
                    {reserva.pode.saida && (
                        <a href={`/hotel/checkout/${reserva.id}`}
                            className={cls('inline-flex items-center gap-2 bg-orange-500 px-4 py-2 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-orange-600', RAIO, FOCO)}>
                            <i className="fas fa-right-from-bracket" aria-hidden="true" />
                            {t('Check-out')}
                        </a>
                    )}
                    <a href={`/hotel/reservations/${reserva.id}/folio`}
                        className={cls('inline-flex items-center gap-2 border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:border-slate-300', RAIO, FOCO)}>
                        <i className="fas fa-list-ul" aria-hidden="true" />
                        {t('Folio')}
                    </a>
                    {reserva.pode.editar && (
                        <Botao icone="fa-pen" onClick={aoEditar}>{t('Editar')}</Botao>
                    )}
                </div>
            </div>
        </Modal>
    );
}

function DarEntrada({ reserva, aTrabalhar, erro, aoFechar, aoEntrar }: {
    reserva: Reserva | null;
    aTrabalhar: boolean;
    erro: unknown;
    aoFechar: () => void;
    aoEntrar: (quarto?: number) => void;
}) {
    const [escolhido, porEscolhido] = useState<number | null>(null);

    const livres = useQuery({
        queryKey: ['hotel', 'reservas', 'quartos-livres', reserva?.id],
        queryFn: () => reservas.quartosLivres(reserva!.id),
        enabled: reserva !== null,
    });

    const quarto = escolhido ?? reserva?.room_id ?? null;

    return (
        <Modal
            aberto={reserva !== null}
            aoFechar={aoFechar}
            titulo={t('Dar entrada')}
            subtitulo={reserva ? t(':numero · :hospede', { numero: reserva.numero, hospede: reserva.hospede }) : undefined}
            icone="fa-right-to-bracket"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aTrabalhar}
                        disabled={!quarto} onClick={() => aoEntrar(quarto ?? undefined)}>
                        {t('Dar entrada')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            {reserva && (
                <p className="mb-3 text-sm text-slate-600">
                    {t(':tipo · :noites noite(s) · até :saida', {
                        tipo: reserva.tipo_de_quarto ?? '—',
                        noites: reserva.noites,
                        saida: reserva.saida ? data(reserva.saida) : '—',
                    })}
                </p>
            )}

            {reserva?.room_id && (
                <p className={cls('mb-3 border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900', RAIO)}>
                    <i className="fas fa-door-open mr-2" aria-hidden="true" />
                    {t('Já tem o quarto :n marcado. Pode dar entrada assim, ou trocar em baixo.', { n: reserva.quarto ?? '' })}
                </p>
            )}

            <h3 className="mb-2 text-sm font-bold text-slate-700">{t('Quartos livres deste tipo')}</h3>

            {livres.isPending ? (
                <Carregando linhas={3} />
            ) : (livres.data?.data.length ?? 0) === 0 ? (
                <p className={cls('border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {t('Não há quartos livres deste tipo. Liberte um quarto, ou mude o tipo da reserva.')}
                </p>
            ) : (
                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {(livres.data?.data ?? []).map((q) => {
                        const activo = quarto === q.id;

                        return (
                            <li key={q.id}>
                                <button
                                    type="button"
                                    onClick={() => porEscolhido(q.id)}
                                    className={cls(
                                        'w-full border p-3 text-center transition-all duration-200',
                                        'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                        activo
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800 shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                                    )}
                                >
                                    <span className="block text-lg font-bold">{q.numero}</span>
                                    <span className="block text-[11px] opacity-70">{q.limpeza_rotulo}</span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Modal>
    );
}

function Receber({ reserva, meios, aTrabalhar, erro, aoFechar, aoReceber }: {
    reserva: Reserva | null;
    meios: OpcoesDasReservas['meios_de_pagamento'];
    aTrabalhar: boolean;
    erro: unknown;
    aoFechar: () => void;
    aoReceber: (valor: string, meio: string, facturar: boolean) => void;
}) {
    const [valor, porValor] = useState('');
    const [meio, porMeio] = useState('');
    const [facturar, porFacturar] = useState(true);

    // O saldo por omissão: é o que se recebe quase sempre.
    useEffect(() => {
        if (reserva) {
            porValor(String(reserva.por_receber));
            porMeio((m) => m || meios[0]?.valor || '');
        }
    }, [reserva, meios]);

    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <Modal
            aberto={reserva !== null}
            aoFechar={aoFechar}
            titulo={t('Receber adiantamento')}
            subtitulo={reserva?.numero}
            icone="fa-money-bill-wave"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aTrabalhar}
                        onClick={() => aoReceber(valor, meio, facturar)}>
                        {t('Receber')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            {reserva && (
                <dl className={cls('mb-4 space-y-1.5 border border-slate-200 bg-slate-50 p-3', RAIO)}>
                    <Dado rotulo={t('Total')} valor={`${kz(reserva.total)} Kz`} />
                    <Dado rotulo={t('Já pago')} valor={`${kz(reserva.pago)} Kz`} />
                    <div className="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-1.5 text-sm">
                        <dt className="font-bold text-slate-700">{t('Por receber')}</dt>
                        <dd className="text-lg font-bold tabular-nums text-orange-600">{kz(reserva.por_receber)} Kz</dd>
                    </div>
                </dl>
            )}

            <div className="grid gap-3">
                <Campo etiqueta={t('Valor (Kz)')} obrigatorio erro={daApi?.erros.valor}
                    ajuda={t('Não se recebe mais do que o saldo em dívida.')}>
                    <input type="number" min={0} step="0.01" value={valor}
                        className={cls(entrada, 'text-right text-lg tabular-nums')}
                        onChange={(e) => porValor(e.target.value)} />
                </Campo>

                <Campo etiqueta={t('Meio de pagamento')} obrigatorio erro={daApi?.erros.meio}>
                    <select value={meio} className={entrada} onChange={(e) => porMeio(e.target.value)}>
                        <option value="">{t('Escolha…')}</option>
                        {meios.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                    </select>
                </Campo>

                <label className={cls('flex cursor-pointer items-start gap-3 border border-slate-200 p-3 transition-colors hover:bg-slate-50', RAIO)}>
                    <input type="checkbox" checked={facturar} className={cls('mt-0.5 h-5 w-5 rounded border-slate-300 text-indigo-600', FOCO)}
                        onChange={(e) => porFacturar(e.target.checked)} />
                    <span>
                        <span className="block text-sm font-semibold text-slate-800">{t('Emitir factura do adiantamento')}</span>
                        <span className="block text-xs text-slate-500">
                            {t('Documento fiscal do sinal, a deduzir na factura final do check-out.')}
                        </span>
                    </span>
                </label>
            </div>
        </Modal>
    );
}

function Inicial({ nome, grande }: { nome: string; grande?: boolean }) {
    return (
        <span className={cls(
            'grid flex-none place-items-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 font-bold text-white',
            grande ? 'h-9 w-9 text-sm' : 'h-7 w-7 text-xs',
        )} aria-hidden="true">
            {(nome || '—').slice(0, 1).toUpperCase()}
        </span>
    );
}

function Dado({ rotulo, valor }: { rotulo: string; valor?: string | null }) {
    if (!valor) return null;

    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="font-semibold tabular-nums text-slate-800">{valor}</dd>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as reservas')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
