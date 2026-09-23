import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { agt, lerLigacao, type Contribuinte, type EstadoDaAgt, type OpcaoDoCae, type ResultadoDaLigacao } from '@/api/agt';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';
import {
    AvisoDeCaeEmFalta,
    AvisoDeComunicacaoAgt,
    InterruptoresDaAgt,
    OpcoesDoCae,
    PainelDaLigacao,
    SemCredenciaisDoProdutor,
} from './pecasDaAgt';

/**
 * A FICHA DO CONTRIBUINTE NA AGT: o NIF, o estabelecimento, os avisos, o
 * comportamento ao emitir e a chave privada «do modo antigo». É a mesma ficha
 * que o ecrã de sempre gravava, pela mesma `GestaoAgt`.
 *
 * A CHAVE NUNCA DESCE. O que este ecrã mostra dela é se está instalada ou não:
 * a resposta da API traz um sim ou não, nunca o PEM nem um pedaço dele. Uma
 * chave privada que sai numa resposta fica no registo do navegador, na cache e
 * em qualquer intermediário pelo caminho — e a partir daí qualquer um assina
 * documentos fiscais em nome da empresa.
 *
 * O par POR AMBIENTE — o caminho de hoje — e o ambiente que emite gerem-se em
 * AGT Angola, com a regra das chaves. Esta é a de trás, sem ambiente, para
 * quem ainda assina com ela.
 */
