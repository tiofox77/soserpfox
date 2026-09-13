import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { crm, type LigacaoMeta } from '@/api/crm';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * A LIGAÇÃO AO META — Facebook, Instagram e WhatsApp desta empresa.
 *
 * OS TOKENS SÃO SEGREDOS E NUNCA VOLTAM AO ECRÃ. Quando já estão configurados,
 * o campo diz «configurado» e só os SUBSTITUI se alguém escrever um valor novo:
 * deixar em branco mantém o que lá está — que é o que acontece sempre que
 * alguém abre isto só para mudar o nome da página.
 *
 * O URL DO WEBHOOK E O TOKEN DE VERIFICAÇÃO estão prontos a copiar: é o par que
 * se cola no painel do Meta, e o token grava-se logo à primeira abertura — sem
 * isso o aperto de mão falhava enquanto ninguém carregasse em Guardar.
 */

type Formulario = LigacaoMeta & {
    app_secret: string;
    facebook_page_token: string;
    whatsapp_token: string;
};

export default function Meta() {
    const [f, porF] = useState<Formulario | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    const [copiado, porCopiado] = useState<string | null>(null);

    const ligacao = useQuery({ queryKey: ['crm', 'meta'], queryFn: () => crm.meta.ler() });

    useEffect(() => {
        if (ligacao.data && f === null) {
            porF({
                ...ligacao.data.data,
                app_secret: '', facebook_page_token: '', whatsapp_token: '',
            });
        }
    }, [ligacao.data, f]);

    const guardar = useMutation({
        mutationFn: () => crm.meta.guardar(f as unknown as Record<string, unknown>),
        onSuccess: async (r) => {
            porRecado(r.message);

            const fresco = await crm.meta.ler();

            porF({ ...fresco.data, app_secret: '', facebook_page_token: '', whatsapp_token: '' });
            await ligacao.refetch();
        },
    });

    const novoToken = useMutation({
        mutationFn: () => crm.meta.novoToken(),
        onSuccess: (r) => porF((x) => (x ? { ...x, webhook_verify_token: r.webhook_verify_token } : x)),
    });

    const testar = useMutation({ mutationFn: () => crm.meta.testarWhatsApp() });

    if (ligacao.isPending || f === null) return <Carregando linhas={8} />;
    if (ligacao.isError) return <AvisoDeErro erro={ligacao.error} />;

    const segredos = ligacao.data.segredos;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const copiar = async (texto: string, qual: string) => {
        try {
            await navigator.clipboard.writeText(texto);
            porCopiado(qual);
            window.setTimeout(() => porCopiado(null), 2000);
        } catch {
            /* Sem área de transferência: o valor está à vista para copiar à mão. */
        }
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Integração Meta')}
                subtitulo={t('Facebook, Instagram e WhatsApp desta empresa')}
                icone="fa-plug"
                cor="ciano"
                accoes={
                    <a href="/crm/leads" className={ACCAO_DA_FAIXA}>
                        <i className="fas fa-user-plus" aria-hidden="true" />
                        {t('Leads')}
                    </a>
                }
            />

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={guardar.error} />

            {/*
              * O PAR QUE SE COLA NO META. É o primeiro bloco de propósito: sem
              * estes dois valores no painel do Meta, nada do resto chega cá.
              */}
            <Cartao
                titulo={t('O que se cola no painel do Meta')}
                subtitulo={t('O endereço do webhook e o token de verificação')}
                icone="fa-link"
            >
                <div className="space-y-4">
                    <Campo etiqueta={t('URL do webhook')}>
                        <div className="flex gap-2">
                            <input type="text" readOnly value={ligacao.data.url_do_webhook}
                                className={cls(entrada, 'font-mono text-xs')} />
                            <Botao icone={copiado === 'url' ? 'fa-check' : 'fa-copy'}
                                onClick={() => copiar(ligacao.data.url_do_webhook, 'url')}
                                aria-label={t('Copiar')} />
                        </div>
                    </Campo>

                    <Campo
                        etiqueta={t('Token de verificação')} obrigatorio erro={erros.webhook_verify_token}
                        ajuda={t('É o que o Meta devolve no aperto de mão. Já está gravado — mudar aqui obriga a repetir a verificação lá.')}
                    >
                        <div className="flex gap-2">
                            <input type="text" value={f.webhook_verify_token}
                                onChange={(e) => porF({ ...f, webhook_verify_token: e.target.value })}
                                className={cls(entrada, 'font-mono text-xs')} />
                            <Botao icone={copiado === 'token' ? 'fa-check' : 'fa-copy'}
                                onClick={() => copiar(f.webhook_verify_token, 'token')}
                                aria-label={t('Copiar')} />
                            <Botao icone="fa-rotate" aTrabalhar={novoToken.isPending}
                                onClick={() => novoToken.mutate()}
                                aria-label={t('Gerar outro')} />
                        </div>
                    </Campo>
                </div>
            </Cartao>

            <Cartao titulo={t('A aplicação do Meta')} icone="fa-cube">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('App ID')} erro={erros.app_id}>
                        <input type="text" value={f.app_id}
                            onChange={(e) => porF({ ...f, app_id: e.target.value })}
                            className={cls(entrada, 'font-mono text-xs')} />
                    </Campo>

                    <CampoDeSegredo
                        etiqueta={t('App Secret')}
                        valor={f.app_secret}
                        guardado={segredos.app_secret}
                        aoMudar={(v) => porF({ ...f, app_secret: v })}
                    />
                </div>
            </Cartao>

            <Cartao titulo={t('WhatsApp')} icone="fa-comment-dots"
                subtitulo={t('É por aqui que se responde a um lead sem sair da fila')}>
                <div className="space-y-4">
                    <label className="flex items-center gap-3">
                        <input type="checkbox" checked={f.whatsapp_enabled}
                            onChange={(e) => porF({ ...f, whatsapp_enabled: e.target.checked })}
                            className="h-5 w-5 rounded text-cyan-600" />
                        <span className="text-sm font-medium text-slate-700">{t('WhatsApp ligado')}</span>
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Phone Number ID')} erro={erros.whatsapp_phone_number_id}>
                            <input type="text" value={f.whatsapp_phone_number_id}
                                onChange={(e) => porF({ ...f, whatsapp_phone_number_id: e.target.value })}
                                className={cls(entrada, 'font-mono text-xs')} />
                        </Campo>

                        <Campo etiqueta={t('Business Account ID')}>
                            <input type="text" value={f.whatsapp_business_account_id}
                                onChange={(e) => porF({ ...f, whatsapp_business_account_id: e.target.value })}
                                className={cls(entrada, 'font-mono text-xs')} />
                        </Campo>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Número que aparece')}
                            ajuda={t('O que o cliente vê quando recebe a mensagem.')}>
                            <input type="text" value={f.whatsapp_display_number}
                                onChange={(e) => porF({ ...f, whatsapp_display_number: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <CampoDeSegredo
                            etiqueta={t('Token do WhatsApp')}
                            valor={f.whatsapp_token}
                            guardado={segredos.whatsapp_token}
                            aoMudar={(v) => porF({ ...f, whatsapp_token: v })}
                        />
                    </div>

                    {/*
                      * O TESTE PERGUNTA AO META PELO PRÓPRIO NÚMERO e não manda
                      * mensagem nenhuma: um teste que escreve a um cliente é
                      * pior do que não ter teste.
                      */}
                    <div className="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                        <Botao icone="fa-plug-circle-check" aTrabalhar={testar.isPending}
                            onClick={() => testar.mutate()}>
                            {t('Testar a ligação')}
                        </Botao>

                        <span className="text-xs text-slate-500">
                            {t('Pergunta ao Meta pelo próprio número. Não envia mensagem nenhuma.')}
                        </span>

                        {testar.data && (
                            <Etiqueta cor={testar.data.ok ? 'bom' : 'perigo'}
                                icone={testar.data.ok ? 'fa-circle-check' : 'fa-triangle-exclamation'}>
                                {testar.data.message}
                            </Etiqueta>
                        )}
                    </div>
                </div>
            </Cartao>

            <Cartao titulo={t('Facebook')} icone="fa-facebook">
                <div className="space-y-4">
                    <label className="flex items-center gap-3">
                        <input type="checkbox" checked={f.facebook_enabled}
                            onChange={(e) => porF({ ...f, facebook_enabled: e.target.checked })}
                            className="h-5 w-5 rounded text-cyan-600" />
                        <span className="text-sm font-medium text-slate-700">{t('Facebook ligado')}</span>
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('ID da página')} erro={erros.facebook_page_id}>
                            <input type="text" value={f.facebook_page_id}
                                onChange={(e) => porF({ ...f, facebook_page_id: e.target.value })}
                                className={cls(entrada, 'font-mono text-xs')} />
                        </Campo>

                        <Campo etiqueta={t('Nome da página')}>
                            <input type="text" value={f.facebook_page_name}
                                onChange={(e) => porF({ ...f, facebook_page_name: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>

                    <CampoDeSegredo
                        etiqueta={t('Token da página')}
                        valor={f.facebook_page_token}
                        guardado={segredos.facebook_page_token}
                        aoMudar={(v) => porF({ ...f, facebook_page_token: v })}
                    />
                </div>
            </Cartao>

            <Cartao titulo={t('Instagram')} icone="fa-instagram">
                <div className="space-y-4">
                    <label className="flex items-center gap-3">
                        <input type="checkbox" checked={f.instagram_enabled}
                            onChange={(e) => porF({ ...f, instagram_enabled: e.target.checked })}
                            className="h-5 w-5 rounded text-cyan-600" />
                        <span className="text-sm font-medium text-slate-700">{t('Instagram ligado')}</span>
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('ID da conta')} erro={erros.instagram_account_id}>
                            <input type="text" value={f.instagram_account_id}
                                onChange={(e) => porF({ ...f, instagram_account_id: e.target.value })}
                                className={cls(entrada, 'font-mono text-xs')} />
                        </Campo>

                        <Campo etiqueta={t('Nome de utilizador')}>
                            <input type="text" value={f.instagram_username}
                                onChange={(e) => porF({ ...f, instagram_username: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                </div>
            </Cartao>

            <Cartao titulo={t('O que fazer com o que chega')} icone="fa-inbox">
                <div className="space-y-3">
                    <label className="flex items-start gap-3">
                        <input type="checkbox" checked={f.criar_leads}
                            onChange={(e) => porF({ ...f, criar_leads: e.target.checked })}
                            className="mt-0.5 h-5 w-5 rounded text-cyan-600" />
                        <span>
                            <span className="block text-sm font-medium text-slate-700">
                                {t('Criar um lead por cada conversa nova')}
                            </span>
                            <span className="block text-xs text-slate-500">
                                {t('Desligado, as mensagens ficam sem ninguém a segui-las.')}
                            </span>
                        </span>
                    </label>

                    <label className="flex items-start gap-3">
                        <input type="checkbox" checked={f.lead_ads_enabled}
                            onChange={(e) => porF({ ...f, lead_ads_enabled: e.target.checked })}
                            className="mt-0.5 h-5 w-5 rounded text-cyan-600" />
                        <span>
                            <span className="block text-sm font-medium text-slate-700">
                                {t('Receber os formulários dos anúncios (Lead Ads)')}
                            </span>
                            <span className="block text-xs text-slate-500">
                                {t('Quem preenche o formulário do anúncio entra na fila dos leads.')}
                            </span>
                        </span>
                    </label>
                </div>
            </Cartao>

            <div className="flex justify-end">
                <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                    onClick={() => guardar.mutate()}>
                    {t('Guardar')}
                </Botao>
            </div>
        </div>
    );
}

/**
 * UM CAMPO DE SEGREDO.
 *
 * Mostra «configurado» quando já há um valor gravado e escreve-se por cima para
 * o substituir. O valor guardado NUNCA chega ao browser — nem sequer ao de quem
 * o escreveu — e por isso não há nada para revelar com um olho ao lado.
 */
function CampoDeSegredo({
    etiqueta, valor, guardado, aoMudar,
}: {
    etiqueta: string;
    valor: string;
    guardado: boolean;
    aoMudar: (v: string) => void;
}) {
    return (
        <Campo
            etiqueta={etiqueta}
            ajuda={guardado
                ? t('Já está guardado. Deixe em branco para o manter; escreva para o substituir.')
                : t('Ainda não está configurado.')}
        >
            <div className="flex items-center gap-2">
                <input
                    type="password"
                    value={valor}
                    autoComplete="off"
                    onChange={(e) => aoMudar(e.target.value)}
                    placeholder={guardado ? '••••••••••••' : ''}
                    className={cls(entrada, 'font-mono text-xs')}
                />
                {guardado && (
                    <Etiqueta cor="bom" icone="fa-lock">{t('Configurado')}</Etiqueta>
                )}
            </div>
        </Campo>
    );
}
