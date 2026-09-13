import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { fecho, type ConsumoParaLancar, type OpcoesDoFecho } from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, dataHora, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t, tPartes } from '@/i18n';

/**
 * O FOLIO — a conta da estada enquanto ela dura.
 *
 * Minibar, lavandaria, restaurante, transferes: o que o hóspede vai
 * consumindo, para no check-out sair tudo na mesma factura. Antes, os consumos
 * lançados aqui NUNCA eram facturados — o check-out só olhava para o que se
 * escrevia nele, e o hóspede saía sem pagar o que tinha consumido.
 *
 * O FOLIO FECHA COM A ESTADA. Depois do check-out continuava a aceitar
 * consumos: o total subia, o pagamento caía de «Pago» para «Parcial», e ficava
 * um saldo de um hóspede que já tinha ido embora — sem relação nenhuma com a
 * factura emitida.
 */

const VAZIO: ConsumoParaLancar = {
    category: 'minibar', description: '', quantity: '1', unit_price: '0', notes: '',
};

export default function Folio({ id }: { id: number }) {
    const cache = useQueryClient();

    const [formulario, porFormulario] = useState<ConsumoParaLancar | null>(null);
    const [aApagar, porAApagar] = useState<number | null>(null);
    const [filtro, porFiltro] = useState('');
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['hotel', 'fecho', 'opcoes'], queryFn: fecho.opcoes, staleTime: 5 * 60_000 });

    const folio = useQuery({
        queryKey: ['hotel', 'folio', id],
        queryFn: () => fecho.conta(id),
        enabled: opcoes.isSuccess,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['hotel', 'folio', id] });

    const lancar = useMutation({
        mutationFn: (dados: ConsumoParaLancar) => fecho.lancar(id, dados),
        onSuccess: (r) => { invalidar(); porFormulario(null); porRecado(r.message); },
    });

    const apagar = useMutation({
        mutationFn: (consumo: number) => fecho.apagarConsumo(id, consumo),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending || folio.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;
    if (folio.isError) return <Falhou erro={folio.error} />;

    const o = opcoes.data;
    const f = folio.data;
    const r = f.reserva;

    const consumos = filtro ? f.consumos.filter((c) => c.categoria === filtro) : f.consumos;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Folio :n', { n: r.numero })}
                subtitulo={t(':hospede · quarto :quarto', { hospede: r.hospede, quarto: r.quarto ?? '—' })}
                icone="fa-list-ul"
                cor="roxo"
                accoes={
                    <>
                        <a href={`/hotel/reservations/${r.id}/folio-pdf`} target="_blank" rel="noreferrer" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-print" aria-hidden="true" />
                            {t('Imprimir')}
                        </a>
                        {f.aberto && o.permissoes.pode_lancar && (
                            <button type="button" onClick={() => porFormulario({ ...VAZIO })} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                                {t('Lançar consumo')}
                            </button>
                        )}
                        {f.aberto && r.estado === 'checked_in' && (
                            <a href={`/hotel/checkout/${r.id}`} className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-right-from-bracket" aria-hidden="true" />
                                {t('Check-out')}
                            </a>
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

            {!f.aberto && f.porque_fechou && (
                <div role="status" className={cls('border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700', RAIO)}>
                    <i className="fas fa-lock mr-2 text-slate-400" aria-hidden="true" />
                    {f.porque_fechou}
                </div>
            )}

            <AvisoDeErro erro={apagar.error} />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Alojamento')} tom="indigo" icone="fa-bed"
                    nota={t(':n noite(s) × :taxa Kz', { n: r.noites, taxa: kz(r.taxa) })}
                    valor={`${kz(f.conta.alojamento)} Kz`} />
                <CartaoNumero aspecto="claro" rotulo={t('Consumos')} tom="roxo" icone="fa-list-ul"
                    nota={t(':n lançamento(s)', { n: f.consumos.length })}
                    valor={`${kz(f.conta.consumos)} Kz`} />
                <CartaoNumero aspecto="claro" rotulo={t('Total com imposto')} tom="azul" icone="fa-receipt"
                    nota={t('imposto: :v Kz', { v: kz(f.conta.imposto) })}
                    valor={`${kz(f.conta.total)} Kz`} />
                <CartaoNumero aspecto="claro" rotulo={t('Por receber')}
                    tom={f.conta.por_receber > 0 ? 'vermelho' : 'verde'} icone="fa-money-bill-wave"
                    nota={t('pago: :v Kz', { v: kz(f.conta.pago) })}
                    valor={`${kz(f.conta.por_receber)} Kz`} />
            </div>

            {f.por_categoria.length > 0 && (
                <div className={cls(CARTAO, 'p-4')}>
                    <h2 className="mb-3 text-sm font-bold text-slate-800">{t('Por categoria')}</h2>
                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={() => porFiltro('')}
                            className={cls(
                                'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                'hover:-translate-y-0.5', RAIO, FOCO,
                                filtro === '' ? 'border-purple-500 bg-purple-50 text-purple-700 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300',
                            )}>
                            <i className="fas fa-layer-group" aria-hidden="true" />
                            {t('Todos')}
                            <span className="rounded-full bg-slate-100 px-1.5 text-xs tabular-nums text-slate-500">
                                {f.consumos.length}
                            </span>
                        </button>

                        {f.por_categoria.map((c) => (
                            <button key={c.categoria} type="button" onClick={() => porFiltro(c.categoria)}
                                className={cls(
                                    'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                    'hover:-translate-y-0.5', RAIO, FOCO,
                                    filtro === c.categoria ? 'border-purple-500 bg-purple-50 text-purple-700 shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300',
                                )}>
                                <i className={cls('fas', c.icone)} aria-hidden="true" />
                                {c.rotulo}
                                <span className="tabular-nums opacity-70">{kz(c.total)} Kz</span>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className={cls(CARTAO, 'overflow-hidden')}>
                {consumos.length === 0 ? (
                    <SemNada
                        icone="fa-receipt"
                        titulo={t('Nenhum consumo')}
                        frase={f.aberto
                            ? t('Lance o minibar, a lavandaria ou o restaurante — sai tudo na factura do check-out.')
                            : t('Esta estada fechou sem consumos lançados.')}
                        accao={f.aberto && o.permissoes.pode_lancar && (
                            <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porFormulario({ ...VAZIO })}>
                                {t('Lançar consumo')}
                            </Botao>
                        )}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-4 py-3 text-left">{t('Quando')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Consumo')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Qtd')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Preço')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Total')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Lançou')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {consumos.map((c, i) => (
                                    <tr key={c.id} className="entra transition-colors duration-150 hover:bg-purple-50/40"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-xs tabular-nums text-slate-500">
                                            {c.quando ? dataHora(c.quando) : '—'}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="block font-semibold text-slate-800">
                                                <i className={cls('fas mr-2 text-slate-400', c.icone)} aria-hidden="true" />
                                                {c.descricao}
                                            </span>
                                            <span className="block text-xs text-slate-400">{c.categoria_rotulo}</span>
                                            {c.notas && <span className="block text-xs italic text-slate-400">{c.notas}</span>}
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-600">{c.quantidade}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-600">{kz(c.preco)}</td>
                                        <td className="px-4 py-2.5 text-right font-bold tabular-nums text-slate-800">{kz(c.total)}</td>
                                        <td className="px-4 py-2.5 text-xs text-slate-400">{c.quem ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {f.aberto && o.permissoes.pode_lancar && (
                                                <button type="button" onClick={() => porAApagar(c.id)}
                                                    title={t('Apagar')} aria-label={t('Apagar: :nome', { nome: c.descricao })}
                                                    className={cls('p-2 text-slate-400 transition-all hover:scale-110 hover:text-red-600', RAIO, FOCO)}>
                                                    <i className="fas fa-trash" aria-hidden="true" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-slate-300 bg-slate-50 font-bold text-slate-900">
                                    <td className="px-4 py-3" colSpan={4}>{t('Consumos')}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{kz(f.conta.consumos)} Kz</td>
                                    <td colSpan={2} />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>

            {formulario && (
                <LancarConsumo
                    o={o}
                    valores={formulario}
                    aGravar={lancar.isPending}
                    erro={lancar.error}
                    aoMudar={porFormulario}
                    aoFechar={() => porFormulario(null)}
                    aoGravar={() => lancar.mutate(formulario)}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar consumo')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar !== null && apagar.mutate(aApagar)}>
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {tPartes('Vai apagar :nome. Não há volta.', {
                        nome: <strong>{f.consumos.find((c) => c.id === aApagar)?.descricao ?? ''}</strong>,
                    })}
                </p>
            </Modal>
        </div>
    );
}

function LancarConsumo({ o, valores, aGravar, erro, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDoFecho;
    valores: ConsumoParaLancar;
    aGravar: boolean;
    erro: unknown;
    aoMudar: (v: ConsumoParaLancar) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const mudar = (campo: keyof ConsumoParaLancar, valor: string) => aoMudar({ ...valores, [campo]: valor });
    const daApi = erro instanceof ErroDaApi ? erro : null;
    const total = (Number(valores.quantity) || 0) * (Number(valores.unit_price) || 0);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Lançar consumo')}
            icone="fa-plus"
            cor="roxo"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {t('Lançar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <div className="mb-3 flex flex-wrap gap-2">
                {o.categorias.map((c) => (
                    <button key={c.valor} type="button" onClick={() => mudar('category', c.valor)}
                        className={cls(
                            'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                            'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                            valores.category === c.valor
                                ? 'border-purple-500 bg-purple-50 text-purple-700 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300',
                        )}>
                        <i className={cls('fas', c.icone)} aria-hidden="true" />
                        {c.rotulo}
                    </button>
                ))}
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Descrição')} obrigatorio erro={daApi?.erros.description} className="sm:col-span-2">
                    <input value={valores.description} className={entrada}
                        placeholder={t('Ex.: 2 águas do minibar')}
                        onChange={(e) => mudar('description', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Quantidade')} obrigatorio erro={daApi?.erros.quantity}>
                    <input type="number" min={0.01} step="0.01" value={valores.quantity}
                        className={cls(entrada, 'text-right tabular-nums')}
                        onChange={(e) => mudar('quantity', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Preço unitário (Kz)')} obrigatorio erro={daApi?.erros.unit_price}>
                    <input type="number" min={0} step="0.01" value={valores.unit_price}
                        className={cls(entrada, 'text-right tabular-nums')}
                        onChange={(e) => mudar('unit_price', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Notas')} erro={daApi?.erros.notes} className="sm:col-span-2">
                    <input value={valores.notes} className={entrada}
                        onChange={(e) => mudar('notes', e.target.value)} />
                </Campo>
            </div>

            {total > 0 && (
                <p className={cls('mt-3 border border-purple-200 bg-purple-50 p-3 text-sm text-purple-900', RAIO)}>
                    <i className="fas fa-calculator mr-2" aria-hidden="true" />
                    {t('Total do lançamento:')} <strong className="tabular-nums">{kz(total)} Kz</strong>
                    <span className="ml-2 text-xs text-purple-700">{t('(o imposto entra no fecho)')}</span>
                </p>
            )}
        </Modal>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o folio')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
