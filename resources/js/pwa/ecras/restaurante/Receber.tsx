import { useState } from 'react';

import { t } from '@/i18n';

import { dinheiro, useAccao, useBaseViva, useEstadoDoMotor } from '../../ganchos';
import { lerMeta, type Cliente, type Registo } from '../../motor/base';
import { restaurante } from '../../motor/restaurante';
import { sync } from '../../motor/sincronizar';
import { numero } from '../../motor/util';
import { getClients } from '../../motor/vendas';
import { avisar } from '../../ui/Dialogos';
import { CAMPO, Folha, ROTULO } from '../../ui/Folha';
import { imprimirTalao, mensagemDe, type Recibo } from './comum';

type TipoDeDocumento = 'FR' | 'FT';

/**
 * A última escolha do documento e do pagamento fica para a conta seguinte,
 * como ficava no objecto do Alpine: numa sala a maior parte das contas fecha
 * da mesma maneira, e voltar a escolher em cada mesa é onde se erra.
 */
let ultimaEscolha: { document_type: TipoDeDocumento; payment_method_id: number | null } = { document_type: 'FR', payment_method_id: null };

/** O id de um cliente que o servidor conhece — os criados sem rede ainda não têm. */
function idDoServidor(cli: Cliente | undefined): number | null {
    if (!cli) return null;
    if (typeof cli.id === 'number') return cli.id;

    return /^\d+$/.test(String(cli.id)) ? Number(cli.id) : null;
}

/**
 * Com rede, espera-se pelo número fiscal: o cliente não devia sair com um
 * talão sem número quando havia internet. Sem rede, ou se demorar de mais,
 * segue como pendente — a fila trata.
 */
async function esperarPeloNumero(uuid: string, msLimite = 8000): Promise<Registo | null> {
    if (!navigator.onLine) return null;

    const fim = Date.now() + msLimite;

    void sync(false);

    while (Date.now() < fim) {
        const c = await restaurante.comanda(uuid);

        if (c?._invoice_number) return c;

        await new Promise((r) => setTimeout(r, 400));
    }

    return null;
}

