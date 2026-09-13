import { useRef, useState } from 'react';

import { t, tn } from '@/i18n';

import { usePwa } from '../../contexto';
import { dataCurta, hora, useAccao, useBaseViva } from '../../ganchos';
import { esquecerAquecimento } from '../../aquecer';
import { getOfflineAuthInfo, type InformacaoDoAcesso } from '../../motor/acesso';
import { db, lerMeta, type Registo } from '../../motor/base';
import { exportarCopia } from '../../motor/copia';
import { notificar, state } from '../../motor/estado';
import { getQueue, refreshPendingCount } from '../../motor/fila';
import { checkRealOnline } from '../../motor/rede';
import { sync } from '../../motor/sincronizar';
import { getShift } from '../../motor/turno';
import { getPosSales } from '../../motor/vendas';
import { avisar, confirmar } from '../../ui/Dialogos';
import { Diagnostico } from './Diagnostico';

/**
 * ============ FERRAMENTAS ============
 *
 * ESTAVA TUDO ABERTO NO ECRÃ INICIAL: sete botões técnicos, com o «Reset Total
 * — apaga TUDO incl. pendentes» a um toque de distância no ecrã que o empregado
 * vê primeiro de manhã. Não é só feio; é uma venda por sincronizar à distância
 * de um dedo enganado.
 *
 * Estas ferramentas continuam todas cá — servem quando alguma coisa corre mal,
 * e é para isso que existem. O que muda é a ordem das coisas: quem abre a
 * aplicação para vender vê o que é de vender; quem vem resolver um problema
 * abre esta gaveta. Os ensaios (`pwa-inicio.spec.js`) exigem que a gaveta seja
 * um `<details>` FECHADO e que todas as ferramentas sejam botões lá dentro.
 */

/** O que «Apagar tudo» NÃO leva da `meta`: a identidade do aparelho (ver `apagarTudo`). */
const META_A_PRESERVAR = ['device_uuid'];

interface EstadoDoAparelho {
    ultimaSync: string | null;
    artigos: number;
    isentos: number;
    comIva: number;
    clientes: number;
    clientesPorEnviar: number;
    vendas: number;
    vendasPorEnviar: number;
    filaAEspera: number;
    filaComErro: number;
    filaDeOutraEmpresa: number;
    acesso: InformacaoDoAcesso;
    turno: { aberto: boolean; numero: string | null };
    email: string;
    empresa: string;
    nif: string;
}

/** Data e hora legíveis, ou nada se o valor não for uma data. */
function quando(iso: unknown): string {
    const d = dataCurta(iso);

    return d ? `${d} ${hora(iso)}` : '';
}

/**
 * O acesso sem rede, em palavras.
 *
 * O Blade lia `auth.expired` e `auth.expires_at` — campos que o
 * `getOfflineAuthInfo()` nunca devolveu —, por isso dizia sempre «ativo até
 * Invalid Date», mesmo num aparelho sem acesso nenhum. Os campos a sério são os
 * funcionários com PIN e a janela (`valid_until`, `window_expired`), e o
 * verificador legado de um só operador (`legacy`).
 */
function textoDoAcesso(a: InformacaoDoAcesso): string {
    const partes: string[] = [];

    if (a.employees > 0) {
        partes.push(tn(':n funcionário com PIN|:n funcionários com PIN', a.employees, { n: a.employees }));

        if (!a.valid_until) {
            // FAIL-CLOSED no motor: sem a janela, o PIN não abre.
            partes.push(t('sem janela de validade — sincronize com rede'));
        } else {
            partes.push(a.window_expired
                ? t('expirado desde :data', { data: quando(a.valid_until) })
                : t('activo até :data', { data: quando(a.valid_until) }));
        }
    }

    if (a.legacy) {
        const expirado = new Date(a.legacy.expires_at) < new Date();
        partes.push(t('legado de :email', { email: a.legacy.email }));
        partes.push(expirado
            ? t('expirado desde :data', { data: quando(a.legacy.expires_at) })
            : t('activo até :data', { data: quando(a.legacy.expires_at) }));
    }

    return partes.length ? partes.join(' · ') : t('não configurado');
}

