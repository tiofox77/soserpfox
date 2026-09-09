import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { kiandastay, type DiagnosticoDoSite, type PaginaDoKiandaStay } from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada } from '@/ui/SemNada';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * A LIGAÇÃO AO KIANDASTAY — as reservas do site a entrarem sozinhas.
 *
 * Três passos, por esta ordem, porque cada um precisa do anterior: dizer onde
 * fica o site e com que chave; escolher qual dos hotéis do site é esta casa; e
 * LIGAR, que é quando o sistema se regista no site para receber as reservas.
 *
 * O MAPA DOS TIPOS DE QUARTO fica para o fim de propósito: sem ele as reservas
 * entram na mesma, no primeiro tipo da casa. Uma ligação que só funcionasse
 * depois de tudo mapeado ficaria por fazer.
 *
 * A CHAVE DA API NUNCA VOLTA AO BROWSER. O ecrã mostra que ela existe, e não
 * qual é — e o campo fica em branco, porque em branco quer dizer «deixa a que
 * lá está».
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    confirmed: 'primaria',
    checked_in: 'bom',
    checked_out: 'neutra',
    cancelled: 'perigo',
    no_show: 'aviso',
};

export default function KiandaStay() {
    const cache = useQueryClient();

    const [url, porUrl] = useState('');
    const [chave, porChave] = useState('');
    const [hotel, porHotel] = useState('');
    const [activa, porActiva] = useState(false);
    const [criarHospede, porCriarHospede] = useState(true);
    const [estadoInicial, porEstadoInicial] = useState('pending');
    const [mapa, porMapa] = useState<Record<string, number | string>>({});
    const [diagnostico, porDiagnostico] = useState<DiagnosticoDoSite | null>(null);
    const [recado, porRecado] = useState('');

    const pagina = useQuery({ queryKey: ['hotel', 'kiandastay'], queryFn: kiandastay.mostrar });

    // O formulário nasce do que o servidor deu. A chave fica sempre em branco.
    useEffect(() => {
        const l = pagina.data?.ligacao;

        if (! l) return;

        porUrl(l.base_url);
        porHotel(l.property_id ? String(l.property_id) : '');
        porActiva(l.activa);
        porCriarHospede(l.criar_hospede);
        porEstadoInicial(l.estado_inicial);
        porMapa(l.mapa_tipos ?? {});
    }, [pagina.data?.ligacao]);

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['hotel', 'kiandastay'] });

    const credenciais = useMutation({
        mutationFn: () => kiandastay.credenciais(url, chave),
        onSuccess: (r) => { invalidar(); porChave(''); porRecado(r.message); },
    });

    const autorizar = useMutation({
        mutationFn: () => kiandastay.autorizar(url),
        // O site do KiandaStay é que pede a autorização: sai-se daqui e volta-se.
        onSuccess: (r) => { window.location.href = r.url; },
    });

    const testar = useMutation({
        mutationFn: () => kiandastay.testar(),
        onSuccess: (r) => { porDiagnostico(r.diagnostico); porRecado(r.message); },
    });

    const escolherHotel = useMutation({
        mutationFn: (id: string) => kiandastay.escolherHotel(id),
        onSuccess: () => invalidar(),
    });

    const ligar = useMutation({
        mutationFn: () => kiandastay.ligar(),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const opcoes = useMutation({
        mutationFn: () => kiandastay.opcoes({
            activa, criar_hospede: criarHospede, estado_inicial: estadoInicial, mapa_tipos: mapa,
        }),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    if (pagina.isPending) return <Carregando linhas={10} />;
    if (pagina.isError) return <Falhou erro={pagina.error} />;

    const p = pagina.data;
    const l = p.ligacao;
    const pode = p.permissoes.pode_editar;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('KiandaStay')}
                subtitulo={t('As reservas do site a entrarem sozinhas')}
                icone="fa-plug"
                cor="ciano"
            >
                <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <Passo n={1} feito={Boolean(l.base_url) && l.tem_chave} rotulo={t('Credenciais')} />
                    <Passo n={2} feito={Boolean(l.property_id)} rotulo={t('Hotel do site')} />
                    <Passo n={3} feito={l.activa} rotulo={t('Ligado')} />
                </div>
            </Faixa>

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} className={cls('p-1', FOCO, RAIO)} aria-label={t('Fechar')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            )}

            {! pode && (
                <div role="status" className={cls('border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700', RAIO)}>
                    <i className="fas fa-eye mr-2 text-slate-400" aria-hidden="true" />
                    {t('Só está a ver: alterar a ligação é outra permissão.')}
                </div>
            )}

            <AvisoDeErro erro={credenciais.error ?? autorizar.error ?? testar.error ?? ligar.error ?? opcoes.error ?? escolherHotel.error} />

            {/* ── Passo 1 ──────────────────────────────────────────────── */}
            <section className={cls(CARTAO, 'p-5')}>
                <h2 className="mb-1 flex items-center gap-2 text-sm font-bold text-slate-800">
                    <span className="grid h-6 w-6 place-items-center rounded-full bg-cyan-100 text-xs font-bold text-cyan-700">1</span>
                    {t('Onde fica o site')}
                </h2>
                <p className="mb-4 text-xs text-slate-500">
                    {t('O endereço do KiandaStay e a chave que ele dá a esta casa.')}
                </p>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Campo etiqueta={t('Endereço do site')} obrigatorio>
                        {/* SEM `name` E COM A AUTOFILL DESLIGADA.
                            Aconteceu num cliente: o gestor de palavras-passe viu
                            «endereço» ao lado de uma «palavra-passe» e encheu os
                            dois com o email e a senha guardados. */}
                        <input value={url} className={entrada} disabled={! pode}
                            autoComplete="off"
                            placeholder="https://kiandastay.com"
                            onChange={(e) => porUrl(e.target.value)} />
                    </Campo>
                    <Campo etiqueta={t('Chave da API')}
                        ajuda={l.tem_chave
                            ? t('Já há uma chave guardada. Em branco, fica a que lá está.')
                            : t('Ainda não há chave guardada.')}>
                        <input type="password" value={chave} className={entrada} disabled={! pode}
                            autoComplete="new-password"
                            placeholder={l.tem_chave ? '••••••••••••' : ''}
                            onChange={(e) => porChave(e.target.value)} />
                    </Campo>
                </div>

                {pode && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={credenciais.isPending}
                            onClick={() => credenciais.mutate()}>
                            {t('Guardar credenciais')}
                        </Botao>
                        <Botao icone="fa-right-to-bracket" aTrabalhar={autorizar.isPending}
                            onClick={() => autorizar.mutate()}>
                            {t('Entrar com o KiandaStay')}
                        </Botao>
                        {l.configurada && (
                            <Botao icone="fa-stethoscope" aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>
                                {t('Testar')}
                            </Botao>
                        )}
                    </div>
                )}

                {diagnostico && (
                    <p className={cls('mt-3 border p-3 text-sm', RAIO,
                        diagnostico.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                        <i className={cls('fas mr-2', diagnostico.ok ? 'fa-circle-check' : 'fa-circle-xmark')} aria-hidden="true" />
                        {diagnostico.ok ? t('O site respondeu.') : t('O site não respondeu: :erro', { erro: String(diagnostico.erro ?? '?') })}
                    </p>
                )}
            </section>

            {/* ── Passo 2 ──────────────────────────────────────────────── */}
            <section className={cls(CARTAO, 'p-5', ! l.configurada && 'opacity-60')}>
                <h2 className="mb-1 flex items-center gap-2 text-sm font-bold text-slate-800">
                    <span className="grid h-6 w-6 place-items-center rounded-full bg-cyan-100 text-xs font-bold text-cyan-700">2</span>
                    {t('Qual dos hotéis do site é esta casa')}
                </h2>
                <p className="mb-4 text-xs text-slate-500">
                    {t('O mesmo site pode ter vários hotéis; as reservas que entram são as deste.')}
                </p>

                {! l.configurada ? (
                    <p className="text-sm text-slate-400">{t('Guarde primeiro o endereço e a chave.')}</p>
                ) : (
                    <Campo etiqueta={t('Hotel no site')}>
                        <select value={hotel} className={entrada} disabled={! pode || escolherHotel.isPending}
                            onChange={(e) => { porHotel(e.target.value); escolherHotel.mutate(e.target.value); }}>
                            <option value="">{t('Escolha…')}</option>
                            {p.do_site.hoteis.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
                        </select>
                    </Campo>
                )}
            </section>

            {/* ── Passo 3 ──────────────────────────────────────────────── */}
            <section className={cls(CARTAO, 'p-5', ! l.property_id && 'opacity-60')}>
                <h2 className="mb-1 flex items-center gap-2 text-sm font-bold text-slate-800">
                    <span className="grid h-6 w-6 place-items-center rounded-full bg-cyan-100 text-xs font-bold text-cyan-700">3</span>
                    {t('Ligar')}
                </h2>
                <p className="mb-4 text-xs text-slate-500">
                    {t('Regista este sistema no site — é isto que faz as reservas entrarem sozinhas.')}
                </p>

                {l.activa ? (
                    <p className={cls('border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900', RAIO)}>
                        <i className="fas fa-circle-check mr-2" aria-hidden="true" />
                        {t('Ligado. As reservas do site entram sozinhas nesta casa.')}
                    </p>
                ) : (
                    pode && (
                        <Botao cor="bom" tom="solida" icone="fa-plug" aTrabalhar={ligar.isPending}
                            disabled={! l.property_id} onClick={() => ligar.mutate()}>
                            {t('Ligar agora')}
                        </Botao>
                    )
                )}

                {l.webhook_url && (
                    <p className="mt-3 break-all text-xs text-slate-400">
                        <i className="fas fa-satellite-dish mr-1.5" aria-hidden="true" />
                        {t('O site avisa esta morada:')} <span className="font-mono">{l.webhook_url}</span>
                    </p>
                )}
            </section>

            {/* ── As opções e o mapa ───────────────────────────────────── */}
            <section className={cls(CARTAO, 'p-5')}>
                <h2 className="mb-1 text-sm font-bold text-slate-800">
                    <i className="fas fa-sliders mr-2 text-slate-400" aria-hidden="true" />
                    {t('Como entram as reservas')}
                </h2>
                <p className="mb-4 text-xs text-slate-500">
                    {t('O mapa dos tipos é opcional: sem ele a reserva entra no primeiro tipo da casa.')}
                </p>

                <fieldset disabled={! pode} className="space-y-3">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className={cls('flex cursor-pointer items-start gap-3 border p-3 transition-colors', RAIO,
                            activa ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50')}>
                            <input type="checkbox" checked={activa}
                                className={cls('mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600', FOCO)}
                                onChange={(e) => porActiva(e.target.checked)} />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800">{t('Ligação activa')}</span>
                                <span className="block text-xs text-slate-500">{t('Desligada, o site continua a avisar mas nada entra.')}</span>
                            </span>
                        </label>

                        <label className={cls('flex cursor-pointer items-start gap-3 border p-3 transition-colors', RAIO,
                            criarHospede ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50')}>
                            <input type="checkbox" checked={criarHospede}
                                className={cls('mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600', FOCO)}
                                onChange={(e) => porCriarHospede(e.target.checked)} />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800">{t('Criar a ficha do hóspede')}</span>
                                <span className="block text-xs text-slate-500">{t('Sem ela não há a quem facturar no check-out.')}</span>
                            </span>
                        </label>

                        <Campo etiqueta={t('Estado com que a reserva entra')}
                            ajuda={t('«Confirmada» só se o site já cobrou; senão fica pendente para a recepção decidir.')}>
                            <select value={estadoInicial} className={entrada}
                                onChange={(e) => porEstadoInicial(e.target.value)}>
                                <option value="pending">{t('Pendente')}</option>
                                <option value="confirmed">{t('Confirmada')}</option>
                            </select>
                        </Campo>
                    </div>

                    {p.do_site.tipos.length > 0 && (
                        <div>
                            <h3 className="mb-2 text-sm font-bold text-slate-800">{t('Que tipo do site é que tipo desta casa')}</h3>
                            <div className="space-y-2">
                                {p.do_site.tipos.map((tp) => (
                                    <div key={tp.id} className="flex items-center gap-3">
                                        <span className="w-48 flex-none truncate text-sm text-slate-700">{tp.name}</span>
                                        <i className="fas fa-arrow-right flex-none text-slate-300" aria-hidden="true" />
                                        <select
                                            value={String(mapa[String(tp.id)] ?? '')}
                                            aria-label={t('Tipo desta casa para :nome', { nome: tp.name })}
                                            className={cls(entrada, 'flex-1')}
                                            onChange={(e) => porMapa((m) => ({ ...m, [String(tp.id)]: e.target.value }))}
                                        >
                                            <option value="">{t('— o primeiro da casa —')}</option>
                                            {p.tipos_locais.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                        </select>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {pode && (
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={opcoes.isPending}
                            onClick={() => opcoes.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    )}
                </fieldset>
            </section>

            {/* ── As últimas que entraram ──────────────────────────────── */}
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <h2 className="border-b border-slate-100 px-5 py-3 text-sm font-bold text-slate-800">
                    <i className="fas fa-inbox mr-2 text-slate-400" aria-hidden="true" />
                    {t('As últimas que entraram pelo site')}
                </h2>

                {p.ultimas.length === 0 ? (
                    <SemNada
                        icone="fa-inbox"
                        frase={l.activa
                            ? t('Ainda não entrou nenhuma reserva pelo site.')
                            : t('A ligação não está activa: nada entra por aqui.')}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-4 py-3 text-left">{t('Reserva')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Hóspede')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Período')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Total')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Estado')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {p.ultimas.map((r, i) => (
                                    <tr key={r.id} className="entra transition-colors duration-150 hover:bg-cyan-50/40"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="px-4 py-2.5">
                                            <span className="block font-mono text-xs font-bold text-cyan-700">{r.numero}</span>
                                            {r.externa && <span className="block text-xs text-slate-400">{t('no site: :n', { n: r.externa })}</span>}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-700">{r.hospede}</td>
                                        <td className="px-4 py-2.5 tabular-nums text-slate-600">
                                            {r.entrada ? data(r.entrada) : '—'} → {r.saida ? data(r.saida) : '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-bold tabular-nums text-slate-800">{kz(r.total)} Kz</td>
                                        <td className="px-4 py-2.5">
                                            <Etiqueta cor={COR_DO_ESTADO[r.estado] ?? 'neutra'}>{r.estado_rotulo}</Etiqueta>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

function Passo({ n, feito, rotulo }: { n: number; feito: boolean; rotulo: string }) {
    return (
        <span className={cls(
            'inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold backdrop-blur-sm',
            RAIO,
            feito ? 'bg-white/30 text-white' : 'bg-white/10 text-white/70',
        )}>
            <span className={cls('grid h-5 w-5 place-items-center rounded-full text-[10px]',
                feito ? 'bg-white text-cyan-700' : 'bg-white/20 text-white')}>
                {feito ? <i className="fas fa-check" aria-hidden="true" /> : n}
            </span>
            {rotulo}
        </span>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a ligação ao KiandaStay')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
