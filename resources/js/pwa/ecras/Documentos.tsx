import { useMemo, useState } from 'react';

import { t, tn, tPartes } from '@/i18n';

import { usePwa } from '../contexto';
import { dataCurta, dinheiro, hora, useAccao, useBaseViva, useEstadoDoMotor } from '../ganchos';
import { db, type Registo } from '../motor/base';
import { getDrafts, imprimirDocumento, partilharPdf } from '../motor/documentos';
import { sync } from '../motor/sincronizar';
import { arredondar2, numero } from '../motor/util';
import { contasDoDocumento } from '../papel/molde';
import { avisar, confirmar, Nota } from '../ui/Dialogos';

/**
 * OS DOCUMENTOS FEITOS NESTE APARELHO — factura, factura-recibo, proforma.
 *
 * Consulta viva ao `getDrafts()` (documentos + estado da fila): quando o
 * documento sobe e ganha número, o crachá passa a «Sync» e o número aparece
 * sem recarregar — o Alpine ouvia `pwa:synced` e relia tudo.
 */

type FiltroDoTipo = 'all' | 'FT' | 'FR' | 'proforma';

/** O rótulo, a cor e o ícone de cada tipo. Cada frase escrita por inteiro, para o dicionário a encontrar. */
function tipoDe(docType: unknown): { rotulo: string; cracha: string; icone: string } {
    switch (String(docType ?? '')) {
        case 'FT': return { rotulo: t('Fatura'), cracha: 'bg-blue-100 text-blue-700', icone: 'fa-file-invoice' };
        case 'FR': return { rotulo: t('Fat-Recibo'), cracha: 'bg-emerald-100 text-emerald-700', icone: 'fa-receipt' };
        // O rótulo da NC continua no mapa — ver o comentário dos filtros.
        case 'NC': return { rotulo: t('Nota Crédito'), cracha: 'bg-red-100 text-red-700', icone: 'fa-file-circle-minus' };
        case 'proforma': return { rotulo: t('Proforma'), cracha: 'bg-amber-100 text-amber-700', icone: 'fa-file-lines' };
        default: return { rotulo: String(docType ?? ''), cracha: 'bg-slate-100 text-slate-700', icone: 'fa-file' };
    }
}

/**
 * O total que a lista mostra — o do PAPEL (`contasDoDocumento`).
 *
 * Os documentos feitos pelo motor antigo guardaram um total sem os descontos
 * do documento nem a retenção: a lista dizia um valor e o papel outro, e é o
 * da lista que o vendedor diz ao cliente antes de haver rede. Recalcula-se
 * pelas linhas; só sem linhas (registo incompleto) se confia no guardado.
 */
function totalDoDocumento(d: Registo): number {
    return d.items?.length ? arredondar2(contasDoDocumento(d).total) : numero(d.total);
}

