import { useEffect, useMemo, useState } from 'react';

import type { Pagamento } from '@/api/pos';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * O PAGAMENTO.
 *
 * O modal de sempre tinha tudo o que é preciso — e um defeito que só se vê
 * com a régua: a **1366×768**, o ecrã de balcão mais comum, o botão
 * «Confirmar Venda» ficava em y 764, ABAIXO da janela. Quem fecha uma venda
 * tinha de rolar dentro do modal, com o cliente à espera.
 *
 * Aqui o corpo rola e o RODAPÉ NÃO: o botão de confirmar está sempre no
 * mesmo sítio, seja qual for a altura do ecrã.
 *
 * O resto é o que lá estava: uma forma ou várias, trocos rápidos, troco
 * calculado, e o aviso quando o dinheiro não chega.
 */
export function ModalDePagamento({
    aberto,
    aoFechar,
    total,
    subtotal,
    desconto,
    formas,
    montantesRapidos,
    aTrabalhar,
    aoConfirmar,
}: {
    aberto: boolean;
    aoFechar: () => void;
    /** A base, já sem desconto. O imposto soma-se no servidor. */
    total: number;
    subtotal: number;
    desconto: number;
    formas: Array<{ valor: string; rotulo: string }>;
    montantesRapidos: number[];
    aTrabalhar: boolean;
    aoConfirmar: (p: {
        payment_method: string;
        payments: Pagamento[] | null;
        amount_received: number;
        notes: string | null;
    }) => void;
}) {
    const [varias, porVarias] = useState(false);
    const [forma, porForma] = useState('');
    const [recebido, porRecebido] = useState('');
    const [pagamentos, porPagamentos] = useState<Pagamento[]>([]);
    const [notas, porNotas] = useState('');

    // Ao abrir, o valor recebido propõe o total: no pagamento certo — que é a
    // maioria das vendas — não há nada a escrever.
    useEffect(() => {
        if (aberto) {
            porRecebido(String(total));
            porVarias(false);
            porPagamentos([]);
            /*
             * O DINHEIRO POR OMISSÃO, e não a primeira da lista.
             *
             * As formas vêm por ordem alfabética da tesouraria, e a primeira
             * calhava ser «Cheque» — que ao balcão é a menos usada de todas.
             * Escolhe-se numerário quando existe; só se não existir é que a
             * ordem da lista decide.
             */
            const numerario = formas.find((f) => /cash|dinheiro|numerario|numerário/i.test(f.valor + f.rotulo));

            porForma(numerario?.valor ?? formas[0]?.valor ?? 'cash');
        }
    }, [aberto, total, formas]);

    const recebidoNum = Number(String(recebido).replace(/\s/g, '').replace(',', '.')) || 0;
    const somaDasFormas = useMemo(() => pagamentos.reduce((s, p) => s + (Number(p.amount) || 0), 0), [pagamentos]);

    const falta = varias ? Math.max(0, total - somaDasFormas) : Math.max(0, total - recebidoNum);
    const excede = varias && somaDasFormas > total + 0.02;
    const troco = varias ? 0 : Math.max(0, recebidoNum - total);
    const podeFechar = varias ? Math.abs(somaDasFormas - total) <= 0.02 : recebidoNum >= total - 0.005;

    function juntarForma() {
        porPagamentos((ps) => [...ps, { method: forma, amount: falta, reference: null }]);
    }

    function confirmar() {
        if (!podeFechar || aTrabalhar) return;

        aoConfirmar({
            payment_method: varias ? 'multiple' : forma,
            payments: varias ? pagamentos : null,
            amount_received: varias ? somaDasFormas : recebidoNum,
            notes: notas || null,
        });
    }

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Finalizar Pagamento')}
            icone="fa-money-bill-wave"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} disabled={aTrabalhar}>{t('Cancelar')}</Botao>
                    <button
                        type="button"
                        onClick={confirmar}
                        disabled={!podeFechar || aTrabalhar}
                        className={cls(
                            'inline-flex items-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-3',
                            'text-base font-bold text-white shadow-lg transition-all duration-200',
                            'hover:-translate-y-0.5 hover:shadow-xl active:translate-y-0',
                            'disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300 disabled:shadow-none disabled:hover:translate-y-0',
                            RAIO,
                            FOCO,
                        )}
                    >
                        <i
                            className={cls('fas', aTrabalhar ? 'fa-spinner fa-spin' : 'fa-circle-check', 'text-lg')}
                            aria-hidden="true"
                        />
                        {aTrabalhar ? t('A registar…') : t('Confirmar Venda')}
                    </button>
                </>
            }
        >
            <div className="space-y-4">
                {/* O QUE SE VAI COBRAR, em grande. É o número que se diz em voz
                    alta ao cliente, e tem de se ler do outro lado do balcão. */}
                <div className={cls('bg-gradient-to-br from-slate-50 to-slate-100 p-4', RAIO)}>
                    <dl className="space-y-1 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-slate-500">{t('Subtotal')}</dt>
                            <dd className="font-semibold tabular-nums text-slate-700">{kz(subtotal)}</dd>
                        </div>
                        {desconto > 0 && (
                            <div className="flex justify-between text-amber-600">
                                <dt>{t('Desconto')}</dt>
                                <dd className="font-semibold tabular-nums">− {kz(desconto)}</dd>
                            </div>
                        )}
                        <div className="flex items-baseline justify-between border-t border-slate-200 pt-2">
                            <dt className="font-bold text-slate-800">{t('A PAGAR')}</dt>
                            <dd className="text-3xl font-bold tabular-nums text-emerald-700">{kz(total)}</dd>
                        </div>
                    </dl>
                    <p className="mt-2 text-[11px] text-slate-400">
                        {t('O imposto é somado pelo servidor, com a taxa que está na ficha de cada artigo.')}
                    </p>
                </div>

                {/* Uma forma ou várias. */}
                <div className="flex items-center justify-between">
                    <span className="text-sm font-semibold text-slate-700">
                        <i className="fas fa-credit-card mr-2 text-slate-400" aria-hidden="true" />
                        {t('Pagamento')}
                    </span>
                    <button
                        type="button"
                        onClick={() => {
                            porVarias(!varias);
                            porPagamentos(varias ? [] : [{ method: forma, amount: total, reference: null }]);
                        }}
                        className={cls(
                            'px-3 py-1.5 text-xs font-semibold transition-colors',
                            varias ? 'bg-indigo-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50',
                            RAIO,
                            FOCO,
                        )}
                    >
                        <i className={cls('fas mr-1.5', varias ? 'fa-rotate-left' : 'fa-layer-group')} aria-hidden="true" />
                        {varias ? t('Voltar a uma forma') : t('Várias formas')}
                    </button>
                </div>

                {varias ? (
                    <VariasFormas
                        formas={formas}
                        pagamentos={pagamentos}
                        porPagamentos={porPagamentos}
                        falta={falta}
                        excede={excede}
                        soma={somaDasFormas}
                        total={total}
                        aoJuntar={juntarForma}
                    />
                ) : (
                    <UmaForma
                        formas={formas}
                        forma={forma}
                        porForma={porForma}
                        recebido={recebido}
                        porRecebido={porRecebido}
                        montantesRapidos={montantesRapidos}
                        troco={troco}
                        falta={falta}
                        total={total}
                        aoConfirmar={confirmar}
                    />
                )}

                <label className="block">
                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {t('Observações (opcional)')}
                    </span>
                    <textarea
                        value={notas}
                        onChange={(e) => porNotas(e.target.value)}
                        rows={2}
                        placeholder={t('Alguma nota para esta venda…')}
                        className={cls('w-full border border-slate-200 px-3 py-2 text-sm', RAIO, FOCO)}
                    />
                </label>
            </div>
        </Modal>
    );
}

