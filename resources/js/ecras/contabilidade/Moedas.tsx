import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { Cambio, Moeda } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { IntervaloDeDatas } from '@/ui/FiltrosComuns';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * AS MOEDAS E OS CÂMBIOS.
 *
 * A LISTA DE MOEDAS É DA PLATAFORMA e não da empresa — as tabelas não têm
 * coluna de empresa, e nunca tiveram. Uma moeda é um código ISO: o dólar é o
 * dólar em todas as companhias. Mas isso tem uma consequência que o ecrã antigo
 * não dizia em lado nenhum: mudar aqui o nome ou o símbolo muda-o PARA TODAS AS
 * EMPRESAS da plataforma. O aviso está no ecrã, e escrever pede a permissão de
 * gerir.
 *
 * O QUE ESTAVA PARTIDO: não havia como apagar nada; `is_active` e as casas
 * decimais só se escreviam ao criar (uma moeda não se podia desactivar nem
 * corrigir); e um câmbio aceitava taxa ZERO, que faz toda a conversão dar zero.
 */
export default function Moedas() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ procura?: string; so_activas?: boolean; moeda?: number | ''; de?: string; ate?: string }>({});
    const [recado, porRecado] = useState('');
    const [modal, porModal] = useState<{ aberto: boolean; moeda: Moeda | null }>({ aberto: false, moeda: null });
    const [modalDoCambio, porModalDoCambio] = useState(false);
    const [aApagar, porAApagar] = useState<Moeda | null>(null);
    const [cambioAApagar, porCambioAApagar] = useState<Cambio | null>(null);

    const lista = useQuery({
        queryKey: ['contabilidade', 'moedas', filtros],
        queryFn: () => contabilidade.moedas.listar(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade', 'moedas'] });
    }

    const apagar = useMutation({
        mutationFn: (m: Moeda) => contabilidade.moedas.apagar(m.id),
        onSuccess: (r) => {
            porAApagar(null);
            feito(r.message);
        },
    });

    const apagarCambio = useMutation({
        mutationFn: (c: Cambio) => contabilidade.moedas.apagarCambio(c.id),
        onSuccess: (r) => {
            porCambioAApagar(null);
            feito(r.message);
        },
    });

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as moedas')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const d = lista.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Moedas e Câmbios')}
                subtitulo={t('As moedas que o sistema conhece e a taxa de cada dia')}
                icone="fa-money-bill-transfer"
                cor="teal"
                accoes={
                    d.permissoes.gerir && (
                        <>
                            <button type="button" onClick={() => porModalDoCambio(true)} className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-arrow-right-arrow-left" aria-hidden="true" />
                                {t('Novo Câmbio')}
                            </button>
                            <button type="button" onClick={() => porModal({ aberto: true, moeda: null })} className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova Moeda')}
                            </button>
                        </>
                    )
                }
            />

            {/* A LISTA É PARTILHADA, e diz-se. */}
            <div className={cls('border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)} role="status">
                <i className="fas fa-globe mr-2" aria-hidden="true" />
                {d.permissoes.gerir
                    ? t('Esta lista é da plataforma, não desta empresa: mudar uma moeda ou um câmbio muda-o para todas as companhias.')
                    : t('Esta lista é da plataforma, não desta empresa. Só quem gere moedas a pode mudar — e a mudança vale para todas as companhias.')}
            </div>

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <ErroDaAccao erro={apagar.error ?? apagarCambio.error} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero rotulo={t('Moedas')} valor={d.resumo.moedas.toLocaleString('pt-PT')} icone="fa-coins" tom="teal" aspecto="claro" />
                <CartaoNumero rotulo={t('Activas')} valor={d.resumo.activas.toLocaleString('pt-PT')} icone="fa-circle-check" tom="verde" aspecto="claro" />
                <CartaoNumero rotulo={t('Câmbios registados')} valor={d.resumo.cambios.toLocaleString('pt-PT')} icone="fa-arrow-right-arrow-left" tom="azul" aspecto="claro" />
                <CartaoNumero
                    rotulo={t('Último câmbio')}
                    valor={d.resumo.ultimo_cambio ?? '—'}
                    icone="fa-calendar-day"
                    tom={d.resumo.ultimo_cambio ? 'indigo' : 'ambar'}
                    aspecto="claro"
                    nota={d.resumo.ultimo_cambio ? undefined : t('Nenhum ainda')}
                />
            </div>

            {/* ─── As moedas ─────────────────────────────────────────── */}
            <Cartao
                titulo={t('Moedas')}
                icone="fa-coins"
                subtitulo={t('O código ISO, o símbolo e as casas decimais')}
                semPadding
                accoes={
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="flex items-center gap-2 text-xs text-slate-500">
                            <input
                                type="checkbox"
                                checked={Boolean(filtros.so_activas)}
                                onChange={(e) => porFiltros((f) => ({ ...f, so_activas: e.target.checked }))}
                                className="h-4 w-4 rounded border-slate-300 text-teal-600 focus-visible:ring-2 focus-visible:ring-teal-500"
                            />
                            {t('Só as activas')}
                        </label>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value }))}
                            placeholder={t('Código ou nome…')}
                            aria-label={t('Procurar moeda')}
                            className={cls(entrada, 'h-9 w-48')}
                        />
                    </div>
                }
            >
                {d.moedas.length === 0 ? (
                    <SemNada icone="fa-coins" titulo={t('Nenhuma moeda encontrada')} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-sm">
                            <thead className="bg-gradient-to-r from-teal-50 to-cyan-50">
                                <tr>
                                    {[t('Código'), t('Nome'), t('Símbolo')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-teal-700">{c}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-teal-700">{t('Casas decimais')}</th>
                                    <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-teal-700">{t('Câmbios')}</th>
                                    <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-teal-700">{t('Estado')}</th>
                                    <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-teal-700">{t('Ações')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.moedas.map((m, i) => (
                                    <tr key={m.id} style={cascata(i)} className="entra transition-colors hover:bg-teal-50/60">
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-700">{m.codigo}</span>
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-slate-900">{m.nome}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">{m.simbolo}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center tabular-nums text-slate-600">{m.casas}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center tabular-nums text-slate-600">{m.cambios}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center">
                                            <Etiqueta cor={m.activa ? 'bom' : 'neutra'} ponto>
                                                {m.activa ? t('Activa') : t('Inactiva')}
                                            </Etiqueta>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            {d.permissoes.gerir ? (
                                                <div className="flex justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => porModal({ aberto: true, moeda: m })}
                                                        title={t('Editar')}
                                                        aria-label={t('Editar :moeda', { moeda: m.codigo })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-blue-200 bg-blue-50 text-blue-700')}
                                                    >
                                                        <i className="fas fa-edit" aria-hidden="true" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => porAApagar(m)}
                                                        title={t('Eliminar')}
                                                        aria-label={t('Eliminar :moeda', { moeda: m.codigo })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                    >
                                                        <i className="fas fa-trash" aria-hidden="true" />
                                                    </button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Cartao>

            {/* ─── Os câmbios ────────────────────────────────────────── */}
            <Cartao
                titulo={t('Câmbios')}
                icone="fa-arrow-right-arrow-left"
                subtitulo={t('Uma taxa por par e por dia — gravar outra vez corrige, não duplica')}
                semPadding
            >
                <div className="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('Moeda')}>
                        <select
                            value={filtros.moeda ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, moeda: e.target.value ? Number(e.target.value) : '' }))}
                            className={entrada}
                        >
                            <option value="">{t('Todas')}</option>
                            {d.moedas.map((m) => <option key={m.id} value={m.id}>{m.codigo} · {m.nome}</option>)}
                        </select>
                    </Campo>

                    <IntervaloDeDatas
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(campo, valor) => porFiltros((f) => ({ ...f, [campo]: valor }))}
                        rotulo={t('Data de')}
                    />
                </div>

                {d.taxas.length === 0 ? (
                    <SemNada
                        icone="fa-arrow-right-arrow-left"
                        titulo={t('Nenhum câmbio registado')}
                        frase={t('Sem uma taxa, nenhuma conversão de moeda sabe por quanto multiplicar.')}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-sm">
                            <thead className="bg-slate-50/60">
                                <tr>
                                    {[t('Data'), t('De'), t('Para')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t('Taxa')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t('Origem')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t('Ações')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.taxas.map((c, i) => (
                                    <tr key={c.id} style={cascata(i)} className="entra transition-colors hover:bg-slate-50">
                                        <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">{c.dia}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 font-mono text-xs font-bold text-slate-700">{c.de ?? '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 font-mono text-xs font-bold text-slate-700">{c.para ?? '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-right font-bold tabular-nums text-slate-900">
                                            {c.taxa.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 6 })}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-slate-500">
                                            {c.origem === 'manual' ? t('À mão') : (c.origem ?? '—')}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-right">
                                            {d.permissoes.gerir ? (
                                                <button
                                                    type="button"
                                                    onClick={() => porCambioAApagar(c)}
                                                    title={t('Eliminar')}
                                                    aria-label={t('Eliminar o câmbio de :dia', { dia: c.dia ?? '' })}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                >
                                                    <i className="fas fa-trash" aria-hidden="true" />
                                                </button>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Cartao>

            <ModalDaMoeda
                aberto={modal.aberto}
                moeda={modal.moeda}
                aoFechar={() => porModal({ aberto: false, moeda: null })}
                aoGravar={(mensagem) => {
                    porModal({ aberto: false, moeda: null });
                    feito(mensagem);
                }}
            />

            <ModalDoCambio
                aberto={modalDoCambio}
                moedas={d.moedas}
                aoFechar={() => porModalDoCambio(false)}
                aoGravar={(mensagem) => {
                    porModalDoCambio(false);
                    feito(mensagem);
                }}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar moeda')}
                subtitulo={aApagar ? `${aApagar.codigo} · ${aApagar.nome}` : undefined}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />

                <p className="text-sm text-slate-700">
                    {t('A moeda sai da lista de TODAS as empresas da plataforma.')}
                </p>

                {(aApagar?.cambios ?? 0) > 0 && (
                    <div className={cls('mt-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('Esta moeda tem :n câmbio(s) registado(s) — o servidor vai recusar. Desactive-a em vez de a apagar.', {
                            n: aApagar?.cambios ?? 0,
                        })}
                    </div>
                )}
            </Modal>

            <Modal
                aberto={cambioAApagar !== null}
                aoFechar={() => porCambioAApagar(null)}
                titulo={t('Eliminar câmbio')}
                subtitulo={cambioAApagar ? `${cambioAApagar.de} → ${cambioAApagar.para} · ${cambioAApagar.dia}` : undefined}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porCambioAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagarCambio.isPending}
                            onClick={() => cambioAApagar && apagarCambio.mutate(cambioAApagar)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagarCambio.error} />

                <p className="text-sm text-slate-700">
                    {t('A taxa daquele dia desaparece. As conversões já feitas não mudam — mas uma nova, naquela data, deixa de ter por onde multiplicar.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── A janela da moeda ───────────────────────────────────────────────── */

function ModalDaMoeda({
    aberto,
    moeda,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    moeda: Moeda | null;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState({ code: '', name: '', symbol: '', decimal_places: 2, is_active: true });

    useEffect(() => {
        if (!aberto) return;

        porF(moeda
            ? {
                code: moeda.codigo, name: moeda.nome, symbol: moeda.simbolo,
                decimal_places: moeda.casas, is_active: moeda.activa,
            }
            : { code: '', name: '', symbol: '', decimal_places: 2, is_active: true });
    }, [aberto, moeda]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.moedas.guardar(moeda?.id ?? null, f),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={moeda ? t('Editar Moeda') : t('Nova Moeda')}
            subtitulo={t('Vale para todas as empresas da plataforma')}
            icone="fa-coins"
            cor="teal"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {moeda ? t('Atualizar') : t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo
                    etiqueta={t('Código')}
                    obrigatorio
                    erro={erros.code}
                    ajuda={t('Três letras do código ISO: AOA, USD, EUR.')}
                >
                    <input
                        type="text"
                        value={f.code}
                        maxLength={3}
                        onChange={(e) => porF((x) => ({ ...x, code: e.target.value.toUpperCase() }))}
                        className={cls(entrada, 'font-mono uppercase')}
                    />
                </Campo>

                <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                    <input
                        type="text"
                        value={f.name}
                        onChange={(e) => porF((x) => ({ ...x, name: e.target.value }))}
                        placeholder={t('Ex.: Kwanza')}
                        className={entrada}
                    />
                </Campo>

                <Campo etiqueta={t('Símbolo')} obrigatorio erro={erros.symbol}>
                    <input
                        type="text"
                        value={f.symbol}
                        onChange={(e) => porF((x) => ({ ...x, symbol: e.target.value }))}
                        placeholder={t('Ex.: Kz')}
                        className={entrada}
                    />
                </Campo>

                <Campo
                    etiqueta={t('Casas decimais')}
                    erro={erros.decimal_places}
                    ajuda={t('Quantas casas se mostram nos valores desta moeda.')}
                >
                    <input
                        type="number"
                        min={0}
                        max={6}
                        value={f.decimal_places}
                        onChange={(e) => porF((x) => ({ ...x, decimal_places: Number(e.target.value) }))}
                        className={cls(entrada, 'tabular-nums')}
                    />
                </Campo>

                <label className={cls('flex cursor-pointer items-start gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 sm:col-span-2', RAIO)}>
                    <input
                        type="checkbox"
                        checked={f.is_active}
                        onChange={(e) => porF((x) => ({ ...x, is_active: e.target.checked }))}
                        className="mt-0.5 h-5 w-5 flex-none rounded border-slate-300 text-emerald-600 focus-visible:ring-2 focus-visible:ring-emerald-500"
                    />
                    <span>
                        <span className="block text-sm font-bold text-slate-900">
                            <i className="fas fa-circle-check mr-1.5" aria-hidden="true" />
                            {t('Activa')}
                        </span>
                        <span className="block text-xs text-slate-600">
                            {t('Uma moeda inactiva deixa de aparecer onde se escolhe moeda, e o histórico continua legível.')}
                        </span>
                    </span>
                </label>
            </div>
        </Modal>
    );
}

/* ─── A janela do câmbio ──────────────────────────────────────────────── */

/**
 * O PAR MAIS A DATA SÃO A CHAVE: gravar outra vez o mesmo par no mesmo dia
 * corrige a taxa em vez de somar uma segunda linha. Duas taxas do mesmo dia
 * fariam a conversão depender da ordem da consulta.
 */
function ModalDoCambio({
    aberto,
    moedas,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    moedas: Moeda[];
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const hoje = new Date().toISOString().slice(0, 10);

    const [f, porF] = useState({ currency_from_id: '', currency_to_id: '', date: hoje, rate: '' });

    useEffect(() => {
        if (aberto) porF({ currency_from_id: '', currency_to_id: '', date: hoje, rate: '' });
    }, [aberto, hoje]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.moedas.guardarCambio({
            currency_from_id: f.currency_from_id ? Number(f.currency_from_id) : null,
            currency_to_id: f.currency_to_id ? Number(f.currency_to_id) : null,
            date: f.date,
            rate: f.rate ? Number(f.rate) : null,
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    const de = moedas.find((m) => String(m.id) === f.currency_from_id);
    const para = moedas.find((m) => String(m.id) === f.currency_to_id);
    const taxa = Number(f.rate) || 0;

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Novo Câmbio')}
            subtitulo={t('Uma taxa por par e por dia')}
            icone="fa-arrow-right-arrow-left"
            cor="teal"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('De')} obrigatorio erro={erros.currency_from_id}>
                    <select value={f.currency_from_id} onChange={(e) => porF((x) => ({ ...x, currency_from_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Escolha a moeda…')}</option>
                        {moedas.map((m) => <option key={m.id} value={m.id}>{m.codigo} · {m.nome}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Para')} obrigatorio erro={erros.currency_to_id}>
                    <select value={f.currency_to_id} onChange={(e) => porF((x) => ({ ...x, currency_to_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Escolha a moeda…')}</option>
                        {moedas.filter((m) => String(m.id) !== f.currency_from_id)
                            .map((m) => <option key={m.id} value={m.id}>{m.codigo} · {m.nome}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Data')} obrigatorio erro={erros.date}>
                    <input
                        type="date"
                        value={f.date}
                        onChange={(e) => porF((x) => ({ ...x, date: e.target.value }))}
                        className={entrada}
                    />
                </Campo>

                <Campo
                    etiqueta={t('Taxa')}
                    obrigatorio
                    erro={erros.rate}
                    ajuda={t('Quanto vale uma unidade da moeda de origem na de destino.')}
                >
                    <input
                        type="number"
                        step="0.000001"
                        min="0"
                        value={f.rate}
                        onChange={(e) => porF((x) => ({ ...x, rate: e.target.value }))}
                        className={cls(entrada, 'text-right tabular-nums')}
                    />
                </Campo>
            </div>

            {/* O QUE A TAXA QUER DIZER, em palavras: um número solto engana-se
                facilmente ao contrário. */}
            {de && para && taxa > 0 && (
                <p className={cls('mt-4 border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900', RAIO)} role="status">
                    <i className="fas fa-equals mr-2" aria-hidden="true" />
                    {t('1 :de vale :taxa :para', {
                        de: de.codigo,
                        taxa: taxa.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 6 }),
                        para: para.codigo,
                    })}
                </p>
            )}

            <p className={cls('mt-3 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                <i className="fas fa-circle-info mr-2 text-blue-600" aria-hidden="true" />
                {t('Se já existir uma taxa para este par nesta data, ela é CORRIGIDA — não se cria uma segunda.')}
            </p>
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-semibold',
    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
    RAIO,
    FOCO,
);

function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const geral = daApi?.erros.geral?.[0];

    return (
        <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
            {geral ?? daApi?.message ?? t('A operação não foi concluída.')}
        </div>
    );
}