/** MODAL: RECEBER — fechar a conta, escolher o documento, o pagamento e o cliente. */
export function FolhaReceber({ comanda, aoFechar, aoFechada }: {
    comanda: Registo;
    aoFechar: () => void;
    aoFechada: (recibo: Recibo) => void;
}) {
    const { online } = useEstadoDoMotor();

    // Os métodos de pagamento vêm na sincronização geral e ficam no aparelho:
    // o fecho de uma comanda precisa do ID do método para lançar o recebimento
    // na caixa certa.
    const metodos = useBaseViva<Registo[]>(async () => (await lerMeta<Registo[]>('payment_methods')) || [], [], []);
    const clientes = useBaseViva<Cliente[]>(async () => (await getClients()).slice(0, 200), [], []);

    const [tipo, setTipo] = useState<TipoDeDocumento>(ultimaEscolha.document_type);
    const [metodoEscolhido, setMetodo] = useState<number | null>(ultimaEscolha.payment_method_id);
    const [clienteId, setClienteId] = useState<string>(comanda.client_id ? String(comanda.client_id) : '');

    // O método por omissão é o primeiro — e só enquanto o escolhido existir na lista.
    const metodoId = metodos.some((m) => m.id === metodoEscolhido) ? metodoEscolhido : (metodos[0]?.id ?? null);

    const [confirmarRecebimento, aReceber] = useAccao(async () => {
        try {
            const cliente = clientes.find((x) => String(x.id) === clienteId);

            const fechada = await restaurante.receber(comanda.local_uuid, {
                document_type: tipo,
                client_id: idDoServidor(cliente),
                payment_method_id: tipo === 'FR' ? metodoId : null,
            });

            ultimaEscolha = { document_type: tipo, payment_method_id: metodoId };

            const comNumero = await esperarPeloNumero(fechada.local_uuid);

            aoFechada({
                local_uuid: fechada.local_uuid,
                numero: comNumero?._invoice_number || null,
                provisorio: fechada.provisional_number || null,
                total: numero(comNumero?.total ?? fechada.total),
            });
        } catch (e) {
            avisar(mensagemDe(e), 'erro');
        }
    });

    // A conta a ser fechada não se abandona a meio: o botão Voltar e o Escape esperam.
    const fechar = () => { if (!aReceber) aoFechar(); };

    return (
        <Folha aberta aoFechar={fechar} zIndex="z-[110]" largura="sm:max-w-md"
               titulo={comanda._server_number || t('Comanda offline')} subtitulo={t('Fechar conta')}
               icone="fa-cash-register" cor="from-slate-950 to-slate-800" fecharNoFundo={!aReceber}
               rodape={(
                   <div className="space-y-1">
                       <button type="button" onClick={() => void confirmarRecebimento()} disabled={aReceber}
                               className="pwa-toque w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 text-lg font-black shadow-lg shadow-emerald-600/20 disabled:opacity-50">
                           {aReceber
                               ? <><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('A processar…')}</>
                               : <><i className="fas fa-check mr-2" aria-hidden="true" />{t('Confirmar')}</>}
                       </button>
                       <button type="button" onClick={fechar} disabled={aReceber}
                               className="w-full p-2 text-sm font-bold text-slate-500 hover:text-slate-700 disabled:opacity-40 transition-colors">
                           <i className="fas fa-arrow-left mr-1" aria-hidden="true" />{t('Voltar à comanda')}
                       </button>
                   </div>
               )}>
            <div className="space-y-4">
                <div className="flex items-center justify-between rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 text-white p-3 shadow-inner">
                    <span className="text-sm opacity-80"><i className="fas fa-coins mr-1.5 text-orange-300" aria-hidden="true" />{t('Total a receber')}</span>
                    <strong className="text-2xl text-orange-300 tabular-nums">{dinheiro(comanda.total)} Kz</strong>
                </div>

                <div>
                    <span className={ROTULO} id="restaurante-documento">{t('Documento')}</span>
                    <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="restaurante-documento">
                        {([['FR', t('Fatura-Recibo'), 'fa-receipt'], ['FT', t('Fatura'), 'fa-file-invoice']] as const).map(([valor, rotulo, icone]) => (
                            <button key={valor} type="button" role="radio" aria-checked={tipo === valor} onClick={() => setTipo(valor)}
                                    className={`pwa-toque rounded-xl py-2.5 text-sm font-bold border-2 transition-colors ${tipo === valor ? 'bg-emerald-600 border-emerald-600 text-white shadow-md shadow-emerald-600/20' : 'bg-slate-100 border-slate-100 text-slate-600 hover:border-emerald-200'}`}>
                                <i className={`fas ${icone} mr-1.5`} aria-hidden="true" />{rotulo}
                            </button>
                        ))}
                    </div>
                    {/* A FT fica por pagar de propósito: é uma conta a receber,
                        não dinheiro em caixa. Dizê-lo aqui evita que se escolha
                        a errada por hábito. */}
                    {tipo === 'FT' && (
                        <p className="pwa-entra text-[11px] text-amber-600 mt-1.5">
                            <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                            {t('A fatura fica por liquidar — sem entrada de dinheiro em caixa.')}
                        </p>
                    )}
                </div>

                {tipo === 'FR' && (
                    <div className="pwa-entra">
                        <label htmlFor="restaurante-pagamento" className={ROTULO}>{t('Pagamento')}</label>
                        <select id="restaurante-pagamento" name="payment_method_id" value={metodoId ?? ''}
                                onChange={(e) => setMetodo(e.target.value === '' ? null : Number(e.target.value))}
                                className={`${CAMPO} focus:border-emerald-500`}>
                            {metodos.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                        </select>
                    </div>
                )}

                <div>
                    <label htmlFor="restaurante-cliente" className={ROTULO}>{t('Cliente (opcional)')}</label>
                    <select id="restaurante-cliente" name="client_id" value={clienteId} onChange={(e) => setClienteId(e.target.value)}
                            className={`${CAMPO} focus:border-emerald-500`}>
                        <option value="">{t('Consumidor Final')}</option>
                        {/* UM CLIENTE CRIADO SEM REDE AINDA NÃO TEM ID DO SERVIDOR, e a
                            comanda só leva `client_id`. O Alpine fazia `parseInt` ao
                            «local_…», dava NaN, e a factura saía em silêncio para
                            Consumidor Final. Aparece, mas não se escolhe até subir. */}
                        {clientes.map((cli) => {
                            const local = idDoServidor(cli) === null;

                            return (
                                <option key={String(cli.id)} value={String(cli.id)} disabled={local}>
                                    {local ? `${cli.name} · ${t('Por sincronizar')}` : cli.name}
                                </option>
                            );
                        })}
                    </select>
                </div>

                {/* O número fiscal sai do servidor. Prometê-lo aqui seria
                    mentira: offline não há numeração da AGT que se possa
                    inventar, e quem está na sala tem de saber que o talão
                    definitivo chega quando a rede voltar. */}
                <p className={`text-[11px] rounded-xl p-3 transition-colors ${online ? 'text-slate-500 bg-slate-50' : 'text-amber-700 bg-amber-50'}`}>
                    <i className={`fas ${online ? 'fa-circle-info text-slate-400' : 'fa-wifi text-amber-500'} mr-1`} aria-hidden="true" />
                    {online
                        ? t('O documento é emitido agora e recebe já o número fiscal.')
                        : t('Sem rede: a conta fica fechada aqui e o número fiscal é atribuído quando sincronizar.')}
                </p>
            </div>
        </Folha>
    );
}

