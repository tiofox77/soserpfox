import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import { casca, type PaginaInicial, type PrimeiroPasso } from '@/api/casca';
import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { cls } from '@/ui/tokens';

/**
 * O PRÓXIMO PASSO E A AJUDA PARA COMEÇAR (26/09/2026).
 *
 * Depois do registo, a página inicial mostrava números a zero e mais nada.
 * Agora diz UM passo concreto, pelo módulo do plano (o primeiro quarto, a
 * primeira mesa, o primeiro produto…), e oferece «Preciso de ajuda para
 * começar» — que abre um pedido de suporte e, SÓ SE a pessoa marcar a caixa,
 * autoriza um contacto por WhatsApp. A caixa começa desmarcada, e a recusa
 * também fica gravada.
 */
export function PrimeiroPassoEAjuda({ passo, ajuda }: { passo: PrimeiroPasso | null; ajuda: PaginaInicial['ajuda'] }) {
    const [aberta, porAberta] = useState(false);

    if (!passo && !ajuda) return null;

    return (
        <section aria-labelledby="primeiro-passo" className="entra mb-8 overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-lg">
            <div className="flex flex-col gap-5 bg-gradient-to-br from-emerald-50 to-teal-50 p-6 sm:flex-row sm:items-center">
                <span className="icon-float flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/40">
                    <i className={cls('fas text-2xl text-white', passo?.icone ?? 'fa-life-ring')} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold uppercase tracking-wider text-emerald-700">{passo ? t('Próximo passo') : t('Ajuda')}</p>
                    <h3 id="primeiro-passo" className="text-xl font-bold text-gray-900">{passo ? passo.titulo : t('Precisa de ajuda para começar?')}</h3>
                    <p className="mt-1 text-sm text-gray-600">{passo ? passo.texto : t('A equipa ajuda-o a pôr o sistema a funcionar para a sua empresa.')}</p>
                </div>
                <div className="flex flex-col gap-2 sm:items-end">
                    {passo && (
                        <a href={passo.url} className="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-emerald-700">
                            <i className="fas fa-arrow-right mr-2" aria-hidden="true" />{passo.botao}
                        </a>
                    )}
                    {ajuda && (
                        <button type="button" onClick={() => porAberta(true)} className="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-emerald-800 underline-offset-4 transition hover:bg-emerald-100 hover:underline">
                            <i className="fas fa-life-ring mr-2" aria-hidden="true" />{t('Preciso de ajuda para começar')}
                        </button>
                    )}
                </div>
            </div>

            {ajuda && aberta && <JanelaDeAjuda ajuda={ajuda} modulo={passo?.modulo ?? null} aoFechar={() => porAberta(false)} />}
        </section>
    );
}

function JanelaDeAjuda({ ajuda, modulo, aoFechar }: { ajuda: NonNullable<PaginaInicial['ajuda']>; modulo: string | null; aoFechar: () => void }) {
    const [mensagem, porMensagem] = useState('');
    // Nunca pré-marcado: o «sim» tem de ser a pessoa a dá-lo.
    const [whatsapp, porWhatsapp] = useState(false);
    const [telefone, porTelefone] = useState(ajuda.telefone ?? '');

    const pedir = useMutation({
        mutationFn: () => casca.pedirAjuda({ mensagem, whatsapp, telefone: whatsapp ? telefone : '', modulo }),
    });

    const erros = pedir.error instanceof ErroDaApi ? pedir.error.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Preciso de ajuda para começar')}
            subtitulo={t('Abre um pedido de suporte; a equipa responde-lhe em Suporte.')}
            icone="fa-life-ring"
            cor="bom"
            rodape={
                pedir.isSuccess ? (
                    <Botao cor="primaria" tom="solida" onClick={aoFechar}>{t('Fechar')}</Botao>
                ) : (
                    <>
                        <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                        <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={pedir.isPending} onClick={() => pedir.mutate()}>
                            {t('Pedir ajuda')}
                        </Botao>
                    </>
                )
            }
        >
            {pedir.isSuccess ? (
                <div role="status" className="space-y-3 text-sm text-slate-700">
                    <p className="flex items-start gap-2 font-semibold text-emerald-700">
                        <i className="fas fa-circle-check mt-0.5" aria-hidden="true" /><span>{pedir.data.message}</span>
                    </p>
                    <p>
                        <a href={ajuda.suporte} className="font-semibold text-emerald-700 underline underline-offset-4">{t('Abrir o Suporte')}</a>
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={pedir.error} />

                    <Campo etiqueta={t('O que quer fazer? (opcional)')} erro={erros.mensagem}>
                        <textarea
                            id="ajuda-mensagem"
                            className={cls(entrada, 'min-h-[84px]')}
                            maxLength={1000}
                            value={mensagem}
                            onChange={(e) => porMensagem(e.target.value)}
                            placeholder={t('Por exemplo: quero emitir a primeira factura')}
                        />
                    </Campo>

                    <div className="rounded-xl border border-slate-200 p-3">
                        <label htmlFor="ajuda-whatsapp" className="flex cursor-pointer items-start gap-3 text-sm text-slate-700">
                            <input
                                id="ajuda-whatsapp"
                                type="checkbox"
                                className="mt-1 h-4 w-4 shrink-0"
                                checked={whatsapp}
                                onChange={(e) => porWhatsapp(e.target.checked)}
                            />
                            <span>
                                <i className="fab fa-whatsapp mr-1 text-emerald-600" aria-hidden="true" />
                                {ajuda.texto_whatsapp}
                            </span>
                        </label>

                        {whatsapp && (
                            <Campo etiqueta={t('Número com WhatsApp')} erro={erros.telefone} obrigatorio className="mt-3">
                                <input
                                    id="ajuda-telefone"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    className={entrada}
                                    value={telefone}
                                    onChange={(e) => porTelefone(e.target.value)}
                                    placeholder="9XX XXX XXX"
                                />
                            </Campo>
                        )}

                        <p className="mt-2 text-xs text-slate-400">
                            {whatsapp
                                ? t('Versão do texto: :versao.', { versao: ajuda.versao_whatsapp })
                                : t('Sem esta autorização, a equipa responde só no Suporte e por email.')}
                        </p>
                    </div>
                </div>
            )}
        </Modal>
    );
}
