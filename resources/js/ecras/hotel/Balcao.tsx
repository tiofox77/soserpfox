import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import {
    balcao,
    type EntradaParaRegistar,
    type EntradaRegistada,
    type HospedeDoBalcao,
    type OpcoesDoBalcao,
    type TipoNoBalcao,
} from '@/api/hotel';
import { catalogos } from '@/api/catalogos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * O BALCÃO — quem chega sem reserva e fica hoje.
 *
 * Três passos: o quarto, o hóspede, a conta. No fim há uma estada com entrada
 * já dada, porque a pessoa está ali de mala na mão.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE, e é o mais grave do módulo:
 *
 *  • A ESTADA NASCIA SEM ADQUIRENTE. O ecrã criava uma ficha na tabela antiga
 *    (`hotel_guests`), que a facturação não conhece, e deixava o cliente a
 *    nulo — no check-out não havia a quem facturar, e o hóspede saía sem
 *    documento. Aqui o hóspede é um CLIENTE, como em todo o resto do módulo.
 *  • OS QUARTOS «LIVRES» eram os de estado `available` e mais nada: um quarto
 *    livre hoje mas reservado para amanhã aparecia na lista, e o walk-in de
 *    três noites entrava por cima da reserva de amanhã.
 */

const HOSPEDE_VAZIO = {
    name: '', phone: '', email: '', document_number: '', nationality: 'Angola', nif: '',
};

type HospedeRapido = typeof HOSPEDE_VAZIO;

const hoje = () => new Date().toISOString().slice(0, 10);
const amanha = () => {
    const d = new Date();

    d.setDate(d.getDate() + 1);

    return d.toISOString().slice(0, 10);
};

function noitesEntre(de: string, ate: string): number {
    if (!de || !ate) return 1;

    const ms = new Date(`${ate}T12:00:00`).getTime() - new Date(`${de}T12:00:00`).getTime();

    return Math.max(1, Math.round(ms / 86_400_000));
}

const PASSOS = [
    { n: 1, rotulo: () => t('Quarto'), icone: 'fa-door-open' },
    { n: 2, rotulo: () => t('Hóspede'), icone: 'fa-user' },
    { n: 3, rotulo: () => t('Conta'), icone: 'fa-money-bill-wave' },
] as const;