/** MODAL: CONTA FECHADA — o talão, e o número fiscal quando chegar. */
export function FolhaRecibo({ recibo, aoFechar }: { recibo: Recibo; aoFechar: () => void }) {
    // O número fiscal chega segundos depois de o cliente pagar, e quem está
    // com o talão na mão ainda ali está. A comanda lê-se VIVA: o provisório
    // passa a definitivo neste modal sozinho, e poupa-se uma reimpressão.
    const viva = useBaseViva<Registo | undefined>(() => restaurante.comanda(recibo.local_uuid), [recibo.local_uuid], undefined);

    const numeroFiscal: string | null = viva?._invoice_number || recibo.numero;
    const total = viva?.total ?? recibo.total;

    const [imprimir, aImprimir] = useAccao(() => imprimirTalao(recibo.local_uuid));

    return (
        <Folha aberta aoFechar={aoFechar} zIndex="z-[120]" largura="sm:max-w-sm" semCabecalho fecharNoFundo={false}
               titulo={numeroFiscal ? t('Documento emitido') : t('Guardado neste aparelho')}>
            <div className={`-mx-5 -mt-5 p-7 text-center text-white transition-colors duration-500 bg-gradient-to-br ${numeroFiscal ? 'from-emerald-500 to-teal-600' : 'from-amber-400 to-orange-500'}`}>
                <span key={numeroFiscal ? 'ok' : 'espera'} className="pwa-cresce inline-flex w-20 h-20 rounded-full bg-white/20 items-center justify-center">
                    <i className={`fas text-5xl ${numeroFiscal ? 'fa-circle-check' : 'fa-clock'}`} aria-hidden="true" />
                </span>
                <p className="mt-3 text-[10px] font-black uppercase tracking-widest">
                    {numeroFiscal ? t('Documento emitido') : t('Guardado neste aparelho')}
                </p>
                <p className="text-xl font-black break-all">{numeroFiscal || recibo.provisorio || t('Sobe quando houver rede')}</p>
            </div>

            <div className="pt-5 space-y-3">
                <p className="text-center text-2xl font-black text-slate-800 tabular-nums">{dinheiro(total)} Kz</p>

                {/* O TALÃO É O QUE O CLIENTE LEVA.
                    Sem rede sai provisório, com o aviso a dizê-lo — é o mesmo
                    papel do balcão, e não uma versão de segunda. Quando a
                    comanda subir, reimprime-se com número, ATCUD e QR. */}
                <button type="button" onClick={() => void imprimir()} disabled={aImprimir}
                        className="pwa-toque w-full rounded-xl bg-gradient-to-r from-slate-900 to-slate-800 hover:from-slate-800 hover:to-slate-700 text-white p-4 font-black shadow-lg disabled:opacity-60">
                    <i className={`fas ${aImprimir ? 'fa-spinner fa-spin' : 'fa-print'} mr-2 text-orange-300`} aria-hidden="true" />{t('Imprimir talão')}
                </button>

                {!numeroFiscal && (
                    <p className="text-[11px] text-amber-700 text-center">
                        {t('Sai como provisório. Reimprima depois de sincronizar para levar o número fiscal.')}
                    </p>
                )}

                <button type="button" onClick={aoFechar}
                        className="pwa-toque w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 hover:bg-slate-50 p-3 font-bold text-slate-600 transition-colors">
                    {t('Continuar')}<i className="fas fa-arrow-right ml-2" aria-hidden="true" />
                </button>
            </div>
        </Folha>
    );
}
