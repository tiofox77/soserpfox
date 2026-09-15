import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import type { Ambito, Copia, Destino, FicheiroRemoto, Restauro, apiDasCopias } from '@/api/copias';
import { ErroDaApi } from '@/api/cliente';
import { avisar } from '@/casca/avisos';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { RAIO, cls } from '@/ui/tokens';

import { dataHora, tamanho } from './comum';

type Api = ReturnType<typeof apiDasCopias>;

/** De onde vem o que se repõe: uma cópia da lista, um ficheiro num destino, ou um do computador. */
export type OrigemDoRestauro =
    | { tipo: 'copia'; copia: Copia }
    | { tipo: 'remoto'; destino: Destino; ficheiro: FicheiroRemoto }
    | { tipo: 'carregado' };

const ESTADOS: Record<Restauro['estado'], { rotulo: () => string; icone: string; cor: string }> = {
    a_correr: { rotulo: () => t('A repor…'), icone: 'fa-spinner fa-spin', cor: 'text-indigo-600' },
    concluido: { rotulo: () => t('Reposição concluída'), icone: 'fa-circle-check', cor: 'text-emerald-600' },
    falhou: { rotulo: () => t('A reposição falhou'), icone: 'fa-circle-xmark', cor: 'text-red-600' },
    recusado: { rotulo: () => t('Reposição recusada'), icone: 'fa-ban', cor: 'text-amber-600' },
};

export function estadoDoRestauro(estado: Restauro['estado']) {
    return ESTADOS[estado];
}

/**
 * REPOR UMA CÓPIA — a acção mais perigosa do sistema, e por isso a mais pedida.
 *
 * Três travões, todos no servidor e aqui só explicados: escrever RESTAURAR, a
 * senha de quem pede (5 tentativas em 10 minutos) e, antes de mexer em nada,
 * uma cópia do estado actual — que é o que permite voltar atrás de um engano.
 * Numa empresa, o servidor recusa se entretanto se emitiram documentos fiscais:
 * repor apagava facturas que já foram comunicadas à AGT.
 */