/**
 * O que «Apagar tudo» leva e ainda não chegou ao servidor.
 *
 * O Blade contava só a fila `pending` (`refreshPendingCount`). Ficavam de fora
 * os trabalhos COM ERRO e os RETIDOS de outra empresa — exactamente os que
 * estão presos e que mais importa não perder —, e as comandas do restaurante
 * ainda abertas, que nem entraram na fila. Com só isso por enviar, apagava-se
 * sem aviso nenhum e sem a segunda confirmação.
 *
 * Não se somam as vendas, os clientes e os rascunhos por enviar: cada um TEM o
 * seu trabalho na fila, e somar os dois lados contava a mesma venda duas vezes.
 * A saída de sessão pedida sem rede também não conta — não é um dado.
 */
async function porEnviarQueSePerde(): Promise<number> {
    const fila = await db.sync_queue.where('status').notEqual('done').filter((j) => j.op !== 'logout').toArray();
    const naFila = new Set(fila.filter((j) => j.op === 'sync_restaurant_order').map((j) => j.payload?.local_uuid));
    const comandasForaDaFila = await db.rest_orders.filter((c) => !c._synced && !naFila.has(c.local_uuid)).count();

    return fila.length + comandasForaDaFila;
}

export function Ferramentas() {
    const { versao } = usePwa();
    const [ocupado, setOcupado] = useState<string | null>(null);
    const aCorrer = useRef(false);
    const [mensagem, setMensagem] = useState<{ ok: boolean; texto: string } | null>(null);
    const [detalhesAbertos, setDetalhesAbertos] = useState(false);
    const [relatorio, setRelatorio] = useState<{ texto: string; copiado: boolean } | null>(null);

    // O estado do aparelho só se lê com o painel aberto: percorre o catálogo
    // inteiro para contar isentos, e isso não tem de acontecer a cada venda.
    const detalhe = useBaseViva<EstadoDoAparelho | null>(async () => {
        if (!detalhesAbertos) return null;

        const produtos = await db.products.toArray();
        const isentos = produtos.filter((p) => !(parseFloat(String(p.tax_rate)) > 0)).length;
        const turno = await getShift();
        const user = await lerMeta<Registo>('user');
        const empresa = await lerMeta<Registo>('company');

        return {
            ultimaSync: await lerMeta<string>('last_sync'),
            artigos: produtos.length,
            isentos,
            comIva: produtos.length - isentos,
            clientes: await db.clients.count(),
            clientesPorEnviar: await db.clients.where('_synced').equals(0).count(),
            vendas: await db.pos_sales.count(),
            vendasPorEnviar: await db.pos_sales.where('_synced').equals(0).count(),
            filaAEspera: await db.sync_queue.where('status').equals('pending').count(),
            filaComErro: await db.sync_queue.where('status').equals('failed').count(),
            filaDeOutraEmpresa: await db.sync_queue.where('status').equals('outra_empresa').count(),
            acesso: await getOfflineAuthInfo(),
            turno: { aberto: !!turno.open, numero: turno.number ?? null },
            email: String(user?.email || ''),
            empresa: String(empresa?.name || ''),
            nif: String(empresa?.nif || ''),
        };
    }, [detalhesAbertos], null);

    /** Corre uma ferramenta com o seu «a trabalhar…» e deixa o resultado escrito por baixo. */
    const correr = async (rotulo: string, fn: () => Promise<void>) => {
        if (aCorrer.current) return;
        aCorrer.current = true;
        setOcupado(rotulo);
        setMensagem(null);

        try {
            await fn();
            setMensagem({ ok: true, texto: t(':accao concluído', { accao: rotulo }) });
        } catch (err) {
            console.error(err);
            setMensagem({ ok: false, texto: (err as Error)?.message || t('Erro inesperado') });
        } finally {
            aCorrer.current = false;
            setOcupado(null);
        }
    };

    // Uma sincronização a meio escreveria no que se está a apagar: o catálogo
    // ficava meio cheio e parecia completo.
    const semSincronizacaoAMeio = () => {
        if (state.syncing) throw new Error(t('Já está a sincronizar — tente daqui a pouco.'));
    };

    /**
     * Sincronização COMPLETA: limpa o catálogo e volta a descarregá-lo todo. O
     * cabeçalho já sincroniza no dia-a-dia; esta é para quando os dados
     * parecem velhos.
     *
     * A REDE VERIFICA-SE ANTES DE APAGAR. O Blade só olhava para
     * `navigator.onLine` (que diz que há placa de rede): com Wi-Fi sem saída
     * apagava os artigos, a sincronização saía calada por não haver internet
     * de verdade, e o balcão ficava sem catálogo — sem rede. E dizia
     * «concluído».
     */
    const sincronizacaoCompleta = () => correr(t('Sincronização completa'), async () => {
        if (!navigator.onLine || !(await checkRealOnline())) throw new Error(t('Sem internet — impossível sync completa'));
        semSincronizacaoAMeio();

        await db.transaction('rw', [db.meta, db.products, db.clients], async () => {
            await db.meta.delete('last_sync');
            await db.meta.delete('catalog_version');
            await db.products.clear();
            // Os clientes por enviar ficam: são dados que só existem aqui.
            await db.clients.where('_synced').equals(1).delete();
        });

        await sync(true);

        // A sincronização não lança: engole o erro e mostra-o na faixa. Sem a
        // `last_sync` de volta, não terminou.
        if (!(await lerMeta('last_sync'))) {
            throw new Error(state.erroDeSync || t('A sincronização não terminou — tente outra vez com rede.'));
        }
    });

    /**
     * A CÓPIA VEM ANTES DO QUE APAGA, e de propósito: quem chega aqui com um
     * problema deve ver primeiro a forma de salvar o que ainda não subiu.
     * Funciona sem rede.
     */
    const guardarCopia = () => correr(t('Guardar cópia'), async () => {
        const r = await exportarCopia();
        const c = r.contagens;

        // As comandas abertas também contam: a cópia leva-as mesmo sem estarem
        // na fila, e o Blade dizia «saiu vazia» com a sala cheia.
        if (!c.fila && !c.vendas && !c.clientes && !c.rascunhos && !c.comandas) {
            avisar(t('Isso é bom sinal: está tudo no servidor.'), 'ok', {
                titulo: t('Não há nada por sincronizar — a cópia saiu vazia.'),
                duracao: 8000,
            });

            return;
        }

        const linhas = [
            tn(':n operação por enviar|:n operações por enviar', c.fila, { n: c.fila }),
            tn(':n venda|:n vendas', c.vendas, { n: c.vendas }),
            tn(':n cliente|:n clientes', c.clientes, { n: c.clientes }),
            tn(':n rascunho|:n rascunhos', c.rascunhos, { n: c.rascunhos }),
        ];
        if (c.comandas) linhas.push(tn(':n comanda|:n comandas', c.comandas, { n: c.comandas }));

        avisar(
            `${linhas.join('\n')}\n\n${t('Guarde este ficheiro. Se este aparelho se perder, importe-o em Faturação → Importar Cópia Offline.')}`,
            'ok',
            { titulo: t('Cópia guardada: :nome', { nome: r.nome }), duracao: 15000 },
        );
    });

    /**
     * Actualizar a aplicação: força o browser a descartar o service worker e as
     * páginas guardadas e a buscar a versão mais recente. NÃO toca no IndexedDB
     * (os dados).
     */
    const actualizarAplicacao = async () => {
        if (!navigator.onLine) {
            setMensagem({ ok: false, texto: t('Sem internet — impossível atualizar a app') });

            return;
        }

        await correr(t('Actualizar aplicação'), async () => {
            // 1) Apagar TODOS os caches do Cache Storage (páginas/recursos).
            if ('caches' in window) {
                const chaves = await caches.keys();
                await Promise.all(chaves.map((k) => caches.delete(k)));
            }

            // 2) Forçar o service worker a procurar versão nova; activá-la logo se estiver à espera.
            if ('serviceWorker' in navigator) {
                const registos = await navigator.serviceWorker.getRegistrations();
                for (const reg of registos) {
                    try { await reg.update(); } catch { /* segue para o próximo */ }
                    reg.waiting?.postMessage({ type: 'SKIP_WAITING' });
                }
            }

            // 3) Limpar a marca de pré-aquecimento (aparelhos que ainda a têm do layout antigo).
            esquecerAquecimento();

            // 4) Recarregar com cache-bust — garante HTML fresco do servidor.
            setTimeout(() => {
                window.location.href = `${window.location.pathname}?_fresh=${Date.now()}`;
            }, 600);
        });
    };

    /**
     * Limpar o catálogo local — útil quando os dados ficam «velhos». O que está
     * por enviar fica.
     */
    const limparCatalogo = async () => {
        if (aCorrer.current) return;

        const sim = await confirmar(t('Limpar produtos e clientes sincronizados do dispositivo?'), {
            texto: t('Dados pendentes de envio são preservados.'),
            sim: t('Limpar catálogo'),
            perigo: true,
            icone: 'fa-broom',
        });
        if (!sim) return;

        await correr(t('Limpar catálogo'), async () => {
            semSincronizacaoAMeio();

            await db.transaction('rw', [db.products, db.clients, db.series, db.tax_rates, db.meta], async () => {
                await db.products.clear();
                await db.clients.where('_synced').equals(1).delete();
                await db.series.clear();
                await db.tax_rates.clear();
                await db.meta.delete('last_sync');
                await db.meta.delete('catalog_version');
            });

            if (navigator.onLine) await sync(true);
        });
    };

    /**
     * APAGAR TUDO — leva também o que não foi enviado.
     *
     * Duas perguntas quando há alguma coisa por enviar, como no Blade: a
     * primeira diz quantas se perdem, a segunda pergunta outra vez. Sem nada
     * por enviar, uma chega.
     *
     * O Blade apagava oito tabelas à mão e esquecia-se das que foram chegando
     * depois: os funcionários com PIN, a sala e as comandas do restaurante, as
     * tabelas do IEC e do Imposto de Selo, a tabela antiga de rascunhos. «Apagar
     * tudo» deixava comandas por subir e verificadores de PIN no aparelho. Agora
     * percorre TODAS as tabelas da base (`db.tables`), incluindo as que vierem.
     *
     * A única coisa que fica é a identidade do aparelho (`device_uuid`): é o
     * mesmo telemóvel, e sem ela o inventário de aparelhos do servidor ganhava
     * um fantasma a cada limpeza.
     */
    const apagarTudo = async () => {
        if (aCorrer.current) return;

        await refreshPendingCount();
        const perdidos = await porEnviarQueSePerde();

        const primeira = await confirmar(t('Apagar TODOS os dados locais do PWA?'), {
            texto: [
                perdidos > 0
                    ? tn('ATENÇÃO: há :n registo POR SINCRONIZAR que será PERDIDO!|ATENÇÃO: há :n registos POR SINCRONIZAR que serão PERDIDOS!', perdidos, { n: perdidos })
                    : null,
                t('Esta ação não pode ser anulada.'),
            ].filter(Boolean).join('\n\n'),
            sim: t('Apagar tudo'),
            perigo: true,
            icone: 'fa-trash-can',
        });
        if (!primeira) return;

        if (perdidos > 0) {
            const segunda = await confirmar(t('Confirma mesmo?'), {
                texto: `${t('As vendas/rascunhos pendentes NÃO chegarão ao servidor.')}\n\n${t('"Apagar tudo" leva também o que ainda não foi enviado. Guarde a cópia primeiro.')}`,
                sim: t('Apagar tudo'),
                perigo: true,
                icone: 'fa-skull-crossbones',
            });
            if (!segunda) return;
        }

        await correr(t('Apagar tudo'), async () => {
            semSincronizacaoAMeio();

            await db.transaction('rw', db.tables, async () => {
                for (const tabela of db.tables) {
                    if (tabela.name === 'meta') {
                        await db.meta.where('key').noneOf(META_A_PRESERVAR).delete();
                    } else {
                        await tabela.clear();
                    }
                }
            });

            try { sessionStorage.clear(); } catch { /* sem armazenamento */ }
            esquecerAquecimento();

            // O cabeçalho e a faixa do estado lêem estes números do motor.
            state.retidos = 0;
            notificar();
            await refreshPendingCount();

            if (navigator.onLine) await sync(true);
        });
    };

    /**
     * Diagnóstico dos DOIS lados.
     *
     * O do servidor dizia o catálogo e ficava-se sem saber o que estava preso no
     * aparelho — e é no aparelho que as vendas ficam. Sem a fila ao lado, «3 por
     * enviar» não se explica de fora.
     *
     * O Blade abria o texto numa janela nova ou, se o browser a bloqueasse, num
     * `alert()` cortado aos 3000 caracteres — num telemóvel instalado, nem uma
     * coisa nem outra se lê. Agora vai para uma folha com o texto seleccionável
     * e um botão para copiar.
     */
    const [diagnosticar, aDiagnosticar] = useAccao(async () => {
        let servidor: unknown = null;

        try {
            const r = await fetch('/api/v1/invoicing/diagnose', {
                credentials: 'same-origin',
                cache: 'no-store',
                // Sem isto, uma sessão caída respondia com a página de login em
                // HTML e o relatório dizia só «Unexpected token <».
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const corpo = await r.text();
            let json: unknown;
            try { json = JSON.parse(corpo); } catch { json = { corpo: corpo.slice(0, 500) }; }
            servidor = r.ok ? json : { http: r.status, resposta: json };
        } catch (e) {
            servidor = { erro: String((e as Error)?.message || e) };
        }

        const fila = await getQueue();
        const vendas = await getPosSales();

        const dados = {
            gerado_em: new Date().toISOString(),
            versao: versao.assinatura,
            servidor,
            aparelho: {
                online: navigator.onLine,
                ligacao_real: state.realOnline,
                ultima_sincronizacao: await lerMeta('last_sync'),
                fila_total: fila.length,
                fila_com_erro: fila.filter((j) => j.estado === 'failed').length,
                fila,
                vendas_locais: vendas.length,
                vendas_por_emitir: vendas.filter((v) => !v._synced).length,
            },
        };

        const texto = JSON.stringify(dados, null, 2);
        let copiado = false;
        try {
            await navigator.clipboard.writeText(texto);
            copiado = true;
        } catch {
            // Sem permissão para a área de transferência: fica o botão Copiar.
        }

        setRelatorio({ texto, copiado });
    });

    return (
        <>
            <details className="pwa-entra mt-4 group bg-white rounded-2xl shadow-sm overflow-hidden" style={{ animationDelay: '260ms' }}>
                <summary className="flex items-center justify-between gap-2 p-4 cursor-pointer list-none select-none [&::-webkit-details-marker]:hidden hover:bg-slate-50 transition-colors">
                    <span className="text-xs font-bold text-gray-500 uppercase tracking-wide">
                        <i className="fas fa-screwdriver-wrench mr-1.5 transition-transform group-open:rotate-12" aria-hidden="true" />{t('Ferramentas')}
                    </span>
                    <span className="flex items-center gap-2 min-w-0">
                        {ocupado && (
                            <span className="text-[10px] text-blue-600 font-bold truncate">
                                <i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{ocupado}
                            </span>
                        )}
                        <i className="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-300 group-open:rotate-180" aria-hidden="true" />
                    </span>
                </summary>

                <div className="px-4 pb-4 space-y-3 border-t border-gray-100 pt-4">
                    {/* Sincronizar. O cabeçalho já sincroniza no dia-a-dia; a que
                        fica aqui é a COMPLETA, que volta a descarregar o catálogo todo. */}
                    <button type="button" onClick={() => void sincronizacaoCompleta()} disabled={!!ocupado}
                            className="pwa-toque w-full flex items-center gap-3 bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 text-blue-800 p-3 rounded-xl text-left disabled:opacity-50">
                        <span className="w-9 h-9 shrink-0 rounded-lg bg-white/70 flex items-center justify-center">
                            <i className={`fas fa-arrows-rotate ${ocupado === t('Sincronização completa') ? 'fa-spin' : ''}`} aria-hidden="true" />
                        </span>
                        <span className="min-w-0">
                            <span className="block text-sm font-bold">{t('Sincronização completa')}</span>
                            <span className="block text-[11px] opacity-70">{t('Volta a descarregar o catálogo e os clientes todos')}</span>
                        </span>
                    </button>

                    <button type="button" onClick={() => void guardarCopia()} disabled={!!ocupado}
                            className="pwa-toque w-full flex items-center gap-3 bg-gradient-to-r from-emerald-50 to-teal-50 hover:from-emerald-100 hover:to-teal-100 text-emerald-800 p-3 rounded-xl text-left disabled:opacity-50">
                        <span className="w-9 h-9 shrink-0 rounded-lg bg-white/70 flex items-center justify-center">
                            <i className={`fas ${ocupado === t('Guardar cópia') ? 'fa-spinner fa-spin' : 'fa-download'}`} aria-hidden="true" />
                        </span>
                        <span className="min-w-0">
                            <span className="block text-sm font-bold">{t('Guardar cópia do que falta enviar')}</span>
                            <span className="block text-[11px] opacity-70">{t('Ficheiro para importar no sistema se este aparelho se perder')}</span>
                        </span>
                    </button>

                    <div className="grid grid-cols-2 gap-2">
                        <button type="button" onClick={() => void actualizarAplicacao()} disabled={!!ocupado}
                                className="pwa-toque bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                            <i className={`fas ${ocupado === t('Actualizar aplicação') ? 'fa-spinner fa-spin' : 'fa-cloud-arrow-down'} mr-1`} aria-hidden="true" />
                            {t('Actualizar aplicação')}
                        </button>
                        {/* O diagnóstico do SERVIDOR não vê a fila, que vive no
                            aparelho. Sem ela, uma venda presa é invisível de fora. */}
                        <button type="button" onClick={() => void diagnosticar()} disabled={aDiagnosticar}
                                className="pwa-toque bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                            <i className={`fas ${aDiagnosticar ? 'fa-spinner fa-spin' : 'fa-stethoscope'} mr-1`} aria-hidden="true" />
                            {t('Diagnosticar')}
                        </button>
                    </div>

                    {mensagem && (
                        <p role={mensagem.ok ? 'status' : 'alert'}
                           className={`pwa-entra text-[11px] font-bold ${mensagem.ok ? 'text-emerald-700' : 'text-red-600'}`}>
                            <i className={`fas ${mensagem.ok ? 'fa-circle-check' : 'fa-circle-exclamation'} mr-1`} aria-hidden="true" />
                            {mensagem.texto}
                        </p>
                    )}

                    {/* O estado do aparelho, em números. Fica fechado: é para quando
                        alguém pergunta «quantos produtos é que este tablet tem?» */}
                    <button type="button" onClick={() => setDetalhesAbertos((v) => !v)} aria-expanded={detalhesAbertos}
                            aria-controls="inicio-estado-do-aparelho"
                            className="w-full text-left text-[11px] font-bold text-gray-500 hover:text-gray-700 pt-1 transition-colors">
                        <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                        {detalhesAbertos ? t('Ocultar estado do aparelho') : t('Estado do aparelho')}
                        <i className={`fas fa-chevron-down ml-1 text-[9px] transition-transform duration-300 ${detalhesAbertos ? 'rotate-180' : ''}`} aria-hidden="true" />
                    </button>

                    {detalhesAbertos && (
                        <div id="inicio-estado-do-aparelho" className="pwa-entra bg-gray-50 rounded-xl p-3 text-[11px] text-gray-700 space-y-1 break-words">
                            {!detalhe ? (
                                <p className="text-gray-400"><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('a verificar…')}</p>
                            ) : (
                                <>
                                    {(detalhe.email || detalhe.empresa) && (
                                        <p>
                                            {detalhe.email && <><i className="fas fa-user mr-1 text-gray-400" aria-hidden="true" /><strong>{detalhe.email}</strong></>}
                                            {detalhe.email && detalhe.empresa && ' · '}
                                            {detalhe.empresa && <><strong>{detalhe.empresa}</strong>{detalhe.nif && <> ({t('NIF')} {detalhe.nif})</>}</>}
                                        </p>
                                    )}
                                    <p>{t('Última sincronização:')} <strong>{detalhe.ultimaSync ? (quando(detalhe.ultimaSync) || detalhe.ultimaSync) : t('nunca')}</strong></p>
                                    <p>{t('Artigos:')} <strong>{detalhe.artigos}</strong> · {t('isentos')}: <strong>{detalhe.isentos}</strong> · {t('com IVA')}: <strong>{detalhe.comIva}</strong></p>
                                    <p>{t('Clientes:')} <strong>{detalhe.clientes}</strong> ({detalhe.clientesPorEnviar} {t('por enviar')})</p>
                                    <p>{t('Vendas no aparelho:')} <strong>{detalhe.vendas}</strong> ({detalhe.vendasPorEnviar} {t('por enviar')})</p>
                                    <p>
                                        {t('Fila:')} <strong>{detalhe.filaAEspera}</strong> {t('à espera')} · <strong className={detalhe.filaComErro ? 'text-red-600' : ''}>{detalhe.filaComErro}</strong> {t('com erro')}
                                        {detalhe.filaDeOutraEmpresa > 0 && (
                                            <> · <strong className="text-amber-700">{detalhe.filaDeOutraEmpresa}</strong> {t('retidos de outra empresa')}</>
                                        )}
                                    </p>
                                    <p>{t('Login offline:')} <strong>{textoDoAcesso(detalhe.acesso)}</strong></p>
                                    <p>{t('Turno:')} <strong>{detalhe.turno.aberto ? t('aberto (:numero)', { numero: detalhe.turno.numero || '—' }) : t('fechado')}</strong></p>
                                </>
                            )}
                        </div>
                    )}

                    {/* ZONA PERIGOSA, e assinalada como tal. Apagar o catálogo ou o
                        aparelho inteiro não pode parecer mais um botão. */}
                    <div className="border-t border-gray-100 pt-3 space-y-2">
                        <p className="text-[10px] font-bold text-red-500 uppercase tracking-wide">
                            <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />{t('Apaga dados deste aparelho')}
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            <button type="button" onClick={() => void limparCatalogo()} disabled={!!ocupado}
                                    className="pwa-toque border border-amber-200 text-amber-700 hover:bg-amber-50 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                                <i className={`fas ${ocupado === t('Limpar catálogo') ? 'fa-spinner fa-spin' : 'fa-broom'} mr-1`} aria-hidden="true" />
                                {t('Limpar catálogo')}
                            </button>
                            <button type="button" onClick={() => void apagarTudo()} disabled={!!ocupado}
                                    className="pwa-toque border border-red-200 text-red-700 hover:bg-red-50 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                                <i className={`fas ${ocupado === t('Apagar tudo') ? 'fa-spinner fa-spin' : 'fa-trash-can'} mr-1`} aria-hidden="true" />
                                {t('Apagar tudo')}
                            </button>
                        </div>
                        <p className="text-[10px] text-gray-400">
                            {t('"Apagar tudo" leva também o que ainda não foi enviado. Guarde a cópia primeiro.')}
                        </p>
                    </div>
                </div>
            </details>

            {/* Fora da gaveta, e fora de qualquer caixa com animação: a folha é
                `fixed`, e um `transform` num antepassado deslocava-a. */}
            <Diagnostico relatorio={relatorio} aoFechar={() => setRelatorio(null)} />
        </>
    );
}

