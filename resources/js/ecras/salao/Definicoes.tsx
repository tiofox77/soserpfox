import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { salao, type PaginaDoSalao, type RegrasDoSalao } from '@/api/salao';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * AS DEFINIÇÕES DO SALÃO.
 *
 * DUAS GRAVAÇÕES SEPARADAS e não uma só: as REGRAS (horário, intervalos,
 * antecedência, depósito) e a PÁGINA PÚBLICA (nome, cores, textos, destaques).
 * Pôr o endereço público ao alcance de um clique numa caixa de horário é o
 * caminho para publicar a marcação online sem querer.
 *
 * O INTERVALO DA AGENDA é a peça mais consequente daqui: é ele que decide as
 * horas que a página pública oferece. A 15 minutos, um corte de 30 pode começar
 * às 9h15 e empurrar o dia todo; a 60, perdem-se meias horas boas.
 */

export default function Definicoes() {
    const cache = useQueryClient();

    const [aba, porAba] = useState('regras');
    const [regras, porRegras] = useState<RegrasDoSalao | null>(null);
    const [pagina, porPagina] = useState<PaginaDoSalao | null>(null);
    const [aTrocarEndereco, porATrocarEndereco] = useState(false);
    const [copiado, porCopiado] = useState(false);

    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useState('');

    const logoRef = useRef<HTMLInputElement>(null);
    const capaRef = useRef<HTMLInputElement>(null);

    const dados = useQuery({ queryKey: ['salao', 'definicoes'], queryFn: salao.definicoes.ler });

    useEffect(() => {
        if (dados.data) { porRegras(dados.data.regras); porPagina(dados.data.pagina); }
    }, [dados.data]);

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['salao', 'definicoes'] });
    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };
    const feito = (r: { message: string }) => { porErros({}); porErro(null); porRecado(r.message); refrescar(); };

    const guardarRegras = useMutation({
        mutationFn: () => salao.definicoes.guardar(regras!),
        onSuccess: feito, onError: falhou,
    });

    const guardarPagina = useMutation({
        mutationFn: () => salao.definicoes.guardarPagina(pagina!),
        onSuccess: feito, onError: falhou,
    });

    const imagem = useMutation({
        mutationFn: ({ qual, ficheiro }: { qual: 'logo' | 'capa'; ficheiro: File }) =>
            salao.definicoes.imagem(qual, ficheiro),
        onSuccess: feito, onError: falhou,
    });

    const removerImagem = useMutation({
        mutationFn: (qual: 'logo' | 'capa') => salao.definicoes.removerImagem(qual),
        onSuccess: feito, onError: falhou,
    });

    const novoEndereco = useMutation({
        mutationFn: () => salao.definicoes.novoEndereco(),
        onSuccess: (r) => { porATrocarEndereco(false); feito(r); },
        onError: falhou,
    });

    if (dados.isPending || !regras || !pagina) return <Carregando linhas={10} />;
    if (dados.isError) return <AvisoDeErro erro={dados.error} />;

    const d = dados.data;
    const podeEditar = d.permissoes.pode_editar;

    const copiar = async () => {
        if (!d.url_de_marcacao) return;

        try {
            await navigator.clipboard.writeText(d.url_de_marcacao);
            porCopiado(true);
            setTimeout(() => porCopiado(false), 2000);
        } catch {
            /* o browser recusou — o endereço continua à vista para se copiar à mão */
        }
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Definições do Salão')}
                subtitulo={t('As regras da casa e a página de marcação')}
                icone="fa-sliders"
                cor="rosa"
                accoes={d.url_de_marcacao && (
                    <a href={d.url_de_marcacao} target="_blank" rel="noopener" className={ACCAO_DA_FAIXA}>
                        <i className="fas fa-arrow-up-right-from-square" aria-hidden="true" />
                        {t('Abrir a página')}
                    </a>
                )}
            />

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <Separadores
                activa={aba}
                aoMudar={porAba}
                abas={[
                    { chave: 'regras', rotulo: t('Agenda'), icone: 'fa-calendar-days' },
                    { chave: 'pagina', rotulo: t('Página de marcação'), icone: 'fa-globe' },
                ]}
            />

            <PainelDoSeparador chave="regras" activa={aba}>
                <div className="space-y-5">
                    <Cartao titulo={t('Horário da casa')} icone="fa-clock">
                        <div className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo etiqueta={t('Abertura')} obrigatorio erro={erros.opening_time}>
                                    <input
                                        type="time" value={regras.opening_time} disabled={!podeEditar}
                                        onChange={(e) => porRegras({ ...regras, opening_time: e.target.value })}
                                        className={entrada}
                                    />
                                </Campo>
                                <Campo etiqueta={t('Fecho')} obrigatorio erro={erros.closing_time}>
                                    <input
                                        type="time" value={regras.closing_time} disabled={!podeEditar}
                                        onChange={(e) => porRegras({ ...regras, closing_time: e.target.value })}
                                        className={entrada}
                                    />
                                </Campo>
                            </div>

                            <fieldset>
                                <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('Dias de abertura')}
                                    <span className="ml-0.5 text-red-500" aria-hidden="true">*</span>
                                </legend>

                                <div className="flex flex-wrap gap-1.5">
                                    {d.dias.map((dia) => {
                                        const dentro = regras.working_days.includes(dia.valor);

                                        return (
                                            <button
                                                key={dia.valor}
                                                type="button"
                                                disabled={!podeEditar}
                                                aria-pressed={dentro}
                                                onClick={() => porRegras({
                                                    ...regras,
                                                    working_days: dentro
                                                        ? regras.working_days.filter((x) => x !== dia.valor)
                                                        : [...regras.working_days, dia.valor],
                                                })}
                                                className={cls(
                                                    'px-3 py-1.5 text-xs font-semibold transition-all duration-200', RAIO, FOCO,
                                                    'disabled:cursor-not-allowed disabled:opacity-60',
                                                    dentro ? 'bg-pink-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                                                )}
                                            >
                                                {dia.rotulo}
                                            </button>
                                        );
                                    })}
                                </div>

                                {erros.working_days?.[0] && (
                                    <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.working_days[0]}</p>
                                )}
                            </fieldset>
                        </div>
                    </Cartao>

                    <Cartao titulo={t('Como a agenda se enche')} icone="fa-calendar-days">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta={t('Intervalo da agenda (min)')}
                                obrigatorio
                                erro={erros.slot_interval}
                                ajuda={t('As horas que a página oferece. A 15, um corte de 30 pode começar às 9h15 e empurrar o dia todo.')}
                            >
                                <input
                                    type="number" min="5" max="120" step="5"
                                    value={regras.slot_interval} disabled={!podeEditar}
                                    onChange={(e) => porRegras({ ...regras, slot_interval: Number(e.target.value) })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo
                                etiqueta={t('Antecedência mínima (h)')}
                                obrigatorio
                                erro={erros.min_advance_booking_hours}
                                ajuda={t('Quanto tempo antes é preciso marcar.')}
                            >
                                <input
                                    type="number" min="0" max="720"
                                    value={regras.min_advance_booking_hours} disabled={!podeEditar}
                                    onChange={(e) => porRegras({ ...regras, min_advance_booking_hours: Number(e.target.value) })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Antecedência máxima (dias)')} obrigatorio erro={erros.max_advance_booking_days}>
                                <input
                                    type="number" min="1" max="365"
                                    value={regras.max_advance_booking_days} disabled={!podeEditar}
                                    onChange={(e) => porRegras({ ...regras, max_advance_booking_days: Number(e.target.value) })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo
                                etiqueta={t('Cancelamento até (h)')}
                                erro={erros.cancellation_hours}
                                ajuda={t('Depois disso, a cliente já não cancela sozinha.')}
                            >
                                <input
                                    type="number" min="0" max="720"
                                    value={regras.cancellation_hours} disabled={!podeEditar}
                                    onChange={(e) => porRegras({ ...regras, cancellation_hours: Number(e.target.value) })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Lembrete (h antes)')} erro={erros.reminder_hours}>
                                <input
                                    type="number" min="0" max="720"
                                    value={regras.reminder_hours} disabled={!podeEditar}
                                    onChange={(e) => porRegras({ ...regras, reminder_hours: Number(e.target.value) })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        </div>

                        <div className="mt-4 space-y-3">
                            <Interruptor
                                ligado={regras.online_booking_enabled}
                                aoMudar={(v) => porRegras({ ...regras, online_booking_enabled: v })}
                                desactivado={!podeEditar}
                                titulo={t('Marcação online aberta')}
                                nota={t('Desligada, a página existe mas não aceita marcações.')}
                            />
                            <Interruptor
                                ligado={regras.require_confirmation}
                                aoMudar={(v) => porRegras({ ...regras, require_confirmation: v })}
                                desactivado={!podeEditar}
                                titulo={t('A marcação online precisa de confirmação')}
                                nota={t('Entra como «marcada» e alguém do salão confirma.')}
                            />
                        </div>
                    </Cartao>

                    <Cartao titulo={t('Faltas e depósitos')} icone="fa-hand-holding-dollar">
                        <div className="space-y-4">
                            <Campo
                                etiqueta={t('Taxa de falta (%)')}
                                erro={erros.no_show_fee_percent}
                                className="max-w-xs"
                                ajuda={t('Sobre o valor da marcação a que a cliente não compareceu.')}
                            >
                                <input
                                    type="number" step="0.01" min="0" max="100"
                                    value={regras.no_show_fee_percent} disabled={!podeEditar}
                                    onChange={(e) => porRegras({ ...regras, no_show_fee_percent: Number(e.target.value) })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Interruptor
                                ligado={regras.require_deposit}
                                aoMudar={(v) => porRegras({ ...regras, require_deposit: v })}
                                desactivado={!podeEditar}
                                titulo={t('Exigir depósito para marcar')}
                                nota={t('Um depósito a zero por cento não é um depósito — é um passo a mais que não cobra nada.')}
                            />

                            {regras.require_deposit && (
                                <Campo etiqueta={t('Depósito (%)')} obrigatorio erro={erros.deposit_percent} className="max-w-xs">
                                    <input
                                        type="number" step="0.01" min="0" max="100"
                                        value={regras.deposit_percent} disabled={!podeEditar}
                                        onChange={(e) => porRegras({ ...regras, deposit_percent: Number(e.target.value) })}
                                        className={cls(entrada, 'text-right tabular-nums')}
                                    />
                                </Campo>
                            )}

                            <Interruptor
                                ligado={regras.allow_online_payment}
                                aoMudar={(v) => porRegras({ ...regras, allow_online_payment: v })}
                                desactivado={!podeEditar}
                                titulo={t('Aceitar pagamento na página')}
                            />
                        </div>
                    </Cartao>

                    {podeEditar && (
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardarRegras.isPending} onClick={() => guardarRegras.mutate()}>
                            {t('Guardar agenda')}
                        </Botao>
                    )}
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="pagina" activa={aba}>
                <div className="space-y-5">
                    <Cartao titulo={t('O endereço da página')} icone="fa-link">
                        {d.url_de_marcacao ? (
                            <div className="space-y-3">
                                <div className={cls('flex flex-wrap items-center gap-2 border border-slate-200 bg-slate-50 px-3 py-2', RAIO)}>
                                    <code className="min-w-0 flex-1 truncate text-sm text-slate-700">{d.url_de_marcacao}</code>
                                    <Botao altura="pequeno" icone={copiado ? 'fa-check' : 'fa-copy'} onClick={copiar}>
                                        {copiado ? t('Copiado') : t('Copiar')}
                                    </Botao>
                                    <a
                                        href={d.url_de_marcacao}
                                        target="_blank"
                                        rel="noopener"
                                        className={cls('inline-flex h-8 items-center gap-1.5 rounded-xl bg-slate-100 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-200', FOCO)}
                                    >
                                        <i className="fas fa-arrow-up-right-from-square" aria-hidden="true" />
                                        {t('Abrir')}
                                    </a>
                                </div>

                                {podeEditar && (
                                    <Botao cor="aviso" icone="fa-rotate" onClick={() => porATrocarEndereco(true)}>
                                        {t('Endereço novo')}
                                    </Botao>
                                )}
                            </div>
                        ) : (
                            <p className="text-sm text-slate-500">
                                {t('O endereço nasce com o nome do salão, assim que guardar a página.')}
                            </p>
                        )}
                    </Cartao>

                    <Cartao titulo={t('Imagens')} icone="fa-image">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <ZonaDeImagem
                                rotulo={t('Logótipo')}
                                url={d.logo}
                                proporcao="aspect-square"
                                podeEditar={podeEditar}
                                aTrabalhar={imagem.isPending && imagem.variables?.qual === 'logo'}
                                referencia={logoRef}
                                aoEscolher={(f) => imagem.mutate({ qual: 'logo', ficheiro: f })}
                                aoRemover={() => removerImagem.mutate('logo')}
                            />
                            <ZonaDeImagem
                                rotulo={t('Capa')}
                                url={d.capa}
                                proporcao="aspect-[16/9]"
                                podeEditar={podeEditar}
                                aTrabalhar={imagem.isPending && imagem.variables?.qual === 'capa'}
                                referencia={capaRef}
                                aoEscolher={(f) => imagem.mutate({ qual: 'capa', ficheiro: f })}
                                aoRemover={() => removerImagem.mutate('capa')}
                            />
                        </div>
                    </Cartao>

                    <Cartao titulo={t('A casa')} icone="fa-store">
                        <div className="space-y-4">
                            <Campo etiqueta={t('Nome do salão')} obrigatorio erro={erros.salon_name}>
                                <input
                                    value={pagina.salon_name} disabled={!podeEditar}
                                    onChange={(e) => porPagina({ ...pagina, salon_name: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Descrição')} erro={erros.salon_description}>
                                <textarea
                                    value={pagina.salon_description} disabled={!podeEditar}
                                    onChange={(e) => porPagina({ ...pagina, salon_description: e.target.value })}
                                    rows={3}
                                    className={cls(entrada, 'h-auto py-2')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Morada')} erro={erros.salon_address}>
                                <input
                                    value={pagina.salon_address} disabled={!podeEditar}
                                    onChange={(e) => porPagina({ ...pagina, salon_address: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Campo etiqueta={t('Telefone')} erro={erros.salon_phone}>
                                    <input value={pagina.salon_phone} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_phone: e.target.value })} className={entrada} />
                                </Campo>
                                <Campo etiqueta={t('WhatsApp')} erro={erros.salon_whatsapp}>
                                    <input value={pagina.salon_whatsapp} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_whatsapp: e.target.value })} className={entrada} />
                                </Campo>
                                <Campo etiqueta={t('E-mail')} erro={erros.salon_email}>
                                    <input type="email" value={pagina.salon_email} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_email: e.target.value })} className={entrada} />
                                </Campo>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo etiqueta={t('Cor principal')} erro={erros.primary_color}>
                                    <EscolherCor valor={pagina.primary_color} desactivado={!podeEditar} etiqueta={t('Cor principal')} aoMudar={(v) => porPagina({ ...pagina, primary_color: v })} />
                                </Campo>
                                <Campo etiqueta={t('Cor secundária')} erro={erros.secondary_color}>
                                    <EscolherCor valor={pagina.secondary_color} desactivado={!podeEditar} etiqueta={t('Cor secundária')} aoMudar={(v) => porPagina({ ...pagina, secondary_color: v })} />
                                </Campo>
                            </div>
                        </div>
                    </Cartao>

                    <Cartao titulo={t('Redes e mapa')} icone="fa-share-nodes">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta="Instagram" erro={erros.salon_instagram}>
                                <input value={pagina.salon_instagram} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_instagram: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta="Facebook" erro={erros.salon_facebook}>
                                <input value={pagina.salon_facebook} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_facebook: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta="TikTok" erro={erros.salon_tiktok}>
                                <input value={pagina.salon_tiktok} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_tiktok: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Sítio')} erro={erros.salon_website}>
                                <input value={pagina.salon_website} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_website: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Google Maps')} erro={erros.salon_google_maps_url} className="sm:col-span-2">
                                <input value={pagina.salon_google_maps_url} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, salon_google_maps_url: e.target.value })} className={entrada} />
                            </Campo>
                        </div>
                    </Cartao>

                    <Cartao
                        titulo={t('Serviços em destaque')}
                        subtitulo={t('Os que aparecem primeiro na página')}
                        icone="fa-star"
                    >
                        {d.servicos.length === 0 ? (
                            <p className="text-sm text-slate-500">{t('Ainda não há serviços activos.')}</p>
                        ) : (
                            <div className="flex flex-wrap gap-1.5">
                                {d.servicos.map((s) => {
                                    const dentro = pagina.featured_services.includes(Number(s.valor));

                                    return (
                                        <button
                                            key={s.valor}
                                            type="button"
                                            disabled={!podeEditar}
                                            aria-pressed={dentro}
                                            onClick={() => porPagina({
                                                ...pagina,
                                                featured_services: dentro
                                                    ? pagina.featured_services.filter((x) => x !== Number(s.valor))
                                                    : [...pagina.featured_services, Number(s.valor)],
                                            })}
                                            className={cls(
                                                'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition', RAIO, FOCO,
                                                'disabled:cursor-not-allowed disabled:opacity-60',
                                                dentro ? 'bg-amber-500 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                                            )}
                                        >
                                            {dentro && <i className="fas fa-star" aria-hidden="true" />}
                                            {s.rotulo}
                                            <span className="font-normal opacity-70">{kz(s.preco, 0)}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </Cartao>

                    <Cartao titulo={t('Textos e condições')} icone="fa-file-lines">
                        <div className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo etiqueta={t('Mensagem de boas-vindas')} erro={erros.welcome_message}>
                                    <textarea value={pagina.welcome_message} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, welcome_message: e.target.value })} rows={2} className={cls(entrada, 'h-auto py-2')} />
                                </Campo>
                                <Campo etiqueta={t('Mensagem de confirmação')} erro={erros.confirmation_message}>
                                    <textarea value={pagina.confirmation_message} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, confirmation_message: e.target.value })} rows={2} className={cls(entrada, 'h-auto py-2')} />
                                </Campo>
                            </div>

                            <Campo etiqueta={t('Condições de marcação')} erro={erros.booking_terms}>
                                <textarea value={pagina.booking_terms} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, booking_terms: e.target.value })} rows={3} className={cls(entrada, 'h-auto py-2')} />
                            </Campo>

                            <Campo etiqueta={t('Política de cancelamento')} erro={erros.cancellation_policy}>
                                <textarea value={pagina.cancellation_policy} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, cancellation_policy: e.target.value })} rows={3} className={cls(entrada, 'h-auto py-2')} />
                            </Campo>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo etiqueta={t('Título para o Google')} erro={erros.meta_title}>
                                    <input value={pagina.meta_title} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, meta_title: e.target.value })} className={entrada} />
                                </Campo>
                                <Campo etiqueta={t('Descrição para o Google')} erro={erros.meta_description}>
                                    <input value={pagina.meta_description} disabled={!podeEditar} onChange={(e) => porPagina({ ...pagina, meta_description: e.target.value })} className={entrada} />
                                </Campo>
                            </div>
                        </div>
                    </Cartao>

                    {podeEditar && (
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardarPagina.isPending} onClick={() => guardarPagina.mutate()}>
                            {t('Guardar página')}
                        </Botao>
                    )}
                </div>
            </PainelDoSeparador>

            <Modal
                aberto={aTrocarEndereco}
                aoFechar={() => porATrocarEndereco(false)}
                titulo={t('Endereço novo')}
                icone="fa-rotate"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porATrocarEndereco(false)}>{t('Cancelar')}</Botao>
                        <Botao cor="aviso" tom="solida" icone="fa-rotate" aTrabalhar={novoEndereco.isPending} onClick={() => novoEndereco.mutate()}>
                            {t('Trocar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('O endereço antigo DEIXA DE FUNCIONAR — e ele pode estar num cartaz, numa biografia do Instagram ou num QR já impresso.')}
                </p>
            </Modal>
        </div>
    );
}