export function ModalDeRepor({ api, ambito, origem, aoFechar, aoMudar }: {
    api: Api;
    ambito: Ambito;
    origem: OrigemDoRestauro | null;
    aoFechar: () => void;
    aoMudar: () => void;
}) {
    const [frase, porFrase] = useState('');
    const [senha, porSenha] = useState('');
    const [confirmacao, porConfirmacao] = useState('');
    const [ficheiro, porFicheiro] = useState<File | null>(null);
    const [restauroId, porRestauroId] = useState<number | null>(null);

    useEffect(() => {
        if (origem) {
            porFrase(''); porSenha(''); porConfirmacao(''); porFicheiro(null); porRestauroId(null);
        }
    }, [origem]);

    const lancar = useMutation({
        mutationFn: () => {
            if (!origem) throw new Error('sem origem');
            if (origem.tipo === 'carregado') {
                if (!ficheiro) throw new Error(t('Escolha o ficheiro da cópia.'));

                return api.restaurarCarregado(ficheiro, { frase, senha, confirmacao });
            }

            return api.restaurar(origem.tipo === 'copia'
                ? { copia_id: origem.copia.id, frase, senha, confirmacao }
                : { destino_id: origem.destino.id, ficheiro: origem.ficheiro.remoto, frase, senha, confirmacao });
        },
        onSuccess: (r) => { porRestauroId(r.restauro_id); porSenha(''); aoMudar(); },
    });

    const acompanhar = useQuery({
        queryKey: ['copias', ambito, 'restauro', restauroId],
        queryFn: () => api.restauro(restauroId as number),
        enabled: restauroId !== null,
        // Repor a base inteira pode fechar a sessão a meio; o erro não interrompe o acompanhamento.
        retry: 5,
        refetchInterval: (q) => (q.state.data?.restauro.estado === 'a_correr' || !q.state.data ? 2000 : false),
    });

    const restauro = acompanhar.data?.restauro ?? null;
    const terminou = restauro !== null && restauro.estado !== 'a_correr';

    useEffect(() => {
        if (!restauro || !terminou) return;
        aoMudar();
        if (restauro.estado === 'concluido') avisar(t('Cópia reposta com sucesso.'), 'ok');
        else avisar(restauro.erro ?? estadoDoRestauro(restauro.estado).rotulo(), restauro.estado === 'recusado' ? 'aviso' : 'erro');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [terminou]);

    if (!origem) return <Modal aberto={false} aoFechar={aoFechar} titulo="">{null}</Modal>;

    const erros = (lancar.error instanceof ErroDaApi ? lancar.error.erros : {}) as Record<string, string[]>;
    const nome = origem.tipo === 'copia' ? origem.copia.ficheiro : origem.tipo === 'remoto' ? origem.ficheiro.nome : ficheiro?.name;
    const cifrada = origem.tipo === 'copia' ? origem.copia.cifrada : (nome ?? '').endsWith('.soscopia') || origem.tipo === 'carregado';
    const aDecorrer = restauroId !== null;
    const podeLancar = confirmacao === 'RESTAURAR' && senha !== '' && (origem.tipo !== 'carregado' || ficheiro !== null);

    return (
        <Modal
            aberto
            aoFechar={() => { if (!aDecorrer || terminou) aoFechar(); }}
            titulo={ambito === 'plataforma' ? t('Repor a base de dados') : t('Repor os dados da empresa')}
            subtitulo={nome ?? t('Ficheiro do computador')}
            icone="fa-rotate-left"
            cor="aviso"
            largura="md"
            rodape={aDecorrer ? (
                <div className="flex justify-end">
                    <Botao cor="neutra" disabled={!terminou} onClick={aoFechar}>{t('Fechar')}</Botao>
                </div>
            ) : (
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="perigo" tom="solida" icone="fa-rotate-left" disabled={!podeLancar} aTrabalhar={lancar.isPending} onClick={() => lancar.mutate()}>
                        {t('Repor agora')}
                    </Botao>
                </div>
            )}
        >
            {aDecorrer ? (
                <div className="py-4 text-center" aria-live="polite">
                    {!restauro ? (
                        <p className="text-sm text-slate-500"><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('A começar…')}</p>
                    ) : (
                        <>
                            <span className={cls('mx-auto mb-3 grid h-20 w-20 place-items-center rounded-full bg-slate-50 text-4xl', estadoDoRestauro(restauro.estado).cor, restauro.estado === 'concluido' && 'animate-scale-in')}>
                                <i className={`fas ${estadoDoRestauro(restauro.estado).icone}`} aria-hidden="true" />
                            </span>
                            <p className="text-lg font-bold text-slate-900">{estadoDoRestauro(restauro.estado).rotulo()}</p>
                            {restauro.estado === 'a_correr' && (
                                <>
                                    <p className="mt-1 text-sm text-slate-500">{t('Primeiro guarda-se uma cópia do estado actual; depois repõe-se. Não feche esta janela.')}</p>
                                    <div className="mx-auto mt-4 h-1.5 max-w-xs overflow-hidden rounded-full bg-slate-100">
                                        <div className="h-full w-1/3 animate-pulse rounded-full bg-gradient-to-r from-amber-400 to-orange-500" />
                                    </div>
                                </>
                            )}
                            {restauro.erro && <p className="mx-auto mt-2 max-w-md break-words text-sm text-red-700">{restauro.erro}</p>}
                            {restauro.copia_previa_id && (
                                <p className="mt-3 text-xs text-slate-500">
                                    <i className="fas fa-life-ring mr-1 text-amber-500" aria-hidden="true" />
                                    {t('O estado anterior ficou guardado na cópia n.º :n — pode repô-la se precisar de voltar atrás.', { n: restauro.copia_previa_id })}
                                </p>
                            )}
                        </>
                    )}
                </div>
            ) : (
                <div className="space-y-4">
                    <div className={cls('border border-red-200 bg-red-50 p-4 text-sm text-red-900', RAIO)}>
                        <p className="flex items-center gap-2 font-bold"><i className="fas fa-triangle-exclamation" aria-hidden="true" />{t('Isto substitui os dados actuais.')}</p>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            {ambito === 'plataforma' ? (
                                <>
                                    <li>{t('A base de dados inteira — todas as empresas — volta ao momento desta cópia.')}</li>
                                    <li>{t('O que foi feito depois dessa hora perde-se (fica guardado na cópia de segurança automática que se faz antes).')}</li>
                                </>
                            ) : (
                                <>
                                    <li>{t('Os dados desta empresa voltam ao momento da cópia. As contas das pessoas, a subscrição e as outras empresas não mudam.')}</li>
                                    <li>{t('Se depois da cópia se emitiram documentos fiscais, a reposição é recusada — esses documentos já foram comunicados.')}</li>
                                </>
                            )}
                            <li>{t('Antes de repor, faz-se sempre uma cópia do estado actual.')}</li>
                        </ul>
                    </div>

                    {origem.tipo === 'copia' && (
                        <dl className="grid grid-cols-2 gap-2 text-sm">
                            <div><dt className="text-xs text-slate-500">{t('Feita em')}</dt><dd className="font-semibold text-slate-800">{dataHora(origem.copia.iniciada_em)}</dd></div>
                            <div><dt className="text-xs text-slate-500">{t('Tamanho')}</dt><dd className="font-semibold text-slate-800">{tamanho(origem.copia.tamanho)}</dd></div>
                        </dl>
                    )}
                    {origem.tipo === 'remoto' && (
                        <p className="text-sm text-slate-600"><i className={cls(origem.destino.icone, 'mr-1.5')} aria-hidden="true" />{t('Vai ser descarregada de «:nome».', { nome: origem.destino.nome })}</p>
                    )}

                    {origem.tipo === 'carregado' && (
                        <Campo etiqueta={t('Ficheiro da cópia')} obrigatorio erro={erros.copia} ajuda={ambito === 'plataforma' ? t('soserp-bd-….sql.gz ou .sql.gz.soscopia') : t('soserp-empresa-….jsonl.gz ou .jsonl.gz.soscopia')}>
                            <input type="file" className={cls(entrada, 'h-auto py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-1 file:text-amber-800')}
                                accept=".gz,.soscopia" onChange={(e) => porFicheiro(e.target.files?.[0] ?? null)} />
                        </Campo>
                    )}

                    {cifrada && (
                        <Campo etiqueta={t('Frase-passe da cópia')} erro={erros.frase} ajuda={t('Deixe em branco para usar a frase-passe gravada na agenda.')}>
                            <input type="password" className={entrada} autoComplete="off" value={frase} onChange={(e) => porFrase(e.target.value)} />
                        </Campo>
                    )}
                    <Campo etiqueta={t('A sua senha')} obrigatorio erro={erros.senha}>
                        <input type="password" className={entrada} autoComplete="current-password" value={senha} onChange={(e) => porSenha(e.target.value)} />
                    </Campo>
                    <Campo etiqueta={t('Escreva RESTAURAR para confirmar')} obrigatorio erro={erros.confirmacao}>
                        <input className={cls(entrada, 'font-mono tracking-widest', confirmacao === 'RESTAURAR' && 'border-emerald-400 ring-1 ring-emerald-300')}
                            value={confirmacao} autoComplete="off" onChange={(e) => porConfirmacao(e.target.value)} placeholder="RESTAURAR" />
                    </Campo>
                    <AvisoDeErro erro={lancar.error} />
                </div>
            )}
        </Modal>
    );
}
