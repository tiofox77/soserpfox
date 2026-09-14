import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { type EstadoDaPersonificacao, casca } from '@/api/casca';
import { ErroDaApi } from '@/api/cliente';
import { etiquetaIntl, t, tPartes } from '@/i18n';
import { FOCO, RAIO, TRANSICAO, TOQUE, cls } from '@/ui/tokens';

/**
 * A FAIXA DA PERSONIFICAÇÃO — o super admin está nesta empresa em nome de
 * alguém, e isso vê-se em TODAS as páginas.
 *
 * Não é um aviso que se fecha: enquanto durar, está lá. É o que impede o erro
 * mais caro desta porta — esquecer em nome de quem se está e fazer, como a
 * pessoa, o que se queria fazer como admin.
 *
 * Vive DENTRO da coluna da página, por cima do cabeçalho: EMPURRA o conteúdo em
 * vez de o tapar, e no telemóvel quebra em duas linhas com o botão inteiro.
 *
 * O ESTADO chega pelas props (desenha-se já, sem esperar pela rede) e depois
 * pelo `topo` da casca, de minuto a minuto — é o mesmo pedido das outras peças
 * do topo, por isso não custa nada a mais. Quando a hora do fim passa, a faixa
 * pergunta ao servidor; o servidor termina a personificação e diz para onde
 * ir, e a faixa leva o browser para lá.
 */
export default function Personificacao({ estado: inicial }: { estado: EstadoDaPersonificacao | null }) {
    const [aSair, porASair] = useState(false);
    const [, porTique] = useState(0);

    const topo = useQuery({ queryKey: ['casca', 'topo'], queryFn: casca.topo, refetchInterval: 60_000, staleTime: 30_000 });

    const estado = topo.data ? topo.data.personificacao : inicial;

    const irPara = (morada: string) => {
        porASair(true);
        window.location.assign(morada);
    };

    // O servidor terminou-a (passou o prazo, a pessoa foi desactivada): vai-se
    // para onde ele disse.
    useEffect(() => {
        const erro = topo.error;
        if (erro instanceof ErroDaApi && erro.corpo.personificacao_terminou && typeof erro.corpo.seguir_para === 'string') {
            irPara(erro.corpo.seguir_para);
        }
    }, [topo.error]);

    // Acabou noutro separador: esta página já não é de ninguém que cá esteja.
    useEffect(() => {
        if (inicial && topo.data && topo.data.personificacao === null) irPara('/home');
    }, [inicial, topo.data]);

    // À hora do fim, pergunta-se — é o pedido que faz o servidor terminá-la.
    const fim = estado?.expira_em;
    const perguntar = topo.refetch;
    useEffect(() => {
        if (!fim) return;
        const falta = new Date(fim).getTime() - Date.now();
        const relogio = window.setTimeout(() => void perguntar(), Math.max(0, falta) + 1500);
        return () => window.clearTimeout(relogio);
    }, [fim, perguntar]);

    // Os minutos que faltam contam-se sozinhos, entre perguntas ao servidor.
    useEffect(() => {
        const relogio = window.setInterval(() => porTique((n) => n + 1), 30_000);
        return () => window.clearInterval(relogio);
    }, []);

    const sair = useMutation({
        mutationFn: casca.sairDaPersonificacao,
        onMutate: () => porASair(true),
        onSuccess: (r) => irPara(r.seguir_para),
        onError: (erro) => {
            // Já tinha acabado entretanto: o servidor diz para onde ir na mesma.
            if (erro instanceof ErroDaApi && typeof erro.corpo.seguir_para === 'string') {
                irPara(erro.corpo.seguir_para);
                return;
            }
            porASair(false);
        },
    });

    if (!estado) return null;

    const minutosQueFaltam = Math.max(0, Math.ceil((new Date(estado.expira_em).getTime() - Date.now()) / 60_000));
    const aAcabar = minutosQueFaltam <= 10;

    return (
        <div
            role="status"
            aria-live="polite"
            data-ensaio="faixa-personificacao"
            className="animate-fade-in relative z-30 overflow-hidden bg-gradient-to-r from-amber-500 via-orange-500 to-orange-600 text-white shadow-md"
        >
            {/* Um risco fino em baixo: separa a faixa do cabeçalho branco mesmo
                num ecrã com pouco contraste. */}
            <div className="absolute inset-x-0 bottom-0 h-0.5 bg-white/30" aria-hidden="true" />

            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2 sm:px-6">
                <p className="flex min-w-0 flex-1 items-center gap-3 text-sm">
                    <span className="relative grid h-9 w-9 flex-none place-items-center rounded-full bg-white/20 ring-2 ring-white/40">
                        <span className="absolute inset-0 animate-ping rounded-full bg-white/20" aria-hidden="true" />
                        <i className="fas fa-user-secret animate-pulse text-base" aria-hidden="true" />
                    </span>

                    <span className="min-w-0">
                        <span className="block text-[10px] font-extrabold uppercase tracking-widest text-white/80">
                            {t('Personificação')}
                        </span>
                        <span className="block leading-snug">
                            {tPartes('Está na empresa :empresa como :pessoa', {
                                empresa: <b className="font-extrabold">{estado.empresa?.nome ?? '—'}</b>,
                                pessoa: <b className="font-extrabold">{estado.utilizador?.nome ?? '—'}</b>,
                            })}{' '}
                            <span className="whitespace-nowrap text-white/85">
                                ({t('desde :inicio · termina às :fim', { inicio: hora(estado.desde), fim: hora(estado.expira_em) })})
                            </span>
                        </span>
                    </span>
                </p>

                <div className="flex w-full flex-none items-center gap-2 sm:w-auto">
                    {aAcabar && (
                        <span className={cls('animate-pulse whitespace-nowrap bg-red-600/90 px-2.5 py-1 text-xs font-bold shadow', RAIO)}>
                            <i className="fas fa-hourglass-end mr-1" aria-hidden="true" />
                            {t('faltam :n min', { n: minutosQueFaltam })}
                        </span>
                    )}

                    <button
                        type="button"
                        onClick={() => sair.mutate()}
                        disabled={aSair}
                        data-ensaio="voltar-a-plataforma"
                        className={cls(
                            'inline-flex flex-1 items-center justify-center gap-2 bg-white px-4 py-2 text-sm font-bold text-orange-700 shadow sm:flex-none',
                            RAIO, TRANSICAO, TOQUE, FOCO,
                            'hover:bg-orange-50 disabled:cursor-wait disabled:opacity-70 disabled:transform-none',
                        )}
                    >
                        <i className={cls('fas', aSair ? 'fa-circle-notch fa-spin' : 'fa-arrow-right-from-bracket')} aria-hidden="true" />
                        {aSair ? t('A voltar…') : t('Voltar à plataforma')}
                    </button>
                </div>
            </div>
        </div>
    );
}

/** A hora de um instante ISO, na língua de quem olha (HH:MM). */
function hora(iso: string): string {
    const d = new Date(iso);

    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleTimeString(etiquetaIntl(), { hour: '2-digit', minute: '2-digit' });
}
