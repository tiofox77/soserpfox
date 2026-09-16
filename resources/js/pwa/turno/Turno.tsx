import { useCallback, useState } from 'react';

import { t, tn } from '@/i18n';

import { dataEHora, dinheiro, useBaseViva } from '../ganchos';
import { db, lerMeta, type Registo } from '../motor/base';
import { sync } from '../motor/sincronizar';
import { produtosVendidos, vendasDesdeAAbertura } from '../motor/produtosDoTurno';
import { closeShiftOffline, diferencaNoFecho, dinheiroEsperado, dinheiroLocalDesdeAAbertura, getShift, openShiftOffline, type Turno } from '../motor/turno';
import { PosOfflineTicket } from '../papel';
import { avisar, Nota } from '../ui/Dialogos';
import { CAMPO, Folha, ROTULO } from '../ui/Folha';

/**
 * O TURNO DE CAIXA NO ECRÃ — uma definição, dois ecrãs (balcão e restaurante).
 *
 * Era o `public/js/pwa-turno.js` com o `partials/pwa-turno.blade.php`. As
 * contas da caixa são as do motor (`motor/turno.ts`); isto é só o que se vê: o
 * estado do turno, e as duas folhas de abrir e fechar.
 *
 * O turno lê-se da base VIVA: quando a sincronização traz o número real de um
 * turno aberto sem rede, ou outro aparelho o fecha, o ecrã muda sozinho.
 */
export function useTurno() {
    const turno = useBaseViva<Turno>(() => getShift(), [], { open: false });
    const vendas = useBaseViva<Registo[]>(() => db.pos_sales.toArray(), [], []);
    const ultimaSync = useBaseViva<string | null>(() => lerMeta<string>('last_sync'), [], null);

    const [abrir, setAbrir] = useState(false);
    const [fechar, setFechar] = useState(false);

    const pendentes = vendas.filter((v) => !v._synced).length;

    const abrirFecho = useCallback(() => {
        // Tenta subir o que está pendente antes de mostrar: o esperado em caixa
        // fica mais perto da verdade. Não se espera — a folha abre já.
        if (pendentes > 0 && navigator.onLine) void sync(true);
        setFechar(true);
    }, [pendentes]);

    return {
        turno,
        vendas,
        pendentes,
        ultimaSync,
        folhaDeAbrir: abrir,
        folhaDeFechar: fechar,
        abrirAbertura: () => setAbrir(true),
        abrirFecho,
        fecharFolhas: () => { setAbrir(false); setFechar(false); },
        /** Aberto → fechar; fechado → abrir. */
        gerir: () => (turno.open ? abrirFecho() : setAbrir(true)),
    };
}

export type ControloDoTurno = ReturnType<typeof useTurno>;

function anunciarMudanca(turno: Turno) {
    // O ecrã que abriu o turno pode ter coisas a reagir — as mesas do restaurante deixam de estar trancadas.
    window.dispatchEvent(new CustomEvent('pwa:turno-mudou', { detail: turno }));
}

