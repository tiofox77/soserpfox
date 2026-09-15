import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { AplicacoesOAuth as Dados, apiDasCopias } from '@/api/copias';
import { avisar } from '@/casca/avisos';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Etiqueta } from '@/ui/Etiqueta';
import { cascata } from '@/ui/SemNada';
import { CARTAO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

type Fornecedor = keyof Dados['aplicacoes'];

const FORNECEDORES: Array<{ chave: Fornecedor; nome: string; icone: string; cor: string; consola: string; ajuda: () => string }> = [
    { chave: 'google', nome: 'Google Drive', icone: 'fab fa-google-drive', cor: 'from-green-500 via-yellow-400 to-blue-500', consola: 'https://console.cloud.google.com/apis/credentials',
        ajuda: () => t('Google Cloud → APIs e serviços → Credenciais → ID de cliente OAuth (aplicação Web). Active a Google Drive API.') },
    { chave: 'microsoft', nome: 'OneDrive', icone: 'fab fa-microsoft', cor: 'from-sky-500 to-blue-700', consola: 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
        ajuda: () => t('Microsoft Entra → Registos de aplicações → Novo registo (contas pessoais e organizacionais) → Certificados e segredos.') },
    { chave: 'dropbox', nome: 'Dropbox', icone: 'fab fa-dropbox', cor: 'from-blue-500 to-indigo-600', consola: 'https://www.dropbox.com/developers/apps',
        ajuda: () => t('Dropbox App Console → Create app → Scoped access, App folder → permissões files.content.write e files.content.read.') },
];

/**
 * AS APLICAÇÕES OAUTH — só na plataforma.
 *
 * Para «Ligar conta» do Google, da Microsoft ou do Dropbox, o SOSERP precisa
 * de estar registado lá como aplicação. Faz-se uma vez, aqui, e serve para a
 * plataforma e para todas as empresas. O segredo nunca volta ao ecrã.
 */
export function AplicacoesOAuth({ api, aoMudar }: { api: ReturnType<typeof apiDasCopias>; aoMudar: () => void }) {
    const dados = useQuery({ queryKey: ['copias', 'plataforma', 'aplicacoes'], queryFn: api.aplicacoes });

    return (
        <section className={cls(CARTAO, 'entra p-5')} style={cascata(2)}>
            <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                <i className="fas fa-key text-amber-500" aria-hidden="true" />{t('Aplicações para ligar contas')}
            </h2>
            <p className="text-sm text-slate-500">{t('Registe o SOSERP no Google, na Microsoft e no Dropbox para as contas se ligarem com um clique — na plataforma e em todas as empresas.')}</p>
            {dados.data && (
                <div className={cls('mt-3 flex flex-wrap items-center gap-2 border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-xs', RAIO)}>
                    <span className="font-semibold text-slate-600">{t('Endereço de retorno (redirect URI):')}</span>
                    <code className="min-w-0 select-all break-all text-slate-800">{dados.data.retorno}</code>
                    <button type="button" className="ml-auto text-indigo-600 hover:text-indigo-800"
                        onClick={() => { void navigator.clipboard?.writeText(dados.data.retorno); avisar(t('Endereço copiado.'), 'ok'); }}>
                        <i className="fas fa-copy mr-1" aria-hidden="true" />{t('Copiar')}
                    </button>
                </div>
            )}
            <AvisoDeErro erro={dados.error} />
            <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                {FORNECEDORES.map((f, i) => (
                    <Aplicacao key={f.chave} f={f} i={i} api={api} estado={dados.data?.aplicacoes[f.chave]}
                        aoMudar={() => { void dados.refetch(); aoMudar(); }} />
                ))}
            </div>
        </section>
    );
}

function Aplicacao({ f, i, api, estado, aoMudar }: {
    f: (typeof FORNECEDORES)[number]; i: number; api: ReturnType<typeof apiDasCopias>;
    estado: { configurado: boolean; client_id: string | null } | undefined; aoMudar: () => void;
}) {
    const [clientId, porClientId] = useState<string | null>(null);
    const [segredo, porSegredo] = useState('');
    const guardar = useMutation({
        mutationFn: () => api.guardarAplicacao(f.chave, { client_id: clientId ?? estado?.client_id ?? '', client_secret: segredo }),
        onSuccess: () => { porSegredo(''); porClientId(null); aoMudar(); },
    });

    return (
        <div style={cascata(i)} className={cls('entra group min-w-0 border border-slate-200 p-4', RAIO, TRANSICAO, 'hover:shadow-md')}>
            <div className="flex items-center gap-3">
                <span className={cls('grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-lg text-white shadow transition-transform duration-300 group-hover:rotate-6 group-hover:scale-110', f.cor)}>
                    <i className={f.icone} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-slate-900">{f.nome}</p>
                    {estado?.configurado ? <Etiqueta cor="bom" ponto>{t('Configurada')}</Etiqueta> : <Etiqueta cor="aviso" icone="fa-circle-exclamation">{t('Por configurar')}</Etiqueta>}
                </div>
                <a href={f.consola} target="_blank" rel="noopener noreferrer" className="text-xs text-indigo-600 hover:underline" title={f.ajuda()}>
                    {t('Consola')} <i className="fas fa-arrow-up-right-from-square" aria-hidden="true" />
                </a>
            </div>
            <p className="mt-2 text-xs text-slate-500">{f.ajuda()}</p>
            <div className="mt-3 space-y-3">
                <Campo etiqueta="Client ID" obrigatorio erro={guardar.error instanceof ErroDaApi ? guardar.error.erros.client_id : undefined}>
                    <input className={entrada} autoComplete="off" value={clientId ?? estado?.client_id ?? ''} onChange={(e) => porClientId(e.target.value)} />
                </Campo>
                <Campo etiqueta="Client secret" obrigatorio={!estado?.configurado}
                    ajuda={estado?.configurado ? t('Deixe em branco para manter o que está gravado.') : undefined}
                    erro={guardar.error instanceof ErroDaApi ? guardar.error.erros.client_secret : undefined}>
                    <input type="password" className={entrada} autoComplete="new-password" value={segredo} onChange={(e) => porSegredo(e.target.value)}
                        placeholder={estado?.configurado ? '••••••••' : ''} />
                </Campo>
                <Botao cor="primaria" tom="solida" altura="pequeno" icone="fa-floppy-disk" className="w-full" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
            </div>
        </div>
    );
}
