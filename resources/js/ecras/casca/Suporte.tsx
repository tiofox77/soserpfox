import { useCallback, useRef, useState } from 'react';

import { t } from '@/i18n';
import { FOCO, cls } from '@/ui/tokens';

import { useFecharFora } from './comum';

/**
 * O BOTÃO FLUTUANTE DE SUPORTE.
 *
 * PODE FECHAR-SE, e isso não é um enfeite: fica por cima do canto inferior
 * direito, que é onde as tabelas põem os botões de acção. Quem está a
 * trabalhar numa lista tinha o último botão de cada linha tapado.
 *
 * Fechado, fica um puxador estreito na borda para o trazer de volta — esconder
 * de vez deixava as pessoas sem suporte e sem saber porquê. A escolha guarda-se
 * no aparelho, senão voltava a aparecer a cada página.
 */
const CHAVE = 'suporte-escondido';

function lerEscondido(): boolean {
    try {
        return localStorage.getItem(CHAVE) === '1';
    } catch {
        return false;
    }
}

type Props = { tickets: string; melhorias: string };

export default function Suporte({ tickets, melhorias }: Props) {
    const [escondido, porEscondido] = useState(lerEscondido);
    const [aberto, porAberto] = useState(false);
    const [separador, porSeparador] = useState<'tickets' | 'melhorias'>('tickets');
    const caixa = useRef<HTMLDivElement>(null);
    const fechar = useCallback(() => porAberto(false), []);

    useFecharFora(caixa, aberto, fechar);

    const esconder = () => {
        porAberto(false);
        porEscondido(true);
        try { localStorage.setItem(CHAVE, '1'); } catch { /* sem memória, volta na próxima página */ }
    };

    const mostrar = () => {
        porEscondido(false);
        try { localStorage.removeItem(CHAVE); } catch { /* idem */ }
    };

    if (escondido) {
        return (
            <button type="button" onClick={mostrar} title={t('Mostrar o suporte')} aria-label={t('Mostrar o suporte')}
                className={cls('animate-fade-in fixed bottom-6 right-0 z-50 rounded-l-lg bg-purple-600/70 px-1.5 py-3 text-white shadow-lg transition hover:bg-purple-600 hover:pr-3', FOCO)}>
                <i className="fas fa-life-ring text-xs" aria-hidden="true" />
            </button>
        );
    }

    const conteudo = separador === 'tickets'
        ? {
            texto: t('Precisa de ajuda? Abra um ticket e nossa equipe irá atendê-lo!'),
            principal: { url: tickets, icone: 'fa-plus', rotulo: t('Abrir Novo Ticket'), cor: 'from-purple-600 to-indigo-600' },
            segunda: { url: tickets, icone: 'fa-list', rotulo: t('Ver Meus Tickets') },
        }
        : {
            texto: t('Tem uma ideia para melhorar o sistema? Compartilhe e vote nas sugestões!'),
            principal: { url: melhorias, icone: 'fa-lightbulb', rotulo: t('Sugerir Melhoria'), cor: 'from-indigo-600 to-purple-600' },
            segunda: { url: melhorias, icone: 'fa-fire', rotulo: t('Ver Sugestões Populares') },
        };

    return (
        <div ref={caixa} className="group/suporte fixed bottom-6 right-6 z-50">
            {/* Fechar: só aparece ao passar o rato, para não competir com o botão. */}
            <button type="button" onClick={esconder} title={t('Fechar o suporte')} aria-label={t('Fechar o suporte')}
                className="absolute -left-1 -top-1 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-gray-700 text-[10px] leading-none text-white opacity-0 shadow transition hover:bg-gray-900 focus:opacity-100 group-hover/suporte:opacity-100">
                <i className="fas fa-xmark" aria-hidden="true" />
            </button>

            <button type="button" onClick={() => porAberto((a) => !a)} aria-expanded={aberto} aria-label={t('Precisa de ajuda?')}
                className="relative rounded-full bg-gradient-to-r from-purple-600 to-indigo-600 p-4 text-white shadow-2xl transition-all duration-300 hover:scale-110 hover:shadow-purple-500/50 focus:outline-none focus:ring-4 focus:ring-purple-300">
                <i className={cls('fas text-2xl transition-transform duration-300', aberto ? 'fa-xmark rotate-90' : 'fa-life-ring group-hover/suporte:rotate-45')} aria-hidden="true" />

                {!aberto && (
                    <span className="pointer-events-none absolute right-full top-1/2 mr-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-3 py-2 text-sm text-white opacity-0 transition-opacity group-hover/suporte:opacity-100">
                        {t('Precisa de ajuda?')}
                        <span className="absolute -right-1 top-1/2 h-2 w-2 -translate-y-1/2 rotate-45 bg-gray-900" />
                    </span>
                )}
            </button>

            {aberto && (
                <div className="animate-scale-in absolute bottom-20 right-0 w-96 max-w-[calc(100vw-3rem)] origin-bottom-right overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl">
                    <div className="bg-gradient-to-r from-purple-600 to-indigo-600 p-4">
                        <h3 className="mb-3 flex items-center text-lg font-bold text-white">
                            <i className="fas fa-headset mr-2" aria-hidden="true" /> {t('Centro de Suporte')}
                        </h3>
                        <div className="flex gap-2" role="tablist">
                            {([['tickets', 'fa-ticket-alt', t('Tickets')], ['melhorias', 'fa-lightbulb', t('Melhorias')]] as const).map(([chave, icone, rotulo]) => (
                                <button key={chave} type="button" role="tab" aria-selected={separador === chave} onClick={() => porSeparador(chave)}
                                    className={cls('flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition', separador === chave ? 'bg-white text-purple-600 shadow' : 'bg-purple-500 text-white hover:bg-purple-400')}>
                                    <i className={cls('fas mr-1', icone)} aria-hidden="true" /> {rotulo}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div key={separador} className="animate-fade-in max-h-96 overflow-y-auto p-4" role="tabpanel">
                        <p className="mb-4 text-sm text-gray-600">{conteudo.texto}</p>
                        <a href={conteudo.principal.url} className={cls('btn-press block w-full rounded-xl bg-gradient-to-r py-3 text-center font-semibold text-white transition hover:shadow-lg', conteudo.principal.cor)}>
                            <i className={cls('fas mr-2', conteudo.principal.icone)} aria-hidden="true" /> {conteudo.principal.rotulo}
                        </a>
                        <a href={conteudo.segunda.url} className="btn-press mt-2 block w-full rounded-xl bg-gray-100 py-3 text-center font-semibold text-gray-700 transition hover:bg-gray-200">
                            <i className={cls('fas mr-2', conteudo.segunda.icone)} aria-hidden="true" /> {conteudo.segunda.rotulo}
                        </a>
                    </div>

                    <div className="border-t bg-gray-50 px-4 py-3 text-center text-xs text-gray-500">
                        {t('Equipe de Suporte disponível 24/7')}
                    </div>
                </div>
            )}
        </div>
    );
}