function FolhaDeAbrir({ c }: { c: ControloDoTurno }) {
    const [saldo, setSaldo] = useState('');
    const [notas, setNotas] = useState('');
    const [ocupado, setOcupado] = useState(false);

    const confirmar = async () => {
        if (ocupado) return;
        const valor = parseFloat(saldo);

        if (Number.isNaN(valor) || valor < 0) {
            avisar(t('Informe o saldo inicial (0 ou mais).'), 'aviso');
            return;
        }

        setOcupado(true);
        try {
            const novo = await openShiftOffline({ opening_balance: valor, opening_notes: notas.trim() || null });
            c.fecharFolhas();
            setSaldo('');
            setNotas('');
            anunciarMudanca(novo);
            avisar(t('Turno aberto. Boas vendas!'), 'ok');
        } catch (err) {
            console.error(err);
            avisar(t('Erro ao abrir turno: :erro', { erro: (err as Error).message }), 'erro');
        } finally {
            setOcupado(false);
        }
    };

    return (
        <Folha aberta={c.folhaDeAbrir} aoFechar={c.fecharFolhas} titulo={t('Abrir Turno')} icone="fa-lock-open"
               cor="from-emerald-600 to-green-700" largura="sm:max-w-md"
               rodape={(
                   <div className="flex gap-2">
                       <button type="button" onClick={c.fecharFolhas} className="pwa-toque flex-1 py-3 border-2 border-gray-300 bg-white text-gray-700 rounded-xl font-bold text-sm">{t('Cancelar')}</button>
                       <button type="button" onClick={() => void confirmar()} disabled={ocupado || saldo === ''}
                               className="pwa-toque flex-[2] bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg disabled:opacity-50">
                           {ocupado
                               ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A abrir…')}</>
                               : <><i className="fas fa-lock-open mr-1" aria-hidden="true" />{t('Abrir turno')}</>}
                       </button>
                   </div>
               )}>
            <div className="space-y-3">
                {!navigator.onLine && (
                    <Nota tipo="aviso" icone="fa-wifi">{t('Está offline — o turno abre localmente e sincroniza quando a internet voltar.')}</Nota>
                )}
                <div>
                    <label htmlFor="turno-saldo" className={ROTULO}>{t('Saldo inicial em caixa (Kz)')} <span className="text-red-500">*</span></label>
                    <input id="turno-saldo" type="number" inputMode="decimal" min={0} step="0.01" placeholder="0,00" autoFocus
                           value={saldo} onChange={(e) => setSaldo(e.target.value)}
                           onKeyDown={(e) => { if (e.key === 'Enter') void confirmar(); }}
                           className={`${CAMPO} text-right font-bold focus:border-emerald-500`} />
                </div>
                <div>
                    <label htmlFor="turno-notas" className={ROTULO}>{t('Notas')}</label>
                    <input id="turno-notas" type="text" maxLength={1000} placeholder={t('Opcional')}
                           value={notas} onChange={(e) => setNotas(e.target.value)} className={`${CAMPO} focus:border-emerald-500`} />
                </div>
            </div>
        </Folha>
    );
}