export default function Balcao() {
    const [passo, porPasso] = useState(1);
    const [tipo, porTipo] = useState<TipoNoBalcao | null>(null);
    const [quarto, porQuarto] = useState<string>('');
    const [de, porDe] = useState(hoje());
    const [ate, porAte] = useState(amanha());
    const [adultos, porAdultos] = useState('1');
    const [criancas, porCriancas] = useState('0');
    const [hospede, porHospede] = useState<HospedeDoBalcao | null>(null);
    const [novoHospede, porNovoHospede] = useState<HospedeRapido | null>(null);
    const [taxa, porTaxa] = useState('0');
    const [desconto, porDesconto] = useState('0');
    const [pago, porPago] = useState('0');
    const [pedidos, porPedidos] = useState('');
    const [feito, porFeito] = useState<EntradaRegistada | null>(null);

    const opcoes = useQuery({ queryKey: ['hotel', 'balcao', 'opcoes'], queryFn: balcao.opcoes, staleTime: 5 * 60_000 });

    const quartos = useQuery({
        queryKey: ['hotel', 'balcao', 'quartos', tipo?.id, de, ate],
        queryFn: () => balcao.quartos(String(tipo!.id), de, ate),
        enabled: tipo !== null && Boolean(de) && Boolean(ate),
    });

    const criarHospede = useMutation({
        // Pela porta de sempre — o catálogo dos hóspedes, com as suas regras.
        mutationFn: (dados: HospedeRapido) => catalogos.criar('hospedes', dados),
        onSuccess: (r) => {
            porNovoHospede(null);
            porHospede({
                id: r.data.id, nome: String(r.data.name ?? ''), telefone: null, email: null,
                nif: null, documento: null, nacionalidade: null, vip: false, lista_negra: false,
            });
        },
    });

    const registar = useMutation({
        mutationFn: (dados: EntradaParaRegistar) => balcao.registar(dados),
        onSuccess: (r) => porFeito(r.data),
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const noites = noitesEntre(de, ate);
    const total = Math.max(0, Number(taxa) * noites - Number(desconto));

    const recomecar = () => {
        porFeito(null); porPasso(1); porTipo(null); porQuarto(''); porHospede(null);
        porDe(hoje()); porAte(amanha()); porAdultos('1'); porCriancas('0');
        porTaxa('0'); porDesconto('0'); porPago('0'); porPedidos('');
    };

    if (feito) {
        return <Pronto entrada={feito} aoRecomecar={recomecar} />;
    }

    const podeAvancar = passo === 1 ? Boolean(tipo && quarto) : passo === 2 ? Boolean(hospede) : true;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Balcão')}
                subtitulo={t('Quem chega sem reserva e fica hoje')}
                icone="fa-person-walking-luggage"
                cor="bom"
            />

            <AvisoDeErro erro={registar.error} />

            {/* OS TRÊS PASSOS. Uma barra que diz onde se está e o que falta. */}
            <ol className={cls(CARTAO, 'flex items-center gap-2 p-4')}>
                {PASSOS.map((p, i) => {
                    const activo = passo === p.n;
                    const feitoJa = passo > p.n;

                    return (
                        <li key={p.n} className="flex flex-1 items-center gap-2">
                            <button
                                type="button"
                                disabled={p.n > passo}
                                onClick={() => porPasso(p.n)}
                                className={cls(
                                    'flex items-center gap-2 px-3 py-2 text-sm font-semibold transition-all duration-200',
                                    RAIO, FOCO,
                                    activo ? 'bg-emerald-50 text-emerald-800 shadow-sm'
                                        : feitoJa ? 'text-emerald-600 hover:bg-emerald-50'
                                            : 'cursor-default text-slate-400',
                                )}
                            >
                                <span className={cls(
                                    'grid h-7 w-7 flex-none place-items-center rounded-full text-xs',
                                    activo ? 'bg-emerald-600 text-white'
                                        : feitoJa ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-slate-100 text-slate-400',
                                )}>
                                    {feitoJa ? <i className="fas fa-check" aria-hidden="true" /> : p.n}
                                </span>
                                <span className="hidden sm:inline">{p.rotulo()}</span>
                            </button>
                            {i < PASSOS.length - 1 && (
                                <span className={cls('h-0.5 flex-1 rounded-full transition-colors',
                                    passo > p.n ? 'bg-emerald-400' : 'bg-slate-150 bg-slate-200')} aria-hidden="true" />
                            )}
                        </li>
                    );
                })}
            </ol>

            {passo === 1 && (
                <div className="space-y-4">
                    <div className={cls(CARTAO, 'grid gap-3 p-4 sm:grid-cols-4')}>
                        <Campo etiqueta={t('Check-in')} obrigatorio>
                            <input type="date" value={de} className={cls(entrada, 'tabular-nums')}
                                onChange={(e) => { porDe(e.target.value); porQuarto(''); }} />
                        </Campo>
                        <Campo etiqueta={t('Check-out')} obrigatorio>
                            <input type="date" value={ate} className={cls(entrada, 'tabular-nums')}
                                onChange={(e) => { porAte(e.target.value); porQuarto(''); }} />
                        </Campo>
                        <Campo etiqueta={t('Adultos')} obrigatorio>
                            <input type="number" min={1} max={10} value={adultos}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => porAdultos(e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Crianças')} obrigatorio>
                            <input type="number" min={0} max={10} value={criancas}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => porCriancas(e.target.value)} />
                        </Campo>
                    </div>

                    <section className={cls(CARTAO, 'p-4')}>
                        <h2 className="mb-3 text-sm font-bold text-slate-800">
                            <i className="fas fa-bed mr-2 text-emerald-600" aria-hidden="true" />
                            {t('Tipo de quarto')}
                        </h2>

                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {o.tipos_de_quarto.map((x, i) => {
                                const activo = tipo?.id === x.id;

                                return (
                                    <li key={x.id} className="entra" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <button
                                            type="button"
                                            onClick={() => { porTipo(x); porQuarto(''); porTaxa(String(x.preco)); }}
                                            className={cls(
                                                'w-full border p-4 text-left transition-all duration-200',
                                                'hover:-translate-y-0.5 hover:shadow-md active:translate-y-0', RAIO, FOCO,
                                                activo
                                                    ? 'border-emerald-500 bg-emerald-50 shadow-sm'
                                                    : 'border-slate-200 bg-white hover:border-slate-300',
                                            )}
                                        >
                                            <span className="flex items-start justify-between gap-2">
                                                <span className="font-bold text-slate-800">{x.nome}</span>
                                                {activo && <i className="fas fa-circle-check text-emerald-500" aria-hidden="true" />}
                                            </span>
                                            <span className="mt-1 block text-xs text-slate-500">
                                                <i className="fas fa-user-group mr-1" aria-hidden="true" />
                                                {t('até :n pessoa(s)', { n: x.capacidade })}
                                            </span>
                                            <span className="mt-2 block text-lg font-bold tabular-nums text-emerald-700">
                                                {kz(x.preco)} Kz
                                                <span className="ml-1 text-xs font-normal text-slate-400">{t('/noite')}</span>
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    {tipo && (
                        <section className={cls(CARTAO, 'p-4')}>
                            <h2 className="mb-3 text-sm font-bold text-slate-800">
                                <i className="fas fa-key mr-2 text-emerald-600" aria-hidden="true" />
                                {t('Quartos livres de :de a :ate', { de: data(de), ate: data(ate) })}
                            </h2>

                            {quartos.isPending ? (
                                <Carregando linhas={2} />
                            ) : (quartos.data?.data.length ?? 0) === 0 ? (
                                <p className={cls('border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                                    {t('Não há quartos deste tipo livres nestas datas. Tente outro tipo, ou outras datas.')}
                                </p>
                            ) : (
                                <ul className="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-8">
                                    {(quartos.data?.data ?? []).map((q) => {
                                        const activo = quarto === String(q.id);

                                        return (
                                            <li key={q.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => porQuarto(String(q.id))}
                                                    className={cls(
                                                        'w-full border p-3 text-center transition-all duration-200',
                                                        'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                                        activo
                                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800 shadow-sm'
                                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                                                    )}
                                                >
                                                    <span className="block text-lg font-bold">{q.numero}</span>
                                                    <span className={cls('block text-[10px]', q.precisa_de_limpeza ? 'font-bold text-amber-600' : 'opacity-60')}>
                                                        {q.precisa_de_limpeza ? t('Por limpar') : q.limpeza_rotulo}
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>
                    )}
                </div>
            )}

            {passo === 2 && (
                <EscolherHospede
                    escolhido={hospede}
                    podeCriar={o.permissoes.pode_criar_hospede}
                    aoEscolher={porHospede}
                    aoCriar={() => porNovoHospede({ ...HOSPEDE_VAZIO })}
                />
            )}

            {passo === 3 && (
                <section className={cls(CARTAO, 'p-4')}>
                    <h2 className="mb-3 text-sm font-bold text-slate-800">
                        <i className="fas fa-receipt mr-2 text-emerald-600" aria-hidden="true" />
                        {t('A conta')}
                    </h2>

                    <div className="grid gap-3 sm:grid-cols-3">
                        <Campo etiqueta={t('Taxa/noite (Kz)')} obrigatorio>
                            <input type="number" min={0} step="0.01" value={taxa}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => porTaxa(e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Desconto (Kz)')} obrigatorio>
                            <input type="number" min={0} step="0.01" value={desconto}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => porDesconto(e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Valor pago (Kz)')} obrigatorio
                            ajuda={t('O que fica por receber cobra-se no check-out.')}>
                            <input type="number" min={0} step="0.01" value={pago}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => porPago(e.target.value)} />
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Pedidos especiais')} className="mt-3">
                        <textarea rows={2} value={pedidos} className={cls(entrada, 'h-auto py-2')}
                            placeholder={t('Preferências do hóspede…')}
                            onChange={(e) => porPedidos(e.target.value)} />
                    </Campo>

                    <dl className={cls('mt-4 space-y-1.5 border border-emerald-200 bg-emerald-50 p-4', RAIO)}>
                        <Dado rotulo={t('Quarto')} valor={quartos.data?.data.find((q) => String(q.id) === quarto)?.numero} />
                        <Dado rotulo={t('Hóspede')} valor={hospede?.nome} />
                        <Dado rotulo={t('Período')} valor={t(':de a :ate', { de: data(de), ate: data(ate) })} />
                        <Dado rotulo={t('Noites')} valor={String(noites)} />
                        <Dado rotulo={t('Alojamento')} valor={`${kz(Number(taxa) * noites)} Kz`} />
                        {Number(desconto) > 0 && <Dado rotulo={t('Desconto')} valor={`−${kz(Number(desconto))} Kz`} />}
                        <div className="flex items-baseline justify-between gap-3 border-t border-emerald-200 pt-1.5">
                            <dt className="font-bold text-emerald-900">{t('Total')}</dt>
                            <dd className="text-xl font-bold tabular-nums text-emerald-900">{kz(total)} Kz</dd>
                        </div>
                        <p className="text-xs text-emerald-700">{t('O imposto entra ao gravar, pelo regime da empresa.')}</p>
                    </dl>
                </section>
            )}

            <div className={cls(CARTAO, 'flex items-center justify-between gap-3 p-4')}>
                <Botao icone="fa-chevron-left" disabled={passo === 1} onClick={() => porPasso((p) => p - 1)}>
                    {t('Voltar')}
                </Botao>

                {passo < 3 ? (
                    <Botao cor="primaria" tom="solida" icone="fa-chevron-right" disabled={!podeAvancar}
                        onClick={() => porPasso((p) => p + 1)}>
                        {t('Seguinte')}
                    </Botao>
                ) : (
                    <Botao cor="bom" tom="solida" icone="fa-key" aTrabalhar={registar.isPending}
                        disabled={!tipo || !quarto || !hospede}
                        onClick={() => registar.mutate({
                            client_id: String(hospede!.id),
                            room_type_id: String(tipo!.id),
                            room_id: quarto,
                            check_in_date: de,
                            check_out_date: ate,
                            adults: adultos,
                            children: criancas,
                            room_rate: taxa,
                            discount: desconto,
                            paid_amount: pago,
                            special_requests: pedidos,
                        })}>
                        {t('Registar entrada')}
                    </Botao>
                )}
            </div>

            {novoHospede && (
                <NovoHospede
                    valores={novoHospede}
                    aGravar={criarHospede.isPending}
                    erro={criarHospede.error}
                    aoMudar={porNovoHospede}
                    aoFechar={() => porNovoHospede(null)}
                    aoGravar={() => criarHospede.mutate(novoHospede)}
                />
            )}
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

function EscolherHospede({ escolhido, podeCriar, aoEscolher, aoCriar }: {
    escolhido: HospedeDoBalcao | null;
    podeCriar: boolean;
    aoEscolher: (h: HospedeDoBalcao | null) => void;
    aoCriar: () => void;
}) {
    const [procura, porProcura] = useState('');
    const [atraso, porAtraso] = useState('');

    useEffect(() => {
        const id = setTimeout(() => porAtraso(procura), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const achados = useQuery({
        queryKey: ['hotel', 'balcao', 'hospedes', atraso],
        queryFn: () => balcao.hospedes(atraso),
        staleTime: 30_000,
    });

    return (
        <section className={cls(CARTAO, 'p-4')}>
            <h2 className="mb-3 text-sm font-bold text-slate-800">
                <i className="fas fa-user mr-2 text-emerald-600" aria-hidden="true" />
                {t('Quem fica')}
            </h2>

            {escolhido ? (
                <div className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 p-4', RAIO)}>
                    <span className="flex min-w-0 items-center gap-3">
                        <span className="grid h-10 w-10 flex-none place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 font-bold text-white" aria-hidden="true">
                            {escolhido.nome.slice(0, 1).toUpperCase()}
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate font-bold text-emerald-900">{escolhido.nome}</span>
                            <span className="block truncate text-xs text-emerald-700">
                                {[escolhido.telefone, escolhido.email, escolhido.documento, escolhido.nif]
                                    .filter(Boolean).join(' · ') || t('Sem contactos')}
                            </span>
                        </span>
                        {escolhido.vip && <Etiqueta cor="aviso">{t('VIP')}</Etiqueta>}
                        {escolhido.lista_negra && <Etiqueta cor="perigo">{t('Lista negra')}</Etiqueta>}
                    </span>
                    <button type="button" onClick={() => aoEscolher(null)}
                        className={cls('p-2 text-emerald-500 transition-colors hover:text-emerald-800', RAIO, FOCO)}
                        aria-label={t('Trocar de hóspede')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            ) : (
                <>
                    <div className="flex gap-2">
                        <input type="search" value={procura} className={entrada}
                            placeholder={t('Nome, telefone, email, documento ou NIF…')}
                            onChange={(e) => porProcura(e.target.value)} />
                        {podeCriar && <Botao icone="fa-user-plus" onClick={aoCriar}>{t('Novo')}</Botao>}
                    </div>

                    <div className="mt-3">
                        {achados.isPending ? (
                            <Carregando linhas={3} />
                        ) : (achados.data?.data.length ?? 0) === 0 ? (
                            <p className="py-6 text-center text-sm text-slate-400">
                                {procura ? t('Ninguém com esse nome. Crie a ficha.') : t('Escreva para procurar, ou crie a ficha.')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {(achados.data?.data ?? []).map((h) => (
                                    <li key={h.id}>
                                        <button type="button" onClick={() => aoEscolher(h)}
                                            className={cls('flex w-full items-center gap-3 px-2 py-2.5 text-left transition-colors hover:bg-emerald-50', RAIO, FOCO)}>
                                            <span className="grid h-8 w-8 flex-none place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-xs font-bold text-white" aria-hidden="true">
                                                {h.nome.slice(0, 1).toUpperCase()}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold text-slate-800">{h.nome}</span>
                                                <span className="block truncate text-xs text-slate-400">
                                                    {[h.telefone, h.email, h.documento].filter(Boolean).join(' · ')}
                                                </span>
                                            </span>
                                            {h.vip && <Etiqueta cor="aviso">{t('VIP')}</Etiqueta>}
                                            {h.lista_negra && <Etiqueta cor="perigo">{t('Lista negra')}</Etiqueta>}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </>
            )}

            {escolhido?.lista_negra && (
                <p className={cls('mt-3 border border-red-200 bg-red-50 p-3 text-sm text-red-800', RAIO)}>
                    <i className="fas fa-ban mr-2" aria-hidden="true" />
                    {t('Este hóspede está na lista negra da casa.')}
                </p>
            )}
        </section>
    );
}

function NovoHospede({ valores, aGravar, erro, aoMudar, aoFechar, aoGravar }: {
    valores: HospedeRapido;
    aGravar: boolean;
    erro: unknown;
    aoMudar: (v: HospedeRapido) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const mudar = (campo: keyof HospedeRapido, valor: string) => aoMudar({ ...valores, [campo]: valor });
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Novo Hóspede')}
            icone="fa-user-plus"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {t('Criar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Nome')} obrigatorio erro={daApi?.erros.name} className="sm:col-span-2">
                    <input value={valores.name} className={entrada}
                        onChange={(e) => mudar('name', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Telefone')} erro={daApi?.erros.phone}>
                    <input value={valores.phone} className={entrada}
                        onChange={(e) => mudar('phone', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Email')} erro={daApi?.erros.email}>
                    <input type="email" value={valores.email} className={entrada}
                        onChange={(e) => mudar('email', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Nº do documento')} erro={daApi?.erros.document_number}>
                    <input value={valores.document_number} className={entrada}
                        onChange={(e) => mudar('document_number', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Nacionalidade')} erro={daApi?.erros.nationality}>
                    <input value={valores.nationality} className={entrada}
                        onChange={(e) => mudar('nationality', e.target.value)} />
                </Campo>
                <Campo etiqueta={t('NIF')} erro={daApi?.erros.nif} className="sm:col-span-2"
                    ajuda={t('Único nesta empresa — é a identidade fiscal.')}>
                    <input value={valores.nif} className={entrada}
                        onChange={(e) => mudar('nif', e.target.value)} />
                </Campo>
            </div>
        </Modal>
    );
}

/** O ecrã do fim: a chave está entregue e há um número para dizer em voz alta. */
function Pronto({ entrada: e, aoRecomecar }: { entrada: EntradaRegistada; aoRecomecar: () => void }) {
    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Entrada registada')}
                subtitulo={t('O hóspede pode subir')}
                icone="fa-circle-check"
                cor="bom"
            />

            <div className={cls(CARTAO, 'p-6 text-center')}>
                <span className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-4xl text-white shadow-lg" aria-hidden="true">
                    <i className="fas fa-key" />
                </span>

                <p className="text-sm text-slate-500">{t('Quarto')}</p>
                <p className="text-5xl font-bold tabular-nums text-slate-900">{e.quarto}</p>
                <p className="mt-1 text-sm text-slate-500">{e.tipo_de_quarto}</p>

                <dl className={cls('mx-auto mt-6 max-w-md space-y-1.5 border border-slate-200 p-4 text-left', RAIO)}>
                    <Dado rotulo={t('Reserva')} valor={e.numero} />
                    <Dado rotulo={t('Código')} valor={e.codigo} />
                    <Dado rotulo={t('Hóspede')} valor={e.hospede} />
                    <Dado rotulo={t('Período')}
                        valor={e.entrada && e.saida ? t(':de a :ate', { de: data(e.entrada), ate: data(e.saida) }) : null} />
                    <Dado rotulo={t('Noites')} valor={String(e.noites)} />
                    <div className="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-1.5">
                        <dt className="font-bold text-slate-700">{t('Total')}</dt>
                        <dd className="text-lg font-bold tabular-nums text-slate-900">{kz(e.total)} Kz</dd>
                    </div>
                    <Dado rotulo={t('Pago')} valor={`${kz(e.pago)} Kz`} />
                    {e.por_receber > 0 && (
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <dt className="text-red-600">{t('Por receber')}</dt>
                            <dd className="font-bold tabular-nums text-red-600">{kz(e.por_receber)} Kz</dd>
                        </div>
                    )}
                </dl>

                <div className="mt-6 flex flex-wrap justify-center gap-2">
                    <Botao cor="primaria" tom="solida" icone="fa-person-walking-luggage" onClick={aoRecomecar}>
                        {t('Nova entrada')}
                    </Botao>
                    <a href={`/hotel/reservations/${e.id}/folio`}
                        className={cls('inline-flex items-center gap-2 border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:border-slate-300', RAIO, FOCO)}>
                        <i className="fas fa-list-ul" aria-hidden="true" />
                        {t('Folio')}
                    </a>
                    <a href={`/hotel/reservations/${e.id}/voucher`} target="_blank" rel="noreferrer"
                        className={cls('inline-flex items-center gap-2 border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:border-slate-300', RAIO, FOCO)}>
                        <i className="fas fa-ticket" aria-hidden="true" />
                        {t('Voucher')}
                    </a>
                </div>
            </div>
        </div>
    );
}

function Dado({ rotulo, valor }: { rotulo: string; valor?: string | null }) {
    if (!valor) return null;

    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="font-semibold tabular-nums text-slate-800">{valor}</dd>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o balcão')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
