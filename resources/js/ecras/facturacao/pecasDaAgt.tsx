import type { ReactNode } from 'react';

import type { EstadoDaAgt, ItemDeProntidao, OpcaoDoCae, ResultadoDaLigacao } from '@/api/agt';
import { etiquetaIntl, t, tPartes } from '@/i18n';
import { FOCO, RAIO, RAIO_GRANDE, TOQUE, TRANSICAO, cls } from '@/ui/tokens';

/**
 * AS PEÇAS QUE A AGT ANGOLA E A FICHA DO CONTRIBUINTE PARTILHAM.
 *
 * Os dois ecrãs mostram o mesmo CAE, os mesmos interruptores, o mesmo teste de
 * ligação e o mesmo aviso de «não está a comunicar» — e as Definições da
 * facturação mostram o aviso também. Escrito duas vezes, divergia à primeira
 * frase corrigida num sítio e esquecida no outro: o mapa de prefixos da AGT já
 * andou copiado por quatro sítios e cada cópia dizia uma coisa.
 */

type EstadoParaOAviso = Pick<EstadoDaAgt, 'comunicacao' | 'cae_em_falta' | 'auto_submit'> & {
    definicoes?: Partial<EstadoDaAgt['definicoes']>;
    ambientes?: Partial<EstadoDaAgt['ambientes']>;
};

const contagem = (n: number) => new Intl.NumberFormat(etiquetaIntl()).format(n);

/**
 * A EMPRESA JULGA QUE ESTÁ A COMUNICAR À AGT, E NÃO ESTÁ.
 *
 * Ligar o «enviar automaticamente» não chega. Sem chaves o documento não se
 * assina, sem CAE a AGT recusa, e em homologação o que segue fica no ambiente
 * de testes. Em qualquer destes casos a falha fica no registo do servidor e a
 * venda faz-se na mesma — ninguém no ecrã fica a saber. Uma farmácia tinha
 * 1108 facturas emitidas e nenhuma comunicada, com o interruptor ligado.
 *
 * Por isso é VERMELHO, diz o que acontece NA PRÁTICA e traz a contagem real.
 * É ÂMBAR só quando o único problema é o ambiente de testes: os documentos
 * seguem, mas perante a AGT continuam por comunicar — outra gravidade, e o
 * texto não as pode confundir.
 *
 * QUEM DECIDE É O SERVIDOR (`comunicacao`). Sem esse campo — um servidor que
 * ainda não o manda — o aviso cala-se: inventar um alarme é pior do que não
 * dar nenhum.
 */
