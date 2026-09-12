import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { Orcamento, OrcamentosDoAno } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * OS ORÇAMENTOS — o que se previu para cada conta, mês a mês.
 *
 * O QUE FALTAVA E É O PONTO INTEIRO: a COMPARAÇÃO COM O REAL. O ecrã antigo
 * gravava doze números e mostrava-os outra vez; um orçamento que não se compara
 * com o que aconteceu é uma folha de cálculo com mais passos. Cada linha traz
 * agora o realizado — a soma dos lançamentos confirmados da conta — o desvio e a
 * execução em percentagem.
 *
 * E O QUE ESTAVA PARTIDO: não havia como apagar nem mudar o estado (gravava
 * sempre «rascunho», pelo que um orçamento aprovado voltava atrás na edição
 * seguinte), o total vinha do browser em vez de sair da soma dos meses, e nada
 * impedia dois orçamentos para a mesma conta no mesmo ano.
 */

const MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

const CURTOS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

export default function Orcamentos() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ ano?: number; procura?: string; estado?: string }>({
        ano: new Date().getFullYear(),
    });
    const [recado, porRecado] = useState('');
    const [modal, porModal] = useState<{ aberto: boolean; orcamento: Orcamento | null }>({ aberto: false, orcamento: null });
    const [aVer, porAVer] = useState<Orcamento | null>(null);
    const [aApagar, porAApagar] = useState<Orcamento | null>(null);

    const lista = useQuery({
        queryKey: ['contabilidade', 'orcamentos', filtros],
        queryFn: () => contabilidade.orcamentos.listar(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade', 'orcamentos'] });
    }

    const estado = useMutation({
        mutationFn: ({ o, para }: { o: Orcamento; para: string }) => contabilidade.orcamentos.estado(o.id, para),
        onSuccess: (r) => feito(r.message),
    });

    const apagar = useMutation({
        mutationFn: (o: Orcamento) => contabilidade.orcamentos.apagar(o.id),
        onSuccess: (r) => {
            porAApagar(null);
            feito(r.message);
        },
    });

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os orçamentos')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const d = lista.data;
    const agora = new Date().getFullYear();
    const anos = Array.from(new Set([...d.anos, agora, agora + 1])).sort((a, b) => b - a);

    const realizado = d.data.reduce((s, o) => s + o.realizado, 0);
    const execucao = d.resumo.previsto > 0 ? (realizado / d.resumo.previsto) * 100 : null;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Orçamentos')}
                subtitulo={t('O que se previu para cada conta — e o que aconteceu')}
                icone="fa-bullseye"
                cor="roxo"
                accoes={
                    d.permissoes.gerir && (
                        <button type="button" onClick={() => porModal({ aberto: true, orcamento: null })} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Novo Orçamento')}
                        </button>
                    )
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <label className="sr-only" htmlFor="orc-ano">{t('Exercício')}</label>
                    <select
                        id="orc-ano"
                        value={filtros.ano ?? agora}
                        onChange={(e) => porFiltros((f) => ({ ...f, ano: Number(e.target.value) }))}
                        className={cls(
                            'h-9 rounded-xl border border-white/30 bg-white/20 px-3 text-sm font-semibold text-white',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
                        )}
                    >
                        {anos.map((a) => <option key={a} value={a} className="text-slate-900">{a}</option>)}
                    </select>

                    {execucao !== null && (
                        <EstadoNaFaixa icone="fa-percent">
                            {t('Execução do exercício: :n%', { n: execucao.toFixed(1) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <ErroDaAccao erro={estado.error ?? apagar.error} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero rotulo={t('Orçamentos')} valor={d.resumo.orcamentos.toLocaleString('pt-PT')} icone="fa-bullseye" tom="roxo" aspecto="claro" />
                <CartaoNumero rotulo={t('Previsto')} valor={kz(d.resumo.previsto)} sufixo="Kz" icone="fa-clipboard-list" tom="azul" aspecto="claro" />
                <CartaoNumero rotulo={t('Realizado')} valor={kz(realizado)} sufixo="Kz" icone="fa-check-double" tom="verde" aspecto="claro" nota={t('Só lançamentos confirmados')} />
                <CartaoNumero
                    rotulo={t('Desvio')}
                    valor={kz(realizado - d.resumo.previsto)}
                    sufixo="Kz"
                    icone="fa-scale-unbalanced"
                    tom={realizado > d.resumo.previsto ? 'vermelho' : 'verde'}
                    aspecto="claro"
                    nota={realizado > d.resumo.previsto ? t('Acima do previsto') : t('Dentro do previsto')}
                />
            </div>

            <Cartao titulo={t('Filtrar')} icone="fa-filter">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Pesquisar')}>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value }))}
                            placeholder={t('Nome do orçamento…')}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Estado')}>
                        <select
                            value={filtros.estado ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value }))}
                            className={entrada}
                        >
                            <option value="">{t('Todos os estados')}</option>
                            {d.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                </div>
            </Cartao>

            {d.data.length === 0 ? (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <SemNada
                        icone="fa-bullseye"
                        titulo={t('Sem orçamentos em :ano', { ano: d.ano })}
                        frase={d.permissoes.gerir
                            ? t('Um orçamento é uma conta, um ano e doze valores. Comece pelo primeiro.')
                            : t('Peça a quem gere a contabilidade para montar o orçamento do ano.')}
                    />
                </section>
            ) : (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-gradient-to-r from-purple-50 to-pink-50">
                                <tr>
                                    {[t('Orçamento'), t('Conta')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-purple-700">{c}</th>
                                    ))}
                                    {[t('Previsto'), t('Realizado'), t('Desvio')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-purple-700">{c}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-purple-700">{t('Execução')}</th>
                                    <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-purple-700">{t('Estado')}</th>
                                    <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-purple-700">{t('Ações')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.data.map((o, i) => (
                                    <tr key={o.id} style={cascata(i)} className="entra transition-colors hover:bg-purple-50/50">
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-slate-900">{o.nome}</p>
                                            {o.centro_de_custo && (
                                                <p className="truncate text-xs text-slate-400">{o.centro_de_custo}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{o.conta ?? '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">{kz(o.previsto)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900">{kz(o.realizado)}</td>
                                        <td className={cls('whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums',
                                            o.desvio > 0 ? 'text-red-700' : o.desvio < 0 ? 'text-emerald-700' : 'text-slate-400')}>
                                            {o.desvio === 0 ? '—' : kz(o.desvio)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Execucao por_cento={o.execucao} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center">
                                            <Etiqueta
                                                cor={o.estado === 'approved' ? 'bom' : o.estado === 'closed' ? 'neutra' : 'aviso'}
                                                ponto
                                            >
                                                {o.estado_rotulo}
                                            </Etiqueta>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => porAVer(o)}
                                                    title={t('Ver mês a mês')}
                                                    aria-label={t('Ver :orcamento mês a mês', { orcamento: o.nome })}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-cyan-200 bg-cyan-50 text-cyan-700')}
                                                >
                                                    <i className="fas fa-table-columns" aria-hidden="true" />
                                                </button>

                                                {d.permissoes.gerir && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => porModal({ aberto: true, orcamento: o })}
                                                            title={t('Editar')}
                                                            aria-label={t('Editar :orcamento', { orcamento: o.nome })}
                                                            className={cls(BOTAO_DE_ACCAO, 'border-blue-200 bg-blue-50 text-blue-700')}
                                                        >
                                                            <i className="fas fa-edit" aria-hidden="true" />
                                                        </button>

                                                        {o.estado === 'draft' && (
                                                            <button
                                                                type="button"
                                                                onClick={() => estado.mutate({ o, para: 'approved' })}
                                                                disabled={estado.isPending}
                                                                title={t('Aprovar')}
                                                                aria-label={t('Aprovar :orcamento', { orcamento: o.nome })}
                                                                className={cls(BOTAO_DE_ACCAO, 'border-emerald-200 bg-emerald-50 text-emerald-700')}
                                                            >
                                                                <i className="fas fa-check" aria-hidden="true" />
                                                            </button>
                                                        )}

                                                        {o.estado === 'approved' && (
                                                            <button
                                                                type="button"
                                                                onClick={() => estado.mutate({ o, para: 'closed' })}
                                                                disabled={estado.isPending}
                                                                title={t('Encerrar')}
                                                                aria-label={t('Encerrar :orcamento', { orcamento: o.nome })}
                                                                className={cls(BOTAO_DE_ACCAO, 'border-slate-200 bg-slate-50 text-slate-600')}
                                                            >
                                                                <i className="fas fa-lock" aria-hidden="true" />
                                                            </button>
                                                        )}

                                                        <button
                                                            type="button"
                                                            onClick={() => porAApagar(o)}
                                                            title={t('Eliminar')}
                                                            aria-label={t('Eliminar :orcamento', { orcamento: o.nome })}
                                                            className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                        >
                                                            <i className="fas fa-trash" aria-hidden="true" />
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* O CENTRO DE CUSTO É INFORMATIVO, e diz-se: as linhas de
                        lançamento não têm coluna de centro de custo. */}
                    {d.data.some((o) => o.centro_de_custo) && (
                        <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                            <i className="fas fa-circle-info mr-1.5 text-blue-500" aria-hidden="true" />
                            {t('O centro de custo é informativo: o realizado é o da conta, porque as linhas de lançamento não registam centro de custo.')}
                        </p>
                    )}
                </section>
            )}

            <ModalDoOrcamento
                aberto={modal.aberto}
                orcamento={modal.orcamento}
                o={d}
                aoFechar={() => porModal({ aberto: false, orcamento: null })}
                aoGravar={(mensagem) => {
                    porModal({ aberto: false, orcamento: null });
                    feito(mensagem);
                }}
            />

            <ModalMesAMes orcamento={aVer} aoFechar={() => porAVer(null)} />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar orçamento')}
                subtitulo={aApagar?.nome}
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
                    {t('A previsão desaparece. Os lançamentos não são tocados — um orçamento nunca mexeu em saldo nenhum.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── A execução em barra ─────────────────────────────────────────────── */

/**
 * A EXECUÇÃO LÊ-SE DE RELANCE.
 *
 * «87%» e «134%» são dois números que se confundem numa coluna de vinte; a
 * barra diz num relance quem passou do previsto, e a cor diz se isso é bom.
 * Acima de 100 a barra enche e o excesso pinta-se — não se desenha uma barra de
 * 134% de largura.
 */
function Execucao({ por_cento }: { por_cento: number | null }) {
    if (por_cento === null) {
        return <span className="text-xs text-slate-400">{t('sem previsão')}</span>;
    }

    const cheia = Math.min(100, por_cento);
    const passou = por_cento > 100;

    return (
        <span className="block min-w-24">
            <span className="mb-1 block text-xs font-bold tabular-nums text-slate-700">
                {por_cento.toFixed(1)}%
            </span>
            <span className="block h-1.5 overflow-hidden rounded-full bg-slate-100">
                <span
                    className={cls('block h-full rounded-full transition-all duration-500',
                        passou ? 'bg-red-500' : por_cento >= 80 ? 'bg-amber-500' : 'bg-emerald-500')}
                    style={{ width: `${cheia}%` }}
                />
            </span>
        </span>
    );
}

/* ─── A janela do orçamento ───────────────────────────────────────────── */

const CHAVES = [
    'january', 'february', 'march', 'april', 'may', 'june',
    'july', 'august', 'september', 'october', 'november', 'december',
];

function ModalDoOrcamento({
    aberto,
    orcamento,
    o,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    orcamento: Orcamento | null;
    o: OrcamentosDoAno;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const vazio = () => ({
        name: '',
        year: o.ano,
        account_id: '',
        cost_center_id: '',
        status: 'draft',
        valores: CHAVES.map(() => ''),
    });

    const [f, porF] = useState(vazio);

    useEffect(() => {
        if (!aberto) return;

        porF(orcamento
            ? {
                name: orcamento.nome,
                year: orcamento.ano,
                account_id: orcamento.conta_id ? String(orcamento.conta_id) : '',
                cost_center_id: orcamento.centro_de_custo_id ? String(orcamento.centro_de_custo_id) : '',
                status: orcamento.estado,
                valores: CHAVES.map((c) => String(orcamento.valores[c] ?? '')),
            }
            : vazio());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [aberto, orcamento, o.ano]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.orcamentos.guardar(orcamento?.id ?? null, {
            name: f.name,
            year: f.year,
            account_id: f.account_id ? Number(f.account_id) : null,
            cost_center_id: f.cost_center_id ? Number(f.cost_center_id) : null,
            status: f.status,
            ...Object.fromEntries(CHAVES.map((c, i) => [c, f.valores[i] ? Number(f.valores[i]) : 0])),
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    // O TOTAL SAI DA SOMA, e vê-se enquanto se escreve.
    const total = f.valores.reduce((s, v) => s + (Number(v) || 0), 0);

    /** Espalhar um total pelos doze meses: é o que se faz em nove casos em dez. */
    const espalhar = (valor: number) => {
        const porMes = Math.round((valor / 12) * 100) / 100;
        // O ARREDONDAMENTO SOBRA NO ÚLTIMO MÊS: doze vezes 83,33 não dão 1.000.
        const sobra = Math.round((valor - porMes * 12) * 100) / 100;

        porF((x) => ({
            ...x,
            valores: CHAVES.map((_, i) => String(i === 11 ? Math.round((porMes + sobra) * 100) / 100 : porMes)),
        }));
    };

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={orcamento ? t('Editar Orçamento') : t('Novo Orçamento')}
            subtitulo={t('Uma conta, um ano e doze valores')}
            icone="fa-bullseye"
            cor="roxo"
            largura="xl"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {orcamento ? t('Atualizar') : t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name} className="lg:col-span-2">
                    <input
                        type="text"
                        value={f.name}
                        onChange={(e) => porF((x) => ({ ...x, name: e.target.value }))}
                        placeholder={t('Ex.: Compras de mercadorias :ano', { ano: f.year })}
                        className={entrada}
                    />
                </Campo>

                <Campo etiqueta={t('Exercício')} obrigatorio erro={erros.year}>
                    <input
                        type="number"
                        min={1900}
                        max={2200}
                        value={f.year}
                        onChange={(e) => porF((x) => ({ ...x, year: Number(e.target.value) }))}
                        className={cls(entrada, 'tabular-nums')}
                    />
                </Campo>

                <Campo etiqueta={t('Estado')} erro={erros.status}>
                    <select value={f.status} onChange={(e) => porF((x) => ({ ...x, status: e.target.value }))} className={entrada}>
                        {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Conta')}
                    obrigatorio
                    erro={erros.account_id}
                    ajuda={t('Só contas que recebem movimento: orçamentar uma de agregação é orçamentar o que já está nas filhas.')}
                    className="lg:col-span-2"
                >
                    <select value={f.account_id} onChange={(e) => porF((x) => ({ ...x, account_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Escolha a conta…')}</option>
                        {o.contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Centro de Custo')}
                    erro={erros.cost_center_id}
                    ajuda={t('Informativo: o realizado é o da conta.')}
                    className="lg:col-span-2"
                >
                    <select value={f.cost_center_id} onChange={(e) => porF((x) => ({ ...x, cost_center_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Nenhum')}</option>
                        {o.centros_de_custo.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {/* OS DOZE MESES */}
            <div className="mt-5">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <i className="fas fa-calendar text-purple-600" aria-hidden="true" />
                        {t('Os doze meses')}
                    </h3>

                    {/* ESPALHAR UM TOTAL é o que se faz em nove casos em dez —
                        e ninguém quer dividir por doze à mão. */}
                    <label className="flex items-center gap-2 text-xs text-slate-500">
                        <span className="whitespace-nowrap">{t('Espalhar um total')}</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0,00"
                            aria-label={t('Total a espalhar pelos doze meses')}
                            onKeyDown={(e) => {
                                if (e.key !== 'Enter') return;

                                e.preventDefault();
                                espalhar(Number((e.target as HTMLInputElement).value) || 0);
                            }}
                            onBlur={(e) => {
                                const v = Number(e.target.value) || 0;

                                if (v > 0) espalhar(v);
                            }}
                            className={cls(entrada, 'h-8 w-32 text-right text-xs tabular-nums')}
                        />
                    </label>
                </div>

                <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    {MESES.map((mes, i) => (
                        <div key={mes}>
                            <Rotulo>{t(mes)}</Rotulo>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={f.valores[i] ?? ''}
                                onChange={(e) => porF((x) => ({
                                    ...x,
                                    valores: x.valores.map((v, j) => (j === i ? e.target.value : v)),
                                }))}
                                aria-label={t('Previsto de :mes', { mes: t(mes) })}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                            {erros[CHAVES[i] as string]?.[0] && (
                                <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros[CHAVES[i] as string]?.[0]}</p>
                            )}
                        </div>
                    ))}
                </div>

                <div className={cls('mt-3 flex flex-wrap items-center justify-between gap-3 border-2 border-purple-200 bg-purple-50 px-4 py-3', RAIO)} role="status">
                    <span className="text-sm text-slate-600">
                        {t('O total sai da soma dos meses — não se escreve.')}
                    </span>
                    <span className="text-lg font-bold tabular-nums text-purple-900">
                        {kz(total)} <span className="text-sm font-semibold">Kz</span>
                    </span>
                </div>
            </div>
        </Modal>
    );
}

/* ─── Mês a mês, previsto contra real ─────────────────────────────────── */

function ModalMesAMes({ orcamento, aoFechar }: { orcamento: Orcamento | null; aoFechar: () => void }) {
    const maximo = orcamento
        ? Math.max(...orcamento.meses.map((m) => Math.max(m.previsto, m.realizado)), 0)
        : 0;

    return (
        <Modal
            aberto={orcamento !== null}
            aoFechar={aoFechar}
            titulo={orcamento ? orcamento.nome : t('Mês a mês')}
            subtitulo={orcamento?.conta ?? undefined}
            icone="fa-table-columns"
            cor="ciano"
            largura="xl"
            rodape={<Botao onClick={aoFechar} icone="fa-times">{t('Fechar')}</Botao>}
        >
            {!orcamento ? null : (
                <>
                    <div className="mb-4 grid gap-3 sm:grid-cols-3">
                        <Numero rotulo={t('Previsto')} valor={orcamento.previsto} tom="azul" />
                        <Numero rotulo={t('Realizado')} valor={orcamento.realizado} tom="verde" />
                        <Numero
                            rotulo={t('Desvio')}
                            valor={orcamento.desvio}
                            tom={orcamento.desvio > 0 ? 'vermelho' : 'ardosia'}
                        />
                    </div>

                    {/* AS DUAS SÉRIES LADO A LADO: previsto e real, por mês. Uma
                        tabela de doze linhas diz os números; as barras dizem a
                        forma do ano. */}
                    <div className="mb-4 flex items-end gap-2 border-b border-slate-200 pb-1" style={{ height: 160 }} role="img"
                        aria-label={t('Previsto e realizado por mês')}>
                        {orcamento.meses.map((m, i) => (
                            <div key={m.mes} className="group relative flex h-full flex-1 items-end gap-0.5">
                                <span className="pointer-events-none absolute -top-1 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] text-white group-hover:block">
                                    {t(CURTOS[i] as string)}: {kz(m.previsto, 0)} / {kz(m.realizado, 0)}
                                </span>
                                <span
                                    className="w-1/2 rounded-t bg-blue-300 transition-all duration-500"
                                    style={{ height: `${maximo > 0 ? Math.max((m.previsto / maximo) * 100, m.previsto > 0 ? 2 : 0) : 0}%` }}
                                />
                                <span
                                    className={cls('w-1/2 rounded-t transition-all duration-500',
                                        m.realizado > m.previsto ? 'bg-red-400' : 'bg-emerald-400')}
                                    style={{ height: `${maximo > 0 ? Math.max((m.realizado / maximo) * 100, m.realizado > 0 ? 2 : 0) : 0}%` }}
                                />
                            </div>
                        ))}
                    </div>

                    <div className="mb-4 flex flex-wrap items-center gap-4 text-xs text-slate-600">
                        <span className="inline-flex items-center gap-1.5">
                            <span className="inline-block h-3 w-3 rounded bg-blue-300" aria-hidden="true" />
                            {t('Previsto')}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span className="inline-block h-3 w-3 rounded bg-emerald-400" aria-hidden="true" />
                            {t('Realizado dentro do previsto')}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span className="inline-block h-3 w-3 rounded bg-red-400" aria-hidden="true" />
                            {t('Realizado acima do previsto')}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t('Mês')}</th>
                                    {[t('Previsto'), t('Realizado'), t('Desvio')].map((c) => (
                                        <th key={c} scope="col" className="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {orcamento.meses.map((m, i) => (
                                    <tr key={m.mes} style={cascata(i)} className="entra">
                                        <td className="whitespace-nowrap px-3 py-2 text-slate-700">{t(MESES[i] as string)}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-600">{kz(m.previsto)}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-slate-900">{kz(m.realizado)}</td>
                                        <td className={cls('whitespace-nowrap px-3 py-2 text-right font-bold tabular-nums',
                                            m.desvio > 0 ? 'text-red-700' : m.desvio < 0 ? 'text-emerald-700' : 'text-slate-300')}>
                                            {m.desvio === 0 ? '—' : kz(m.desvio)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                                <tr>
                                    <td className="px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais')}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-slate-700">{kz(orcamento.previsto)}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(orcamento.realizado)}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(orcamento.desvio)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </>
            )}
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

const NUMEROS = {
    ardosia: 'border-slate-200 bg-slate-50 text-slate-700',
    verde: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    vermelho: 'border-red-200 bg-red-50 text-red-800',
    azul: 'border-blue-200 bg-blue-50 text-blue-900',
} as const;

function Numero({ rotulo, valor, tom }: { rotulo: string; valor: number; tom: keyof typeof NUMEROS }) {
    return (
        <div className={cls('border px-3 py-2.5', RAIO, NUMEROS[tom])}>
            <p className="text-[11px] font-semibold uppercase tracking-wider opacity-80">{rotulo}</p>
            <p className="text-lg font-bold tabular-nums">{kz(valor)}</p>
        </div>
    );
}

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