function UmaForma({
    formas,
    forma,
    porForma,
    recebido,
    porRecebido,
    montantesRapidos,
    troco,
    falta,
    total,
    aoConfirmar,
}: {
    formas: Array<{ valor: string; rotulo: string }>;
    forma: string;
    porForma: (v: string) => void;
    recebido: string;
    porRecebido: (v: string) => void;
    montantesRapidos: number[];
    troco: number;
    falta: number;
    total: number;
    aoConfirmar: () => void;
}) {
    return (
        <div className="space-y-3">
            {/* AS FORMAS COMO BOTÕES e não como um `select`. Ao balcão, abrir
                uma lista e escolher lá dentro são dois gestos onde podia ser um. */}
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {formas.map((f) => (
                    <button
                        key={f.valor}
                        type="button"
                        onClick={() => porForma(f.valor)}
                        aria-pressed={forma === f.valor}
                        className={cls(
                            'px-3 py-2.5 text-sm font-semibold transition-all duration-200 active:scale-95',
                            forma === f.valor
                                ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md'
                                : 'border border-slate-200 bg-white text-slate-600 hover:border-indigo-300',
                            RAIO,
                            FOCO,
                        )}
                    >
                        {f.rotulo}
                    </button>
                ))}
            </div>

            <div>
                <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <i className="fas fa-bolt mr-1.5 text-amber-500" aria-hidden="true" />
                    {t('Trocos rápidos')}
                </span>
                <div className="flex flex-wrap gap-1.5">
                    {/* O total certo primeiro: é o caso mais comum de todos. */}
                    <button
                        type="button"
                        onClick={() => porRecebido(String(total))}
                        className={cls(
                            'bg-emerald-100 px-3 py-2 text-xs font-bold text-emerald-700 transition-all duration-200 hover:bg-emerald-200 active:scale-95',
                            RAIO,
                            FOCO,
                        )}
                    >
                        {t('certo')}
                    </button>
                    {montantesRapidos.map((m) => (
                        <button
                            key={m}
                            type="button"
                            onClick={() => porRecebido(String(m))}
                            className={cls(
                                'border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition-all duration-200 hover:border-indigo-300 hover:bg-indigo-50 active:scale-95',
                                RAIO,
                                FOCO,
                            )}
                        >
                            {m >= 1000 ? `${m / 1000}K` : m}
                        </button>
                    ))}
                </div>
            </div>

            <label className="block">
                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {t('Valor recebido')}
                </span>
                <input
                    type="text"
                    inputMode="decimal"
                    value={recebido}
                    onChange={(e) => porRecebido(e.target.value)}
                    onFocus={(e) => e.target.select()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            aoConfirmar();
                        }
                    }}
                    aria-label={t('Valor recebido')}
                    className={cls(
                        'w-full border-2 border-slate-300 bg-white px-4 py-3 text-center text-3xl font-bold tabular-nums',
                        'focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200',
                        RAIO,
                        FOCO,
                    )}
                />
            </label>

            {/* O TROCO, ou o aviso de que não chega. Um dos dois está sempre à
                vista: é a conta que o operador faz de cabeça e é onde se engana. */}
            {falta > 0.005 ? (
                <div className={cls('border-2 border-red-200 bg-red-50 p-4 text-center', RAIO)} role="alert">
                    <p className="text-xs font-bold uppercase tracking-wide text-red-600">{t('Falta pagar')}</p>
                    <p className="text-3xl font-bold tabular-nums text-red-700">{kz(falta)}</p>
                </div>
            ) : (
                <div className={cls('border-2 border-emerald-200 bg-emerald-50 p-4 text-center', RAIO)}>
                    <p className="text-xs font-bold uppercase tracking-wide text-emerald-600">{t('Troco')}</p>
                    <p className="text-3xl font-bold tabular-nums text-emerald-700">{kz(troco)}</p>
                </div>
            )}
        </div>
    );
}