function Interruptor({
    ligado, aoMudar, titulo, nota, desactivado,
}: {
    ligado: boolean; aoMudar: (v: boolean) => void; titulo: string; nota?: string; desactivado?: boolean;
}) {
    return (
        <div className="flex items-start gap-3">
            <button
                type="button"
                role="switch"
                aria-checked={ligado}
                aria-label={titulo}
                disabled={desactivado}
                onClick={() => aoMudar(!ligado)}
                className={cls(
                    'relative mt-0.5 inline-flex h-6 w-11 flex-none items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                    FOCO,
                    ligado ? 'bg-pink-600' : 'bg-slate-300',
                )}
            >
                <span className={cls('inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', ligado ? 'translate-x-6' : 'translate-x-1')} />
            </button>

            <div className="min-w-0">
                <p className="text-sm font-semibold text-slate-800">{titulo}</p>
                {nota && <p className="text-xs text-slate-500">{nota}</p>}
            </div>
        </div>
    );
}

function EscolherCor({
    valor, aoMudar, etiqueta, desactivado,
}: {
    valor: string; aoMudar: (v: string) => void; etiqueta: string; desactivado?: boolean;
}) {
    return (
        <div className="flex items-center gap-2">
            <input
                type="color"
                value={valor}
                disabled={desactivado}
                onChange={(e) => aoMudar(e.target.value)}
                aria-label={etiqueta}
                className="h-10 w-14 flex-none cursor-pointer rounded-xl border border-slate-300 bg-white p-1 disabled:cursor-not-allowed"
            />
            <input
                value={valor}
                disabled={desactivado}
                onChange={(e) => aoMudar(e.target.value)}
                className={cls(entrada, 'font-mono')}
            />
        </div>
    );
}

