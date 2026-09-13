import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { definicoes } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Recado } from './comum';

/**
 * AS CHAVES RSA DO SAF-T.
 *
 * Regenerar pede que se escreva REGENERAR e guarda sempre uma cópia das
 * actuais; se a cópia falhar, nada muda. A chave pública já não viaja inteira
 * para a página — vai a impressão digital.
 */
export default function ChavesSaft() {
    const fila = useQueryClient();
    const [recado, porRecado] = useRecadoNoCanto(null);
    const [aRegenerar, porARegenerar] = useState(false);
    const [confirmacao, porConfirmacao] = useState('');

    const dados = useQuery({ queryKey: ['plataforma', 'chaves-saft'], queryFn: definicoes.chavesSaft.ler });

    const feito = (m: string) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'chaves-saft'] }); };

    const gerar = useMutation({ mutationFn: definicoes.chavesSaft.gerar, onSuccess: (r) => feito(r.message) });
    const regenerar = useMutation({
        mutationFn: () => definicoes.chavesSaft.regenerar(confirmacao),
        onSuccess: (r) => { porARegenerar(false); porConfirmacao(''); feito(r.message); },
    });

    if (dados.isPending) return <Carregando linhas={6} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir as chaves do SAF-T')} erro={dados.error} />;

    const d = dados.data;
    const temAmbas = Boolean(d.publica && d.privada);
    const erros = regenerar.error instanceof ErroDaApi ? regenerar.error.erros : {};

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Chaves do SAF-T')} subtitulo={t('A assinatura digital dos ficheiros SAF-T, conforme a AGT')} icone="fa-key" cor="roxo">
                <EstadoNaFaixa icone={temAmbas ? 'fa-shield-halved' : 'fa-triangle-exclamation'}>
                    {temAmbas ? t('Par de chaves instalado') : t('Chaves por gerar')}
                </EstadoNaFaixa>
                {!d.openssl && <EstadoNaFaixa icone="fa-circle-xmark">{t('OpenSSL desligado neste servidor')}</EstadoNaFaixa>}
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={gerar.error} />

            <div className="grid gap-4 md:grid-cols-2">
                <section className={cls(CARTAO, 'card-hover overflow-hidden border-2', d.publica ? 'border-emerald-200' : 'border-slate-200')}>
                    <header className={cls('flex items-center gap-2 px-5 py-4 font-bold text-white', d.publica ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-slate-500')}>
                        <i className="fas fa-lock-open icon-float" aria-hidden="true" />{t('Chave pública')}
                    </header>
                    <div className="space-y-3 p-5">
                        {d.publica ? (
                            <>
                                <p className="font-semibold text-emerald-700"><i className="fas fa-circle-check mr-2" aria-hidden="true" />{t('Gerada')}</p>
                                <dl className="space-y-1 text-sm text-slate-600">
                                    <div><dt className="inline font-semibold">{t('Data')}:</dt> <dd className="inline">{d.publica.data}</dd></div>
                                    <div><dt className="inline font-semibold">{t('Algoritmo')}:</dt> <dd className="inline">{d.metadados.algoritmo} · {d.metadados.digest}</dd></div>
                                </dl>
                                <p className="break-all rounded-lg bg-slate-50 p-3 font-mono text-xs text-slate-600" title={t('Impressão digital SHA-256')}>{d.publica.impressao}</p>
                                <div className="grid grid-cols-2 gap-2">
                                    <Descarga href="/superadmin/saft-configuration/descarregar/publica/pem" icone="fa-download" cor="bg-emerald-600 hover:bg-emerald-700">PEM</Descarga>
                                    <Descarga href="/superadmin/saft-configuration/descarregar/publica/txt" icone="fa-file-lines" cor="bg-teal-600 hover:bg-teal-700">TXT</Descarga>
                                </div>
                            </>
                        ) : <p className="py-6 text-center text-slate-500">{t('Chave por gerar')}</p>}
                    </div>
                </section>

                <section className={cls(CARTAO, 'card-hover overflow-hidden border-2', d.privada ? 'border-red-200' : 'border-slate-200')}>
                    <header className={cls('flex items-center gap-2 px-5 py-4 font-bold text-white', d.privada ? 'bg-gradient-to-r from-red-600 to-rose-600' : 'bg-slate-500')}>
                        <i className="fas fa-lock icon-float" aria-hidden="true" />{t('Chave privada')}
                    </header>
                    <div className="space-y-3 p-5">
                        {d.privada ? (
                            <>
                                <p className="font-semibold text-red-700"><i className="fas fa-shield-halved mr-2" aria-hidden="true" />{t('Protegida')}</p>
                                <p className="text-sm text-slate-600">{t('Data')}: {d.privada.data}</p>
                                <p className="rounded-lg border border-red-100 bg-red-50 p-3 text-xs text-red-800">
                                    <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                                    {t('Nunca partilhe esta chave. Quem a tiver assina ficheiros SAF-T em nome do software.')}
                                </p>
                                <div className="grid grid-cols-2 gap-2">
                                    <Descarga href="/superadmin/saft-configuration/descarregar/privada/pem" icone="fa-download" cor="bg-red-600 hover:bg-red-700">PEM</Descarga>
                                    <Descarga href="/superadmin/saft-configuration/descarregar/privada/txt" icone="fa-file-lines" cor="bg-rose-600 hover:bg-rose-700">TXT</Descarga>
                                </div>
                            </>
                        ) : <p className="py-6 text-center text-slate-500">{t('Chave por gerar')}</p>}
                    </div>
                </section>
            </div>

            <section className={cls(CARTAO, 'flex flex-wrap items-center justify-between gap-3 p-5')}>
                <div>
                    <h3 className="font-bold text-slate-900">{temAmbas ? t('Regenerar as chaves') : t('Gerar as chaves')}</h3>
                    <p className="text-sm text-slate-600">
                        {temAmbas
                            ? t('As chaves actuais ficam guardadas numa cópia. Os ficheiros já exportados deixam de se verificar com as novas.')
                            : t('Um par RSA de 2048 bits com SHA-256, como a AGT pede.')}
                    </p>
                    {d.copias.length > 0 && (
                        <p className="mt-1 text-xs text-slate-500">{t(':n cópia(s) de segurança guardadas — a última de :dia.', { n: d.copias.length, dia: d.copias[0] ?? '' })}</p>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    {temAmbas && <Descarga href="/superadmin/saft-configuration/descarregar/ambas/txt" icone="fa-file-zipper" cor="bg-slate-700 hover:bg-slate-800">{t('As duas num ficheiro')}</Descarga>}
                    {temAmbas ? (
                        <Botao cor="perigo" tom="solida" icone="fa-rotate" onClick={() => porARegenerar(true)}>{t('Regenerar')}</Botao>
                    ) : (
                        <Botao cor="primaria" tom="solida" icone="fa-key" disabled={!d.openssl} aTrabalhar={gerar.isPending} onClick={() => gerar.mutate()}>{t('Gerar chaves')}</Botao>
                    )}
                </div>
            </section>

            <Modal
                aberto={aRegenerar}
                aoFechar={() => porARegenerar(false)}
                titulo={t('Regenerar as chaves do SAF-T?')}
                icone="fa-triangle-exclamation"
                cor="perigo"
                largura="md"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porARegenerar(false)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-rotate" disabled={confirmacao !== 'REGENERAR'} aTrabalhar={regenerar.isPending} onClick={() => regenerar.mutate()}>{t('Regenerar')}</Botao>
                    </div>
                }
            >
                <div className="space-y-3">
                    <AvisoDeErro erro={regenerar.error} />
                    <p className="text-sm text-slate-700">{t('Os ficheiros SAF-T já exportados deixam de se verificar com a chave nova. As actuais ficam numa cópia de segurança.')}</p>
                    <Campo etiqueta={t('Escreva REGENERAR para confirmar')} obrigatorio erro={erros.confirmacao}>
                        <input className={cls(entrada, 'font-mono')} value={confirmacao} autoComplete="off" onChange={(e) => porConfirmacao(e.target.value)} />
                    </Campo>
                </div>
            </Modal>
        </div>
    );
}

function Descarga({ href, icone, cor, children }: { href: string; icone: string; cor: string; children: React.ReactNode }) {
    return (
        <a href={href} className={cls('inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white', RAIO, TRANSICAO, FOCO, 'hover:-translate-y-0.5', cor)}>
            <i className={cls('fas', icone)} aria-hidden="true" />{children}
        </a>
    );
}
