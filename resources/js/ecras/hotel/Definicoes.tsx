import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    definicoesDoHotel,
    type ComodidadeDoHotel,
    type DefinicoesDoHotel,
    type TipoNasDefinicoes,
} from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * AS DEFINIÇÕES DO HOTEL — a casa como ela se apresenta.
 *
 * O nome, as estrelas, as horas de entrada e de saída, as regras de reserva,
 * as políticas, as comodidades e a página pública. É o ecrã que decide como o
 * hotel aparece a quem o procura de fora.
 *
 * VER NÃO É ALTERAR. O ecrã de sempre pedia `hotel.settings.view` na morada e
 * mais nada: chegar lá era poder mudar o preço do check-in tardio, a política
 * de cancelamento e o endereço público da casa.
 */

const ABAS = [
    { chave: 'geral', rotulo: () => t('Geral'), icone: 'fa-hotel' },
    { chave: 'marca', rotulo: () => t('Marca'), icone: 'fa-palette' },
    { chave: 'horarios', rotulo: () => t('Horários'), icone: 'fa-clock' },
    { chave: 'reservas', rotulo: () => t('Reservas'), icone: 'fa-calendar-check' },
    { chave: 'politicas', rotulo: () => t('Políticas'), icone: 'fa-file-contract' },
    { chave: 'comodidades', rotulo: () => t('Comodidades'), icone: 'fa-star' },
    { chave: 'publica', rotulo: () => t('Página pública'), icone: 'fa-globe' },
    { chave: 'fidelidade', rotulo: () => t('Fidelidade'), icone: 'fa-gem' },
] as const;

type Aba = typeof ABAS[number]['chave'];