function ZonaDeImagem({
    rotulo, url, proporcao, podeEditar, aTrabalhar, referencia, aoEscolher, aoRemover,
}: {
    rotulo: string;
    url: string | null;
    proporcao: string;
    podeEditar: boolean;
    aTrabalhar: boolean;
    referencia: React.RefObject<HTMLInputElement | null>;
    aoEscolher: (f: File) => void;
    aoRemover: () => void;
}) {
    return (
        <div>
            <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</p>

            <div className={cls('relative overflow-hidden border-2 border-dashed border-slate-300 bg-slate-50', RAIO, proporcao)}>
                {url ? (
                    <img src={url} alt={rotulo} className="h-full w-full object-cover" />
                ) : (
                    <span className="absolute inset-0 grid place-items-center text-slate-300">
                        <i className="fas fa-image text-3xl" aria-hidden="true" />
                    </span>
                )}

                {aTrabalhar && (
                    <span className="absolute inset-0 grid place-items-center bg-white/70 text-slate-600">
                        <i className="fas fa-spinner fa-spin text-2xl" aria-hidden="true" />
                    </span>
                )}
            </div>

            {podeEditar && (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <input
                        ref={referencia}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => {
                            const f = e.target.files?.[0];

                            if (f) aoEscolher(f);

                            e.target.value = '';
                        }}
                    />
                    <Botao altura="pequeno" icone="fa-upload" onClick={() => referencia.current?.click()}>
                        {url ? t('Trocar') : t('Escolher')}
                    </Botao>
                    {url && <Botao altura="pequeno" cor="perigo" icone="fa-trash" onClick={aoRemover}>{t('Remover')}</Botao>}
                    <span className="text-xs text-slate-400">{t('Até 5 MB')}</span>
                </div>
            )}
        </div>
    );
}