export function Documentos() {
    const { rotas } = usePwa();
    const motor = useEstadoDoMotor();

    // `null` até a primeira leitura voltar: «ainda a ler» não é «não há documentos».
    const documentos = useBaseViva<Registo[] | null>(() => getDrafts(), [], null);

    const [tipo, setTipo] = useState<FiltroDoTipo>('all');
    const [aImprimir, setAImprimir] = useState<string | null>(null);
    const [aPartilhar, setAPartilhar] = useState<string | null>(null);

    const lista = documentos ?? [];
    const porSincronizar = useMemo(() => lista.filter((d) => !d._synced).length, [lista]);
    const comErro = useMemo(() => lista.filter((d) => d._estado_fila === 'failed').length, [lista]);

    const contagens = useMemo(() => {
        const c: Record<FiltroDoTipo, number> = { all: lista.length, FT: 0, FR: 0, proforma: 0 };
        for (const d of lista) {
            if (d.doc_type === 'FT' || d.doc_type === 'FR' || d.doc_type === 'proforma') c[d.doc_type as FiltroDoTipo]++;
        }

        return c;
    }, [lista]);

    const filtrados = tipo === 'all' ? lista : lista.filter((d) => d.doc_type === tipo);

    /** Repõe os trabalhos falhados na fila e sincroniza — o `sync(true)` já o faz. A lista muda sozinha. */
    const [tentarDeNovo, aTentar] = useAccao(async () => {
        if (!navigator.onLine) {
            avisar(t('Sem ligação. Tente outra vez quando houver rede.'), 'aviso');

            return;
        }

        try { await sync(true); } catch { /* o erro fica na fila e aparece no cartão */ }
    });

    /**
     * Imprimir, com ou sem rede — como o talão do POS. Com rede e por
     * sincronizar, o motor espera uns segundos pelo número fiscal; sem rede
     * sai já, com a faixa de PROVISÓRIO. A espera pode trazer o número: a
     * lista, sendo viva, mostra-o sem mais nada.
     */
    const imprimir = async (d: Registo) => {
        if (aImprimir) return;
        setAImprimir(d.local_uuid);
        try {
            await imprimirDocumento(d.local_uuid);
        } catch (e) {
            avisar(e instanceof Error ? e.message : String(e), 'erro');
        } finally {
            setAImprimir(null);
        }
    };

    /** Em PDF, para o WhatsApp: sem rede faz-se no aparelho; emitido e com rede, vai o PDF do servidor. */
    const partilhar = async (d: Registo) => {
        if (aPartilhar) return;
        setAPartilhar(d.local_uuid);
        try {
            const r = await partilharPdf('documento', d.local_uuid);
            if (r.modo === 'descarregado') avisar(t('PDF descarregado — anexe-o na conversa.'), 'info');
        } catch (e) {
            // Fechar a folha de partilha sem escolher ninguém não é um erro.
            if (e instanceof Error && e.name === 'AbortError') return;
            avisar(t('Não foi possível gerar o PDF: :erro', { erro: e instanceof Error ? e.message : String(e) }), 'erro');
        } finally {
            setAPartilhar(null);
        }
    };

    const remover = async (d: Registo) => {
        const ok = await confirmar(t('Apagar este documento da lista local?'), {
            texto: d._synced
                ? t('Se já foi emitido, continua no servidor — um documento fiscal não se apaga.')
                // Por enviar: o trabalho está na fila, e a fila não lê esta lista.
                // Dizê-lo evita que alguém apague a pensar que anulou uma venda.
                : `${t('Se já foi emitido, continua no servidor — um documento fiscal não se apaga.')}\n\n${t('Ainda não foi enviado: continua na fila e sobe na mesma quando houver rede.')}`,
            sim: t('Apagar'),
            perigo: true,
            icone: 'fa-trash',
        });
        if (!ok) return;

        await db.draft_documents.where('local_uuid').equals(d.local_uuid).delete();
    };

    const FILTROS: { chave: FiltroDoTipo; rotulo: string; activo: string }[] = [
        { chave: 'all', rotulo: t('Todos'), activo: 'bg-blue-600 text-white shadow-blue-600/30' },
        { chave: 'FT', rotulo: t('Faturas'), activo: 'bg-blue-600 text-white shadow-blue-600/30' },
        { chave: 'FR', rotulo: t('FR'), activo: 'bg-emerald-600 text-white shadow-emerald-600/30' },
        { chave: 'proforma', rotulo: t('Proformas'), activo: 'bg-amber-600 text-white shadow-amber-600/30' },
    ];

    return (
        <div>
            {/* Cabeçalho */}
            <div className="pwa-entra relative overflow-hidden bg-gradient-to-br from-blue-700 to-indigo-800 text-white rounded-2xl shadow-lg p-4 mb-3">
                <i className="fas fa-folder-open absolute -right-2 -bottom-4 text-7xl opacity-10 pwa-flutua" aria-hidden="true" />
                <div className="relative flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold flex items-center gap-2">
                            <i className="fas fa-file-invoice" aria-hidden="true" />{t('Documentos')}
                        </h1>
                        <p className="text-xs opacity-90 mt-0.5">
                            {t(':n total', { n: lista.length })}
                            {' · '}
                            <span className="font-bold">{t(':n por sincronizar', { n: porSincronizar })}</span>
                            {comErro > 0 && (
                                <span className="ml-1.5 inline-flex items-center gap-1 bg-red-500/90 px-1.5 rounded-full font-bold">
                                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />{t(':n com erro', { n: comErro })}
                                </span>
                            )}
                        </p>
                    </div>
                    <a href={rotas.novoDocumento}
                       className="pwa-toque shrink-0 bg-white/20 hover:bg-white/30 backdrop-blur px-3 py-2 rounded-xl text-sm font-bold transition">
                        <i className="fas fa-plus mr-1" aria-hidden="true" />{t('Novo')}
                    </a>
                </div>
            </div>

            {/* Filtros por tipo */}
            <div className="sticky top-[60px] z-30 bg-slate-50/95 backdrop-blur flex gap-2 mb-3 overflow-x-auto py-2 text-xs no-scrollbar"
                 role="group" aria-label={t('Tipo')}>
                {FILTROS.map((f) => (
                    <button key={f.chave} type="button" onClick={() => setTipo(f.chave)} aria-pressed={tipo === f.chave}
                            className={`pwa-toque px-3.5 py-2 rounded-full font-semibold shadow-sm whitespace-nowrap transition-colors inline-flex items-center gap-1.5 ${tipo === f.chave ? `${f.activo} shadow-md` : 'bg-white text-slate-600 hover:bg-slate-100'}`}>
                        {f.rotulo}
                        <span className={`px-1.5 rounded-full text-[10px] font-bold ${tipo === f.chave ? 'bg-white/25' : 'bg-slate-100 text-slate-500'}`}>
                            {contagens[f.chave]}
                        </span>
                    </button>
                ))}
                {/* O FILTRO "NC" SAIU, porque o PWA não faz notas de crédito.

                    O formulário deixou de as oferecer há muito — o servidor escrevia-as
                    na tabela das VENDAS e nascia uma factura que não estornava nada.
                    O filtro ficou para trás e prometia uma lista que nunca pode ter
                    nada: quem lá tocava concluía que as suas notas de crédito se
                    tinham perdido. O rótulo continua no mapa de tipos, para uma NC
                    antiga vinda do servidor continuar a mostrar-se com o nome certo. */}
            </div>

            {documentos === null ? (
                <div className="space-y-2" aria-busy="true">
                    {[0, 1, 2].map((i) => (
                        <div key={i} className="bg-white rounded-xl shadow-sm p-3 h-36 animate-pulse" />
                    ))}
                </div>
            ) : (
                <div className="space-y-2">
                    {filtrados.map((d, i) => {
                        const tp = tipoDe(d.doc_type);
                        const falhou = d._estado_fila === 'failed';
                        const nItens = d.items?.length || 0;

                        return (
                            <div key={d.local_uuid}
                                 className={`pwa-entra pwa-cartao bg-white rounded-xl shadow-sm border p-3 ${falhou ? 'border-red-200' : 'border-slate-100'}`}
                                 style={{ animationDelay: `${Math.min(i, 10) * 30}ms` }}>
                                <div className="flex items-start justify-between gap-2 mb-1.5">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase ${tp.cracha}`}>
                                            <i className={`fas ${tp.icone}`} aria-hidden="true" />{tp.rotulo}
                                        </span>
                                        {d._synced ? (
                                            <span className="pwa-cresce inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold">
                                                <i className="fas fa-check" aria-hidden="true" />{t('Sync')}
                                            </span>
                                        ) : falhou ? (
                                            <span className="inline-flex items-center gap-1 text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-bold">
                                                <i className="fas fa-circle-xmark" aria-hidden="true" />{t('Falhou')}
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold">
                                                <i className={`fas ${motor.syncing ? 'fa-rotate fa-spin' : 'fa-clock'}`} aria-hidden="true" />{t('Pendente')}
                                            </span>
                                        )}
                                    </div>
                                    <button type="button" onClick={() => void remover(d)} aria-label={t('Apagar da lista local')} title={t('Apagar da lista local')}
                                            className="pwa-toque w-8 h-8 -mt-1 -mr-1 rounded-lg flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 text-xs transition">
                                        <i className="fas fa-trash" aria-hidden="true" />
                                    </button>
                                </div>

                                <p className="font-semibold text-sm text-slate-800 truncate">{d.client_name || t('Consumidor Final')}</p>

                                {/* O que se passa com ele, quando não é «à espera de rede».
                                    Um documento que o servidor recusou dizia «Pendente» para
                                    sempre; o motivo ficava na fila, onde ninguém olha. */}
                                {falhou && (
                                    <div role="alert" className="pwa-entra mt-1.5 rounded-lg bg-red-50 border border-red-200 px-2.5 py-2 text-[11px] text-red-800">
                                        <p className="font-bold">
                                            <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />{t('Não foi aceite pelo servidor')}
                                        </p>
                                        {d._erro && <p className="mt-0.5 break-words">{d._erro}</p>}
                                        <button type="button" onClick={() => void tentarDeNovo()} disabled={aTentar || motor.syncing}
                                                className="pwa-toque mt-1.5 inline-flex items-center gap-1 rounded-md bg-red-600 hover:bg-red-700 px-2.5 py-1 text-[11px] font-bold text-white disabled:opacity-60">
                                            <i className={`fas fa-rotate-right ${aTentar || motor.syncing ? 'fa-spin' : ''}`} aria-hidden="true" />{t('Tentar outra vez')}
                                        </button>
                                    </div>
                                )}
                                {d._estado_fila === 'pending' && d._erro && (
                                    <p className="mt-1 text-[10px] text-amber-700 break-words">
                                        <i className="fas fa-circle-info mr-1" aria-hidden="true" />{t('Última tentativa: :erro', { erro: String(d._erro) })}
                                    </p>
                                )}

                                <div className="flex justify-between items-end mt-1 gap-2">
                                    <div className="min-w-0">
                                        <p className="text-xs text-slate-500">
                                            <i className="far fa-calendar mr-1 opacity-60" aria-hidden="true" />{dataCurta(d.created_at)} {hora(d.created_at)}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            <i className="fas fa-list-ul mr-1 opacity-60" aria-hidden="true" />{tn(':n item|:n itens', nItens, { n: nItens })}
                                        </p>
                                        {d._server_number && (
                                            <p className="pwa-cresce text-[10px] text-emerald-700 font-bold">
                                                <i className="fas fa-hashtag mr-0.5" aria-hidden="true" />{t('Nº: :numero', { numero: String(d._server_number) })}
                                            </p>
                                        )}
                                    </div>
                                    <p className="text-lg font-bold text-blue-700 whitespace-nowrap">
                                        {dinheiro(totalDoDocumento(d))} <span className="text-xs font-semibold opacity-70">Kz</span>
                                    </p>
                                </div>

                                {/* Imprimir, com ou sem rede — como o talão do POS. Com rede
                                    e por sincronizar, espera uns segundos pelo número fiscal;
                                    sem rede sai já, com a faixa de PROVISÓRIO. */}
                                <div className="mt-2 grid grid-cols-2 gap-2">
                                    <button type="button" onClick={() => void imprimir(d)} disabled={aImprimir === d.local_uuid}
                                            className="pwa-toque rounded-lg bg-slate-800 hover:bg-slate-900 py-2 text-xs font-bold text-white disabled:opacity-50 transition">
                                        {aImprimir === d.local_uuid
                                            ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A obter o número…')}</>
                                            : <><i className="fas fa-print mr-1" aria-hidden="true" />{t('Imprimir')}</>}
                                    </button>
                                    {/* Em PDF, para o WhatsApp: sem rede faz-se no aparelho;
                                        emitido e com rede, vai o PDF do servidor. */}
                                    <button type="button" onClick={() => void partilhar(d)} disabled={aPartilhar === d.local_uuid} data-ensaio="partilhar-pdf"
                                            className="pwa-toque rounded-lg bg-teal-700 hover:bg-teal-800 py-2 text-xs font-bold text-white disabled:opacity-50 transition">
                                        {aPartilhar === d.local_uuid
                                            ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A gerar o PDF…')}</>
                                            : <><i className="fas fa-file-pdf mr-1" aria-hidden="true" />{t('PDF · WhatsApp')}</>}
                                    </button>
                                </div>
                            </div>
                        );
                    })}

                    {!filtrados.length && (
                        <div className="pwa-entra text-center py-16 text-slate-400 text-sm">
                            <i className="fas fa-inbox text-5xl mb-3 block opacity-50 pwa-flutua" aria-hidden="true" />
                            {lista.length ? (
                                <>
                                    <p className="italic">{t('Nenhum documento com estes filtros')}</p>
                                    <button type="button" onClick={() => setTipo('all')}
                                            className="pwa-toque mt-3 inline-flex items-center gap-1 text-xs bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold">
                                        <i className="fas fa-layer-group" aria-hidden="true" />{t('Ver todos')}
                                    </button>
                                </>
                            ) : (
                                <>
                                    <p className="italic">{t('Nenhum documento ainda.')}</p>
                                    <p className="italic">{t('Toca em "+ Novo" para começar.')}</p>
                                    <a href={rotas.novoDocumento}
                                       className="pwa-toque inline-block mt-3 text-xs bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold shadow-md shadow-blue-600/20 not-italic">
                                        <i className="fas fa-file-circle-plus mr-1" aria-hidden="true" />{t('Novo documento')}
                                    </a>
                                </>
                            )}
                        </div>
                    )}
                </div>
            )}

            <Nota tipo="info" className="mt-6">
                <div className="text-[11px]">
                    <p className="font-bold mb-1"><i className="fas fa-circle-info mr-1" aria-hidden="true" />{t('O que acontece a seguir')}</p>
                    {t('Os documentos sobem já emitidos, com número fiscal e hash.')}{' '}
                    {tPartes('Para emitir com validade fiscal, abre o documento no servidor e usa o botão :botao para obter o número AGT.', {
                        botao: <strong>"{t('Finalizar')}"</strong>,
                    })}
                </div>
            </Nota>
        </div>
    );
}
