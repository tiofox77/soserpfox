import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { turnos, type TipoDeFecho, type Turno } from '@/api/turnos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Modal } from '@/ui/Modal';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa, SemNada, cascata } from './faixa';
import { EscolhaDoFecho, Mosaico, PapelDoFecho, VendasPorProduto } from './VendasDoTurno';

/**
 * O TURNO DO BALCÃO: abrir, ver o que entrou, fechar com o dinheiro
 * CONTADO. Nunca se pré-preenche o contado com o esperado — senão a
 * diferença de caixa dá sempre zero e as quebras nunca aparecem. Tudo pelo
 * mesmo serviço do ecrã de sempre (`TurnosDoPos`).
 */
const kz = (v: number | null | undefined) => `${new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v) || 0)} Kz`;

export default function TurnosDoPos() {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['turnos', 'estado'], queryFn: turnos.estado });
    const [abrirModal, porAbrirModal] = useState(false);
    const [fecharModal, porFecharModal] = useState(false);
    const [fechado, porFechado] = useState<{ turno: Turno; tipo: TipoDeFecho; reimpressao?: boolean } | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['turnos'] }); };

    if (q.isPending) return <Carregando linhas={6} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o turno')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const { turno, ultimo_fechado, caixa } = q.data;
    const reimprimir = () => ultimo_fechado && porFechado({ turno: ultimo_fechado, tipo: 'resumido', reimpressao: true });

    return (
        <div className="space-y-4" data-turnos>
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Faixa
                icone="fa-cash-register"
                titulo={t('POS — Ponto de Venda')}
                subtitulo={t('Sistema de Caixa e Turnos')}
                accoes={
                    <>
                        {ultimo_fechado && <button type="button" onClick={reimprimir} className={ACCAO_DA_FAIXA}><i className="fas fa-print" aria-hidden="true" />{t('Reimprimir último fecho')}</button>}
                        <a href="/invoicing/pos/shift-history" className={ACCAO_DA_FAIXA}><i className="fas fa-clock-rotate-left" aria-hidden="true" />{t('Histórico')}</a>
                        {turno
                            ? <Botao cor="perigo" tom="solida" icone="fa-lock" onClick={() => porFecharModal(true)}>{t('Fechar turno')}</Botao>
                            : <Botao cor="bom" tom="solida" icone="fa-unlock" onClick={() => porAbrirModal(true)}>{t('Abrir turno')}</Botao>}
                    </>
                }
            >
                {/* O ESTADO DO TURNO VIVE NO CABEÇALHO, que é onde se olha
                    primeiro. Cada etiqueta leva ícone: o turno aberto não pode
                    ser só «o verde». */}
                <div className="flex flex-wrap items-center gap-2" data-estado-turno>
                    {turno
                        ? <EstadoNaFaixa icone="fa-circle-play">{t('Turno :numero aberto às :hora', { numero: turno.shift_number, hora: turno.opened_at ?? '' })}</EstadoNaFaixa>
                        : <EstadoNaFaixa icone="fa-circle-stop">{t('Sem turno aberto')}</EstadoNaFaixa>}
                    {caixa
                        ? <EstadoNaFaixa icone="fa-vault">{t('Caixa :nome · :estado', { nome: caixa.nome, estado: caixa.estado === 'open' ? t('aberta') : t('fechada') })}</EstadoNaFaixa>
                        : <EstadoNaFaixa icone="fa-vault">{t('Sem caixa atribuída')}</EstadoNaFaixa>}
                </div>
            </Faixa>

            {!turno && (
                <div className={CARTAO}>
                    <SemNada
                        icone="fa-cash-register"
                        titulo={t('Nenhum turno aberto')}
                        frase={t('Abra o turno com o dinheiro que está na gaveta. As vendas do POS ficam ligadas a ele até o fechar.')}
                        accao={(
                            <div className="flex flex-wrap items-center justify-center gap-2">
                                <Botao cor="bom" tom="solida" altura="grande" icone="fa-play" onClick={() => porAbrirModal(true)}>{t('Abrir novo turno')}</Botao>
                                {ultimo_fechado && <Botao cor="neutra" tom="suave" altura="grande" icone="fa-print" onClick={reimprimir}>{t('Reimprimir o fecho do turno :numero', { numero: ultimo_fechado.shift_number })}</Botao>}
                            </div>
                        )}
                    />
                </div>
            )}

            {turno && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-resumo>
                    {/* A COR SEGUE O SIGNIFICADO: o dinheiro em verde, o que se
                        espera na gaveta em âmbar (é o que se vai contar), e as
                        contagens no azul da casa. */}
                    {([
                        [t('Saldo inicial'), kz(turno.opening_balance), 'fa-wallet', 'azul'],
                        [t('Vendas em dinheiro'), kz(turno.cash_sales), 'fa-money-bill-wave', 'verde'],
                        [t('Cartão / TPA'), kz(turno.card_sales), 'fa-credit-card', 'indigo'],
                        [t('Transferência'), kz(turno.bank_transfer_sales), 'fa-building-columns', 'roxo'],
                        [t('Outros'), kz(turno.other_sales), 'fa-ellipsis', 'cinza'],
                        /*
                         * O QUE FICOU, e não o que passou. Uma devolução paga
                         * da gaveta tira dinheiro dela: o turno só sabia de
                         * facturas, e o total lia-se igual com ou sem
                         * devoluções.
                         */
                        [t('Total de vendas'), kz(turno.net_sales), 'fa-chart-line', 'verde'],
                        ...(turno.credit_notes_amount > 0
                            ? [[t('Devolvido'), kz(turno.credit_notes_amount), 'fa-rotate-left', 'ambar']] as Array<[string, string, string, TomDoCartao]>
                            : []),
                        [t('Documentos'), t(':facturas facturas · :recibos recibos', { facturas: turno.total_invoices, recibos: turno.total_receipts }), 'fa-file-invoice', 'azul'],
                        // Os documentos a prazo dos Documentos: no fecho, fora da gaveta.
                        ...(turno.a_prazo?.quantos
                            ? [[t('A prazo (fora da gaveta)'), kz(turno.a_prazo.valor), 'fa-hourglass-half', 'cinza']] as Array<[string, string, string, TomDoCartao]>
                            : []),
                        [t('Esperado em caixa'), kz(turno.expected_cash), 'fa-vault', 'ambar'],
                    ] as Array<[string, string, string, TomDoCartao]>).map(([r, v, icone, tom], i) => (
                        <div key={r} className="entra" style={cascata(i)}>
                            <CartaoNumero rotulo={r} valor={v} icone={icone} tom={tom} />
                        </div>
                    ))}
                </div>
            )}

            {/* O QUE SE VENDEU ATÉ AGORA, artigo a artigo — o mesmo que o
                fecho com produtos leva para o papel. */}
            {turno && (
                <Cartao titulo={t('Vendas por produto')} subtitulo={t('O que este turno já vendeu, artigo a artigo')} icone="fa-boxes-stacked">
                    <VendasPorProduto id={turno.id} />
                </Cartao>
            )}

            {turno && (
                <Cartao titulo={t('Movimentos do turno (:quantos)', { quantos: turno.movimentos_n ?? 0 })} icone="fa-list" semPadding>
                    <div className="max-h-96 overflow-auto">
                        <table className="min-w-[680px] w-full text-sm">
                            <thead className="sticky top-0 z-10"><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Quando')}</th><th className="px-4 py-3 font-semibold">{t('Tipo')}</th><th className="px-4 py-3 font-semibold">{t('Documento')}</th><th className="px-4 py-3 font-semibold">{t('Meio')}</th><th className="px-4 py-3 text-right font-semibold">{t('Valor')}</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {(turno.movimentos ?? []).length === 0 && <tr><td colSpan={5}><SemNada icone="fa-inbox" titulo={t('Ainda não houve movimentos.')} frase={t('Cada venda do balcão entra aqui assim que é fechada.')} /></td></tr>}
                                {(turno.movimentos ?? []).map((m, i) => (
                                    <tr key={m.id} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}><td className="whitespace-nowrap px-4 py-2 text-slate-600">{m.quando}</td><td className="px-4 py-2">{m.tipo_rotulo}</td><td className="px-4 py-2 font-mono text-xs">{m.reference_number ?? '—'}</td><td className="px-4 py-2">{m.meio}</td><td className="px-4 py-2 text-right tabular-nums">{kz(m.amount)}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {abrirModal && <AbrirTurno aoFechar={() => porAbrirModal(false)} feito={(m) => { feito(m); porAbrirModal(false); }} />}
            {fecharModal && turno && <FecharTurno turno={turno} aoFechar={() => porFecharModal(false)} feito={(t, m, tipo) => { feito(m); porFecharModal(false); porFechado({ turno: t, tipo }); }} />}

            {fechado && <TurnoFechado turno={fechado.turno} tipo={fechado.tipo} reimpressao={fechado.reimpressao} aoFechar={() => porFechado(null)} />}
        </div>
    );
}

function AbrirTurno({ aoFechar, feito }: { aoFechar: () => void; feito: (m: string) => void }) {
    const [saldo, porSaldo] = useState('0');
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const abrir = useMutation({ mutationFn: () => turnos.abrir({ opening_balance: Number(saldo.replace(',', '.')) || 0, opening_notes: notas || undefined }), onSuccess: (r) => feito(r.message), onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Abrir turno')} rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-unlock" aTrabalhar={abrir.isPending} onClick={() => abrir.mutate()}>{t('Abrir')}</Botao></>}>
            <AvisoDeErro erro={abrir.error} />
            <div className="grid gap-4">
                <Campo etiqueta={t('Saldo inicial em caixa (Kz)')} erro={erros.opening_balance} obrigatorio><input type="number" min="0" step="0.01" value={saldo} onChange={(e) => porSaldo(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta={t('Notas')} erro={erros.opening_notes}><textarea value={notas} onChange={(e) => porNotas(e.target.value)} rows={2} className={entrada} /></Campo>
            </div>
        </Modal>
    );
}

/**
 * O TURNO FECHADO: a conferência da caixa e, no fecho com produtos, o que se
 * vendeu artigo a artigo — com o papel no formato escolhido à frente.
 */
function TurnoFechado({ turno, tipo, reimpressao = false, aoFechar }: { turno: Turno; tipo: TipoDeFecho; reimpressao?: boolean; aoFechar: () => void }) {
    const diferenca = turno.cash_difference ?? 0;

    return (
        <Modal aberto aoFechar={aoFechar}
            titulo={reimpressao ? t('Reimprimir o fecho — turno :numero', { numero: turno.shift_number }) : t('Turno :numero fechado', { numero: turno.shift_number })}
            subtitulo={reimpressao ? (turno.closed_at ? t('Fechado em :quando', { quando: turno.closed_at }) : undefined) : (tipo === 'produtos' ? t('Fecho com produtos') : t('Fecho resumido'))}
            icone={reimpressao ? 'fa-print' : 'fa-lock'} cor="bom"
            largura={tipo === 'produtos' ? 'xl' : 'lg'} rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}>
            <div className="space-y-4 text-sm text-slate-700" data-pos-fecho data-tipo-fecho={tipo}>
                <div className="grid gap-2 sm:grid-cols-3">
                    <Mosaico i={0} rotulo={t('Esperado em caixa')} valor={kz(turno.expected_cash)} icone="fa-vault" tom="ambar" />
                    <Mosaico i={1} rotulo={t('Contado')} valor={kz(turno.actual_cash)} icone="fa-hand-holding-dollar" tom="azul" />
                    <Mosaico i={2} rotulo={t('Diferença')} valor={kz(turno.cash_difference)} icone={diferenca < 0 ? 'fa-triangle-exclamation' : 'fa-scale-balanced'}
                        tom={diferenca < 0 ? 'vermelho' : diferenca > 0 ? 'ambar' : 'verde'} />
                </div>

                <PapelDoFecho turno={turno} tipo={tipo} />

                <div className="grid gap-x-6 gap-y-1 tabular-nums sm:grid-cols-2">
                    {([
                        [t('Dinheiro'), turno.cash_sales], [t('Cartão / TPA'), turno.card_sales],
                        [t('Transferência'), turno.bank_transfer_sales], [t('Outros'), turno.other_sales],
                        ...(turno.credit_notes_amount > 0 ? [[t('Devolvido'), -turno.credit_notes_amount]] as Array<[string, number]> : []),
                        [t('Total de vendas'), turno.net_sales],
                        ...(turno.a_prazo?.quantos ? [[t('A prazo (fora da gaveta)'), turno.a_prazo.valor]] as Array<[string, number]> : []),
                    ] as Array<[string, number]>).map(([r, v]) => (
                        <p key={r} className="flex justify-between gap-3 border-b border-dashed border-slate-200 py-1"><span className="text-slate-600">{r}</span><strong>{kz(v)}</strong></p>
                    ))}
                </div>

                {tipo === 'produtos' && <VendasPorProduto id={turno.id} />}
            </div>
        </Modal>
    );
}

function FecharTurno({ turno, aoFechar, feito }: { turno: Turno; aoFechar: () => void; feito: (t: Turno, m: string, tipo: TipoDeFecho) => void }) {
    const [tipo, porTipo] = useState<TipoDeFecho | null>(null);
    const [contado, porContado] = useState('');
    const [notas, porNotas] = useState('');
    const [motivo, porMotivo] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const fechar = useMutation({ mutationFn: () => turnos.fechar({ actual_cash: Number(contado.replace(',', '.')), closing_notes: notas || undefined, difference_reason: motivo || undefined }), onSuccess: (r) => feito(r.turno, r.message, tipo ?? 'resumido'), onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}) });
    const diferenca = contado === '' ? null : (Number(contado.replace(',', '.')) || 0) - turno.expected_cash;
    const falta = !tipo ? t('Escolha o tipo de fecho.') : contado === '' ? t('Escreva o dinheiro contado.') : null;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Fechar o turno :numero', { numero: turno.shift_number })} icone="fa-lock" cor="perigo" largura={tipo === 'produtos' ? 'lg' : 'md'}
            rodape={(
                <>
                    {falta && <span className="mr-auto text-xs text-slate-500"><i className="fas fa-circle-info mr-1" aria-hidden="true" />{falta}</span>}
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="perigo" tom="solida" icone="fa-lock" aTrabalhar={fechar.isPending} disabled={falta !== null} onClick={() => fechar.mutate()}>{t('Fechar turno')}</Botao>
                </>
            )}>
            <AvisoDeErro erro={fechar.error} />
            <div className="mb-5 space-y-4">
                <EscolhaDoFecho valor={tipo} aoMudar={porTipo} />
                {tipo === 'produtos' && (
                    <div className="entra">
                        <VendasPorProduto id={turno.id} compacto />
                    </div>
                )}
            </div>
            <p className="mb-4 text-sm text-slate-600">{t('Esperado em caixa:')} <strong className="tabular-nums">{kz(turno.expected_cash)}</strong> {turno.saidas_da_gaveta || turno.entradas_na_gaveta
                ? t('(saldo inicial :saldo + dinheiro :dinheiro + entradas na gaveta :entradas − saídas da gaveta :saidas). Conte a gaveta e escreva o que lá está.', { saldo: kz(turno.opening_balance), dinheiro: kz(turno.cash_sales), entradas: kz(turno.entradas_na_gaveta), saidas: kz(turno.saidas_da_gaveta) })
                : t('(saldo inicial :saldo + dinheiro :dinheiro). Conte a gaveta e escreva o que lá está.', { saldo: kz(turno.opening_balance), dinheiro: kz(turno.cash_sales) })}</p>
            <div className="grid gap-4">
                <Campo etiqueta={t('Dinheiro contado (Kz)')} erro={erros.actual_cash} obrigatorio><input type="number" min="0" step="0.01" value={contado} onChange={(e) => porContado(e.target.value)} placeholder="0,00" className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                {diferenca !== null && <p className={cls('text-sm font-semibold tabular-nums', diferenca < 0 ? 'text-red-700' : diferenca > 0 ? 'text-amber-700' : 'text-emerald-700')} data-diferenca>{t('Diferença:')} {kz(diferenca)}</p>}
                {diferenca !== null && Math.abs(diferenca) >= 0.01 && <Campo etiqueta={t('Motivo da diferença')} erro={erros.difference_reason}><input value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada} /></Campo>}
                <Campo etiqueta={t('Notas de fecho')} erro={erros.closing_notes}><textarea value={notas} onChange={(e) => porNotas(e.target.value)} rows={2} className={entrada} /></Campo>
            </div>
        </Modal>
    );
}
