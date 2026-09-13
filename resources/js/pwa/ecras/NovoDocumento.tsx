import { useCallback, useEffect, useMemo, useState } from 'react';

import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { dinheiro, useAccao, useBaseViva, useEstadoDoMotor } from '../ganchos';
import { db, type Cliente, type Produto, type Registo } from '../motor/base';
import { createDraftOffline, imprimirDocumento, partilharPdf } from '../motor/documentos';
import { sync } from '../motor/sincronizar';
import { contasDoDocumento } from '../papel/molde';
import { avisar } from '../ui/Dialogos';
import { AvisoDeGuardado } from './novo-documento/AvisoDeGuardado';
import { FolhaDoCliente } from './novo-documento/FolhaDoCliente';
import { FolhaDoProduto } from './novo-documento/FolhaDoProduto';
import {
    camposDoCliente,
    formularioVazio,
    linhaDoProduto,
    paraOMotor,
    TIPOS_DE_DOCUMENTO,
    type Formulario,
    type LinhaDoFormulario,
} from './novo-documento/formulario';
import { LinhaDoDocumento } from './novo-documento/LinhaDoDocumento';
import { CAMPO_PEQUENO, ROTULO_PEQUENO, Seccao } from './novo-documento/Seccao';

const mensagemDe = (e: unknown): string => (e instanceof Error ? e.message : String(e ?? ''));

const ROTULO_DATA = 'block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1';

/** Sem taxas sincronizadas, a única oferta. */
const SO_ISENTA = (): Registo[] => [{ rate: 0, label: t('Isento (0%)') }];

/**
 * NOVO DOCUMENTO — Fatura, Fatura-Recibo ou Proforma, feito no aparelho.
 *
 * O documento é DEFINITIVO: grava-se na base local, entra na fila, e ao
 * sincronizar o servidor emite-o com número fiscal e hash. Sem rede fica em
 * fila e sobe assim que houver ligação.
 *
 * Era o `invoicing/offline/draft-form.blade.php` (Alpine).
 */