export default function CredenciaisAgt() {
    const q = useQuery({ queryKey: ['agt', 'contribuinte'], queryFn: () => agt.contribuinte() });
    const opcoes = useQuery({ queryKey: ['agt', 'opcoes'], queryFn: agt.opcoes, staleTime: 5 * 60_000 });

    /*
     * O ESTADO TRAZ O QUE A FICHA NÃO TEM: se a empresa está mesmo a comunicar
     * e se o CAE gravado serve. Não segura o ecrã — a ficha abre sem ele, e se
     * ele falhar fica só sem o aviso. É a mesma permissão que a ficha pede.
     */
    const estado = useQuery({ queryKey: ['agt', 'estado', 'activo', 0], queryFn: () => agt.estado(null), enabled: q.isSuccess, retry: false });

    if (q.isPending || opcoes.isPending) return <Carregando linhas={8} />;
    if (q.isError || opcoes.isError) {
        const erro = q.error ?? opcoes.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a ficha do contribuinte')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    return <Ficha inicial={q.data.data} podeEditar={q.data.permissoes.pode_editar} cae={opcoes.data.cae} estado={estado.data} />;
}

function Ficha({ inicial, podeEditar, cae, estado }: { inicial: Contribuinte; podeEditar: boolean; cae: OpcaoDoCae[]; estado?: EstadoDaAgt }) {
    const cache = useQueryClient();
    const [forma, porForma] = useState({
        tax_registration_number: inicial.tax_registration_number,
        agt_establishment_number: inicial.agt_establishment_number,
        agt_notification_emails: inicial.agt_notification_emails,
        agt_eac_code: inicial.agt_eac_code ?? '',
        agt_auto_submit: inicial.agt_auto_submit,
        agt_require_validation: inicial.agt_require_validation,
    });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [recado, porRecado] = useRecadoNoCanto('');
    const [ligacao, porLigacao] = useState<ResultadoDaLigacao | null>(null);

    const gravar = useMutation({
        mutationFn: () => agt.guardarContribuinte(forma),
        onSuccess: (r) => { porRecado(r.message); porErros({}); void cache.invalidateQueries({ queryKey: ['agt'] }); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const testar = useMutation({
        mutationFn: () => agt.testarLigacao(inicial.agt_environment),
        onSuccess: (r) => { porLigacao(lerLigacao(r)); void cache.invalidateQueries({ queryKey: ['agt', 'estado'] }); },
        onError: (e) => porLigacao({ ...lerLigacao(e instanceof ErroDaApi ? e.corpo : null), ok: false, mensagem: e instanceof ErroDaApi ? e.message : t('Não foi possível testar.') }),
    });

    const m = (chave: keyof typeof forma) => (ev: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => porForma({ ...forma, [chave]: ev.target.value });
    const ambiente = inicial.agt_environment === 'production' ? t('Produção') : t('Homologação');

    // O aviso segue o que está escrito, e volta se o servidor disser que o código gravado não serve.
    const caeEmFalta = !forma.agt_eac_code || (forma.agt_eac_code === (inicial.agt_eac_code ?? '') && estado?.cae_em_falta === true);

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}
            <AvisoDeErro erro={gravar.error} />

            {/* Os cartões dizem «envio automático ligado» e «sem chaves» lado a
                lado e deixam a quem lê juntar as duas coisas. O aviso junta-as:
                os documentos não estão a ser comunicados — e diz quantos. */}
            <AvisoDeComunicacaoAgt e={estado} corrigir="/invoicing/agt-settings" />

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-id-card text-slate-400" aria-hidden="true" />{t('Contribuinte na AGT')}</span>}
                accoes={<span className="flex gap-2"><Botao icone="fa-plug" aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>{t('Testar ligação')}</Botao>{podeEditar && <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao>}</span>}
            >
                <div className="mb-4 flex flex-wrap gap-2" data-estado>
                    <Etiqueta cor={inicial.agt_environment === 'production' ? 'perigo' : 'aviso'} icone={inicial.agt_environment === 'production' ? 'fa-shield-halved' : 'fa-flask'}>{t('A emitir em')} {ambiente}</Etiqueta>
                    <Etiqueta cor={inicial.produtor ? 'bom' : 'aviso'} icone="fa-building">{inicial.produtor ? t('Produtor configurado') : t('Produtor por configurar')}</Etiqueta>
                    <Etiqueta cor={forma.agt_auto_submit ? 'primaria' : 'neutra'} icone="fa-paper-plane">{forma.agt_auto_submit ? t('Envio automático ligado') : t('Envio automático desligado')}</Etiqueta>
                    {inicial.chave_legado && <Etiqueta icone="fa-key">{t('Chave do modo antigo presente')}</Etiqueta>}
                </div>

                {!inicial.produtor && <SemCredenciaisDoProdutor ambiente={ambiente} />}
                {ligacao && <PainelDaLigacao r={ligacao} />}

                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('NIF do contribuinte')} erro={erros.tax_registration_number} ajuda={t('Máximo de 15 dígitos. Tem de estar registado e ter aderido à facturação electrónica no portal da AGT.')}>
                        <input value={forma.tax_registration_number} onChange={m('tax_registration_number')} maxLength={15} placeholder={t('Ex.: 5001636863')} autoComplete="off" disabled={!podeEditar} className={cls(entrada, 'font-mono')} />
                    </Campo>
                    <Campo etiqueta={t('Estabelecimento')} erro={erros.agt_establishment_number} obrigatorio ajuda={t('O código registado no Portal do Contribuinte. Use «SEDE» se tiver apenas uma localização.')}>
                        <input value={forma.agt_establishment_number} onChange={m('agt_establishment_number')} maxLength={200} placeholder="SEDE" disabled={!podeEditar} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Código CAE (classe)')} erro={erros.agt_eac_code} className="sm:col-span-2" ajuda={t('Vai no campo eacCode de cada documento enviado à AGT.')}>
                        <select value={forma.agt_eac_code} onChange={m('agt_eac_code')} disabled={!podeEditar} className={entrada}>
                            <OpcoesDoCae cae={cae} gravado={inicial.agt_eac_code} />
                        </select>
                    </Campo>
                    {caeEmFalta && <AvisoDeCaeEmFalta className="sm:col-span-2" />}
                    <Campo
                        etiqueta={t('E-mails de aviso de erros da AGT')}
                        erro={erros.agt_notification_emails}
                        className="sm:col-span-2"
                        // resultCode 1 e 2 são códigos da própria AGT, e invoicing.agt.view o nome da permissão: não se traduzem.
                        ajuda={t('Separados por vírgula. Recebem um aviso quando a AGT rejeita uma submissão (resultCode 2) ou a aceita com erros parciais (resultCode 1). Sem e-mails, o aviso vai para quem tem a permissão invoicing.agt.view.')}
                    >
                        <input value={forma.agt_notification_emails} onChange={m('agt_notification_emails')} placeholder="financeiro@empresa.ao, fiscal@empresa.ao" disabled={!podeEditar} className={cls(entrada, 'font-mono')} />
                    </Campo>
                    <InterruptoresDaAgt
                        automatico={forma.agt_auto_submit}
                        validacao={forma.agt_require_validation}
                        aoMudarAutomatico={(v) => porForma({ ...forma, agt_auto_submit: v })}
                        aoMudarValidacao={(v) => porForma({ ...forma, agt_require_validation: v })}
                        desactivado={!podeEditar}
                    />
                </div>
                <p className="mt-4 text-sm text-slate-500">{t('As chaves RSA e o ambiente que emite gerem-se em')} <a href="/invoicing/agt-settings" className="font-semibold text-indigo-700 underline">AGT Angola</a>, {t('por ambiente.')}</p>
            </Cartao>

            <ChaveDoModoAntigo
                instalada={inicial.chave_legado}
                podeEditar={podeEditar}
                aoConcluir={(mensagem) => { porRecado(mensagem); void cache.invalidateQueries({ queryKey: ['agt'] }); }}
            />
        </div>
    );
}

/**
 * A CHAVE PRIVADA «DO MODO ANTIGO» — colar, substituir e remover, e mais nada.
 *
 * O ecrã de sempre deixava colá-la aqui e há empresas que ainda assinam com
 * ela; a migração para React tinha-a deixado de fora. O que se vê dela é a
 * etiqueta de instalada ou por instalar: o PEM sobe e nunca mais desce.
 *
 * INSTALADA, A CAIXA ESCONDE-SE. Uma caixa vazia ao lado de «Instalada»
 * convidava a colar outra por cima sem pensar — e trocar a chave com que a
 * empresa assina é coisa para se fazer de propósito. Remover pede confirmação:
 * não há volta, a chave não está guardada em mais lado nenhum.
 */
function ChaveDoModoAntigo({ instalada, podeEditar, aoConcluir }: { instalada: boolean; podeEditar: boolean; aoConcluir: (mensagem: string) => void }) {
    const [chave, porChave] = useState('');
    const [erro, porErro] = useState<string[] | undefined>(undefined);
    const [aSubstituir, porASubstituir] = useState(false);
    const [aRemover, porARemover] = useState(false);

    /*
     * A chave sobe e some-se da caixa: deixá-la escrita no ecrã depois de
     * gravada é guardá-la no sítio onde ela não devia estar.
     */
    const gravar = useMutation({
        mutationFn: () => agt.guardarChaveLegado(chave),
        onSuccess: (r) => { porChave(''); porErro(undefined); porASubstituir(false); aoConcluir(r.message); },
        onError: (e) => porErro(e instanceof ErroDaApi ? e.erros.contributor_private_key : undefined),
    });

    const remover = useMutation({
        mutationFn: () => agt.removerChaveLegado(),
        onSuccess: (r) => { porChave(''); porErro(undefined); porARemover(false); porASubstituir(false); aoConcluir(r.message); },
        onError: () => porARemover(false),
    });

    const caixaAberta = !instalada || aSubstituir;

    return (
        <Cartao
            titulo={<span className="flex items-center gap-2"><i className="fas fa-key text-slate-400" aria-hidden="true" />{t('Chave privada do contribuinte (modo antigo)')}</span>}
            accoes={
                <Etiqueta cor={instalada ? 'bom' : 'aviso'} icone={instalada ? 'fa-lock' : 'fa-lock-open'}>
                    <span data-chave-legado>{instalada ? t('Instalada') : t('Por instalar')}</span>
                </Etiqueta>
            }
        >
            <p className="mb-4 text-sm text-slate-500">
                {t('Só para as empresas que ainda assinam sem ambiente. Hoje as chaves instalam-se aos pares, por ambiente, em AGT Angola. A chave nunca volta a aparecer neste ecrã: guardada, fica só a indicação de que está instalada.')}
            </p>

            {!podeEditar ? (
                <p className="text-sm text-slate-400">{t('Sem permissão para alterar a chave.')}</p>
            ) : (
                <>
                    {instalada && !aSubstituir && (
                        <div className={cls('animate-fade-in flex flex-wrap items-center gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3', RAIO)}>
                            <i className="fas fa-circle-check text-emerald-600" aria-hidden="true" />
                            <span className="text-sm font-medium text-emerald-900">{t('Chave privada instalada.')}</span>
                            <span className="ml-auto flex flex-wrap gap-2">
                                <Botao icone="fa-arrows-rotate" onClick={() => porASubstituir(true)}>{t('Substituir a chave')}</Botao>
                                <Botao cor="perigo" icone="fa-trash" onClick={() => porARemover(true)}>{t('Remover chave')}</Botao>
                            </span>
                        </div>
                    )}

                    {caixaAberta && (
                        <div className="animate-fade-in">
                            {aSubstituir && (
                                <p className={cls('mb-3 flex items-start gap-2 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                                    <i className="fas fa-triangle-exclamation mt-0.5" aria-hidden="true" />
                                    {t('Ao guardar, a chave instalada é substituída por esta e os documentos passam a ser assinados com a nova. A antiga não fica guardada em lado nenhum.')}
                                </p>
                            )}
                            <Campo etiqueta={t('Chave privada em PEM')} erro={erro}>
                                <textarea
                                    value={chave}
                                    onChange={(ev) => porChave(ev.target.value)}
                                    rows={6}
                                    spellCheck={false}
                                    autoComplete="new-password"
                                    placeholder={'-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----'}
                                    // `entrada` traz a altura de uma linha; uma chave
                                    // são vinte e cinco, e cortadas não se conferem.
                                    className={cls(entrada, 'h-auto py-2 font-mono text-xs')}
                                />
                            </Campo>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} disabled={!chave.trim()} onClick={() => gravar.mutate()}>
                                    {t('Guardar chave')}
                                </Botao>
                                {aSubstituir && <Botao onClick={() => { porASubstituir(false); porChave(''); porErro(undefined); }}>{t('Cancelar')}</Botao>}
                            </div>
                        </div>
                    )}
                </>
            )}

            <Modal
                aberto={aRemover}
                aoFechar={() => { if (!remover.isPending) porARemover(false); }}
                titulo={t('Remover a chave do modo antigo?')}
                icone="fa-trash"
                cor="perigo"
                rodape={
                    <>
                        <Botao onClick={() => porARemover(false)} disabled={remover.isPending}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={remover.isPending} onClick={() => remover.mutate()}>{t('Remover chave')}</Botao>
                    </>
                }
            >
                <p className="text-sm font-semibold text-red-700">{t('A chave será removida permanentemente.')}</p>
                <p className="mt-2 text-sm text-slate-700">{t('Uma empresa que ainda assine com ela deixa de conseguir assinar documentos até se instalar outra.')}</p>
            </Modal>
        </Cartao>
    );
}
