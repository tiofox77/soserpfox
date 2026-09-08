import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { Bolso, FormularioDaTransferencia, OpcoesDasTransferencias, Transferencia } from '@/api/tesouraria';
import { transferencias } from '@/api/tesouraria';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * MOVER DINHEIRO ENTRE CONTAS E CAIXAS.
 *
 * Uma transferência são três lançamentos que acontecem juntos ou nenhum: a
 * saída na origem, a entrada no destino e, quando a há, a taxa — também na
 * origem. Quem escreve é o servidor, pelo mesmo serviço dos movimentos.
 *
 * O SALDO DE CADA BOLSO ESTÁ À VISTA na escolha da origem, como no ecrã de
 * sempre: mandar dinheiro de onde não o há é o erro que se comete quando o
 * número não está lá.
 *
 * O AVISO DE DESCOBERTO é novo. O servidor não impede — há contas que podem
 * ficar a descoberto, e não é este ecrã que decide isso — mas dizer «isto
 * deixa a conta em −12.000» antes de carregar é diferente de o descobrir
 * depois.
 */
export default function Transferencias() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ procura?: string; por_pagina: number; page: number }>({
        por_pagina: 15,
        page: 1,
    });
    const [recado, porRecado] = useState('');
    const [aRegistar, porARegistar] = useState(false);
    const [aAnular, porAAnular] = useState<Transferencia | null>(null);

    const opcoes = useQuery({
        queryKey: ['tesouraria', 'transferencias', 'opcoes'],
        queryFn: transferencias.opcoes,
        staleTime: 60_000,
    });

    const lista = useQuery({
        queryKey: ['tesouraria', 'transferencias', filtros],
        queryFn: () => transferencias.lista(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['tesouraria'] });
    }

    const anular = useMutation({
        mutationFn: (x: Transferencia) => transferencias.anular(x.id),
        onSuccess: (r) => {
            porAAnular(null);
            feito(r.message);
        },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as transferências')}</h2>
                <p className="text-sm text-red-800">
                    {opcoes.error instanceof ErroDaApi ? opcoes.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Transferências entre Contas')}
                subtitulo={t('Mover fundos entre contas bancárias e caixas')}
                icone="fa-right-left"
                cor="ciano"
                accoes={
                    o.permissoes.pode_criar && (
                        <button type="button" onClick={() => porARegistar(true)} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Nova Transferência')}
                        </button>
                    )
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <AvisoDeErro erro={anular.error} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <CartaoNumero rotulo={t('Transferências')} valor={resumo.transferencias.toLocaleString('pt-PT')} icone="fa-right-left" tom="teal" aspecto="claro" />
                    <CartaoNumero rotulo={t('Valor Total Movido')} valor={kz(resumo.movido)} sufixo="Kz" icone="fa-money-bill-transfer" tom="azul" aspecto="claro" />
                    {/* AS TAXAS SÃO DINHEIRO QUE SAI E NÃO CHEGA A LADO NENHUM.
                        O ecrã de sempre somava só o movido e deixava-as fora
                        de vista, uma a uma. */}
                    <CartaoNumero
                        rotulo={t('Pago em taxas')}
                        valor={kz(resumo.taxas)}
                        sufixo="Kz"
                        icone="fa-receipt"
                        tom={resumo.taxas > 0 ? 'ambar' : 'cinza'}
                        aspecto="claro"
                    />
                </div>
            )}

            <div className={cls(CARTAO, 'overflow-hidden')}>
                <div className="border-b border-slate-100 p-4">
                    <label className="block max-w-md">
                        <span className="sr-only">{t('Pesquisar')}</span>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={t('Pesquisar por nº, descrição ou referência…')}
                            className={entrada}
                        />
                    </label>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-gradient-to-r from-cyan-50 to-blue-50">
                            <tr>
                                {[t('Nº'), t('Data'), t('De'), t('Para')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-cyan-700">{c}</th>
                                ))}
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-cyan-700">{t('Valor')}</th>
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-cyan-700">{t('Taxa')}</th>
                                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-cyan-700">{t('Ações')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lista.isPending && (
                                <tr><td colSpan={7} className="px-4 py-8"><Carregando linhas={4} /></td></tr>
                            )}

                            {!lista.isPending && linhas.length === 0 && (
                                <tr>
                                    <td colSpan={7}>
                                        <SemNada icone="fa-right-left" titulo={t('Nenhuma transferência registada')} />
                                    </td>
                                </tr>
                            )}

                            {linhas.map((x, i) => (
                                <tr key={x.id} style={cascata(i)} className="entra transition-colors hover:bg-cyan-50/60">
                                    <td className="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">{x.numero}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{x.data}</td>
                                    <td className="px-4 py-3 text-slate-700"><Bolsinho nome={x.de} caixa={x.de_e_caixa} /></td>
                                    <td className="px-4 py-3 text-slate-700"><Bolsinho nome={x.para} caixa={x.para_e_caixa} /></td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                        {kz(x.valor)} <span className="text-xs text-slate-400">{x.moeda}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-500">
                                        {x.taxa > 0 ? kz(x.taxa) : '—'}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-center">
                                        {o.permissoes.pode_anular ? (
                                            <button
                                                type="button"
                                                onClick={() => porAAnular(x)}
                                                title={t('Anular :numero', { numero: x.numero })}
                                                aria-label={t('Anular :numero', { numero: x.numero })}
                                                className={cls(
                                                    'inline-flex items-center gap-1.5 border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700',
                                                    'transition-all duration-200 hover:-translate-y-0.5 hover:border-red-300 hover:shadow-sm',
                                                    RAIO,
                                                    FOCO,
                                                )}
                                            >
                                                <i className="fas fa-rotate-left" aria-hidden="true" />
                                                {t('Anular')}
                                            </button>
                                        ) : (
                                            <span className="text-xs text-slate-300">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                    <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))} />

                    {contas && contas.last_page > 1 && (
                        <div className="flex items-center gap-3 text-sm text-slate-600">
                            <Botao icone="fa-chevron-left" altura="pequeno" disabled={contas.current_page <= 1}
                                onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>
                                {t('Anterior')}
                            </Botao>
                            <span>{t('Página :pagina de :ultima', { pagina: contas.current_page, ultima: contas.last_page })}</span>
                            <Botao icone="fa-chevron-right" altura="pequeno" disabled={contas.current_page >= contas.last_page}
                                onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>
                                {t('Seguinte')}
                            </Botao>
                        </div>
                    )}
                </div>
            </div>

            <ModalDaTransferencia
                aberto={aRegistar}
                o={o}
                aoFechar={() => porARegistar(false)}
                aoGravar={(mensagem) => {
                    porARegistar(false);
                    feito(mensagem);
                }}
            />

            <Modal
                aberto={aAnular !== null}
                aoFechar={() => porAAnular(null)}
                titulo={t('Anular transferência')}
                subtitulo={aAnular?.numero}
                icone="fa-rotate-left"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAAnular(null)} icone="fa-times">{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-rotate-left"
                            aTrabalhar={anular.isPending}
                            onClick={() => aAnular && anular.mutate(aAnular)}
                        >
                            {t('Anular e reverter')}
                        </Botao>
                    </>
                }
            >
                {aAnular && (
                    <div className="space-y-4 text-center">
                        <p className="text-slate-600">
                            {t('Os saldos das contas serão revertidos e as transações associadas removidas.')}
                        </p>
                        <div className={cls('bg-slate-50 px-4 py-3 text-left text-sm', RAIO)}>
                            <div className="flex items-center justify-between gap-3">
                                <Bolsinho nome={aAnular.de} caixa={aAnular.de_e_caixa} />
                                <i className="fas fa-arrow-right text-slate-400" aria-hidden="true" />
                                <Bolsinho nome={aAnular.para} caixa={aAnular.para_e_caixa} />
                            </div>
                            <p className="mt-2 text-center text-xl font-bold tabular-nums text-slate-900">
                                {kz(aAnular.valor)} {aAnular.moeda}
                                {aAnular.taxa > 0 && (
                                    <span className="ml-2 text-sm font-normal text-amber-700">
                                        + {kz(aAnular.taxa)} {t('de taxa')}
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

/** O nome de um bolso com o ícone do que ele é: banco ou caixa. */
function Bolsinho({ nome, caixa }: { nome: string | null; caixa: boolean }) {
    return (
        <span className="inline-flex min-w-0 items-center gap-1.5">
            <i
                className={cls('fas', caixa ? 'fa-cash-register text-amber-500' : 'fa-building-columns text-blue-500')}
                aria-hidden="true"
            />
            <span className="truncate">{nome ?? '—'}</span>
        </span>
    );
}

/** Hoje, na hora de cá. `toISOString()` recua um dia em Angola (UTC+1). */
function hoje(): string {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const VAZIO: FormularioDaTransferencia = {
    de: '',
    para: '',
    amount: '',
    fee: 0,
    currency: 'AOA',
    transfer_date: hoje(),
    description: '',
    reference: '',
};

function ModalDaTransferencia({
    aberto,
    o,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    o: OpcoesDasTransferencias;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState<FormularioDaTransferencia>(VAZIO);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aGravar, porAGravar] = useState(false);
    const [recado, porRecado] = useState('');

    useEffect(() => {
        if (!aberto) return;

        porF({ ...VAZIO, transfer_date: hoje(), currency: o.moedas[0] ?? 'AOA' });
        porErros({});
        porRecado('');
    }, [aberto, o]);

    const bolsos = [
        ...o.contas.map((c) => ({ ...c, chave: `account:${c.id}`, caixa: false })),
        ...o.caixas.map((c) => ({ ...c, chave: `cash:${c.id}`, caixa: true })),
    ];

    const origem = bolsos.find((b) => b.chave === f.de);
    const sai = Number(f.amount || 0) + Number(f.fee || 0);

    /*
     * O QUE FICA NA ORIGEM depois disto.
     *
     * O servidor não impede um descoberto — há contas que o permitem, e não
     * é aqui que isso se decide. Mas ver o resultado antes de carregar é
     * outra coisa do que o descobrir depois no extracto.
     */
    const sobra = origem ? origem.saldo - sai : null;

    async function gravar(e: React.FormEvent) {
        e.preventDefault();
        porErros({});
        porRecado('');
        porAGravar(true);

        try {
            const r = await transferencias.criar(f);

            aoGravar(r.message);
        } catch (erro) {
            if (erro instanceof ErroDaApi) {
                porErros(erro.erros);
                porRecado(erro.message);
            } else {
                porRecado(t('Não foi possível gravar. Verifique a ligação.'));
            }
        } finally {
            porAGravar(false);
        }
    }

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Nova Transferência')}
            icone="fa-right-left"
            cor="ciano"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times" disabled={aGravar}>{t('Cancelar')}</Botao>
                    <Botao form="transferencia" type="submit" cor="bom" tom="solida" icone="fa-check" aTrabalhar={aGravar}>
                        {t('Registar')}
                    </Botao>
                </>
            }
        >
            <form id="transferencia" onSubmit={gravar} className="space-y-4">
                {recado && (
                    <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {recado}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('De')} obrigatorio erro={erros.de}>
                        <select value={f.de} onChange={(e) => porF((a) => ({ ...a, de: e.target.value }))} className={entrada}>
                            <option value="">{t('Selecionar origem…')}</option>
                            <Bolsos contas={o.contas} caixas={o.caixas} comSaldo />
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Para')} obrigatorio erro={erros.para}>
                        <select value={f.para} onChange={(e) => porF((a) => ({ ...a, para: e.target.value }))} className={entrada}>
                            <option value="">{t('Selecionar destino…')}</option>
                            <Bolsos contas={o.contas} caixas={o.caixas} excepto={f.de} />
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Valor')} obrigatorio erro={erros.amount}>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={f.amount}
                            onChange={(e) => porF((a) => ({ ...a, amount: e.target.value }))}
                            className={cls(entrada, 'text-right font-semibold tabular-nums')}
                        />
                    </Campo>

                    <Campo etiqueta={t('Taxa')} erro={erros.fee} ajuda={t('Sai também da origem.')}>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={f.fee}
                            onChange={(e) => porF((a) => ({ ...a, fee: e.target.value }))}
                            className={cls(entrada, 'text-right tabular-nums')}
                        />
                    </Campo>

                    <Campo etiqueta={t('Data')} obrigatorio erro={erros.transfer_date}>
                        <input
                            type="date"
                            value={f.transfer_date}
                            onChange={(e) => porF((a) => ({ ...a, transfer_date: e.target.value }))}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Moeda')} erro={erros.currency}>
                        <select value={f.currency} onChange={(e) => porF((a) => ({ ...a, currency: e.target.value }))} className={entrada}>
                            {o.moedas.map((m) => <option key={m} value={m}>{m}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Descrição')} erro={erros.description} className="sm:col-span-2">
                        <input
                            type="text"
                            value={f.description}
                            onChange={(e) => porF((a) => ({ ...a, description: e.target.value }))}
                            placeholder={t('Motivo da transferência…')}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Referência')} erro={erros.reference} className="sm:col-span-2">
                        <input
                            type="text"
                            value={f.reference}
                            onChange={(e) => porF((a) => ({ ...a, reference: e.target.value }))}
                            className={entrada}
                        />
                    </Campo>
                </div>

                {origem && sai > 0 && (
                    <div
                        className={cls(
                            'flex flex-wrap items-center justify-between gap-2 border px-4 py-3 text-sm',
                            RAIO,
                            sobra !== null && sobra < 0
                                ? 'border-amber-300 bg-amber-50 text-amber-900'
                                : 'border-slate-200 bg-slate-50 text-slate-700',
                        )}
                    >
                        <span>
                            <i
                                className={cls('mr-1.5 fas', sobra !== null && sobra < 0 ? 'fa-triangle-exclamation' : 'fa-calculator')}
                                aria-hidden="true"
                            />
                            {t('Sai de :bolso', { bolso: origem.nome })}: <strong className="tabular-nums">{kz(sai)}</strong>
                        </span>
                        <span>
                            {t('Fica em')} <strong className={cls('tabular-nums', sobra !== null && sobra < 0 && 'text-amber-800')}>{kz(sobra ?? 0)}</strong>
                        </span>
                    </div>
                )}
            </form>
        </Modal>
    );
}

/**
 * As duas listas dentro de um só campo, agrupadas.
 *
 * `<optgroup>` porque uma conta bancária e um caixa não são a mesma coisa e
 * uma lista corrida de vinte nomes não o diz.
 */
function Bolsos({
    contas,
    caixas,
    comSaldo = false,
    excepto,
}: {
    contas: Bolso[];
    caixas: Bolso[];
    /** Na origem mostra-se quanto lá está: escolher às cegas é como se erra. */
    comSaldo?: boolean;
    /** O destino não pode ser a origem — some da lista em vez de dar erro. */
    excepto?: string;
}) {
    const rotulo = (b: Bolso) => (comSaldo ? `${b.nome} (${kz(b.saldo)} Kz)` : b.nome);

    return (
        <>
            {contas.length > 0 && (
                <optgroup label={t('Contas Bancárias')}>
                    {contas
                        .filter((c) => `account:${c.id}` !== excepto)
                        .map((c) => <option key={c.id} value={`account:${c.id}`}>{rotulo(c)}</option>)}
                </optgroup>
            )}
            {caixas.length > 0 && (
                <optgroup label={t('Caixas')}>
                    {caixas
                        .filter((c) => `cash:${c.id}` !== excepto)
                        .map((c) => <option key={c.id} value={`cash:${c.id}`}>{rotulo(c)}</option>)}
                </optgroup>
            )}
        </>
    );
}