function FolhaDeFechar({ c }: { c: ControloDoTurno }) {
    const esperado = dinheiroEsperado(c.turno, c.vendas);
    const offline = dinheiroLocalDesdeAAbertura(c.turno, c.vendas);
    // O contado começa no esperado: na maior parte dos fechos bate, e escrever
    // o valor inteiro à mão é onde nascem as diferenças que não existem.
    const [contado, setContado] = useState<string | null>(null);
    const valorContado = contado ?? esperado.toFixed(2);
    const [notas, setNotas] = useState('');
    const [ocupado, setOcupado] = useState(false);
    // A PERGUNTA do fecho: resumido ou com produtos. Não vem escolhida.
    const [tipo, setTipo] = useState<'resumido' | 'produtos' | null>(null);
    const doTurno = vendasDesdeAAbertura(c.turno.opened_at, c.vendas);
    const vendidos = produtosVendidos(doTurno);
    const diferenca = diferencaNoFecho(valorContado, esperado);

    const sair = () => { c.fecharFolhas(); setContado(null); setNotas(''); };

    const confirmar = async () => {
        if (ocupado) return;
        if (!tipo) {
            avisar(t('Escolha o tipo de fecho: resumido ou com produtos.'), 'aviso');
            return;
        }
        const n = parseFloat(valorContado);

        if (Number.isNaN(n) || n < 0) {
            avisar(t('Informe o dinheiro contado em caixa (0 ou mais).'), 'aviso');
            return;
        }

        setOcupado(true);
        const retrato = { ...c.turno };
        const vendas = c.vendas;
        const pendentesAntes = c.pendentes;

        try {
            await closeShiftOffline({
                actual_cash: n,
                closing_notes: notas.trim() || null,
                // NÃO traduzir: vai para o fecho do servidor e sai nos relatórios de caixa.
                difference_reason: diferenca !== 0 ? `Diferença apurada no fecho offline: ${diferenca.toFixed(2)} Kz` : null,
            });

            const depois = await getShift();
            sair();
            anunciarMudanca(depois);

            // Relatório de fecho (X/Z) — imprime sem rede.
            try {
                PosOfflineTicket.printShiftReport(retrato, vendas, (await lerMeta<Registo>('company')) || {}, tipo === 'produtos');
            } catch (re) {
                console.warn('[PWA] Erro ao imprimir relatório de fecho:', re);
            }

            if (!navigator.onLine || pendentesAntes > 0) {
                avisar(t('Será efetivado no servidor assim que todas as vendas sincronizarem.'), 'info', { titulo: t('Fecho registado.'), duracao: 7000 });
            } else {
                avisar(t('Fecho registado.'), 'ok');
            }
        } catch (err) {
            console.error(err);
            avisar(t('Erro ao fechar turno: :erro', { erro: (err as Error).message }), 'erro');
        } finally {
            setOcupado(false);
        }
    };

    return (
        <Folha aberta={c.folhaDeFechar} aoFechar={sair} icone="fa-lock" cor="from-red-600 to-rose-700" largura="sm:max-w-md"
               titulo={c.turno.number ? `${t('Fechar Turno')} · ${c.turno.number}` : t('Fechar Turno')}
               rodape={(
                   <div className="flex gap-2">
                       <button type="button" onClick={sair} className="pwa-toque flex-1 py-3 border-2 border-gray-300 bg-white text-gray-700 rounded-xl font-bold text-sm">{t('Cancelar')}</button>
                       <button type="button" onClick={() => void confirmar()} disabled={ocupado || valorContado === '' || !tipo}
                               className="pwa-toque flex-[2] bg-gradient-to-r from-red-500 to-rose-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg disabled:opacity-50">
                           {ocupado
                               ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A fechar…')}</>
                               : <><i className="fas fa-lock mr-1" aria-hidden="true" />{t('Fechar e Imprimir')}</>}
                       </button>
                   </div>
               )}>
            <div className="space-y-3">
                {c.pendentes > 0 && (
                    <Nota tipo="aviso" icone="fa-clock">
                        <strong>{tn(':n documento por sincronizar|:n documentos por sincronizar', c.pendentes, { n: c.pendentes })}</strong>
                        {' — '}{t('O fecho fica em fila e só é efetivado no servidor depois de todas as vendas sincronizarem.')}
                    </Nota>
                )}
                {!navigator.onLine && (
                    <Nota tipo="aviso" icone="fa-wifi">{t('Está offline — o fecho é guardado localmente e sincroniza quando a internet voltar.')}</Nota>
                )}

                <fieldset>
                    <legend className={ROTULO}>{t('Que fecho quer?')} <span className="text-red-500">*</span></legend>
                    <div className="grid grid-cols-2 gap-2" role="radiogroup">
                        {([
                            ['resumido', t('Fecho resumido'), t('Totais e caixa'), 'fa-receipt'],
                            ['produtos', t('Fecho com produtos'), t('Artigo a artigo'), 'fa-boxes-stacked'],
                        ] as const).map(([valor, titulo, frase, icone]) => (
                            <button key={valor} type="button" role="radio" aria-checked={tipo === valor} onClick={() => setTipo(valor)}
                                    className={`pwa-toque rounded-2xl border-2 p-3 text-left transition-all ${tipo === valor ? 'border-emerald-500 bg-emerald-50 shadow-md' : 'border-gray-200 bg-white'}`}>
                                <i className={`fas ${icone} text-lg ${tipo === valor ? 'text-emerald-600' : 'text-gray-400'}`} aria-hidden="true" />
                                <span className="mt-1 block text-sm font-bold text-gray-900">{titulo}</span>
                                <span className="block text-[11px] text-gray-500">{frase}</span>
                            </button>
                        ))}
                    </div>
                </fieldset>

                {tipo === 'produtos' && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-3 text-sm" data-produtos-offline>
                        <p className="mb-1 flex justify-between font-semibold text-emerald-900">
                            <span><i className="fas fa-boxes-stacked mr-1" aria-hidden="true" />{tn(':n artigo|:n artigos', vendidos.produtos.length, { n: vendidos.produtos.length })}</span>
                            <span className="tabular-nums">{dinheiro(vendidos.total)} Kz</span>
                        </p>
                        {vendidos.produtos.length === 0
                            ? <p className="text-xs text-gray-500">{t('Sem vendas neste aparelho desde a abertura.')}</p>
                            : (
                                <ul className="max-h-40 space-y-0.5 overflow-auto text-xs tabular-nums">
                                    {vendidos.produtos.map((p) => (
                                        <li key={p.chave} className="flex justify-between gap-2">
                                            <span className="truncate">{p.nome} <span className="text-gray-500">× {p.quantidade}</span></span>
                                            <strong className="whitespace-nowrap">{dinheiro(p.total)}</strong>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        <p className="mt-1 text-[11px] text-gray-500">{t('Vendas deste aparelho desde a abertura. A lista inteira sai no papel.')}</p>
                    </div>
                )}

                <div className="bg-gray-50 rounded-2xl p-3 text-sm space-y-1 tabular-nums">
                    <p className="flex justify-between"><span className="text-gray-500">{t('Saldo inicial')}</span><strong>{dinheiro(c.turno.opening_balance)} Kz</strong></p>
                    <p className="flex justify-between"><span className="text-gray-500">{t('Vendas dinheiro (sincr.)')}</span><strong>{dinheiro(c.turno.cash_sales)} Kz</strong></p>
                    {offline > 0 && (
                        <p className="flex justify-between"><span className="text-gray-500">{t('Vendas dinheiro (offline)')}</span><strong>{dinheiro(offline)} Kz</strong></p>
                    )}
                    <p className="flex justify-between border-t pt-1 mt-1"><span className="text-gray-600 font-semibold">{t('Esperado em caixa')}</span><strong className="text-emerald-700">{dinheiro(esperado)} Kz</strong></p>
                </div>

                <div>
                    <label htmlFor="turno-contado" className={ROTULO}>{t('Dinheiro contado em caixa (Kz)')} <span className="text-red-500">*</span></label>
                    <input id="turno-contado" type="number" inputMode="decimal" min={0} step="0.01"
                           value={valorContado} onChange={(e) => setContado(e.target.value)}
                           className={`${CAMPO} text-right font-bold focus:border-red-500`} />
                    {valorContado !== '' && (
                        <p className={`text-[11px] mt-1 transition-colors ${diferenca === 0 ? 'text-gray-400' : diferenca > 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                            {t('Diferença:')} <strong>{(diferenca > 0 ? '+' : '') + dinheiro(diferenca)} Kz</strong>
                        </p>
                    )}
                </div>

                <div>
                    <label htmlFor="turno-notas-fecho" className={ROTULO}>{t('Notas de fecho')}</label>
                    <input id="turno-notas-fecho" type="text" maxLength={1000} placeholder={t('Opcional')}
                           value={notas} onChange={(e) => setNotas(e.target.value)} className={`${CAMPO} focus:border-red-500`} />
                </div>

                {c.ultimaSync && (
                    <div className="bg-blue-50 rounded-xl px-3 py-2 text-[11px] text-blue-700 flex flex-wrap items-center gap-1">
                        <i className="fas fa-clock-rotate-left" aria-hidden="true" />
                        <span>{t('Última sincronização:')} <strong>{dataEHora(c.ultimaSync) || t('Nunca sincronizado')}</strong></span>
                        {c.pendentes > 0 && <span className="ml-auto font-bold text-amber-600">{t('Valores locais podem não incluir outros dispositivos.')}</span>}
                    </div>
                )}
            </div>
        </Folha>
    );
}

/** As duas folhas do turno. Monta-se uma vez no ecrã que usa `useTurno()`. */
export function FolhasDoTurno({ c }: { c: ControloDoTurno }) {
    return (
        <>
            <FolhaDeAbrir c={c} />
            {/* Chave pelo abrir: o contado volta ao esperado de cada vez que a folha abre. */}
            {c.folhaDeFechar && <FolhaDeFechar key={c.turno.number ?? 'turno'} c={c} />}
        </>
    );
}

/** O crachá do turno — «Turno #12» a verde, ou «Sem turno» a vermelho. Toca-se para abrir ou fechar. */
export function CrachaDoTurno({ c, className = '' }: { c: ControloDoTurno; className?: string }) {
    return (
        <button type="button" onClick={c.gerir}
                title={c.turno.open ? t('Fechar turno') : t('Abrir turno')}
                className={`pwa-toque inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition ${c.turno.open
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                    : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100 pwa-pulsa'} ${className}`}>
            <span className={`w-2 h-2 rounded-full ${c.turno.open ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
            {c.turno.open ? (c.turno.number ? `${t('Turno')} ${c.turno.number}` : t('Turno aberto')) : t('Sem turno')}
        </button>
    );
}