export function NovoDocumento() {
    const { rotas } = usePwa();
    const { online } = useEstadoDoMotor();

    const [form, setForm] = useState<Formulario>(formularioVazio);
    const [cliente, setCliente] = useState<Cliente | null>(null);
    const [folhaCliente, setFolhaCliente] = useState(false);
    const [folhaProduto, setFolhaProduto] = useState(false);
    const [guardado, setGuardado] = useState<{ uuid: string; mensagem: string } | null>(null);

    /*
     * AS TABELAS FISCAIS DA EMPRESA, vivas.
     *
     * Sem taxas sincronizadas fica SÓ a isenta. Oferecer 14% a quem ainda não
     * sincronizou é adivinhar o regime da empresa — e adivinhar a favor do
     * imposto é o pior lado para errar. O IEC e o Selo são tabelas da AGT.
     */
    const fiscal = useBaseViva(async () => ({
        taxas: await db.tax_rates.toArray(),
        pautais: await db.iec_pautais.toArray(),
        verbas: await db.is_verbas.toArray(),
    }), [], { taxas: [] as Registo[], pautais: [] as Registo[], verbas: [] as Registo[] });

    const taxas = useMemo(() => (fiscal.taxas.length ? fiscal.taxas : SO_ISENTA()), [fiscal.taxas]);

    /*
     * Rede de segurança: catálogo vazio mas com rede → força a sincronização.
     * Não é preciso recarregar nada depois: as folhas lêem a base viva e
     * enchem-se sozinhas quando o catálogo chega.
     */
    useEffect(() => {
        void (async () => {
            try {
                if (navigator.onLine && (await db.products.count()) === 0) await sync(true);
            } catch (e) {
                console.error('[Documentos] sync inicial falhou', e);
            }
        })();
    }, []);

    /*
     * AS CONTAS SÃO AS DO PAPEL (`contasDoDocumento`), a mesma função que o
     * motor usa para os totais guardados e o papel sem rede para o que imprime.
     * Pela MESMA ordem do servidor: o comercial sai do líquido e o imposto
     * recalcula-se sobre o que sobra; o financeiro sai do total já com imposto;
     * a retenção só em serviços, e baixa o total sem mexer no imposto. Se o
     * ecrã contasse de outra maneira, o total mostrado ao cliente não bateria
     * com o do documento emitido.
     */
    const contas = useMemo(() => contasDoDocumento(form), [form]);

    const mudar = useCallback((parcial: Partial<Formulario>) => setForm((f) => ({ ...f, ...parcial })), []);

    const mudarLinha = useCallback((chave: string, parcial: Partial<LinhaDoFormulario>) => {
        setForm((f) => ({ ...f, items: f.items.map((l) => (l.chave === chave ? { ...l, ...parcial } : l)) }));
    }, []);

    const escolherCliente = useCallback((c: Cliente | null) => {
        setCliente(c);
        mudar(camposDoCliente(c));
    }, [mudar]);

    // Estáveis: a folha liga o Escape e trava o scroll num efeito que depende disto.
    const fecharCliente = useCallback(() => setFolhaCliente(false), []);
    const fecharProduto = useCallback(() => setFolhaProduto(false), []);

    const juntarProduto = useCallback((p: Produto) => {
        setForm((f) => ({ ...f, items: [...f.items, linhaDoProduto(p)] }));
    }, []);

    const eNotaDeCredito = form.doc_type === 'NC';
    const podeGuardar = form.items.length > 0 && (!eNotaDeCredito || form.reference.trim() !== '');

    const [emitir, aEmitir] = useAccao(async () => {
        if (guardado || !podeGuardar) return;

        try {
            const registo = await createDraftOffline(paraOMotor(form));

            // Sem redireccionamento automático: quem emite quase sempre quer o
            // papel a seguir, e o salto para a lista deixava-o sem botão nenhum
            // de imprimir. Fica o aviso com as saídas. O botão de emitir fica
            // travado: um segundo toque era um segundo documento fiscal.
            setGuardado({
                uuid: String(registo.local_uuid),
                mensagem: navigator.onLine
                    ? t('Documento guardado — a emitir no servidor…')
                    : t('Documento guardado. Sai emitido assim que houver rede.'),
            });
        } catch (err) {
            console.error(err);
            avisar(t('Erro: :mensagem', { mensagem: mensagemDe(err) }), 'erro');
        }
    });

    const [imprimir, aImprimir] = useAccao(async () => {
        if (!guardado) return;

        try {
            // Com rede, o motor espera uns segundos pelo número fiscal — o
            // mesmo prazo do talão do POS; sem rede sai já, marcado como
            // provisório.
            const doc = await imprimirDocumento(guardado.uuid);

            if (doc._server_number) {
                setGuardado((g) => (g ? { ...g, mensagem: t('Emitido: :numero', { numero: String(doc._server_number) }) } : g));
            }
        } catch (e) {
            avisar(mensagemDe(e), 'erro');
        }
    });

    const [partilhar, aPartilhar] = useAccao(async () => {
        if (!guardado) return;

        try {
            const r = await partilharPdf('documento', guardado.uuid);
            if (r.modo === 'descarregado') avisar(t('PDF descarregado — anexe-o na conversa.'), 'info');
        } catch (e) {
            // Quem fecha a folha de partilha do Android não fez nada de errado.
            if ((e as { name?: string } | null)?.name === 'AbortError') return;
            avisar(t('Não foi possível gerar o PDF: :mensagem', { mensagem: mensagemDe(e) }), 'erro');
        }
    });

    const trancado = !!guardado;

    return (
        <div className="max-w-2xl mx-auto">
            {/* Cabeçalho do ecrã */}
            <div className="pwa-entra mb-4 flex items-center gap-3">
                <a href={rotas.documentos} aria-label={t('Voltar')} title={t('Voltar')}
                   className="pwa-toque w-10 h-10 bg-white rounded-xl shadow-sm ring-1 ring-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-50 hover:text-blue-700 transition">
                    <i className="fas fa-arrow-left" aria-hidden="true" />
                </a>
                <span className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i className="fas fa-file-circle-plus" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <h1 className="text-xl font-bold text-slate-900 leading-tight">{t('Novo Documento')}</h1>
                    <p className="text-xs text-slate-500">{t('Documento · emitido ao sincronizar')}</p>
                </div>
            </div>

            {/* Depois de gravado não se mexe: o documento já está na fila. */}
            <fieldset disabled={trancado} className={`min-w-0 border-0 p-0 m-0 transition-opacity ${trancado ? 'opacity-70' : ''}`}>
                {/* Tipo de Documento */}
                <Seccao titulo={t('Tipo de Documento')} icone="fa-layer-group" ordem={0}>
                    <div className="grid grid-cols-3 gap-2" role="group" aria-label={t('Tipo de Documento')}>
                        {TIPOS_DE_DOCUMENTO.map((dt) => {
                            const activo = form.doc_type === dt.code;

                            return (
                                <button key={dt.code} type="button" onClick={() => mudar({ doc_type: dt.code })}
                                        aria-pressed={activo} data-ensaio="tipo-documento" data-tipo={dt.code}
                                        className={`pwa-toque border-2 rounded-xl py-2.5 px-1 text-xs font-bold transition-all duration-200 ${activo
                                            ? `bg-gradient-to-br ${dt.activo} text-white shadow-lg ${dt.sombra} -translate-y-0.5`
                                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:bg-slate-50'}`}>
                                    <i className={`fas ${dt.icon} block text-base mb-1 transition-transform ${activo ? 'scale-110' : ''}`} aria-hidden="true" />
                                    {t(dt.label)}
                                </button>
                            );
                        })}
                    </div>
                </Seccao>

                {/* Cliente */}
                <Seccao titulo={t('Cliente')} icone="fa-user" cor="bg-indigo-100 text-indigo-700" ordem={1}>
                    <button type="button" onClick={() => setFolhaCliente(true)} data-ensaio="escolher-cliente"
                            className="pwa-toque w-full text-left px-3 py-3 border-2 border-slate-200 rounded-xl flex items-center justify-between gap-3 hover:border-blue-500 hover:bg-blue-50/40 transition">
                        <span className="flex items-center gap-3 min-w-0">
                            <span className={`w-9 h-9 shrink-0 rounded-xl flex items-center justify-center ${cliente ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400'}`}>
                                <i className={`fas ${cliente ? 'fa-user-check' : 'fa-user'}`} aria-hidden="true" />
                            </span>
                            <span className="min-w-0">
                                <span className="block font-semibold text-sm text-slate-800 truncate">
                                    {cliente ? cliente.name : t('Selecionar cliente...')}
                                </span>
                                {cliente?.nif && <span className="block text-xs text-slate-500">{t('NIF: :nif', { nif: cliente.nif })}</span>}
                            </span>
                        </span>
                        <i className="fas fa-chevron-right text-slate-400" aria-hidden="true" />
                    </button>
                    {!cliente && <p className="text-[10px] text-slate-400 mt-1 px-1">{t('Cliente é opcional para Consumidor Final')}</p>}
                </Seccao>

                {/* Referência (para NC). A nota de crédito já não é oferecida
                    (ver TIPOS_DE_DOCUMENTO), portanto este bloco não aparece; fica
                    para que, se ela um dia voltar, volte com a referência
                    obrigatória e não sem ela. */}
                {eNotaDeCredito && (
                    <Seccao titulo={t('Nº da Fatura Original')} icone="fa-link" cor="bg-red-100 text-red-700" htmlFor="doc-referencia" ordem={2}
                            direita={<span className="text-red-500 font-bold" aria-hidden="true">*</span>}>
                        <input id="doc-referencia" name="reference" type="text" required value={form.reference}
                               onChange={(e) => mudar({ reference: e.target.value })} placeholder={t('Ex: FT 2025/123')}
                               className="w-full px-3 py-2.5 border-2 border-slate-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none transition" />
                        <p className="text-[10px] text-amber-700 mt-1 px-1">
                            <i className="fas fa-circle-info mr-1" aria-hidden="true" />{t('Será associada à fatura no momento da finalização')}
                        </p>
                    </Seccao>
                )}

                {/* Datas */}
                <Seccao ordem={3} className="grid grid-cols-2 gap-3">
                    <div>
                        <label htmlFor="doc-data" className={ROTULO_DATA}>
                            <i className="fas fa-calendar-day text-blue-500 mr-1" aria-hidden="true" />{t('Data')}
                        </label>
                        <input id="doc-data" name="invoice_date" type="date" value={form.invoice_date}
                               onChange={(e) => mudar({ invoice_date: e.target.value })}
                               className="w-full px-3 py-2 border-2 border-slate-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none transition" />
                    </div>
                    {/* A Fatura-Recibo é paga no acto: não tem vencimento. */}
                    {form.doc_type !== 'FR' && (
                        <div className="pwa-aparece">
                            <label htmlFor="doc-vencimento" className={ROTULO_DATA}>
                                <i className="fas fa-hourglass-half text-amber-500 mr-1" aria-hidden="true" />
                                {form.doc_type === 'proforma' ? t('Válido até') : t('Vencimento')}
                            </label>
                            <input id="doc-vencimento" name="due_date" type="date" value={form.due_date}
                                   onChange={(e) => mudar({ due_date: e.target.value })}
                                   className="w-full px-3 py-2 border-2 border-slate-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none transition" />
                        </div>
                    )}
                </Seccao>

                {/* Itens */}
                <Seccao titulo={t('Itens (:n)', { n: form.items.length })} icone="fa-list-ul" cor="bg-emerald-100 text-emerald-700" ordem={4}
                        direita={(
                            <button type="button" onClick={() => setFolhaProduto(true)} data-ensaio="adicionar-item"
                                    className="pwa-toque bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-md shadow-emerald-500/25">
                                <i className="fas fa-plus mr-1" aria-hidden="true" />{t('Adicionar')}
                            </button>
                        )}>
                    <div className="space-y-2">
                        {form.items.map((linha, i) => {
                            const conta = contas.linhas[i];

                            return (
                                <LinhaDoDocumento key={linha.chave} linha={linha}
                                                  liquido={conta?.liquido ?? 0} iva={conta?.iva ?? 0}
                                                  taxas={taxas} pautais={fiscal.pautais} verbas={fiscal.verbas}
                                                  aoMudar={(parcial) => mudarLinha(linha.chave, parcial)}
                                                  aoRemover={() => mudar({ items: form.items.filter((l) => l.chave !== linha.chave) })} />
                            );
                        })}
                        {!form.items.length && (
                            <button type="button" onClick={() => setFolhaProduto(true)}
                                    className="pwa-toque w-full text-center py-6 rounded-xl border-2 border-dashed border-slate-200 text-slate-400 italic text-sm hover:border-emerald-300 hover:text-emerald-600 transition">
                                <i className="fas fa-cart-plus block text-2xl mb-1 not-italic pwa-flutua" aria-hidden="true" />
                                {t('Sem itens. Toca em "Adicionar"')}
                            </button>
                        )}
                    </div>
                </Seccao>

                {/* Descontos do documento.

                    O comercial incide ANTES do IVA e baixa o imposto; o financeiro
                    incide DEPOIS e não lhe toca. Quem apura é o servidor — aqui só
                    se escrevem os valores, para o total no ecrã bater com o do
                    documento. */}
                <Seccao titulo={t('Descontos')} icone="fa-percent" cor="bg-pink-100 text-pink-700" ordem={5}>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <label htmlFor="doc-desc-comercial" className={ROTULO_PEQUENO}>{t('Comercial (antes do IVA)')}</label>
                            <input id="doc-desc-comercial" name="discount_commercial" type="number" min="0" step="0.01" inputMode="decimal"
                                   value={form.discount_commercial} onChange={(e) => mudar({ discount_commercial: e.target.value })}
                                   className={CAMPO_PEQUENO} />
                        </div>
                        <div>
                            <label htmlFor="doc-desc-financeiro" className={ROTULO_PEQUENO}>{t('Financeiro (após IVA)')}</label>
                            <input id="doc-desc-financeiro" name="discount_financial" type="number" min="0" step="0.01" inputMode="decimal"
                                   value={form.discount_financial} onChange={(e) => mudar({ discount_financial: e.target.value })}
                                   className={CAMPO_PEQUENO} />
                        </div>
                    </div>
                </Seccao>

                {/* Entrega */}
                <Seccao titulo={t('Entrega')} icone="fa-truck" cor="bg-sky-100 text-sky-700" ordem={6}>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <label htmlFor="doc-entrega-data" className={ROTULO_PEQUENO}>{t('Data')}</label>
                            <input id="doc-entrega-data" name="delivery_date" type="date" value={form.delivery_date}
                                   onChange={(e) => mudar({ delivery_date: e.target.value })} className={CAMPO_PEQUENO} />
                        </div>
                        <div>
                            <label htmlFor="doc-entrega-local" className={ROTULO_PEQUENO}>{t('Local')}</label>
                            <input id="doc-entrega-local" name="delivery_location" type="text" value={form.delivery_location}
                                   onChange={(e) => mudar({ delivery_location: e.target.value })}
                                   placeholder={t('Local de entrega dos bens')} className={CAMPO_PEQUENO} />
                        </div>
                    </div>
                </Seccao>

                {/* Retenção na fonte.

                    Só existe em prestação de serviços — uma venda de mercadoria não
                    retém IRT. E não é imposto do documento: é dinheiro que o cliente
                    entrega ao Estado em vez de o entregar a quem factura, por isso
                    baixa o total a receber e não mexe no IVA. */}
                <Seccao ordem={7}>
                    <label htmlFor="doc-servico" className="flex items-center gap-3 cursor-pointer px-1">
                        <input id="doc-servico" name="is_service" type="checkbox" checked={form.is_service}
                               onChange={(e) => mudar({ is_service: e.target.checked })}
                               className="w-5 h-5 rounded border-slate-300 accent-violet-600" />
                        <span className="w-6 h-6 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center text-[11px]">
                            <i className="fas fa-screwdriver-wrench" aria-hidden="true" />
                        </span>
                        <span className="text-sm font-semibold text-slate-700">{t('É prestação de serviço')}</span>
                    </label>

                    {form.is_service && (
                        <div className="pwa-entra mt-3">
                            <label htmlFor="doc-retencao" className={ROTULO_PEQUENO}>{t('Retenção %')}</label>
                            <input id="doc-retencao" name="withholding_percentage" type="number" min="0" max="100" step="0.01" inputMode="decimal"
                                   value={form.withholding_percentage} onChange={(e) => mudar({ withholding_percentage: e.target.value })}
                                   className={CAMPO_PEQUENO} />
                            <p className="text-[11px] text-slate-400 mt-1">
                                {t('6,5% é a taxa corrente do IRT sobre serviços. Deixe assim se não souber.')}
                            </p>
                        </div>
                    )}
                </Seccao>

                {/* Notas */}
                <Seccao titulo={t('Notas')} icone="fa-note-sticky" cor="bg-yellow-100 text-yellow-700" htmlFor="doc-notas" ordem={8}>
                    <textarea id="doc-notas" name="notes" rows={2} maxLength={2000} value={form.notes}
                              onChange={(e) => mudar({ notes: e.target.value })} placeholder={t('Observações...')}
                              className="w-full px-3 py-2 border-2 border-slate-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none transition" />
                </Seccao>
            </fieldset>

            {/* Totais — os mesmos números do papel, linha a linha. */}
            <section style={{ animationDelay: '315ms' }} data-ensaio="totais"
                     className="pwa-entra relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl shadow-lg shadow-blue-900/20 p-4 mb-3">
                <i className="fas fa-calculator absolute -right-3 -bottom-4 text-7xl text-white/10 rotate-12" aria-hidden="true" />
                <div className="relative space-y-0.5 tabular-nums">
                    <div className="flex justify-between text-sm opacity-90"><span>{t('Subtotal')}</span><span>{dinheiro(contas.subtotal)}</span></div>
                    {contas.comercial > 0 && (
                        <div className="pwa-aparece flex justify-between text-sm opacity-90"><span>{t('Comercial (antes do IVA)')}</span><span>-{dinheiro(contas.comercial)}</span></div>
                    )}
                    <div className="flex justify-between text-sm opacity-90"><span>{t('IVA')}</span><span>{dinheiro(contas.iva)}</span></div>
                    {contas.financeiro > 0 && (
                        <div className="pwa-aparece flex justify-between text-sm opacity-90"><span>{t('Financeiro (após IVA)')}</span><span>-{dinheiro(contas.financeiro)}</span></div>
                    )}
                    {contas.retencao > 0 && (
                        <div className="pwa-aparece flex justify-between text-sm opacity-90"><span>{t('Retenção na fonte')}</span><span>-{dinheiro(contas.retencao)}</span></div>
                    )}
                    <div className="border-t border-white/30 mt-2 pt-2 flex justify-between items-baseline text-lg font-bold">
                        <span>{t('TOTAL')}</span>
                        <span key={contas.total.toFixed(2)} className="pwa-cresce inline-block">{dinheiro(contas.total)} <small className="text-xs font-semibold opacity-80">Kz</small></span>
                    </div>
                </div>
            </section>

            {!online && (
                <div role="status" className="pwa-entra bg-amber-50 border-l-4 border-amber-500 p-3 rounded-lg text-xs text-amber-900 mb-3">
                    <i className="fas fa-wifi mr-1 opacity-60" aria-hidden="true" />
                    <strong>{t('Sem conexão.')}</strong> {t('Será guardado e enviado quando voltar online.')}
                </div>
            )}

            <div className="pwa-entra bg-orange-50 border-l-4 border-orange-500 p-3 rounded-lg text-xs text-orange-900 mb-3" style={{ animationDelay: '350ms' }}>
                <i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />
                <strong>{t('Documento definitivo.')}</strong>{' '}
                {t('Ao sincronizar, o documento é emitido com número fiscal e hash. Sem rede fica em fila e sobe assim que houver ligação.')}
            </div>

            <div className="flex gap-3 pb-4">
                <a href={rotas.documentos}
                   className="pwa-toque flex-1 text-center py-3 border-2 border-slate-300 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-50 transition">
                    {t('Cancelar')}
                </a>
                <button type="button" onClick={() => void emitir()} disabled={aEmitir || trancado || !podeGuardar} data-ensaio="emitir-documento"
                        className="pwa-toque flex-1 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white py-3 rounded-xl font-bold text-sm shadow-lg shadow-emerald-500/25 disabled:opacity-50 disabled:shadow-none">
                    {aEmitir
                        ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A guardar...')}</>
                        : trancado
                            ? <><i className="fas fa-check mr-1" aria-hidden="true" />{t('Guardado')}</>
                            : <><i className="fas fa-save mr-1" aria-hidden="true" />{t('Emitir Documento')}</>}
                </button>
            </div>

            {/* As folhas e o aviso ficam FORA dos cartões animados: são `fixed`. */}
            <FolhaDoCliente aberta={folhaCliente} aoFechar={fecharCliente} aoEscolher={escolherCliente}
                            rotaNovoCliente={rotas.novoCliente} />
            <FolhaDoProduto aberta={folhaProduto} aoFechar={fecharProduto} aoEscolher={juntarProduto} />

            {guardado && (
                <AvisoDeGuardado mensagem={guardado.mensagem}
                                 aImprimir={aImprimir} aPartilhar={aPartilhar}
                                 aoImprimir={() => void imprimir()} aoPartilhar={() => void partilhar()}
                                 rotaDocumentos={rotas.documentos} rotaNovoDocumento={rotas.novoDocumento} />
            )}
        </div>
    );
}
