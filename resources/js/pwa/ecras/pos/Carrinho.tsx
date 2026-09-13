import { useEffect, useState } from 'react';

import { t, tn } from '@/i18n';

import { dinheiro, kz } from '../../ganchos';
import { CrachaDoTurno } from '../../turno/Turno';
import type { CodigoDoMetodo, LinhaDoCarrinho } from './comum';
import type { ControloDoPos } from './usePos';

/**
 * O CARRINHO — coluna à direita no ecrã largo, folha que sobe no telemóvel.
 *
 * O conteúdo é o mesmo nas duas formas; muda só a caixa por fora (ver `Pos.tsx`).
 */
export function Carrinho({ pos, folha = false }: { pos: ControloDoPos; folha?: boolean }) {
    const { turno: c } = pos;
    const temItens = pos.carrinho.length > 0;

    return (
        <>
            {/* Cabeçalho do carrinho */}
            <div className="shrink-0 px-4 pt-3 pb-2 border-b border-gray-100">
                <div className="flex items-center justify-between gap-2">
                    <h2 className="text-lg font-bold text-gray-900 flex items-center gap-2 min-w-0">
                        <span className="w-8 h-8 shrink-0 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 text-white flex items-center justify-center shadow-sm">
                            <i className="fas fa-cart-shopping text-sm" aria-hidden="true" />
                        </span>
                        {t('Carrinho')}
                        {temItens && (
                            <span key={pos.contagem} className="pwa-cresce text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full whitespace-nowrap">
                                {tn(':n item|:n itens', pos.contagem, { n: pos.contagem })}
                            </span>
                        )}
                    </h2>
                    <div className="flex items-center gap-1 shrink-0">
                        {temItens && (
                            <button type="button" onClick={() => void pos.limparCarrinho()} title={t('Limpar')} aria-label={t('Limpar')}
                                    className="pwa-toque w-9 h-9 rounded-xl text-red-500 hover:text-red-600 hover:bg-red-50 text-sm flex items-center justify-center transition">
                                <i className="fas fa-trash" aria-hidden="true" />
                            </button>
                        )}
                        {folha && (
                            <button type="button" onClick={() => pos.setMostrarCarrinho(false)} aria-label={t('Fechar')}
                                    className="pwa-toque w-9 h-9 rounded-xl text-gray-400 hover:bg-gray-100 text-2xl leading-none flex items-center justify-center">&times;</button>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2 mt-1.5 flex-wrap">
                    <span className="inline-block text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold tracking-wide">{t('FATURA-RECIBO')}</span>
                    {/* O número do turno em que a venda vai cair — o crachá abre ou fecha o turno. */}
                    <CrachaDoTurno c={c} className="!py-0.5 !text-[10px]" />
                </div>
            </div>

            {/* Cliente */}
            <div className="shrink-0 px-4 py-2">
                <button type="button" onClick={() => pos.setEscolherCliente(true)}
                        className="pwa-toque w-full bg-blue-50 hover:bg-blue-100 border-2 border-dashed border-blue-200 hover:border-blue-300 rounded-xl px-3 py-2.5 text-left text-sm flex items-center gap-2 transition">
                    <i className={`fas ${pos.cliente ? 'fa-user-check' : 'fa-user'} text-blue-600`} aria-hidden="true" />
                    <span className={`flex-1 truncate ${pos.cliente ? 'font-semibold text-gray-800' : 'text-gray-500'}`}>
                        {pos.cliente ? String(pos.cliente.name ?? '') : t('Consumidor Final')}
                    </span>
                    {pos.cliente?.nif && <span className="text-[10px] text-gray-400 font-mono shrink-0">{String(pos.cliente.nif)}</span>}
                    <i className="fas fa-chevron-right text-blue-300 text-xs" aria-hidden="true" />
                </button>
            </div>

            {/*
              Aviso: sem turno aberto.

              Só com o carrinho JÁ CHEIO. Vazio, quem manda a mensagem é o cartão
              grande no meio do painel — dizer a mesma coisa duas vezes no mesmo ecrã
              não a torna mais clara, torna o ecrã mais confuso. Com artigos no
              carrinho a história é outra: a pessoa já escolheu e precisa de saber,
              ali mesmo, porque não avança.
            */}
            {!c.turno.open && temItens && (
                <div className="pwa-entra shrink-0 mx-4 mb-2 bg-red-50 border border-red-200 text-red-700 rounded-xl px-3 py-2 text-xs flex items-center gap-2">
                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                    <span className="flex-1">{t('Sem turno aberto — abra um turno para poder vender.')}</span>
                    <button type="button" onClick={c.abrirAbertura} className="underline font-bold whitespace-nowrap">{t('Abrir')}</button>
                </div>
            )}

            {/* Aviso: turno aberto offline (por sincronizar) */}
            {c.turno.open && !!c.turno._local && (
                <div className="pwa-entra shrink-0 mx-4 mb-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl px-3 py-2 text-xs flex items-center gap-2">
                    <i className="fas fa-cloud-arrow-up" aria-hidden="true" />
                    <span className="flex-1">{t('Turno aberto offline — será sincronizado quando a internet voltar.')}</span>
                </div>
            )}

            {/* Itens */}
            <div className="flex-1 overflow-y-auto overscroll-contain px-4 space-y-2 min-h-[120px]">
                {pos.carrinho.map((linha, idx) => (
                    <LinhaDoCarrinhoUI key={`${linha.product_id ?? 'x'}|${linha.product_name}|${linha.unit_price}`}
                                       linha={linha} idx={idx} pos={pos} />
                ))}

                {/*
                  O vazio do carrinho diz o que FALTA FAZER, não que está vazio.

                  Sem turno, o ecrã dizia três coisas ao mesmo tempo: um crachá
                  vermelho «S/ turno» no topo, um aviso a meio, e no fundo um botão
                  verde de finalizar venda. Quem chega ao balcão não sabe por onde
                  começar. Aqui o espaço maior e mais vazio passa a ter a única acção
                  que interessa.
                */}
                {!temItens && !c.turno.open && (
                    <div className="pwa-entra text-center py-10 px-4">
                        <div className="w-16 h-16 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center mx-auto mb-3 pwa-flutua">
                            <i className="fas fa-lock text-red-400 text-2xl" aria-hidden="true" />
                        </div>
                        <p className="text-sm font-bold text-gray-800 mb-1">{t('Turno fechado')}</p>
                        <p className="text-xs text-gray-500 mb-4">{t('Abra o turno para começar a vender.')}</p>
                        <button type="button" onClick={c.abrirAbertura}
                                className="pwa-toque px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-sm font-bold shadow transition">
                            <i className="fas fa-lock-open mr-1.5" aria-hidden="true" />{t('Abrir turno')}
                        </button>
                    </div>
                )}

                {!temItens && c.turno.open && (
                    <div className="pwa-entra text-center py-10 text-gray-300">
                        <i className="fas fa-shopping-basket text-5xl mb-2 block pwa-flutua" aria-hidden="true" />
                        <p className="text-sm font-medium">{t('Carrinho vazio')}</p>
                        <p className="text-xs">{t('Toca num produto para adicionar')}</p>
                    </div>
                )}
            </div>

            {/* Aviso oversell no carrinho */}
            {pos.sobrevenda && (
                <div className="pwa-entra shrink-0 mx-4 mt-1 mb-1 bg-orange-50 border border-orange-200 text-orange-700 rounded-xl px-3 py-2 text-xs flex items-center gap-2">
                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                    <span>{t('Atenção: um ou mais itens excedem o stock local disponível. A venda será registada, mas pode ser rejeitada pelo servidor.')}</span>
                </div>
            )}

            {/* Rodapé: totais + pagamento + acção */}
            <div className="shrink-0 border-t border-gray-100 px-4 pt-3 pb-4 space-y-3 bg-white">
                <Totais pos={pos} />
                {temItens && <Pagamento pos={pos} />}

                {/* Finalizar. Desactivado sem turno: um botão que se carrega e não vende ensina a carregar duas vezes. */}
                <button type="button" onClick={() => void pos.finalizar()}
                        disabled={!pos.carrinho.length || pos.aGuardar || !c.turno.open || !pos.divisaoFechada}
                        className="pwa-toque w-full bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-2xl font-bold text-base py-4 shadow-lg shadow-emerald-600/20 disabled:opacity-50 disabled:shadow-none transition">
                    {pos.aGuardar
                        ? <><i className="fas fa-spinner fa-spin mr-1.5" aria-hidden="true" />{t('A guardar…')}</>
                        : <><i className="fas fa-circle-check mr-1.5" aria-hidden="true" />{t('Finalizar Venda')}</>}
                </button>

                {/* Fechar turno (funciona offline — fecho enfileirado após as vendas) */}
                {c.turno.open && (
                    <button type="button" onClick={c.abrirFecho}
                            className="w-full text-xs text-gray-500 hover:text-red-600 font-bold py-1.5 transition-colors">
                        <i className="fas fa-lock mr-1" aria-hidden="true" />{t('Fechar turno')}
                    </button>
                )}
            </div>
        </>
    );
}

function LinhaDoCarrinhoUI({ linha, idx, pos }: { linha: LinhaDoCarrinho; idx: number; pos: ControloDoPos }) {
    const taxa = linha.tax_rate;
    const totalDaLinha = linha.quantity * linha.unit_price * (1 + taxa / 100);

    return (
        <div className="pwa-entra bg-gray-50 hover:bg-gray-100/80 rounded-2xl p-2.5 flex items-center gap-2.5 transition-colors">
            <div className="flex-1 min-w-0">
                <p className="font-semibold text-sm truncate text-slate-800">{linha.product_name}</p>
                <p className="text-[11px] text-gray-500">
                    {dinheiro(linha.unit_price)} · {taxa > 0 ? t('IVA :taxa%', { taxa }) : t('Isento')}
                </p>
                <p className="text-xs font-bold text-blue-700 mt-0.5 tabular-nums">{kz(totalDaLinha)}</p>
            </div>
            <div className="flex items-center gap-1.5 bg-white rounded-xl p-1 shadow-sm">
                {/* Na última unidade o «−» mostra o caixote: carregar ali tira a linha, e isso tem de se ver antes de carregar. */}
                <button type="button" onClick={() => pos.decrementar(idx)} aria-label={linha.quantity > 1 ? t('Diminuir') : t('Remover')}
                        className="pwa-toque w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold text-lg leading-none flex items-center justify-center">
                    {linha.quantity > 1 ? '−' : <i className="fas fa-trash text-xs" aria-hidden="true" />}
                </button>
                <CampoDaQuantidade valor={linha.quantity} aoMudar={(v) => pos.definirQuantidade(idx, v)} />
                <button type="button" onClick={() => pos.incrementar(idx)} aria-label={t('Aumentar')}
                        className="pwa-toque w-8 h-8 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-bold text-lg leading-none flex items-center justify-center">+</button>
            </div>
        </div>
    );
}

/**
 * A quantidade escrita à mão só conta quando se sai do campo (ou Enter).
 *
 * A cada tecla não pode ser: apagar o «2» para escrever «12» passava por um
 * campo vazio, e vazio tira a linha do carrinho — era o que o `@change` do
 * Alpine evitava, e um `onChange` do React (que dispara a cada tecla) desfazia.
 */
function CampoDaQuantidade({ valor, aoMudar }: { valor: number; aoMudar: (v: string) => void }) {
    const [texto, setTexto] = useState(String(valor));

    useEffect(() => { setTexto(String(valor)); }, [valor]);

    return (
        <input type="number" inputMode="numeric" min={1} name="quantidade" aria-label={t('Quantidade')}
               value={texto}
               onChange={(e) => setTexto(e.target.value)}
               onBlur={() => { if (texto !== String(valor)) aoMudar(texto); }}
               onKeyDown={(e) => { if (e.key === 'Enter') e.currentTarget.blur(); }}
               onFocus={(e) => e.currentTarget.select()}
               className="w-10 text-center font-bold text-sm bg-transparent border-0 focus:outline-none focus:ring-1 focus:ring-blue-400 rounded tabular-nums" />
    );
}

function Totais({ pos }: { pos: ControloDoPos }) {
    const { totais } = pos;

    return (
        <div className="bg-gradient-to-br from-emerald-600 to-green-700 text-white rounded-2xl shadow-lg shadow-emerald-700/20 p-3 tabular-nums relative overflow-hidden">
            {/* O brilho no canto — o mesmo dos cartões de total da aplicação web. */}
            <span className="absolute -top-8 -right-8 w-24 h-24 rounded-full bg-white/10" aria-hidden="true" />
            <div className="relative">
                <div className="flex justify-between text-xs opacity-90"><span>{t('Subtotal')}</span><span>{kz(totais.subtotal)}</span></div>
                {totais.desconto > 0 && (
                    <div className="flex justify-between text-xs opacity-90 mt-0.5">
                        <span>{t('Desconto (:pct%)', { pct: pos.desconto })}</span><span>-{kz(totais.desconto)}</span>
                    </div>
                )}
                {totais.imposto > 0
                    ? <div className="flex justify-between text-xs opacity-90 mt-0.5"><span>{t('IVA')}</span><span>{kz(totais.imposto)}</span></div>
                    : <div className="flex justify-between text-xs opacity-90 mt-0.5"><span>{t('IVA')}</span><span>{t('Isento')}</span></div>}
                <div className="border-t border-white/30 mt-1.5 pt-1.5 flex justify-between items-baseline">
                    <span className="font-semibold">{t('TOTAL')}</span>
                    <span key={totais.total} className="pwa-aparece text-2xl font-extrabold">{kz(totais.total)}</span>
                </div>
            </div>
        </div>
    );
}

function Pagamento({ pos }: { pos: ControloDoPos }) {
    const certo = Math.abs(pos.faltaPagar) < 0.01;

    return (
        <div className="space-y-3 pwa-entra">
            {/* Pagamento */}
            <div>
                <div className="flex items-center justify-between mb-1.5">
                    <span className="text-[10px] font-bold text-gray-500 uppercase" id="pos-rotulo-pagamento">{t('Forma de Pagamento')}</span>

                    {/*
                      DIVIDIR O PAGAMENTO.
                      O servidor já aceitava `payments[]` e o POS online já dividia; só
                      este ecrã é que não. Um cliente que paga metade em dinheiro e
                      metade a cartão — coisa de todos os dias — obrigava o operador a
                      fazer duas vendas, e saíam dois documentos fiscais para uma
                      compra só.
                    */}
                    <button type="button" onClick={pos.alternarDivisao} aria-pressed={pos.dividir}
                            className={`pwa-toque rounded-lg border px-2.5 py-1 text-[10px] font-bold transition ${pos.dividir ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-500 border-gray-200 hover:border-blue-300'}`}>
                        <i className="fas fa-divide mr-1" aria-hidden="true" />{t('Dividir')}
                    </button>
                </div>

                {/* Um método só: o caminho normal, e continua a um toque. */}
                {!pos.dividir && (
                    <div className="grid grid-cols-4 gap-1.5" role="radiogroup" aria-labelledby="pos-rotulo-pagamento">
                        {pos.metodos.map((m) => {
                            const activo = pos.pagamento === m.code;

                            return (
                                <button key={m.code} type="button" role="radio" aria-checked={activo} onClick={() => pos.setPagamento(m.code)}
                                        className={`pwa-toque rounded-xl py-2 text-[10px] font-bold border-2 transition ${activo ? 'bg-blue-600 text-white shadow border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300'}`}>
                                    <i className={`fas ${m.icon} block mb-0.5 text-sm`} aria-hidden="true" />
                                    <span>{m.label}</span>
                                </button>
                            );
                        })}
                    </div>
                )}

                {/* Dividido: uma linha por método. */}
                {pos.dividir && (
                    <div className="space-y-1.5">
                        {pos.pagamentos.map((linha, i) => (
                            <div key={i} className="pwa-entra flex items-center gap-1.5">
                                <select name="metodo" aria-label={t('Método de pagamento')} value={linha.method}
                                        onChange={(e) => pos.mudarLinhaDoPagamento(i, { method: e.target.value as CodigoDoMetodo })}
                                        className="flex-1 min-w-0 bg-white border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-bold focus:outline-none focus:border-blue-400">
                                    {pos.metodos.map((m) => <option key={m.code} value={m.code}>{m.label}</option>)}
                                </select>

                                <input type="number" inputMode="decimal" min={0} step="0.01" name="valor" aria-label={t('Valor')}
                                       value={linha.amount}
                                       onChange={(e) => pos.mudarLinhaDoPagamento(i, { amount: e.target.value })}
                                       className="w-24 bg-white border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-right font-bold focus:outline-none focus:border-blue-400 tabular-nums" />

                                {pos.pagamentos.length > 1 && (
                                    <button type="button" onClick={() => pos.removerPagamento(i)} aria-label={t('Remover')}
                                            className="pwa-toque shrink-0 w-8 h-8 rounded-lg bg-gray-100 hover:bg-red-50 hover:text-red-600 text-gray-500 text-lg leading-none transition">&times;</button>
                                )}
                            </div>
                        ))}

                        <div className="flex items-center justify-between gap-2 pt-0.5">
                            {pos.pagamentos.length < pos.metodos.length && (
                                <button type="button" onClick={pos.juntarPagamento} className="text-[11px] font-bold text-blue-600 hover:text-blue-700">
                                    <i className="fas fa-plus mr-1" aria-hidden="true" />{t('Outro método')}
                                </button>
                            )}

                            {/* A DIFERENÇA À VISTA. Sem ela, o operador só descobria que
                                faltavam 200 Kz quando o botão de finalizar não respondia. */}
                            <span className={`text-[11px] font-bold ml-auto tabular-nums transition-colors ${certo ? 'text-emerald-600' : 'text-red-600'}`} aria-live="polite">
                                {certo && <><i className="fas fa-check mr-1" aria-hidden="true" />{t('Certo')}</>}
                                {pos.faltaPagar > 0.01 && `${t('Falta')} ${kz(pos.faltaPagar)}`}
                                {pos.faltaPagar < -0.01 && `${t('A mais')} ${kz(-pos.faltaPagar)}`}
                            </span>
                        </div>
                    </div>
                )}
            </div>

            {/* Desconto comercial */}
            <div className="flex items-center gap-2">
                <label htmlFor="pos-desconto" className="text-[10px] font-bold text-gray-500 uppercase whitespace-nowrap">{t('Desconto %')}</label>
                <input id="pos-desconto" name="desconto" type="number" inputMode="decimal" min={0} max={100} step="0.5" placeholder="0"
                       value={pos.desconto}
                       onChange={(e) => {
                           // Acima de 100% o total ficava negativo e a venda saía a pagar ao cliente.
                           const v = e.target.value;
                           pos.setDesconto(parseFloat(v) > 100 ? '100' : parseFloat(v) < 0 ? '0' : v);
                       }}
                       className="w-20 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-right font-bold focus:outline-none focus:border-blue-400 tabular-nums" />
                {parseFloat(pos.desconto) > 0 && (
                    <span className="pwa-aparece text-xs text-red-600 font-bold tabular-nums">-{kz(pos.totais.desconto)}</span>
                )}
            </div>

            {/* Troco (apenas dinheiro) */}
            {pos.pagamento === 'cash' && (
                <div className="pwa-entra bg-amber-50 border border-amber-200 rounded-xl p-2.5">
                    <div className="flex items-center gap-2">
                        <label htmlFor="pos-recebido" className="text-[11px] font-bold text-amber-800 whitespace-nowrap">
                            <i className="fas fa-hand-holding-dollar mr-1" aria-hidden="true" />{t('Valor recebido')}
                        </label>
                        <input id="pos-recebido" name="recebido" type="number" inputMode="decimal" min={0} step="0.01"
                               placeholder={dinheiro(pos.totais.total)}
                               value={pos.recebido} onChange={(e) => pos.setRecebido(e.target.value)}
                               className="flex-1 w-full min-w-0 bg-white border border-amber-300 rounded-lg px-2 py-1.5 text-sm text-right font-bold focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition tabular-nums" />
                    </div>
                    {pos.dinheiroRapido.length > 0 && (
                        <div className="flex gap-1 mt-1.5">
                            {pos.dinheiroRapido.map((q) => (
                                <button key={q} type="button" onClick={() => pos.setRecebido(String(q))}
                                        className={`pwa-toque flex-1 border rounded-lg py-1 text-[11px] font-bold transition tabular-nums ${Number(pos.recebido) === q ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-amber-300 text-amber-700 hover:bg-amber-100'}`}>
                                    {dinheiro(q)}
                                </button>
                            ))}
                        </div>
                    )}
                    {pos.troco > 0 && (
                        <div className="pwa-aparece flex justify-between mt-2 pt-2 border-t border-amber-200 text-sm font-bold text-amber-900 tabular-nums">
                            <span><i className="fas fa-coins mr-1" aria-hidden="true" />{t('Troco')}</span><span>{kz(pos.troco)}</span>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
