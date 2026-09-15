import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    ordens,
    type AnexoDaOrdem,
    type FichaDaOrdem,
    type FiltrosDasOrdens,
    type LinhaDaOrdem,
    type LinhaParaGravar,
    type OpcoesDasOrdens,
    type Ordem,
    type OrdemParaGravar,
} from '@/api/oficina';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada } from '@/ui/SemNada';
import { Separadores } from '@/ui/Separadores';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { CheckinDaViatura } from './CheckinDaViatura';
import { InspeccaoDaOrdem } from './InspeccaoDaOrdem';
import { AprovacaoDoCliente, SeloDaAprovacao } from './AprovacaoDoCliente';
import { etiquetaIntl, t, tPartes } from '@/i18n';

/**
 * AS ORDENS DE SERVIÇO — o ecrã que a oficina abre todos os dias.
 *
 * A REGRA QUE MANDA NESTE ECRÃ é que a mudança de estado tem UMA porta só: o
 * botão de estado e o formulário de edição chamam ambos a mesma rota, porque
 * passar a «Concluída» desconta as peças do stock e anular devolve-as. O ecrã
 * em Livewire já tinha tido o buraco de gravar o estado por fora, e ficou lá
 * escrito porque é que o ponto único existe.
 *
 * O QUE ESTE ECRÃ NÃO DECIDE: se as peças saíram. Quando alguma não sai por
 * falta de stock, o servidor devolve-as em `falhas` e mostra-se um AVISO — e
 * não um «gravado com sucesso» com uma frase comprida atrás, que era como
 * antes se dizia que tinha corrido bem quando não tinha.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    scheduled: 'primaria',
    in_progress: 'aviso',
    waiting_parts: 'neutra',
    completed: 'bom',
    delivered: 'neutra',
    cancelled: 'perigo',
};

const COR_DA_PRIORIDADE: Record<string, 'neutra' | 'primaria' | 'aviso' | 'perigo'> = {
    low: 'neutra',
    normal: 'primaria',
    high: 'aviso',
    urgent: 'perigo',
};

const ICONE_DO_EVENTO: Record<string, string> = {
    created: 'fa-plus',
    updated: 'fa-pen',
    status_changed: 'fa-right-left',
    item_added: 'fa-cart-plus',
    item_removed: 'fa-trash',
    invoiced: 'fa-file-invoice',
    comment: 'fa-paperclip',
};

const agora = () => {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());

    return d.toISOString().slice(0, 16);
};

const formularioVazio = (): OrdemParaGravar => ({
    vehicle_id: '', mechanic_id: '', received_at: agora(), scheduled_for: '',
    mileage_in: '0', problem_description: '', diagnosis: '', work_performed: '',
    recommendations: '', status: 'pending', priority: 'normal', warranty_days: '30', notes: '',
});

const linhaVazia = (tipo: 'service' | 'part'): LinhaParaGravar => ({
    type: tipo, service_id: '', product_id: '', code: '', name: '', description: '',
    quantity: '1', unit_price: '0', discount_percent: '0', hours: '0',
    mechanic_id: '', part_number: '', brand: '', is_original: false, precisa_aprovacao: false,
});

export default function OrdensDeServico() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDasOrdens>({ procura: '', page: 1 });
    const [formulario, porFormulario] = useState<OrdemParaGravar | null>(null);
    const [aEditar, porAEditar] = useState<Ordem | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aVer, porAVer] = useState<number | null>(null);
    // A aba com que a ficha abre: uma ordem acabada de criar abre no check-in.
    const [abaDaFicha, porAbaDaFicha] = useState('info');

    /*
     * ABRIR UMA ORDEM PELO ENDEREÇO — `?ordem=12`.
     *
     * É por aqui que a ficha da viatura leva à folha de obra. Lê-se uma vez e
     * tira-se do endereço, para um F5 não voltar a abrir a janela.
     */
    useEffect(() => {
        const url = new URL(window.location.href);
        const pedida = Number(url.searchParams.get('ordem'));
        if (!pedida) return;
        porAVer(pedida);
        url.searchParams.delete('ordem');
        window.history.replaceState(null, '', url.toString());
    }, []);
    const [aApagar, porAApagar] = useState<Ordem | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aviso, porAviso] = useState<string[]>([]);

    const opcoes = useQuery({ queryKey: ['oficina', 'ordens', 'opcoes'], queryFn: ordens.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({
        queryKey: ['oficina', 'ordens', filtros],
        queryFn: () => ordens.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['oficina', 'ordens'] });

    const gravar = useMutation({
        mutationFn: (dados: OrdemParaGravar) => (aEditar ? ordens.guardar(aEditar.id, dados) : ordens.criar(dados)),
        onSuccess: (r) => {
            const nova = !aEditar;
            invalidar(); porFormulario(null); porAEditar(null); porErros({}); porRecado(r.message);
            // O carro acabou de entrar: segue-se o check-in (km, combustível, danos, assinatura).
            if (nova && r.data?.id && opcoes.data?.permissoes.pode_editar) { porAbaDaFicha('checkin'); porAVer(r.data.id); }
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const mudarEstado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => ordens.estado(id, estado),
        onSuccess: (r) => { invalidar(); porRecado(r.message); porAviso(r.falhas ?? []); },
    });

    const apagar = useMutation({
        mutationFn: (o: Ordem) => ordens.apagar(o.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError || lista.isError) return <Falhou erro={opcoes.error ?? lista.error} />;

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const resumo = lista.data?.resumo;
    const contas = lista.data?.meta;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    const abrirNovo = () => { porAEditar(null); porErros({}); porFormulario(formularioVazio()); };

    const abrirEdicao = async (linha: Ordem) => {
        const { data: f } = await ordens.ficha(linha.id);

        porAEditar(linha);
        porErros({});
        porFormulario({
            vehicle_id: String(f.vehicle_id ?? ''),
            mechanic_id: String(f.mechanic_id ?? ''),
            received_at: (f.entrada ?? '').slice(0, 16),
            scheduled_for: (f.agendada_para ?? '').slice(0, 16),
            mileage_in: String(f.km ?? 0),
            problem_description: f.problema ?? '',
            diagnosis: f.diagnostico ?? '',
            work_performed: f.trabalho ?? '',
            recommendations: f.recomendacoes ?? '',
            status: f.estado,
            priority: f.prioridade,
            warranty_days: String(f.garantia_dias ?? 30),
            notes: f.notas ?? '',
        });
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Ordens de Serviço')}
                subtitulo={t('O que entrou na oficina, o que se fez e o que falta fazer')}
                icone="fa-clipboard-list"
                cor="roxo"
                accoes={o.permissoes.pode_criar && (
                    <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                        {t('Nova Ordem')}
                    </button>
                )}
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} className={cls('p-1', FOCO, RAIO)} aria-label={t('Fechar')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            )}

            {/* AS PEÇAS QUE NÃO SAÍRAM DO STOCK são um aviso e não uma nota de
                rodapé de um sucesso: a ordem ficou concluída, o inventário
                não. Quem está no balcão tem de saber. */}
            {aviso.length > 0 && (
                <div role="alert" className={cls('border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                    <p className="font-bold">
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('Houve peças que não saíram do stock:')}
                    </p>
                    <ul className="mt-1 list-inside list-disc">
                        {aviso.map((f, i) => <li key={i}>{f}</li>)}
                    </ul>
                    <button type="button" onClick={() => porAviso([])} className={cls('mt-2 text-xs font-semibold underline', FOCO, RAIO)}>
                        {t('Entendi')}
                    </button>
                </div>
            )}

            <AvisoDeErro erro={apagar.error ?? mudarEstado.error} />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Ordens')} tom="roxo" icone="fa-clipboard-list"
                    nota={t('nesta empresa')} valor={resumo ? numero(resumo.total) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Em aberto')} tom={resumo && resumo.em_aberto > 0 ? 'ambar' : 'cinza'}
                    icone="fa-clock" valor={resumo ? numero(resumo.em_aberto) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Em curso')} tom="laranja" icone="fa-screwdriver-wrench"
                    valor={resumo ? numero(resumo.em_curso) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Concluídas')} tom="verde" icone="fa-circle-check"
                    valor={resumo ? numero(resumo.concluidas) : '—'} />
            </div>

            <div className={cls(CARTAO, 'grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4')}>
                <Campo etiqueta={t('Procurar')}>
                    <input
                        type="search"
                        value={filtros.procura ?? ''}
                        placeholder={t('Nº da ordem, matrícula ou dono')}
                        onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                        className={entrada}
                    />
                </Campo>
                <Campo etiqueta={t('Estado')}>
                    <select value={filtros.estado ?? ''} className={entrada}
                        onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))}>
                        <option value="">{t('Todos')}</option>
                        {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Prioridade')}>
                    <select value={filtros.prioridade ?? ''} className={entrada}
                        onChange={(e) => porFiltros((f) => ({ ...f, prioridade: e.target.value, page: 1 }))}>
                        <option value="">{t('Todas')}</option>
                        {o.prioridades.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                    </select>
                </Campo>
                <PorPagina
                    valor={filtros.por_pagina ?? 10}
                    aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))}
                />
            </div>

            <div className={cls(CARTAO, 'overflow-hidden', lista.isFetching && 'opacity-70 transition-opacity')}>
                {lista.isPending ? (
                    <Carregando linhas={6} />
                ) : linhas.length === 0 ? (
                    <SemNada
                        icone="fa-clipboard-list"
                        titulo={t('Nenhuma ordem de serviço')}
                        frase={t('Limpe a procura, ou abra a primeira ordem.')}
                        accao={o.permissoes.pode_criar && (
                            <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>{t('Nova Ordem')}</Botao>
                        )}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-4 py-3 text-left">{t('Nº')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Viatura')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Mecânico')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Entrada')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Estado')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Prioridade')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Total')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((l, i) => (
                                    <tr key={l.id} className="entra transition-colors duration-150 hover:bg-slate-50"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="whitespace-nowrap px-4 py-2.5">
                                            <span className="font-bold text-slate-900">{l.numero}</span>
                                            {l.facturada && (
                                                <span className="ml-1.5 text-[10px] font-bold uppercase text-emerald-600">
                                                    <i className="fas fa-file-invoice mr-0.5" aria-hidden="true" />{t('Facturada')}
                                                </span>
                                            )}
                                            {/* ATRASADA: agendada para uma data que já passou e ainda
                                                por fechar — é isto que faz alguém pegar na lista. */}
                                            {l.atrasada && (
                                                <span className="ml-1.5 text-[10px] font-bold uppercase text-red-600">
                                                    <i className="fas fa-triangle-exclamation mr-0.5" aria-hidden="true" />{t('Atrasada')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="block font-semibold text-slate-800">{l.matricula ?? '—'}</span>
                                            <span className="block text-xs text-slate-400">{l.viatura}{l.dono ? ` · ${l.dono}` : ''}</span>
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">{l.mecanico ?? t('Por atribuir')}</td>
                                        <td className="px-4 py-2.5 tabular-nums text-slate-600">{l.entrada ? data(l.entrada) : '—'}</td>
                                        <td className="px-4 py-2.5">
                                            <Etiqueta cor={COR_DO_ESTADO[l.estado] ?? 'neutra'}>{l.estado_rotulo}</Etiqueta>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <Etiqueta cor={COR_DA_PRIORIDADE[l.prioridade] ?? 'neutra'}>{l.prioridade_rotulo}</Etiqueta>
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-800">{kz(l.total)}</td>
                                        <td className="px-4 py-2.5">
                                            <div className="flex justify-end gap-1">
                                                <button type="button" onClick={() => porAVer(l.id)}
                                                    title={t('Ver')} aria-label={t('Ver: :nome', { nome: l.numero })}
                                                    className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO, FOCO)}>
                                                    <i className="fas fa-eye" aria-hidden="true" />
                                                </button>
                                                {o.permissoes.pode_editar && (
                                                    <button type="button" onClick={() => void abrirEdicao(l)}
                                                        title={t('Editar')} aria-label={t('Editar: :nome', { nome: l.numero })}
                                                        className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-indigo-600', RAIO, FOCO)}>
                                                        <i className="fas fa-pen" aria-hidden="true" />
                                                    </button>
                                                )}
                                                {o.permissoes.pode_apagar && (
                                                    <button type="button" disabled={l.facturada} onClick={() => porAApagar(l)}
                                                        title={l.facturada ? t('Já facturada — não se apaga') : t('Apagar')}
                                                        aria-label={t('Apagar: :nome', { nome: l.numero })}
                                                        className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 active:scale-100 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40', RAIO, FOCO)}>
                                                        <i className="fas fa-trash" aria-hidden="true" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {contas && (
                    <Paginacao
                        pagina={contas.current_page}
                        ultima={contas.last_page}
                        aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                        total={contas.total}
                        de={contas.total > 0 ? (contas.current_page - 1) * contas.per_page + 1 : null}
                        ate={contas.total > 0 ? Math.min(contas.current_page * contas.per_page, contas.total) : null}
                        aCarregar={lista.isFetching}
                    />
                )}
            </div>

            {formulario && (
                <FormularioDaOrdem
                    o={o}
                    valores={formulario}
                    erros={erros}
                    titulo={aEditar ? t('Editar Ordem') : t('Nova Ordem')}
                    subtitulo={aEditar?.numero}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    aoFechar={() => { porFormulario(null); porAEditar(null); porErros({}); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            {aVer !== null && (
                <FichaDaOrdemModal
                    id={aVer}
                    o={o}
                    abaInicial={abaDaFicha}
                    aoFechar={() => { porAVer(null); porAbaDaFicha('info'); }}
                    aoMudar={(m, falhas) => { invalidar(); porRecado(m); porAviso(falhas ?? []); }}
                    mudarEstado={(id, estado) => mudarEstado.mutate({ id, estado })}
                    aMudarEstado={mudarEstado.isPending}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar ordem de serviço')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}>
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {tPartes('Vai apagar :nome. Não há volta.', { nome: <strong>{aApagar?.numero ?? ''}</strong> })}
                </p>
                <p className="mt-2 text-xs text-slate-500">
                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                    {t('As peças que já tinham saído voltam ao stock.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── O formulário da ordem ─────────────────────────────────────────── */

function FormularioDaOrdem({ o, valores, erros, titulo, subtitulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDasOrdens;
    valores: OrdemParaGravar;
    erros: Record<string, string[]>;
    titulo: string;
    subtitulo?: string;
    aGravar: boolean;
    erroGeral: unknown;
    aoMudar: (v: OrdemParaGravar) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const [aba, porAba] = useState('entrada');

    const mudar = (campo: keyof OrdemParaGravar, valor: string) => aoMudar({ ...valores, [campo]: valor });

    /*
     * ESCOLHER A VIATURA TRAZ OS QUILÓMETROS DELA.
     *
     * Escrevê-los outra vez à mão é onde nascem os enganos — e a diferença
     * entre a última entrada e esta é o que diz se o carro andou.
     */
    const escolherViatura = (id: string) => {
        const v = o.viaturas.find((x) => x.valor === id);

        aoMudar({ ...valores, vehicle_id: id, mileage_in: v ? String(v.km) : valores.mileage_in });
    };

    const dosCampos = (campos: Array<keyof OrdemParaGravar>) =>
        campos.filter((c) => (erros[c] ?? []).length > 0).length;

    const abas = [
        { chave: 'entrada', rotulo: t('Entrada'), icone: 'fa-car-side',
            erros: dosCampos(['vehicle_id', 'mechanic_id', 'received_at', 'scheduled_for', 'mileage_in']) },
        { chave: 'trabalho', rotulo: t('Trabalho'), icone: 'fa-screwdriver-wrench',
            erros: dosCampos(['problem_description', 'diagnosis', 'work_performed', 'recommendations']) },
        { chave: 'estado', rotulo: t('Estado'), icone: 'fa-flag',
            erros: dosCampos(['status', 'priority', 'warranty_days', 'notes']) },
    ];

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={titulo}
            subtitulo={subtitulo}
            icone="fa-clipboard-list"
            cor="roxo"
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

            <Separadores abas={abas} activa={aba} aoMudar={porAba} />

            <div className="pt-4">
                <div hidden={aba !== 'entrada'} className={cls('grid gap-3 sm:grid-cols-2', aba !== 'entrada' && 'hidden')}>
                    <Campo etiqueta={t('Viatura')} obrigatorio erro={erros.vehicle_id}>
                        <select value={valores.vehicle_id} onChange={(e) => escolherViatura(e.target.value)} className={entrada}>
                            <option value="">—</option>
                            {o.viaturas.map((v) => (
                                <option key={v.valor} value={v.valor}>{v.rotulo}{v.dono ? ` · ${v.dono}` : ''}</option>
                            ))}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Mecânico')} erro={erros.mechanic_id}>
                        <select value={valores.mechanic_id} onChange={(e) => mudar('mechanic_id', e.target.value)} className={entrada}>
                            <option value="">{t('Por atribuir')}</option>
                            {o.mecanicos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Entrada')} obrigatorio erro={erros.received_at}>
                        <input type="datetime-local" value={valores.received_at}
                            onChange={(e) => mudar('received_at', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>
                    <Campo etiqueta={t('Agendada para')} erro={erros.scheduled_for}
                        ajuda={t('Passada esta data com a ordem por fechar, ela aparece como atrasada.')}>
                        <input type="datetime-local" value={valores.scheduled_for}
                            onChange={(e) => mudar('scheduled_for', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>
                    <Campo etiqueta={t('Quilómetros à entrada')} erro={erros.mileage_in}>
                        <input type="number" min={0} step={1} value={valores.mileage_in}
                            onChange={(e) => mudar('mileage_in', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                </div>

                <div hidden={aba !== 'trabalho'} className={cls('grid gap-3', aba !== 'trabalho' && 'hidden')}>
                    <Campo etiqueta={t('Problema relatado')} obrigatorio erro={erros.problem_description}
                        ajuda={t('O que o dono do carro disse — nas palavras dele.')}>
                        <textarea rows={3} value={valores.problem_description}
                            onChange={(e) => mudar('problem_description', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                    <Campo etiqueta={t('Diagnóstico')} erro={erros.diagnosis}>
                        <textarea rows={2} value={valores.diagnosis}
                            onChange={(e) => mudar('diagnosis', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                    <Campo etiqueta={t('Trabalho realizado')} erro={erros.work_performed}>
                        <textarea rows={2} value={valores.work_performed}
                            onChange={(e) => mudar('work_performed', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                    <Campo etiqueta={t('Recomendações')} erro={erros.recommendations}
                        ajuda={t('O que fica para a próxima visita.')}>
                        <textarea rows={2} value={valores.recommendations}
                            onChange={(e) => mudar('recommendations', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                </div>

                <div hidden={aba !== 'estado'} className={cls('grid gap-3 sm:grid-cols-2', aba !== 'estado' && 'hidden')}>
                    <Campo etiqueta={t('Estado')} obrigatorio erro={erros.status}
                        ajuda={t('Passar a Concluída ou Entregue desconta as peças do stock; anular devolve-as.')}>
                        <select value={valores.status} onChange={(e) => mudar('status', e.target.value)} className={entrada}>
                            {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Prioridade')} obrigatorio erro={erros.priority}>
                        <select value={valores.priority} onChange={(e) => mudar('priority', e.target.value)} className={entrada}>
                            {o.prioridades.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Garantia (dias)')} erro={erros.warranty_days}>
                        <input type="number" min={0} step={1} value={valores.warranty_days}
                            onChange={(e) => mudar('warranty_days', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                    <Campo etiqueta={t('Notas')} erro={erros.notes} className="sm:col-span-2">
                        <textarea rows={2} value={valores.notes}
                            onChange={(e) => mudar('notes', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                </div>
            </div>
        </Modal>
    );
}

/* ─── A ficha ───────────────────────────────────────────────────────── */

function FichaDaOrdemModal({ id, o, abaInicial = 'info', aoFechar, aoMudar, mudarEstado, aMudarEstado }: {
    id: number;
    o: OpcoesDasOrdens;
    abaInicial?: string;
    aoFechar: () => void;
    aoMudar: (mensagem: string, falhas?: string[]) => void;
    mudarEstado: (id: number, estado: string) => void;
    aMudarEstado: boolean;
}) {
    const cache = useQueryClient();
    const [aba, porAba] = useState(abaInicial);
    const [aJuntar, porAJuntar] = useState<'service' | 'part' | null>(null);
    const [aAnexar, porAAnexar] = useState(false);

    const q = useQuery({ queryKey: ['oficina', 'ordens', 'ficha', id], queryFn: () => ordens.ficha(id) });

    const refazer = () => void cache.invalidateQueries({ queryKey: ['oficina', 'ordens'] });

    const tirarLinha = useMutation({
        mutationFn: (linha: LinhaDaOrdem) => ordens.tirarLinha(id, linha.id),
        onSuccess: (r) => { refazer(); aoMudar(r.message); },
    });

    const facturar = useMutation({
        mutationFn: () => ordens.facturar(id),
        onSuccess: (r) => { refazer(); window.location.assign(r.morada); },
    });

    const apagarAnexo = useMutation({
        mutationFn: (anexo: AnexoDaOrdem) => ordens.apagarAnexo(id, anexo.id),
        onSuccess: (r) => { refazer(); aoMudar(r.message); },
    });

    const f = q.data?.data;

    const abas = [
        { chave: 'info', rotulo: t('Informação'), icone: 'fa-circle-info' },
        // OF-01: como o carro chegou — combustível, danos no desenho, acessórios e assinatura.
        { chave: 'checkin', rotulo: t('Check-in'), icone: 'fa-clipboard-check' },
        // OF-02: a inspecção digital com semáforo.
        { chave: 'inspeccao', rotulo: t('Inspecção'), icone: 'fa-list-check' },
        { chave: 'linhas', rotulo: t('Serviços e peças'), icone: 'fa-list' },
        { chave: 'contas', rotulo: t('Contas'), icone: 'fa-calculator' },
        { chave: 'historico', rotulo: t('Histórico'), icone: 'fa-clock-rotate-left' },
        { chave: 'anexos', rotulo: t('Anexos'), icone: 'fa-paperclip' },
        { chave: 'factura', rotulo: t('Facturação'), icone: 'fa-file-invoice' },
    ];

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={f?.numero ?? t('Ordem de serviço')}
            subtitulo={f ? `${f.matricula ?? ''} · ${f.viatura ?? ''}` : undefined}
            icone="fa-clipboard-list"
            cor="roxo"
            largura="xl"
            rodape={
                <>
                    {/* A FOLHA DA ORDEM, para o cliente assinar e a oficina arquivar: o ecrã
                        de sempre tinha «Imprimir» aqui e a migração deixou-o cair. */}
                    {f && (
                        <a
                            href={`/workshop/work-orders/${id}/print`}
                            target="_blank"
                            rel="noopener"
                            className={cls('inline-flex items-center justify-center gap-2 border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-md', RAIO, FOCO)}
                        >
                            <i className="fas fa-print text-indigo-600" aria-hidden="true" />{t('Imprimir')}
                        </a>
                    )}
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>
                </>
            }
        >
            {q.isPending ? <Carregando linhas={8} /> : !f ? <Falhou erro={q.error} /> : (
                <div className="space-y-4">
                    {/* MUDAR O ESTADO A PARTIR DA FICHA — a mesma porta do
                        formulário, porque desconta ou devolve peças. */}
                    {o.permissoes.pode_editar && (
                        <div className={cls('flex flex-wrap items-center gap-2 border border-slate-200 bg-slate-50 p-3', RAIO)}>
                            <span className="text-xs font-bold uppercase tracking-wide text-slate-500">{t('Estado')}</span>
                            {o.estados.map((e) => {
                                const actual = e.valor === f.estado;

                                return (
                                    <button
                                        key={e.valor}
                                        type="button"
                                        disabled={actual || aMudarEstado}
                                        onClick={() => mudarEstado(id, e.valor)}
                                        className={cls(
                                            'border px-2.5 py-1.5 text-xs font-bold transition-all duration-200',
                                            'hover:-translate-y-0.5 active:translate-y-0 disabled:translate-y-0',
                                            RAIO, FOCO,
                                            actual
                                                ? 'cursor-default border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
                                                : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                                        )}
                                    >
                                        {actual && <i className="fas fa-check mr-1 text-[10px]" aria-hidden="true" />}
                                        {e.rotulo}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    <Separadores abas={abas} activa={aba} aoMudar={porAba} />

                    <div hidden={aba !== 'info'} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Bloco titulo={t('Viatura')} icone="fa-car">
                                <Dado rotulo={t('Matrícula')} valor={f.viatura_ficha?.matricula} />
                                <Dado rotulo={t('Viatura')} valor={f.viatura_ficha ? `${f.viatura_ficha.marca ?? ''} ${f.viatura_ficha.modelo ?? ''}`.trim() : null} />
                                <Dado rotulo={t('Ano')} valor={f.viatura_ficha?.ano ? String(f.viatura_ficha.ano) : null} />
                                <Dado rotulo={t('Cor')} valor={f.viatura_ficha?.cor} />
                                <Dado rotulo={t('Combustível')} valor={f.viatura_ficha?.combustivel} />
                                <Dado rotulo={t('Quilómetros à entrada')} valor={`${f.km.toLocaleString(etiquetaIntl())} km`} />
                            </Bloco>

                            <Bloco titulo={t('Quem e quando')} icone="fa-user-gear">
                                <Dado rotulo={t('Proprietário')} valor={f.viatura_ficha?.dono} />
                                <Dado rotulo={t('Telefone')} valor={f.viatura_ficha?.telefone} />
                                <Dado rotulo={t('Mecânico')} valor={f.mecanico ?? t('Por atribuir')} />
                                <Dado rotulo={t('Entrada')} valor={f.entrada ? data(f.entrada) : null} />
                                <Dado rotulo={t('Agendada para')} valor={f.agendada_para ? data(f.agendada_para) : null} />
                                <Dado rotulo={t('Concluída em')} valor={f.concluida_em ? data(f.concluida_em) : null} />
                                <Dado rotulo={t('Entregue em')} valor={f.entregue_em ? data(f.entregue_em) : null} />
                                <Dado rotulo={t('Garantia até')} valor={f.garantia_ate ? data(f.garantia_ate) : null} />
                            </Bloco>
                        </div>

                        <Texto titulo={t('Problema relatado')} icone="fa-circle-exclamation" tom="vermelho" corpo={f.problema} />
                        <Texto titulo={t('Diagnóstico')} icone="fa-stethoscope" tom="azul" corpo={f.diagnostico} />
                        <Texto titulo={t('Trabalho realizado')} icone="fa-screwdriver-wrench" tom="verde" corpo={f.trabalho} />
                        <Texto titulo={t('Recomendações')} icone="fa-lightbulb" tom="ambar" corpo={f.recomendacoes} />
                        <Texto titulo={t('Notas')} icone="fa-note-sticky" tom="cinza" corpo={f.notas} />
                    </div>

                    <div hidden={aba !== 'checkin'}>
                        {aba === 'checkin' && <CheckinDaViatura id={id} dono={f.viatura_ficha?.dono} />}
                    </div>

                    <div hidden={aba !== 'inspeccao'}>
                        {aba === 'inspeccao' && <InspeccaoDaOrdem id={id} />}
                    </div>

                    <div hidden={aba !== 'linhas'} className="space-y-3">
                        <AprovacaoDoCliente id={id} ficha={f} podeEditar={o.permissoes.pode_editar} aoMudar={(m) => { refazer(); aoMudar(m); }} />
                        {o.permissoes.pode_editar && (
                            <div className="flex flex-wrap gap-2">
                                <Botao cor="primaria" tom="solida" icone="fa-screwdriver-wrench" onClick={() => porAJuntar('service')}>
                                    {t('Juntar serviço')}
                                </Botao>
                                <Botao cor="primaria" icone="fa-box" onClick={() => porAJuntar('part')}>
                                    {t('Juntar peça')}
                                </Botao>
                            </div>
                        )}

                        {f.linhas.length === 0 ? (
                            <SemNada icone="fa-list" frase={t('Ainda não há serviços nem peças nesta ordem.')} />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            <th scope="col" className="px-3 py-2 text-left">{t('Tipo')}</th>
                                            <th scope="col" className="px-3 py-2 text-left">{t('Descrição')}</th>
                                            <th scope="col" className="px-3 py-2 text-right">{t('Qtd')}</th>
                                            <th scope="col" className="px-3 py-2 text-right">{t('Preço')}</th>
                                            <th scope="col" className="px-3 py-2 text-right">{t('Desc.')}</th>
                                            <th scope="col" className="px-3 py-2 text-right">{t('Subtotal')}</th>
                                            {o.permissoes.pode_editar && <th scope="col" className="px-3 py-2" />}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {f.linhas.map((l) => (
                                            <tr key={l.id} className={cls(l.aprovacao === 'declined' && 'bg-slate-50 text-slate-400', l.aprovacao === 'pending' && 'bg-amber-50/50')}>
                                                <td className="px-3 py-2">
                                                    <Etiqueta cor={l.tipo === 'service' ? 'primaria' : 'bom'}>
                                                        {l.tipo === 'service' ? t('Serviço') : t('Peça')}
                                                    </Etiqueta>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span className={cls('block font-semibold', l.aprovacao === 'declined' ? 'text-slate-400 line-through' : 'text-slate-800')}>{l.nome}</span>
                                                    <SeloDaAprovacao l={l} id={id} podeEditar={o.permissoes.pode_editar && !f.factura} aoMudar={(m) => { refazer(); aoMudar(m); }} />
                                                    {l.codigo && <span className="block font-mono text-xs text-slate-400">{l.codigo}</span>}
                                                    {l.mecanico && (
                                                        <span className="block text-xs text-slate-400">
                                                            <i className="fas fa-user-gear mr-1" aria-hidden="true" />{l.mecanico}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">{l.quantidade}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{kz(l.preco)}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{l.desconto > 0 ? `${l.desconto}%` : '—'}</td>
                                                <td className="px-3 py-2 text-right font-bold tabular-nums text-slate-900">{kz(l.subtotal)}</td>
                                                {o.permissoes.pode_editar && (
                                                    <td className="px-3 py-2 text-right">
                                                        <button type="button" onClick={() => tirarLinha.mutate(l)}
                                                            aria-label={t('Remover: :nome', { nome: l.nome })}
                                                            className={cls('p-1.5 text-slate-400 transition-all hover:scale-110 hover:text-red-600', RAIO, FOCO)}>
                                                            <i className="fas fa-trash" aria-hidden="true" />
                                                        </button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div hidden={aba !== 'contas'}>
                        <Contas ficha={f} podeEditar={o.permissoes.pode_editar}
                            aoGravar={(m) => { refazer(); aoMudar(m); }} />
                    </div>

                    <div hidden={aba !== 'historico'}>
                        {f.historico.length === 0 ? (
                            <SemNada icone="fa-clock-rotate-left" frase={t('Ainda não há nada no histórico desta ordem.')} />
                        ) : (
                            <ul className="space-y-2">
                                {f.historico.map((h, i) => (
                                    <li key={h.id} className="entra flex items-start gap-3" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <span className="mt-0.5 grid h-8 w-8 flex-none place-items-center rounded-full bg-slate-100 text-slate-500">
                                            <i className={cls('fas text-xs', ICONE_DO_EVENTO[h.accao] ?? 'fa-circle')} aria-hidden="true" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm text-slate-800">{h.descricao}</span>
                                            <span className="block text-xs text-slate-400">
                                                {h.quem ?? t('Sistema')}{h.quando ? ` · ${data(h.quando)}` : ''}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div hidden={aba !== 'anexos'} className="space-y-3">
                        {o.permissoes.pode_editar && (
                            <Botao cor="primaria" tom="solida" icone="fa-paperclip" onClick={() => porAAnexar(true)}>
                                {t('Anexar ficheiros')}
                            </Botao>
                        )}

                        {f.anexos.length === 0 ? (
                            <SemNada icone="fa-paperclip" frase={t('Ainda não há ficheiros anexados.')} />
                        ) : (
                            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {f.anexos.map((a) => (
                                    <li key={a.id} className={cls('overflow-hidden border border-slate-200', RAIO)}>
                                        {a.imagem ? (
                                            <img src={a.url} alt={a.nome} className="h-32 w-full object-cover" />
                                        ) : (
                                            <span className="grid h-32 w-full place-items-center bg-slate-50 text-3xl text-slate-300">
                                                <i className="fas fa-file" aria-hidden="true" />
                                            </span>
                                        )}
                                        <div className="p-2.5">
                                            <a href={a.url} target="_blank" rel="noreferrer"
                                                className={cls('block truncate text-sm font-semibold text-indigo-700 hover:underline', FOCO, RAIO)}>
                                                {a.nome}
                                            </a>
                                            <span className="mt-1 flex items-center justify-between gap-2">
                                                <Etiqueta>{a.categoria_rotulo}</Etiqueta>
                                                <span className="text-xs text-slate-400">{a.tamanho}</span>
                                            </span>
                                            {o.permissoes.pode_editar && (
                                                <button type="button" onClick={() => apagarAnexo.mutate(a)}
                                                    className={cls('mt-2 text-xs font-semibold text-red-600 hover:underline', FOCO, RAIO)}>
                                                    <i className="fas fa-trash mr-1" aria-hidden="true" />{t('Remover')}
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div hidden={aba !== 'factura'} className="space-y-3">
                        <AvisoDeErro erro={facturar.error} />

                        {f.factura ? (
                            <div className={cls('border border-emerald-200 bg-emerald-50 p-4', RAIO)}>
                                <p className="font-bold text-emerald-900">
                                    <i className="fas fa-file-invoice mr-2" aria-hidden="true" />
                                    {t('Facturada: :numero', { numero: f.factura.numero })}
                                </p>
                                <p className="mt-1 text-sm text-emerald-800">
                                    {f.factura.cliente}{f.factura.quando ? ` · ${data(f.factura.quando)}` : ''}
                                </p>
                                <a href={f.factura.morada} className={cls('mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline', FOCO, RAIO)}>
                                    {t('Abrir a factura')}
                                    <i className="fas fa-arrow-right text-xs" aria-hidden="true" />
                                </a>
                            </div>
                        ) : (
                            <>
                                <p className="text-sm text-slate-600">
                                    {t('A ordem passa a factura de venda pela mesma porta fiscal da facturação: imposto por linha, isenções, retenção de IRT e assinatura.')}
                                </p>
                                {o.permissoes.pode_facturar ? (
                                    <Botao cor="bom" tom="solida" icone="fa-file-invoice" aTrabalhar={facturar.isPending}
                                        onClick={() => facturar.mutate()}>
                                        {t('Emitir factura')}
                                    </Botao>
                                ) : (
                                    <p className="text-sm text-slate-400">
                                        <i className="fas fa-lock mr-1.5" aria-hidden="true" />
                                        {t('Emitir facturas é uma permissão da facturação.')}
                                    </p>
                                )}
                            </>
                        )}
                    </div>
                </div>
            )}

            {aJuntar && (
                <JuntarLinha
                    id={id}
                    tipo={aJuntar}
                    o={o}
                    aoFechar={() => porAJuntar(null)}
                    aoGravar={(m) => { porAJuntar(null); refazer(); aoMudar(m); }}
                />
            )}

            {aAnexar && (
                <Anexar
                    id={id}
                    o={o}
                    aoFechar={() => porAAnexar(false)}
                    aoGravar={(m) => { porAAnexar(false); refazer(); aoMudar(m); }}
                />
            )}
        </Modal>
    );
}

/* ─── As contas ─────────────────────────────────────────────────────── */

function Contas({ ficha, podeEditar, aoGravar }: {
    ficha: FichaDaOrdem;
    podeEditar: boolean;
    aoGravar: (mensagem: string) => void;
}) {
    const [desconto, porDesconto] = useState(String(ficha.desconto));

    const gravar = useMutation({
        mutationFn: () => ordens.desconto(ficha.id, Number(desconto) || 0),
        onSuccess: (r) => aoGravar(r.message),
    });

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <CartaoNumero aspecto="claro" rotulo={t('Mão-de-obra')} tom="azul" icone="fa-screwdriver-wrench"
                    valor={`${kz(ficha.mao_de_obra)} Kz`} />
                <CartaoNumero aspecto="claro" rotulo={t('Peças')} tom="verde" icone="fa-box"
                    valor={`${kz(ficha.pecas)} Kz`} />
            </div>

            <div className={cls('space-y-2 border border-slate-200 p-4', RAIO)}>
                <Linha rotulo={t('Mão-de-obra')} valor={ficha.mao_de_obra} />
                <Linha rotulo={t('Peças')} valor={ficha.pecas} />
                {/* Sem desconto não se escreve «-0,00»: um menos à frente de
                    zero lê-se como se alguma coisa tivesse sido tirada. */}
                <Linha rotulo={t('Desconto')} valor={ficha.desconto > 0 ? -ficha.desconto : 0} tom="text-rose-700" />
                <Linha rotulo={t('Imposto')} valor={ficha.imposto} />
                <div className="border-t border-slate-200 pt-2">
                    <Linha rotulo={t('Total')} valor={ficha.total} forte />
                </div>
                <Linha rotulo={t('Pago')} valor={ficha.pago} tom="text-emerald-700" />
                <Linha rotulo={t('Por receber')} valor={ficha.saldo} tom={ficha.saldo > 0 ? 'text-amber-700' : undefined} forte />
            </div>

            {podeEditar && (
                <div className="flex flex-wrap items-end gap-3">
                    <Campo etiqueta={t('Desconto comercial (Kz)')}
                        ajuda={t('Aplica-se ao total da ordem, depois das linhas.')}>
                        <input type="number" min={0} step="0.01" value={desconto}
                            onChange={(e) => porDesconto(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={gravar.isPending}
                        onClick={() => gravar.mutate()}>
                        {t('Guardar desconto')}
                    </Botao>
                </div>
            )}

            <AvisoDeErro erro={gravar.error} />
        </div>
    );
}

/* ─── Juntar uma linha ──────────────────────────────────────────────── */

/**
 * JUNTAR UM SERVIÇO OU UMA PEÇA.
 *
 * A peça PROCURA-SE em vez de vir numa lista fechada: o catálogo desta casa
 * tem doze mil artigos, e o ecrã em Livewire mandava-os todos para o browser
 * para encher um `<select>`.
 *
 * E O STOCK É O DO ARMAZÉM DE ONDE A PEÇA VAI SAIR. Somando todos, uma peça
 * com existência só noutro armazém aparecia disponível e a baixa rebentava
 * depois — já com a ordem dada por concluída.
 */
function JuntarLinha({ id, tipo, o, aoFechar, aoGravar }: {
    id: number;
    tipo: 'service' | 'part';
    o: OpcoesDasOrdens;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [valores, porValores] = useState<LinhaParaGravar>(() => linhaVazia(tipo));
    const [procura, porProcura] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const artigos = useQuery({
        queryKey: ['oficina', 'ordens', 'artigos', procura],
        queryFn: () => ordens.artigos(procura),
        enabled: tipo === 'part',
        placeholderData: keepPreviousData,
    });

    const gravar = useMutation({
        mutationFn: () => ordens.juntarLinha(id, valores),
        onSuccess: (r) => aoGravar(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const mudar = (campo: keyof LinhaParaGravar, valor: string | boolean) =>
        porValores((v) => ({ ...v, [campo]: valor }));

    /* Escolher do catálogo preenche o resto — e o preço vem de lá. */
    const escolherServico = (idServico: string) => {
        const s = o.servicos.find((x) => x.valor === idServico);

        porValores((v) => ({
            ...v,
            service_id: idServico,
            code: s?.codigo ?? '',
            name: s?.rotulo ?? v.name,
            description: s?.descricao ?? '',
            unit_price: s ? String(s.preco) : v.unit_price,
            hours: s ? String(s.horas) : v.hours,
        }));
    };

    const escolherArtigo = (idArtigo: string) => {
        const a = (artigos.data?.data ?? []).find((x) => x.valor === idArtigo);

        porValores((v) => ({
            ...v,
            product_id: idArtigo,
            code: a?.codigo ?? '',
            name: a?.rotulo ?? v.name,
            description: a?.descricao ?? '',
            unit_price: a ? String(a.preco) : v.unit_price,
        }));
    };

    const escolhido = (artigos.data?.data ?? []).find((x) => x.valor === valores.product_id);
    const pouco = escolhido !== undefined && escolhido.stock < Number(valores.quantity || 0);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={tipo === 'service' ? t('Juntar serviço') : t('Juntar peça')}
            icone={tipo === 'service' ? 'fa-screwdriver-wrench' : 'fa-box'}
            cor="roxo"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-plus" aTrabalhar={gravar.isPending}
                        onClick={() => gravar.mutate()}>
                        {t('Juntar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-3 sm:grid-cols-2">
                {tipo === 'service' ? (
                    <Campo etiqueta={t('Do catálogo')} className="sm:col-span-2"
                        ajuda={t('Escolher traz o preço e as horas previstas. Também se pode escrever à mão.')}>
                        <select value={valores.service_id} onChange={(e) => escolherServico(e.target.value)} className={entrada}>
                            <option value="">{t('Escrever à mão')}</option>
                            {o.servicos.map((s) => (
                                <option key={s.valor} value={s.valor}>{s.rotulo} — {kz(s.preco)} Kz</option>
                            ))}
                        </select>
                    </Campo>
                ) : (
                    <>
                        <Campo etiqueta={t('Procurar peça')} className="sm:col-span-2">
                            <input type="search" value={procura} onChange={(e) => porProcura(e.target.value)}
                                placeholder={t('Nome, código ou código de barras')} className={entrada} />
                        </Campo>
                        <Campo etiqueta={t('Do catálogo')} className="sm:col-span-2"
                            ajuda={artigos.data?.armazem
                                ? t('Existência no armazém :nome — é de lá que a peça sai.', { nome: artigos.data.armazem })
                                : t('Escolher traz o preço do artigo.')}>
                            <select value={valores.product_id} onChange={(e) => escolherArtigo(e.target.value)} className={entrada}>
                                <option value="">{t('Escrever à mão')}</option>
                                {(artigos.data?.data ?? []).map((a) => (
                                    <option key={a.valor} value={a.valor}>
                                        {a.rotulo} — {kz(a.preco)} Kz · {t('stock: :n', { n: a.stock })}
                                    </option>
                                ))}
                            </select>
                        </Campo>
                    </>
                )}

                {pouco && (
                    <p className={cls('sm:col-span-2 border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                        {t('Só há :stock em armazém e esta linha pede :pedido. A baixa vai falhar ao concluir a ordem.', {
                            stock: escolhido?.stock ?? 0, pedido: valores.quantity,
                        })}
                    </p>
                )}

                <Campo etiqueta={t('Código')} erro={erros.code}>
                    <input value={valores.code} onChange={(e) => mudar('code', e.target.value)} className={cls(entrada, 'font-mono')} />
                </Campo>
                <Campo etiqueta={t('Descrição')} obrigatorio erro={erros.name}>
                    <input value={valores.name} onChange={(e) => mudar('name', e.target.value)} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Quantidade')} obrigatorio erro={erros.quantity}>
                    <input type="number" min={0.01} step="0.01" value={valores.quantity}
                        onChange={(e) => mudar('quantity', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Preço unitário (Kz)')} obrigatorio erro={erros.unit_price}>
                    <input type="number" min={0} step="0.01" value={valores.unit_price}
                        onChange={(e) => mudar('unit_price', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Desconto (%)')} erro={erros.discount_percent}>
                    <input type="number" min={0} max={100} step="0.01" value={valores.discount_percent}
                        onChange={(e) => mudar('discount_percent', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Mecânico')} erro={erros.mechanic_id}>
                    <select value={valores.mechanic_id} onChange={(e) => mudar('mechanic_id', e.target.value)} className={entrada}>
                        <option value="">—</option>
                        {o.mecanicos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                    </select>
                </Campo>

                {tipo === 'service' ? (
                    <Campo etiqueta={t('Horas')} erro={erros.hours}>
                        <input type="number" min={0} step="0.5" value={valores.hours}
                            onChange={(e) => mudar('hours', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                ) : (
                    <>
                        <Campo etiqueta={t('Referência da peça')} erro={erros.part_number}>
                            <input value={valores.part_number} onChange={(e) => mudar('part_number', e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta={t('Marca')} erro={erros.brand}>
                            <input value={valores.brand} onChange={(e) => mudar('brand', e.target.value)} className={entrada} />
                        </Campo>
                        <label className={cls('flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50', RAIO)}>
                            <input type="checkbox" checked={valores.is_original}
                                onChange={(e) => mudar('is_original', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                            {t('Peça original')}
                        </label>
                    </>
                )}

                {/* OF-03: propor a linha ao cliente em vez de a dar por aprovada. */}
                <label className={cls('flex cursor-pointer items-start gap-3 border px-3 py-2.5 text-sm sm:col-span-2', RAIO, TRANSICAO,
                    valores.precisa_aprovacao ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 text-slate-700 hover:bg-slate-50')}>
                    <input type="checkbox" checked={valores.precisa_aprovacao} onChange={(e) => mudar('precisa_aprovacao', e.target.checked)}
                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-600" />
                    <span>
                        <span className="block font-semibold"><i className="fas fa-hand mr-1.5" aria-hidden="true" />{t('Precisa da aprovação do cliente')}</span>
                        <span className="block text-xs opacity-80">{t('Fica à espera: não conta para o total nem gasta peças até o cliente aprovar.')}</span>
                    </span>
                </label>

                <Campo etiqueta={t('Notas da linha')} className="sm:col-span-2" erro={erros.description}>
                    <textarea rows={2} value={valores.description}
                        onChange={(e) => mudar('description', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── Anexar ────────────────────────────────────────────────────────── */

function Anexar({ id, o, aoFechar, aoGravar }: {
    id: number;
    o: OpcoesDasOrdens;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [ficheiros, porFicheiros] = useState<File[]>([]);
    const [categoria, porCategoria] = useState('other');
    const [descricao, porDescricao] = useState('');

    const gravar = useMutation({
        mutationFn: () => ordens.anexar(id, ficheiros, categoria, descricao),
        onSuccess: (r) => aoGravar(r.message),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Anexar ficheiros')}
            icone="fa-paperclip"
            cor="roxo"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-upload" aTrabalhar={gravar.isPending}
                        disabled={ficheiros.length === 0} onClick={() => gravar.mutate()}>
                        {t('Anexar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-3">
                <Campo etiqueta={t('Categoria')}
                    ajuda={t('As fotos antes e depois são a prova do trabalho — e a defesa numa reclamação.')}>
                    <select value={categoria} onChange={(e) => porCategoria(e.target.value)} className={entrada}>
                        {o.categorias_de_anexo.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Ficheiros')} ajuda={t('Até 10 ficheiros, 10 MB cada.')}>
                    <input type="file" multiple
                        onChange={(e) => porFicheiros(Array.from(e.target.files ?? []))}
                        className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700" />
                </Campo>

                <Campo etiqueta={t('Descrição')}>
                    <textarea rows={2} value={descricao} onChange={(e) => porDescricao(e.target.value)}
                        className={cls(entrada, 'h-auto py-2')} />
                </Campo>

                {ficheiros.length > 0 && (
                    <p className="text-xs text-slate-500">
                        <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                        {t(':n ficheiro(s) escolhido(s)', { n: ficheiros.length })}
                    </p>
                )}
            </div>
        </Modal>
    );
}

/* ─── As peças pequenas ─────────────────────────────────────────────── */

function Bloco({ titulo, icone, children }: { titulo: string; icone: string; children: React.ReactNode }) {
    return (
        <section className={cls('border border-slate-200 p-4', RAIO)}>
            <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                <i className={cls('fas text-slate-400', icone)} aria-hidden="true" />
                {titulo}
            </h3>
            <dl className="space-y-1.5">{children}</dl>
        </section>
    );
}

function Dado({ rotulo, valor }: { rotulo: string; valor?: string | null }) {
    if (!valor) return null;

    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="font-semibold text-slate-800">{valor}</dd>
        </div>
    );
}

const TOM_DO_TEXTO: Record<string, string> = {
    vermelho: 'border-red-200 bg-red-50 text-red-900',
    azul: 'border-sky-200 bg-sky-50 text-sky-900',
    verde: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    ambar: 'border-amber-200 bg-amber-50 text-amber-900',
    cinza: 'border-slate-200 bg-slate-50 text-slate-800',
};

function Texto({ titulo, icone, tom, corpo }: { titulo: string; icone: string; tom: string; corpo?: string | null }) {
    if (!corpo) return null;

    return (
        <section className={cls('border p-3', RAIO, TOM_DO_TEXTO[tom] ?? TOM_DO_TEXTO.cinza)}>
            <h3 className="mb-1 text-sm font-bold">
                <i className={cls('fas mr-2', icone)} aria-hidden="true" />
                {titulo}
            </h3>
            <p className="whitespace-pre-line text-sm">{corpo}</p>
        </section>
    );
}

function Linha({ rotulo, valor, tom, forte }: { rotulo: string; valor: number; tom?: string; forte?: boolean }) {
    return (
        <div className="flex items-baseline justify-between">
            <span className="text-sm text-slate-500">{rotulo}</span>
            <span className={cls('tabular-nums', forte ? 'text-lg font-bold' : 'font-semibold', tom ?? 'text-slate-800')}>
                {kz(valor)} <span className="text-xs font-normal text-slate-400">Kz</span>
            </span>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as ordens de serviço')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
