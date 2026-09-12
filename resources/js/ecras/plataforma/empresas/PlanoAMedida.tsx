import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { RAIO, TRANSICAO, cls, kz } from '@/ui/tokens';

/**
 * UM PLANO SÓ PARA ESTA EMPRESA.
 *
 * Escolhem-se os módulos, o preço e os dias de teste de cada um, os limites e o
 * ciclo; o plano nasce activo e FORA DA MONTRA, e sai logo o pedido e a
 * factura, para o valor entrar na facturação e na receita.
 *
 * Arranca com o que a empresa JÁ TEM: o caso comum é acrescentar, não recomeçar
 * do zero.
 */
export function PlanoAMedida({ id, aoFechar, aoGuardar }: { id: number; aoFechar: () => void; aoGuardar: (recado: string) => void }) {
    const dados = useQuery({
        queryKey: ['plataforma', 'empresas', 'medida', id],
        queryFn: () => plataforma.empresas.medida(id),
    });

    const [nome, porNome] = useState('');
    const [escolhidos, porEscolhidos] = useState<string[]>([]);
    const [precos, porPrecos] = useState<Record<string, string>>({});
    const [testes, porTestes] = useState<Record<string, string>>({});
    const [anual, porAnual] = useState('');
    const [limites, porLimites] = useState({ utilizadores: 5, empresas: 1, armazenamento: 2000 });
    const [ciclo, porCiclo] = useState('monthly');
    const [jaPago, porJaPago] = useState(false);

    useEffect(() => {
        const d = dados.data;

        if (!d) return;

        porNome(d.sugestao.nome);
        porEscolhidos(d.modulos.filter((m) => m.ja_tem).map((m) => m.slug));
        porPrecos(Object.fromEntries(d.modulos.map((m) => [m.slug, String(m.preco)])));
        porTestes(Object.fromEntries(d.modulos.map((m) => [m.slug, '0'])));
        porLimites({ utilizadores: d.sugestao.utilizadores, empresas: d.sugestao.empresas, armazenamento: d.sugestao.armazenamento });
        porCiclo(d.sugestao.ciclo);
    }, [dados.data]);

    // A MENSALIDADE É A SOMA DO QUE CADA MÓDULO ESCOLHIDO CUSTA — a mesma conta
    // que o servidor faz ao gravar (`PlanoAMedida::somar`), que é a que manda.
    const mensal = Math.round(escolhidos.reduce((s, slug) => s + (Number(precos[slug]) || 0), 0) * 100) / 100;

    const guardar = useMutation({
        mutationFn: () => plataforma.empresas.guardarMedida(id, {
            nome,
            modulos: escolhidos,
            precos: Object.fromEntries(escolhidos.map((s) => [s, Number(precos[s]) || 0])),
            testes: Object.fromEntries(escolhidos.map((s) => [s, Number(testes[s]) || 0])),
            preco_anual: anual === '' ? null : Number(anual),
            utilizadores: limites.utilizadores,
            empresas: limites.empresas,
            armazenamento: limites.armazenamento,
            ciclo,
            ja_pago: jaPago,
        }),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const d = dados.data;

    const alternar = (slug: string) => porEscolhidos((a) => (a.includes(slug) ? a.filter((s) => s !== slug) : [...a, slug]));

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Plano à medida')}
            subtitulo={d?.empresa.nome}
            icone="fa-sliders"
            cor="laranja"
            largura="xl"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="aviso" tom="solida" icone="fa-wand-magic-sparkles" disabled={mensal <= 0} aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                        {t('Criar e atribuir')}
                    </Botao>
                </div>
            }
        >
            {dados.isPending || !d ? (
                <Carregando linhas={10} />
            ) : (
                <div className="space-y-5">
                    <AvisoDeErro erro={guardar.error} />

                    <Campo etiqueta={t('Nome do plano')} obrigatorio erro={erros.nome}>
                        <input className={entrada} value={nome} onChange={(e) => porNome(e.target.value)} />
                    </Campo>

                    <div>
                        <div className="mb-2 flex flex-wrap items-end justify-between gap-2">
                            <p className="text-sm font-bold text-slate-800">
                                <i className="fas fa-puzzle-piece mr-1.5 text-orange-500" aria-hidden="true" />{t('Módulos, preço e teste')}
                            </p>
                            <p className="text-xs text-slate-500">{t('As dependências entram sozinhas (quem leva Facturação leva Tesouraria).')}</p>
                        </div>
                        {erros.modulos && <p className="mb-2 text-xs text-red-600">{erros.modulos[0]}</p>}

                        <div className="overflow-hidden rounded-xl border border-slate-200">
                            <div className="grid grid-cols-12 gap-2 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <span className="col-span-6">{t('Módulo')}</span>
                                <span className="col-span-3 text-right">{t('Preço mensal (Kz)')}</span>
                                <span className="col-span-3 text-right">{t('Dias de teste')}</span>
                            </div>
                            <div className="max-h-72 divide-y divide-slate-100 overflow-y-auto">
                                {d.modulos.map((m) => {
                                    const escolhido = escolhidos.includes(m.slug);

                                    return (
                                        <div key={m.slug} className={cls('grid grid-cols-12 items-center gap-2 px-3 py-2', TRANSICAO, escolhido && 'bg-orange-50/60')}>
                                            <label className="col-span-6 flex min-w-0 cursor-pointer items-center gap-2">
                                                <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-orange-600" checked={escolhido} onChange={() => alternar(m.slug)} />
                                                <i className={cls('fas text-slate-400', m.icone)} aria-hidden="true" />
                                                <span className="truncate text-sm text-slate-800">{m.nome}</span>
                                                {m.ja_tem && <Etiqueta cor="bom">{t('já tem')}</Etiqueta>}
                                            </label>
                                            <input
                                                type="number" min="0" step="100"
                                                disabled={!escolhido}
                                                aria-label={t('Preço de :m', { m: m.nome })}
                                                className={cls(entrada, 'col-span-3 h-9 text-right disabled:bg-slate-50 disabled:text-slate-300')}
                                                value={precos[m.slug] ?? ''}
                                                onChange={(e) => porPrecos((a) => ({ ...a, [m.slug]: e.target.value }))}
                                            />
                                            <input
                                                type="number" min="0" max="365"
                                                disabled={!escolhido}
                                                aria-label={t('Dias de teste de :m', { m: m.nome })}
                                                className={cls(entrada, 'col-span-3 h-9 text-right disabled:bg-slate-50 disabled:text-slate-300')}
                                                value={testes[m.slug] ?? '0'}
                                                onChange={(e) => porTestes((a) => ({ ...a, [m.slug]: e.target.value }))}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-slate-500">
                            <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                            {t('Dias de teste por módulo: passado o prazo, só esse módulo deixa de estar disponível — o resto do plano continua. A zero, o módulo vale enquanto o plano valer.')}
                        </p>
                    </div>

                    <div className={cls('grid gap-4 border-2 p-4 md:grid-cols-2', RAIO, mensal > 0 ? 'border-orange-200 bg-orange-50/60' : 'border-red-200 bg-red-50')}>
                        <div>
                            <p className="text-sm font-bold text-slate-700">{t('Mensalidade')}</p>
                            <p className="text-xs text-slate-500">{t('soma de :n módulo(s) escolhido(s)', { n: escolhidos.length })}</p>
                            <p className="mt-1 text-3xl font-extrabold tabular-nums text-slate-900">{kz(mensal)} <span className="text-base">Kz</span></p>
                        </div>
                        <Campo etiqueta={t('Anual (Kz)')} ajuda={t('Em branco: 12 × a mensalidade.')}>
                            <input type="number" min="0" step="0.01" className={entrada} value={anual} placeholder={kz(mensal * 12)} onChange={(e) => porAnual(e.target.value)} />
                        </Campo>
                        {(mensal <= 0 || erros.precos) && (
                            <p className="text-xs font-semibold text-red-700 md:col-span-2">
                                <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                                {erros.precos?.[0] ?? t('A soma tem de ser maior que zero. Um plano a zero é tratado em todo o sistema como o plano gratuito e gasta a cortesia única do cliente — para oferecer, ponha um valor simbólico e faça o desconto na cobrança.')}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-4">
                        <Campo etiqueta={t('Utilizadores')} obrigatorio erro={erros.utilizadores}>
                            <input type="number" min="1" className={entrada} value={limites.utilizadores} onChange={(e) => porLimites({ ...limites, utilizadores: Number(e.target.value) })} />
                        </Campo>
                        <Campo etiqueta={t('Empresas')} obrigatorio erro={erros.empresas}>
                            <input type="number" min="1" className={entrada} value={limites.empresas} onChange={(e) => porLimites({ ...limites, empresas: Number(e.target.value) })} />
                        </Campo>
                        <Campo etiqueta={t('Armazenamento (MB)')} obrigatorio erro={erros.armazenamento}>
                            <input type="number" min="100" step="100" className={entrada} value={limites.armazenamento} onChange={(e) => porLimites({ ...limites, armazenamento: Number(e.target.value) })} />
                        </Campo>
                        <Campo etiqueta={t('Ciclo a aplicar')} obrigatorio erro={erros.ciclo}>
                            <select className={entrada} value={ciclo} onChange={(e) => porCiclo(e.target.value)}>
                                <option value="monthly">{t('Mensal')}</option>
                                <option value="quarterly">{t('Trimestral')}</option>
                                <option value="semiannual">{t('Semestral')}</option>
                                <option value="yearly">{t('Anual (12 + 2)')}</option>
                            </select>
                        </Campo>
                    </div>

                    {/* A FACTURA SAI SEMPRE; o que muda é se nasce paga ou a aguardar. */}
                    <label className={cls('flex cursor-pointer items-start gap-3 border-2 p-3', RAIO, TRANSICAO, jaPago ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200')}>
                        <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600" checked={jaPago} onChange={(e) => porJaPago(e.target.checked)} />
                        <span>
                            <span className="block font-bold text-slate-800">{t('O cliente já pagou')}</span>
                            <span className="block text-xs text-slate-600">
                                {jaPago
                                    ? t('A factura sai paga, com data de hoje.')
                                    : t('A factura sai pendente, com vencimento a 8 dias — fica a constar o que há a receber.')}
                            </span>
                        </span>
                    </label>

                    <p className="text-xs text-slate-500">
                        <i className="fas fa-eye-slash mr-1" aria-hidden="true" />
                        {t('O plano fica activo para esta empresa mas fora da montra: não aparece na página de preços, no registo, nem aos outros clientes. É sempre emitido o pedido e a factura, para o valor entrar na facturação e na receita.')}
                    </p>
                </div>
            )}
        </Modal>
    );
}