export default function Definicoes() {
    const cache = useQueryClient();

    const [aba, porAba] = useState<Aba>('geral');
    const [forma, porForma] = useState<DefinicoesDoHotel | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aTrocarEndereco, porATrocarEndereco] = useState(false);

    const pagina = useQuery({ queryKey: ['hotel', 'definicoes'], queryFn: definicoesDoHotel.mostrar });

    // O formulário nasce do que o servidor deu, e só uma vez.
    useEffect(() => {
        if (pagina.data && ! forma) porForma(pagina.data.definicoes);
    }, [pagina.data, forma]);

    const aplicar = (d: DefinicoesDoHotel, mensagem: string) => {
        void cache.invalidateQueries({ queryKey: ['hotel', 'definicoes'] });
        porForma(d); porRecado(mensagem);
    };

    const guardar = useMutation({
        mutationFn: (d: DefinicoesDoHotel) => definicoesDoHotel.guardar(d),
        onSuccess: (r) => aplicar(r.definicoes, r.message),
    });

    const imagem = useMutation({
        mutationFn: ({ qual, ficheiro }: { qual: 'logo' | 'capa'; ficheiro: File }) =>
            definicoesDoHotel.imagem(qual, ficheiro),
        onSuccess: (r) => aplicar(r.definicoes, r.message),
    });

    const apagarImagem = useMutation({
        mutationFn: (qual: 'logo' | 'capa') => definicoesDoHotel.apagarImagem(qual),
        onSuccess: (r) => aplicar(r.definicoes, r.message),
    });

    const novoEndereco = useMutation({
        mutationFn: () => definicoesDoHotel.novoEndereco(),
        onSuccess: (r) => { aplicar(r.definicoes, r.message); porATrocarEndereco(false); },
    });

    if (pagina.isPending || ! forma) return <Carregando linhas={12} />;
    if (pagina.isError) return <Falhou erro={pagina.error} />;

    const p = pagina.data;
    const pode = p.permissoes.pode_editar;
    const daApi = guardar.error instanceof ErroDaApi ? guardar.error : null;

    const mudar = <K extends keyof DefinicoesDoHotel>(campo: K, valor: DefinicoesDoHotel[K]) =>
        porForma((f) => (f ? { ...f, [campo]: valor } : f));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Definições do Hotel')}
                subtitulo={t('A casa como ela se apresenta')}
                icone="fa-gear"
                cor="neutra"
                accoes={
                    <>
                        {forma.booking_url && (
                            <a href={forma.booking_url} target="_blank" rel="noreferrer" className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-up-right-from-square" aria-hidden="true" />
                                {t('Ver a página pública')}
                            </a>
                        )}
                        {pode && (
                            <button type="button" onClick={() => guardar.mutate(forma)}
                                disabled={guardar.isPending}
                                className={cls(ACCAO_DA_FAIXA, 'disabled:opacity-60')}>
                                <i className={cls('fas', guardar.isPending ? 'fa-spinner fa-spin' : 'fa-check')} aria-hidden="true" />
                                {t('Guardar')}
                            </button>
                        )}
                    </>
                }
            />

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
                    {t('Só está a ver: alterar as definições do hotel é outra permissão.')}
                </div>
            )}

            <AvisoDeErro erro={guardar.error ?? imagem.error ?? apagarImagem.error} />

            <div className={cls(CARTAO, 'p-4')}>
                <div className="flex flex-wrap gap-2" role="tablist" aria-label={t('Definições')}>
                    {ABAS.map((x) => (
                        <button
                            key={x.chave}
                            type="button"
                            role="tab"
                            aria-selected={aba === x.chave}
                            onClick={() => porAba(x.chave)}
                            className={cls(
                                'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                aba === x.chave
                                    ? 'border-slate-700 bg-slate-100 text-slate-800 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            )}
                        >
                            <i className={cls('fas', x.icone)} aria-hidden="true" />
                            {x.rotulo()}
                        </button>
                    ))}
                </div>
            </div>

            <fieldset disabled={! pode} className={cls(CARTAO, 'space-y-4 p-5', ! pode && 'opacity-70')}>
                {aba === 'geral' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Campo etiqueta={t('Nome do hotel')} obrigatorio erro={daApi?.erros.hotel_name} className="sm:col-span-2">
                            <input value={forma.hotel_name} className={entrada}
                                onChange={(e) => mudar('hotel_name', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Descrição')} erro={daApi?.erros.hotel_description} className="sm:col-span-2"
                            ajuda={t('É o que se lê na página pública, por baixo do nome.')}>
                            <textarea rows={3} value={forma.hotel_description} className={cls(entrada, 'h-auto py-2')}
                                onChange={(e) => mudar('hotel_description', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Estrelas')} obrigatorio erro={daApi?.erros.star_rating}>
                            <select value={forma.star_rating} className={entrada}
                                onChange={(e) => mudar('star_rating', Number(e.target.value))}>
                                {[1, 2, 3, 4, 5].map((n) => (
                                    <option key={n} value={n}>{'★'.repeat(n)}</option>
                                ))}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Telefone')} erro={daApi?.erros.hotel_phone}>
                            <input value={forma.hotel_phone} className={entrada}
                                onChange={(e) => mudar('hotel_phone', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('WhatsApp')} erro={daApi?.erros.hotel_whatsapp}>
                            <input value={forma.hotel_whatsapp} className={entrada}
                                onChange={(e) => mudar('hotel_whatsapp', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Email')} erro={daApi?.erros.hotel_email}>
                            <input type="email" value={forma.hotel_email} className={entrada}
                                onChange={(e) => mudar('hotel_email', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Website')} erro={daApi?.erros.hotel_website}>
                            <input value={forma.hotel_website} className={entrada}
                                onChange={(e) => mudar('hotel_website', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Morada')} erro={daApi?.erros.hotel_address}>
                            <input value={forma.hotel_address} className={entrada}
                                onChange={(e) => mudar('hotel_address', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Cidade')} erro={daApi?.erros.hotel_city}>
                            <input value={forma.hotel_city} className={entrada}
                                onChange={(e) => mudar('hotel_city', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('País')} erro={daApi?.erros.hotel_country}>
                            <input value={forma.hotel_country} className={entrada}
                                onChange={(e) => mudar('hotel_country', e.target.value)} />
                        </Campo>

                        <h3 className="mt-2 text-sm font-bold text-slate-800 sm:col-span-2">
                            <i className="fas fa-share-nodes mr-2 text-slate-400" aria-hidden="true" />
                            {t('Onde a casa também está')}
                        </h3>
                        <Campo etiqueta={t('Instagram')} erro={daApi?.erros.instagram}>
                            <input value={forma.instagram} className={entrada} placeholder="@hotel"
                                onChange={(e) => mudar('instagram', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Facebook')} erro={daApi?.erros.facebook}>
                            <input value={forma.facebook} className={entrada}
                                onChange={(e) => mudar('facebook', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Google Maps')} erro={daApi?.erros.google_maps_url}>
                            <input value={forma.google_maps_url} className={entrada}
                                onChange={(e) => mudar('google_maps_url', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('TripAdvisor')} erro={daApi?.erros.tripadvisor_url}>
                            <input value={forma.tripadvisor_url} className={entrada}
                                onChange={(e) => mudar('tripadvisor_url', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Booking.com')} erro={daApi?.erros.booking_com_url} className="sm:col-span-2">
                            <input value={forma.booking_com_url} className={entrada}
                                onChange={(e) => mudar('booking_com_url', e.target.value)} />
                        </Campo>
                    </div>
                )}

                {aba === 'marca' && (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Campo etiqueta={t('Cor principal')} erro={daApi?.erros.primary_color}>
                                <span className="flex gap-2">
                                    <input type="color" value={forma.primary_color}
                                        aria-label={t('Cor principal')}
                                        className={cls('h-10 w-14 flex-none cursor-pointer border border-slate-200 p-1', RAIO, FOCO)}
                                        onChange={(e) => mudar('primary_color', e.target.value)} />
                                    <input value={forma.primary_color} className={cls(entrada, 'font-mono')}
                                        onChange={(e) => mudar('primary_color', e.target.value)} />
                                </span>
                            </Campo>
                            <Campo etiqueta={t('Cor secundária')} erro={daApi?.erros.secondary_color}>
                                <span className="flex gap-2">
                                    <input type="color" value={forma.secondary_color}
                                        aria-label={t('Cor secundária')}
                                        className={cls('h-10 w-14 flex-none cursor-pointer border border-slate-200 p-1', RAIO, FOCO)}
                                        onChange={(e) => mudar('secondary_color', e.target.value)} />
                                    <input value={forma.secondary_color} className={cls(entrada, 'font-mono')}
                                        onChange={(e) => mudar('secondary_color', e.target.value)} />
                                </span>
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Imagem
                                titulo={t('Logótipo')}
                                ajuda={t('Aparece no topo da página pública e no voucher.')}
                                url={forma.logo}
                                pode={pode}
                                aTrabalhar={imagem.isPending}
                                aoEscolher={(f) => imagem.mutate({ qual: 'logo', ficheiro: f })}
                                aoApagar={() => apagarImagem.mutate('logo')}
                            />
                            <Imagem
                                titulo={t('Imagem de capa')}
                                ajuda={t('A fotografia grande da página pública.')}
                                url={forma.capa}
                                larga
                                pode={pode}
                                aTrabalhar={imagem.isPending}
                                aoEscolher={(f) => imagem.mutate({ qual: 'capa', ficheiro: f })}
                                aoApagar={() => apagarImagem.mutate('capa')}
                            />
                        </div>
                    </div>
                )}

                {aba === 'horarios' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Campo etiqueta={t('Hora do check-in')} obrigatorio erro={daApi?.erros.default_check_in_time}>
                            <input type="time" value={forma.default_check_in_time} className={cls(entrada, 'tabular-nums')}
                                onChange={(e) => mudar('default_check_in_time', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Hora do check-out')} obrigatorio erro={daApi?.erros.default_check_out_time}>
                            <input type="time" value={forma.default_check_out_time} className={cls(entrada, 'tabular-nums')}
                                onChange={(e) => mudar('default_check_out_time', e.target.value)} />
                        </Campo>

                        <Interruptor
                            rotulo={t('Aceita entrada antecipada')}
                            nota={t('Entrar antes da hora, quando o quarto já está pronto.')}
                            ligado={forma.early_check_in_available}
                            aoMudar={(v) => mudar('early_check_in_available', v)}
                        />
                        <Campo etiqueta={t('Preço da entrada antecipada (Kz)')} erro={daApi?.erros.early_check_in_fee}>
                            <input type="number" min={0} step="0.01" value={forma.early_check_in_fee}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('early_check_in_fee', Number(e.target.value))} />
                        </Campo>

                        <Interruptor
                            rotulo={t('Aceita saída tardia')}
                            nota={t('Sair depois da hora — o quarto fica ocupado mais tempo.')}
                            ligado={forma.late_check_out_available}
                            aoMudar={(v) => mudar('late_check_out_available', v)}
                        />
                        <Campo etiqueta={t('Preço da saída tardia (Kz)')} erro={daApi?.erros.late_check_out_fee}>
                            <input type="number" min={0} step="0.01" value={forma.late_check_out_fee}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('late_check_out_fee', Number(e.target.value))} />
                        </Campo>
                    </div>
                )}

                {aba === 'reservas' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Interruptor
                            rotulo={t('Reservas pela página pública')}
                            nota={t('Desligado, a página continua a mostrar a casa mas ninguém reserva por lá.')}
                            ligado={forma.online_booking_enabled}
                            aoMudar={(v) => mudar('online_booking_enabled', v)}
                            largo
                        />

                        <Campo etiqueta={t('Antecedência mínima (horas)')} obrigatorio erro={daApi?.erros.min_advance_booking_hours}
                            ajuda={t('Quanto tempo antes da entrada se pode reservar.')}>
                            <input type="number" min={0} value={forma.min_advance_booking_hours}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('min_advance_booking_hours', Number(e.target.value))} />
                        </Campo>
                        <Campo etiqueta={t('Antecedência máxima (dias)')} obrigatorio erro={daApi?.erros.max_advance_booking_days}
                            ajuda={t('Até quando o calendário público aceita reservas.')}>
                            <input type="number" min={1} value={forma.max_advance_booking_days}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('max_advance_booking_days', Number(e.target.value))} />
                        </Campo>
                        <Campo etiqueta={t('Cancelamento gratuito até (horas)')} erro={daApi?.erros.cancellation_hours}>
                            <input type="number" min={0} value={forma.cancellation_hours}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('cancellation_hours', Number(e.target.value))} />
                        </Campo>
                        <Campo etiqueta={t('Sinal (% do total)')} erro={daApi?.erros.deposit_percent}>
                            <input type="number" min={0} max={100} value={forma.deposit_percent}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => mudar('deposit_percent', Number(e.target.value))} />
                        </Campo>

                        <Interruptor
                            rotulo={t('Exige sinal para confirmar')}
                            nota={t('A reserva só passa a confirmada depois de o sinal entrar.')}
                            ligado={forma.require_deposit}
                            aoMudar={(v) => mudar('require_deposit', v)}
                        />
                        <Interruptor
                            rotulo={t('Aceita overbooking')}
                            nota={t('Vender mais do que os quartos que há, contando com as faltas.')}
                            ligado={forma.overbooking_enabled}
                            aoMudar={(v) => mudar('overbooking_enabled', v)}
                        />
                        {forma.overbooking_enabled && (
                            <Campo etiqueta={t('Overbooking (%)')} erro={daApi?.erros.overbooking_percent}>
                                <input type="number" min={0} max={100} value={forma.overbooking_percent}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                    onChange={(e) => mudar('overbooking_percent', Number(e.target.value))} />
                            </Campo>
                        )}

                        <h3 className="mt-2 text-sm font-bold text-slate-800 sm:col-span-2">
                            <i className="fas fa-bell mr-2 text-slate-400" aria-hidden="true" />
                            {t('O que se avisa ao hóspede')}
                        </h3>
                        <Interruptor rotulo={t('Confirmação da reserva')} ligado={forma.notify_reservation_confirmed}
                            aoMudar={(v) => mudar('notify_reservation_confirmed', v)} />
                        <Interruptor rotulo={t('Véspera da chegada')} ligado={forma.notify_pre_arrival}
                            aoMudar={(v) => mudar('notify_pre_arrival', v)} />
                        <Interruptor rotulo={t('Agradecimento depois da estada')} ligado={forma.notify_post_stay}
                            aoMudar={(v) => mudar('notify_post_stay', v)} largo />
                    </div>
                )}

                {aba === 'politicas' && (
                    <div className="grid gap-3">
                        <Campo etiqueta={t('Política de reserva')} erro={daApi?.erros.booking_policies}
                            ajuda={t('O que o hóspede aceita ao reservar.')}>
                            <textarea rows={4} value={forma.booking_policies} className={cls(entrada, 'h-auto py-2')}
                                onChange={(e) => mudar('booking_policies', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Política de cancelamento')} erro={daApi?.erros.cancellation_policies}>
                            <textarea rows={4} value={forma.cancellation_policies} className={cls(entrada, 'h-auto py-2')}
                                onChange={(e) => mudar('cancellation_policies', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Regras da casa')} erro={daApi?.erros.house_rules}>
                            <textarea rows={4} value={forma.house_rules} className={cls(entrada, 'h-auto py-2')}
                                onChange={(e) => mudar('house_rules', e.target.value)} />
                        </Campo>
                    </div>
                )}

                {aba === 'comodidades' && (
                    <div className="space-y-5">
                        <section>
                            <h3 className="mb-3 text-sm font-bold text-slate-800">
                                <i className="fas fa-star mr-2 text-slate-400" aria-hidden="true" />
                                {t('O que a casa oferece')}
                            </h3>
                            <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                {p.comodidades.map((c) => (
                                    <Comodidade
                                        key={c.valor}
                                        c={c}
                                        activa={forma.amenities_list.includes(c.valor)}
                                        aoTrocar={() => mudar('amenities_list',
                                            forma.amenities_list.includes(c.valor)
                                                ? forma.amenities_list.filter((x) => x !== c.valor)
                                                : [...forma.amenities_list, c.valor])}
                                    />
                                ))}
                            </div>
                        </section>

                        <section>
                            <h3 className="mb-1 text-sm font-bold text-slate-800">
                                <i className="fas fa-bed mr-2 text-slate-400" aria-hidden="true" />
                                {t('Quartos em destaque')}
                            </h3>
                            <p className="mb-3 text-xs text-slate-500">
                                {t('São estes que aparecem primeiro na página pública.')}
                            </p>

                            {p.tipos_de_quarto.length === 0 ? (
                                <p className="text-sm text-slate-400">{t('Ainda não há tipos de quarto.')}</p>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {p.tipos_de_quarto.map((tp) => (
                                        <Destaque
                                            key={tp.id}
                                            tipo={tp}
                                            activo={forma.featured_rooms.includes(tp.id)}
                                            aoTrocar={() => mudar('featured_rooms',
                                                forma.featured_rooms.includes(tp.id)
                                                    ? forma.featured_rooms.filter((x) => x !== tp.id)
                                                    : [...forma.featured_rooms, tp.id])}
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                )}

                {aba === 'publica' && (
                    <div className="space-y-4">
                        <section className={cls('border border-indigo-200 bg-indigo-50 p-4', RAIO)}>
                            <h3 className="mb-2 text-sm font-bold text-indigo-900">
                                <i className="fas fa-link mr-2" aria-hidden="true" />
                                {t('O endereço de reservas')}
                            </h3>

                            {forma.booking_url ? (
                                <>
                                    <p className="break-all font-mono text-sm text-indigo-800">{forma.booking_url}</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <a href={forma.booking_url} target="_blank" rel="noreferrer"
                                            className={cls('inline-flex items-center gap-1.5 border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-all hover:-translate-y-0.5', RAIO, FOCO)}>
                                            <i className="fas fa-up-right-from-square" aria-hidden="true" />{t('Abrir')}
                                        </a>
                                        <button type="button"
                                            onClick={() => { void navigator.clipboard?.writeText(forma.booking_url); porRecado(t('Endereço copiado.')); }}
                                            className={cls('inline-flex items-center gap-1.5 border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-all hover:-translate-y-0.5', RAIO, FOCO)}>
                                            <i className="fas fa-copy" aria-hidden="true" />{t('Copiar')}
                                        </button>
                                        {pode && (
                                            <button type="button" onClick={() => porATrocarEndereco(true)}
                                                className={cls('inline-flex items-center gap-1.5 border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 transition-all hover:-translate-y-0.5', RAIO, FOCO)}>
                                                <i className="fas fa-rotate" aria-hidden="true" />{t('Trocar endereço')}
                                            </button>
                                        )}
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-indigo-800">
                                    {t('Ainda não há endereço. Guarde as definições e ele nasce do nome do hotel.')}
                                </p>
                            )}
                        </section>

                        <div className="grid gap-3">
                            <Campo etiqueta={t('Mensagem de boas-vindas')} erro={daApi?.erros.welcome_message}
                                ajuda={t('A primeira frase de quem chega à página.')}>
                                <textarea rows={2} value={forma.welcome_message} className={cls(entrada, 'h-auto py-2')}
                                    onChange={(e) => mudar('welcome_message', e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Título para os motores de busca')} erro={daApi?.erros.meta_title}>
                                <input value={forma.meta_title} className={entrada}
                                    onChange={(e) => mudar('meta_title', e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Descrição para os motores de busca')} erro={daApi?.erros.meta_description}
                                ajuda={t('É o texto que aparece por baixo do título no Google.')}>
                                <textarea rows={2} value={forma.meta_description} className={cls(entrada, 'h-auto py-2')}
                                    onChange={(e) => mudar('meta_description', e.target.value)} />
                            </Campo>
                        </div>
                    </div>
                )}

                {aba === 'fidelidade' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Interruptor
                            rotulo={t('Programa de fidelidade')}
                            nota={t('Cada estada dá pontos ao hóspede, e os pontos dão escalões.')}
                            ligado={forma.loyalty_enabled}
                            aoMudar={(v) => mudar('loyalty_enabled', v)}
                            largo
                        />

                        {forma.loyalty_enabled && (
                            <>
                                <Campo etiqueta={t('Pontos por Kz gasto')} erro={daApi?.erros.loyalty_points_per_kz}
                                    ajuda={t('0,01 dá um ponto por cada 100 Kz.')}>
                                    <input type="number" min={0} step="0.001" value={forma.loyalty_points_per_kz}
                                        className={cls(entrada, 'text-right tabular-nums')}
                                        onChange={(e) => mudar('loyalty_points_per_kz', Number(e.target.value))} />
                                </Campo>
                                <div />
                                <Campo etiqueta={t('Escalão Prata (pontos)')} erro={daApi?.erros.loyalty_tier_silver}>
                                    <input type="number" min={0} value={forma.loyalty_tier_silver}
                                        className={cls(entrada, 'text-right tabular-nums')}
                                        onChange={(e) => mudar('loyalty_tier_silver', Number(e.target.value))} />
                                </Campo>
                                <Campo etiqueta={t('Escalão Ouro (pontos)')} erro={daApi?.erros.loyalty_tier_gold}>
                                    <input type="number" min={0} value={forma.loyalty_tier_gold}
                                        className={cls(entrada, 'text-right tabular-nums')}
                                        onChange={(e) => mudar('loyalty_tier_gold', Number(e.target.value))} />
                                </Campo>
                                <Campo etiqueta={t('Escalão Platina (pontos)')} erro={daApi?.erros.loyalty_tier_platinum}>
                                    <input type="number" min={0} value={forma.loyalty_tier_platinum}
                                        className={cls(entrada, 'text-right tabular-nums')}
                                        onChange={(e) => mudar('loyalty_tier_platinum', Number(e.target.value))} />
                                </Campo>
                            </>
                        )}
                    </div>
                )}
            </fieldset>

            {pode && (
                <div className={cls(CARTAO, 'flex justify-end p-4')}>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                        onClick={() => guardar.mutate(forma)}>
                        {t('Guardar')}
                    </Botao>
                </div>
            )}

            <Modal
                aberto={aTrocarEndereco}
                aoFechar={() => porATrocarEndereco(false)}
                titulo={t('Trocar o endereço de reservas')}
                icone="fa-rotate"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porATrocarEndereco(false)}>{t('Cancelar')}</Botao>
                        <Botao cor="aviso" tom="solida" icone="fa-rotate" aTrabalhar={novoEndereco.isPending}
                            onClick={() => novoEndereco.mutate()}>
                            {t('Trocar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('O endereço muda e as ligações já partilhadas — no cartaz, no Instagram, no WhatsApp — deixam de funcionar.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

function Interruptor({ rotulo, nota, ligado, aoMudar, largo }: {
    rotulo: string;
    nota?: string;
    ligado: boolean;
    aoMudar: (v: boolean) => void;
    largo?: boolean;
}) {
    return (
        <label className={cls(
            'flex cursor-pointer items-start gap-3 border p-3 transition-colors',
            RAIO,
            ligado ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50',
            largo && 'sm:col-span-2',
        )}>
            <input type="checkbox" checked={ligado}
                className={cls('mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600', FOCO)}
                onChange={(e) => aoMudar(e.target.checked)} />
            <span>
                <span className="block text-sm font-semibold text-slate-800">{rotulo}</span>
                {nota && <span className="block text-xs text-slate-500">{nota}</span>}
            </span>
        </label>
    );
}

function Comodidade({ c, activa, aoTrocar }: { c: ComodidadeDoHotel; activa: boolean; aoTrocar: () => void }) {
    return (
        <button
            type="button"
            onClick={aoTrocar}
            aria-pressed={activa}
            className={cls(
                'flex items-center gap-2 border px-3 py-2.5 text-left text-sm font-semibold transition-all duration-200',
                'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                activa
                    ? 'border-emerald-500 bg-emerald-50 text-emerald-800 shadow-sm'
                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300',
            )}
        >
            <i className={cls('fas w-4 flex-none text-center', c.icone)} aria-hidden="true" />
            <span className="min-w-0 flex-1 truncate">{c.rotulo}</span>
            {activa && <i className="fas fa-check flex-none text-emerald-500" aria-hidden="true" />}
        </button>
    );
}

function Destaque({ tipo, activo, aoTrocar }: { tipo: TipoNasDefinicoes; activo: boolean; aoTrocar: () => void }) {
    return (
        <button
            type="button"
            onClick={aoTrocar}
            aria-pressed={activo}
            className={cls(
                'border p-3 text-left transition-all duration-200',
                'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                activo
                    ? 'border-amber-500 bg-amber-50 shadow-sm'
                    : 'border-slate-200 bg-white hover:border-slate-300',
            )}
        >
            <span className="flex items-center justify-between gap-2">
                <span className="truncate font-bold text-slate-800">{tipo.nome}</span>
                <i className={cls('fas flex-none', activo ? 'fa-star text-amber-500' : 'fa-star text-slate-200')} aria-hidden="true" />
            </span>
            <span className="mt-1 block text-xs text-slate-500">
                {t(':n quarto(s)', { n: tipo.quartos })} · <span className="tabular-nums">{kz(tipo.preco)} Kz</span>
            </span>
        </button>
    );
}

function Imagem({ titulo, ajuda, url, larga, pode, aTrabalhar, aoEscolher, aoApagar }: {
    titulo: string;
    ajuda: string;
    url: string | null;
    larga?: boolean;
    pode: boolean;
    aTrabalhar: boolean;
    aoEscolher: (f: File) => void;
    aoApagar: () => void;
}) {
    const campo = useRef<HTMLInputElement>(null);

    return (
        <div className={cls('border border-slate-200 p-4', RAIO)}>
            <h3 className="text-sm font-bold text-slate-800">{titulo}</h3>
            <p className="mb-3 text-xs text-slate-500">{ajuda}</p>

            <div className={cls(
                'grid place-items-center overflow-hidden border border-dashed border-slate-300 bg-slate-50',
                RAIO, larga ? 'h-28' : 'h-24',
            )}>
                {url ? (
                    <img src={url} alt={titulo} className="h-full w-full object-contain" />
                ) : (
                    <span className="text-sm text-slate-400">
                        <i className="fas fa-image mr-2" aria-hidden="true" />
                        {t('Sem imagem')}
                    </span>
                )}
            </div>

            {pode && (
                <div className="mt-3 flex gap-2">
                    <input ref={campo} type="file" accept="image/*" className="hidden"
                        onChange={(e) => {
                            const f = e.target.files?.[0];

                            if (f) aoEscolher(f);
                            e.target.value = '';
                        }} />
                    <Botao icone="fa-upload" aTrabalhar={aTrabalhar} onClick={() => campo.current?.click()}>
                        {url ? t('Trocar') : t('Escolher')}
                    </Botao>
                    {url && <Botao cor="perigo" icone="fa-trash" onClick={aoApagar}>{t('Tirar')}</Botao>}
                </div>
            )}
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as definições do hotel')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