export function AvisoDeComunicacaoAgt({ e, corrigir }: { e: EstadoParaOAviso | null | undefined; corrigir?: string }) {
    const c = e?.comunicacao;

    if (!e || !c) return null;

    const motivos = Array.isArray(c.motivos) ? c.motivos.filter((m) => typeof m === 'string' && m.trim() !== '') : [];

    if (c.comunica !== false && motivos.length === 0) return null;

    const activo = e.definicoes?.agt_environment;
    const automatico = e.auto_submit ?? e.definicoes?.agt_auto_submit;
    const testes = e.ambientes?.sandbox;

    // Âmbar só se, fora o ambiente, estiver tudo: chaves, produtor, CAE e o envio ligado.
    const soEmTestes = activo === 'sandbox'
        && testes?.chaves === true
        && testes?.produtor !== false
        && e.cae_em_falta !== true
        && automatico !== false;

    const tom = soEmTestes
        ? { caixa: 'border-amber-300 bg-amber-50', icone: 'bg-amber-100 text-amber-600', forte: 'text-amber-900', texto: 'text-amber-800', botao: 'from-amber-500 to-orange-500' }
        : { caixa: 'border-red-300 bg-red-50', icone: 'bg-red-100 text-red-600', forte: 'text-red-900', texto: 'text-red-800', botao: 'from-red-600 to-rose-600' };

    const explicacao = soEmTestes
        ? t('O envio automático está ligado e os documentos seguem — mas para o ambiente de testes. Perante a AGT, continuam por comunicar.')
        : automatico === false
            ? t('O envio automático está desligado: nenhum documento segue para a AGT sem que alguém o mande.')
            : t('O envio automático está ligado, por isso o sistema tenta enviar cada documento. As tentativas falham, ficam apenas no registo do servidor, e quem emite não vê nada — a venda faz-se e o documento imprime-se na mesma.');

    return (
        <div role="alert" data-aviso-comunicacao={soEmTestes ? 'testes' : 'bloqueado'} className={cls('animate-fade-in border-2 p-5 shadow-sm', RAIO_GRANDE, tom.caixa)}>
            <div className="flex items-start gap-4">
                <span className={cls('grid h-11 w-11 flex-none place-items-center rounded-xl text-xl', tom.icone)}>
                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className={cls('font-bold', tom.forte)}>
                        {soEmTestes ? t('Os documentos estão a ir para o ambiente de testes da AGT') : t('Os documentos NÃO estão a ser comunicados à AGT')}
                    </h3>
                    <p className={cls('mt-1 text-sm', tom.texto)}>{explicacao}</p>

                    {typeof c.emitidos_30d === 'number' && typeof c.por_comunicar === 'number' && (
                        <p className={cls('mt-2 text-sm font-bold', tom.forte)} data-contagem>
                            {t(':emitidas emitidas nos últimos 30 dias, :porComunicar por comunicar.', { emitidas: contagem(c.emitidos_30d), porComunicar: contagem(c.por_comunicar) })}
                        </p>
                    )}

                    {motivos.length > 0 && (
                        <ul className={cls('mt-3 space-y-1.5 text-sm', tom.texto)}>
                            {motivos.map((m) => (
                                <li key={m} className="flex items-start gap-2">
                                    <i className="fas fa-circle mt-2 flex-none text-[5px]" aria-hidden="true" />
                                    <span>{m}</span>
                                </li>
                            ))}
                        </ul>
                    )}

                    {corrigir && (
                        <a href={corrigir} className={cls('mt-4 inline-flex items-center gap-2 bg-gradient-to-r px-4 py-2 text-xs font-bold text-white shadow-md', RAIO, TOQUE, TRANSICAO, FOCO, tom.botao)}>
                            <i className="fas fa-shield-halved" aria-hidden="true" />
                            {t('Corrigir a configuração AGT')}
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}

/** O resultado de testar a ligação: o que a AGT disse, em que ambiente, e o HTTP. */
export function PainelDaLigacao({ r }: { r: ResultadoDaLigacao }) {
    return (
        <div role="status" data-ligacao={r.ok ? 'ok' : 'falhou'} className={cls('animate-fade-in mt-4 flex items-start gap-3 border px-4 py-3 text-sm', RAIO, r.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
            <span className={cls('grid h-9 w-9 flex-none place-items-center rounded-xl', r.ok ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600')}>
                <i className={cls('fas', r.ok ? 'fa-circle-check' : 'fa-circle-xmark')} aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <p className="font-bold">{r.ok ? t('A AGT respondeu.') : t('A AGT não respondeu.')}</p>
                {r.mensagem && <p className="mt-0.5 break-words">{r.mensagem}</p>}
                {(r.ambiente || r.http !== null) && (
                    <p className="mt-1 flex flex-wrap gap-x-3 text-xs opacity-80">
                        {r.ambiente && <span><i className="fas fa-server mr-1" aria-hidden="true" />{t('Ambiente: :ambiente', { ambiente: r.ambiente })}</span>}
                        {r.http !== null && <span className="font-mono">HTTP {r.http}</span>}
                    </p>
                )}
            </div>
        </div>
    );
}

/**
 * AS OPÇÕES DO CAE — com o código gravado, mesmo que já não esteja na lista.
 *
 * Um código que saiu da lista (ou de outro nível da classificação) deixava o
 * `<select>` a mostrar «— sem código —», e quem o via julgava a empresa sem
 * CAE e escolhia outro por cima de um que estava certo.
 *
 * As 575 subclasses vêm agrupadas pela divisão; escrever o código com a lista
 * aberta salta para ele, porque cada opção começa pelo código.
 */
export function OpcoesDoCae({ cae, gravado }: { cae: OpcaoDoCae[]; gravado: string | null | undefined }) {
    const foraDaLista = !!gravado && !cae.some((c) => c.codigo === gravado);
    const grupos = new Map<string, OpcaoDoCae[]>();
    for (const c of cae) {
        grupos.set(c.divisao ?? '', [...(grupos.get(c.divisao ?? '') ?? []), c]);
    }
    const opcao = (c: OpcaoDoCae) => <option key={c.codigo} value={c.codigo}>{c.codigo} · {c.descricao}</option>;

    return (
        <>
            <option value="">{t('— sem código —')}</option>
            {foraDaLista && <option value={gravado}>{t('(código gravado: :codigo)', { codigo: gravado })}</option>}
            {[...grupos].map(([divisao, lista]) => (divisao
                ? <optgroup key={divisao} label={divisao}>{lista.map(opcao)}</optgroup>
                : lista.map(opcao)))}
        </>
    );
}

/** Sem CAE, o documento vai com um marcador que não identifica actividade nenhuma. */
export function AvisoDeCaeEmFalta({ className }: { className?: string }) {
    return (
        <p role="note" data-cae-em-falta className={cls('animate-fade-in flex items-start gap-2 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO, className)}>
            <i className="fas fa-triangle-exclamation mt-0.5" aria-hidden="true" />
            {/* O «eacCode 00000» é literal: é o valor que vai mesmo na AGT. */}
            <span>{tPartes('Sem CAE os documentos vão com :codigo, que não identifica a actividade — a AGT pode recusá-los.', { codigo: <span className="font-mono font-semibold">eacCode 00000</span> })}</span>
        </p>
    );
}

/** As credenciais do produtor são do software, por ambiente — e sem elas nada chega à AGT. */
export function SemCredenciaisDoProdutor({ ambiente }: { ambiente?: string }) {
    return (
        <div role="alert" data-sem-produtor className={cls('animate-fade-in mt-4 flex items-start gap-3 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)}>
            <i className="fas fa-triangle-exclamation mt-0.5 text-red-500" aria-hidden="true" />
            <div className="space-y-1">
                <p className="font-bold">{ambiente ? t('Credenciais do produtor não configuradas em :ambiente.', { ambiente }) : t('Credenciais do produtor não configuradas.')}</p>
                <p className="text-xs">{t('O administrador do sistema tem de configurar as credenciais Basic Auth do produtor de software — sem elas nenhum pedido chega à AGT. São do produtor, não do contribuinte.')}</p>
            </div>
        </div>
    );
}

/**
 * UM INTERRUPTOR DA AGT, com a frase que diz o que faz.
 *
 * «Exigir validação prévia», sozinho, cada um lê à sua maneira; a linha por
 * baixo é o que o ecrã de sempre tinha e a migração deixou cair.
 */
export function InterruptorDaAgt({
    marcado, aoMudar, desactivado, titulo, descricao, icone, nota,
}: {
    marcado: boolean; aoMudar: (v: boolean) => void; desactivado?: boolean;
    titulo: string; descricao: string; icone: string; nota?: string;
}) {
    return (
        <label className={cls(
            'flex items-start gap-3 border p-4',
            RAIO, TRANSICAO,
            desactivado ? 'cursor-not-allowed' : 'cursor-pointer hover:-translate-y-0.5 hover:shadow-md',
            marcado ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-white',
        )}>
            <input type="checkbox" checked={marcado} disabled={desactivado} onChange={(ev) => aoMudar(ev.target.checked)} className={cls('mt-1 h-4 w-4 rounded border-slate-300', FOCO)} />
            <span className={cls('grid h-9 w-9 flex-none place-items-center rounded-xl transition-colors duration-200', marcado ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400')}>
                <i className={cls('fas', icone)} aria-hidden="true" />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-slate-800">
                    {titulo}
                    {nota && <span className="ml-1 text-xs font-normal text-slate-400">{nota}</span>}
                </span>
                <span className="mt-0.5 block text-xs text-slate-500">{descricao}</span>
            </span>
        </label>
    );
}

/**
 * OS DOIS INTERRUPTORES, iguais nos dois ecrãs.
 *
 * A VALIDAÇÃO OBRIGATÓRIA AINDA NÃO FAZ NADA: grava-se, e nenhum código do
 * servidor a lê antes de emitir. Diz-se no ecrã — ligar um travão que não
 * trava é pior do que não o ter.
 */
export function InterruptoresDaAgt({
    automatico, validacao, aoMudarAutomatico, aoMudarValidacao, desactivado,
}: {
    automatico: boolean; validacao: boolean; aoMudarAutomatico: (v: boolean) => void; aoMudarValidacao: (v: boolean) => void; desactivado?: boolean;
}) {
    return (
        <>
            <InterruptorDaAgt
                marcado={automatico} aoMudar={aoMudarAutomatico} desactivado={desactivado} icone="fa-paper-plane"
                titulo={t('Enviar à AGT automaticamente ao emitir')}
                descricao={t('Cada documento fiscal (FT/FR/NC/ND) é enviado à AGT automaticamente após a gravação.')}
            />
            <InterruptorDaAgt
                marcado={validacao} aoMudar={aoMudarValidacao} desactivado={desactivado} icone="fa-check-double"
                titulo={t('Exigir validação prévia')}
                nota={t('(ainda sem efeito)')}
                descricao={t('Exigir a validação da AGT antes de imprimir o documento.')}
            />
        </>
    );
}

/** A lista do que é preciso para um passo, com um visto ou uma cruz por item. */
export function ListaDeRequisitos({ itens, titulo, frase }: { itens: ItemDeProntidao[]; titulo?: ReactNode; frase?: ReactNode }) {
    return (
        <div data-requisitos>
            {titulo && <p className="text-sm font-bold text-slate-800">{titulo}</p>}
            {frase && <p className="mt-0.5 text-xs text-slate-500">{frase}</p>}
            <ul className="mt-2 space-y-1.5 text-sm">
                {itens.map((i) => (
                    <li key={i.chave} data-requisito={i.chave} data-ok={i.ok ? '1' : '0'} className={cls('flex items-center gap-2', i.ok ? 'text-emerald-700' : 'font-semibold text-red-700')}>
                        <i className={cls('fas', i.ok ? 'fa-circle-check' : 'fa-circle-xmark')} aria-hidden="true" />
                        <span>{i.rotulo}</span>
                        <span className="sr-only">{i.ok ? t('(cumprido)') : t('(em falta)')}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Os itens válidos de uma lista que veio do servidor — o que não tiver forma fica de fora. */
export function itensDeProntidao(lista: unknown): ItemDeProntidao[] | null {
    if (!Array.isArray(lista)) return null;

    return lista
        .filter((i): i is ItemDeProntidao => !!i && typeof i === 'object' && typeof (i as ItemDeProntidao).rotulo === 'string')
        .map((i, n) => ({ chave: String(i.chave ?? n), rotulo: i.rotulo, ok: i.ok === true }));
}
