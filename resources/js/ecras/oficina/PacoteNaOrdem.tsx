import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { pacotesDeServico } from '@/api/oficina';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, kz } from '@/ui/tokens';

/**
 * OS PACOTES NA ORDEM (15/09/2026, OF-07) — juntar um pacote de uma vez, ou
 * guardar as linhas desta ordem como um pacote novo.
 */
export function JuntarPacote({ id, aoFechar, aoGravar }: { id: number; aoFechar: () => void; aoGravar: (m: string) => void }) {
    const q = useQuery({ queryKey: ['oficina', 'pacotes'], queryFn: pacotesDeServico.lista });
    const [escolhido, porEscolhido] = useState<number | null>(null);
    const [aprovacao, porAprovacao] = useState(false);
    const juntar = useMutation({ mutationFn: () => pacotesDeServico.juntarAOrdem(id, escolhido as number, aprovacao), onSuccess: (r) => aoGravar(r.message) });

    const activos = q.data?.data.filter((p) => p.activo) ?? [];
    const p = activos.find((x) => x.id === escolhido);

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Juntar pacote')} icone="fa-box-open" cor="roxo" largura="lg"
            rodape={
                <>
                    <a href="/workshop/packages" className={cls('mr-auto inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-700 hover:underline', FOCO, RAIO)}><i className="fas fa-box-open" aria-hidden="true" />{t('Gerir pacotes')}</a>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-plus" aTrabalhar={juntar.isPending} disabled={!p} onClick={() => juntar.mutate()}>
                        {p ? tn('Juntar :n linha|Juntar :n linhas', p.linhas.length, { n: p.linhas.length }) : t('Juntar')}
                    </Botao>
                </>
            }>
            {q.isPending ? <Carregando linhas={4} /> : activos.length === 0 ? (
                <SemNada icone="fa-box-open" frase={t('Ainda não há pacotes activos. Crie-os no menu Pacotes de Serviço.')} />
            ) : (
                <div className="space-y-3">
                    <AvisoDeErro erro={juntar.error} />
                    <div role="radiogroup" aria-label={t('Pacotes')} className="grid gap-2 sm:grid-cols-2">
                        {activos.map((x) => (
                            <button key={x.id} type="button" role="radio" aria-checked={escolhido === x.id} onClick={() => porEscolhido(x.id)}
                                className={cls('flex items-start gap-3 border p-3 text-left', RAIO_GRANDE, TRANSICAO, FOCO,
                                    escolhido === x.id ? 'border-violet-400 bg-violet-50 ring-2 ring-violet-200' : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:shadow-md')}>
                                <span className={cls('grid h-9 w-9 flex-none place-items-center rounded-lg text-white', escolhido === x.id ? 'bg-violet-600' : 'bg-slate-300')}>
                                    <i className={cls('fas', escolhido === x.id ? 'fa-check' : 'fa-box-open')} aria-hidden="true" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-semibold text-slate-900">{x.nome}</span>
                                    <span className="block text-xs text-slate-500">{tn(':n linha|:n linhas', x.linhas.length, { n: x.linhas.length })}{x.horas > 0 && ` · ${x.horas} h`}</span>
                                </span>
                                <span className="font-bold tabular-nums text-slate-900">{kz(x.total)}</span>
                            </button>
                        ))}
                    </div>
                    {p && (
                        <div className={cls('animate-fade-in border border-slate-200 bg-slate-50 p-3', RAIO)}>
                            <ul className="space-y-1 text-sm">
                                {p.linhas.map((l, n) => (
                                    <li key={n} className="flex items-center gap-2">
                                        <i className={cls('fas w-4 text-center', l.tipo === 'service' ? 'fa-screwdriver-wrench text-indigo-400' : 'fa-gear text-emerald-500')} aria-hidden="true" />
                                        <span className="min-w-0 flex-1 truncate">{l.quantidade}× {l.nome}</span>
                                        <span className="tabular-nums text-slate-500">{kz(l.quantidade * l.preco * (1 - l.desconto / 100))}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <label className={cls('flex cursor-pointer items-start gap-3 border px-3 py-2.5 text-sm', RAIO, TRANSICAO, aprovacao ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 text-slate-700')}>
                        <input type="checkbox" checked={aprovacao} onChange={(e) => porAprovacao(e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-600" />
                        <span><span className="block font-semibold"><i className="fas fa-hand mr-1.5" aria-hidden="true" />{t('Precisa da aprovação do cliente')}</span>
                            <span className="block text-xs opacity-80">{t('As linhas do pacote ficam à espera até o cliente aprovar.')}</span></span>
                    </label>
                </div>
            )}
        </Modal>
    );
}

export function GuardarComoPacote({ id, numero, aoFechar, aoGravar }: { id: number; numero: string; aoFechar: () => void; aoGravar: (m: string) => void }) {
    const [nome, porNome] = useState('');
    const guardar = useMutation({ mutationFn: () => pacotesDeServico.guardarDaOrdem(id, nome), onSuccess: (r) => aoGravar(r.message) });
    const erro = guardar.error instanceof ErroDaApi ? guardar.error.erros.nome?.[0] : null;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Guardar como pacote')} subtitulo={numero} icone="fa-box-archive" cor="roxo" largura="sm"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} disabled={nome.trim() === ''} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao></>}>
            <div className="space-y-3">
                <p className="text-sm text-slate-600">{t('As linhas aprovadas desta ordem passam a um pacote, pronto a usar noutras ordens.')}</p>
                <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Nome do pacote')}</span>
                    <input value={nome} onChange={(e) => porNome(e.target.value)} placeholder={t('Ex.: Revisão 10 000 km')} autoFocus className={cls('w-full border border-slate-300 px-3 py-2 text-sm', RAIO, FOCO)} />
                </label>
                {erro && <p className="text-sm text-red-600">{erro}</p>}
                {!erro && <AvisoDeErro erro={guardar.error} />}
            </div>
        </Modal>
    );
}