function VariasFormas({
    formas,
    pagamentos,
    porPagamentos,
    falta,
    excede,
    soma,
    total,
    aoJuntar,
}: {
    formas: Array<{ valor: string; rotulo: string }>;
    pagamentos: Pagamento[];
    porPagamentos: (f: (ps: Pagamento[]) => Pagamento[]) => void;
    falta: number;
    excede: boolean;
    soma: number;
    total: number;
    aoJuntar: () => void;
}) {
    return (
        <div className="space-y-2">
            {pagamentos.map((p, i) => (
                <div key={i} className={cls('flex items-center gap-2 border border-slate-200 bg-white p-2', RAIO)}>
                    <select
                        value={p.method}
                        onChange={(e) =>
                            porPagamentos((ps) => ps.map((x, j) => (j === i ? { ...x, method: e.target.value } : x)))
                        }
                        aria-label={t('Forma :n', { n: i + 1 })}
                        className={cls('flex-1 border border-slate-200 px-2 py-2 text-sm', RAIO, FOCO)}
                    >
                        {formas.map((f) => (
                            <option key={f.valor} value={f.valor}>
                                {f.rotulo}
                            </option>
                        ))}
                    </select>
                    <input
                        type="text"
                        inputMode="decimal"
                        value={p.amount}
                        onChange={(e) =>
                            porPagamentos((ps) =>
                                ps.map((x, j) => (j === i ? { ...x, amount: Number(e.target.value.replace(',', '.')) || 0 } : x)),
                            )
                        }
                        onFocus={(e) => e.target.select()}
                        aria-label={t('Valor da forma :n', { n: i + 1 })}
                        className={cls('w-32 border border-slate-200 px-2 py-2 text-right text-sm font-bold tabular-nums', RAIO, FOCO)}
                    />
                    <button
                        type="button"
                        onClick={() => porPagamentos((ps) => ps.filter((_, j) => j !== i))}
                        aria-label={t('Remover a forma :n', { n: i + 1 })}
                        className={cls('grid h-9 w-9 place-items-center text-red-500 hover:bg-red-50', RAIO, FOCO)}
                    >
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            ))}

            <Botao icone="fa-plus" onClick={aoJuntar} tom="suave" cor="primaria">
                {t('Adicionar pagamento')}
            </Botao>

            <div
                className={cls(
                    'border-2 p-3 text-center',
                    excede
                        ? 'border-red-200 bg-red-50'
                        : falta > 0.005
                          ? 'border-amber-200 bg-amber-50'
                          : 'border-emerald-200 bg-emerald-50',
                    RAIO,
                )}
                role={excede || falta > 0.005 ? 'alert' : 'status'}
            >
                {excede ? (
                    <p className="font-bold text-red-700">
                        {t('Excede o total em :valor', { valor: kz(soma - total) })}
                    </p>
                ) : falta > 0.005 ? (
                    <p className="font-bold text-amber-700">{t('Falta pagar :valor', { valor: kz(falta) })}</p>
                ) : (
                    <p className="font-bold text-emerald-700">
                        <i className="fas fa-check mr-2" aria-hidden="true" />
                        {t('As formas somam o total')}
                    </p>
                )}
            </div>
        </div>
    );
}
