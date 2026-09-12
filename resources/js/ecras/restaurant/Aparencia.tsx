import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type AparenciaDaCarta } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * A APARÊNCIA DA CARTA — o que a faz parecer a casa.
 *
 * A PRÉ-VISUALIZAÇÃO É A CARTA VERDADEIRA num iframe, e não uma imitação: uma
 * imitação diverge do original ao primeiro retoque, e o dono acabaria a
 * decidir por uma coisa que não é a que o cliente vai ver.
 *
 * SÓ HÁ PRÉ-VISUALIZAÇÃO COM A CARTA PUBLICADA — um iframe para um endereço
 * desligado mostrava a página de recusa e parecia avaria.
 *
 * OS DESTAQUES TÊM TECTO. Uma fila longa deixa de destacar o que quer que seja.
 */

export default function Aparencia() {
    const cache = useQueryClient();

    const [forma, porForma] = useState<AparenciaDaCarta['aparencia'] | null>(null);
    const [procura, porProcura] = useState('');
    const [atrasada, porAtrasada] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useState('');
    const [versao, porVersao] = useState(0);
    const [aRemover, porARemover] = useState<'capa' | 'logo' | null>(null);

    const capaRef = useRef<HTMLInputElement>(null);
    const logoRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const id = setTimeout(() => porAtrasada(procura), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const dados = useQuery({ queryKey: ['restaurante', 'aparencia'], queryFn: restaurante.aparencia.ler });

    useEffect(() => { if (dados.data) porForma(dados.data.aparencia); }, [dados.data]);

    const candidatos = useQuery({
        queryKey: ['restaurante', 'aparencia', 'candidatos', atrasada],
        queryFn: () => restaurante.aparencia.candidatos(atrasada),
        enabled: atrasada.trim().length >= 2,
    });

    const refrescar = () => {
        void cache.invalidateQueries({ queryKey: ['restaurante', 'aparencia'] });
        // O iframe tem de voltar a pedir a página: sem isto ficava a mostrar a
        // capa antiga, e parecia que a gravação não fez nada.
        porVersao((v) => v + 1);
    };

    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };
    const feito = (r: { message: string }) => { porErros({}); porErro(null); porRecado(r.message); refrescar(); };

    const guardar = useMutation({
        mutationFn: () => restaurante.aparencia.guardar(forma!),
        onSuccess: feito, onError: falhou,
    });

    const enviarImagem = useMutation({
        mutationFn: ({ qual, ficheiro }: { qual: 'capa' | 'logo'; ficheiro: File }) =>
            restaurante.aparencia.imagem(qual, ficheiro),
        onSuccess: feito, onError: falhou,
    });

    const removerImagem = useMutation({
        mutationFn: (qual: 'capa' | 'logo') => restaurante.aparencia.removerImagem(qual),
        onSuccess: (r) => { porARemover(null); feito(r); }, onError: falhou,
    });

    const destacar = useMutation({
        mutationFn: (id: number) => restaurante.aparencia.destacar(id),
        onSuccess: (r) => { porProcura(''); feito(r); }, onError: falhou,
    });

    const mover = useMutation({
        mutationFn: ({ id, sentido }: { id: number; sentido: 'cima' | 'baixo' }) => restaurante.aparencia.mover(id, sentido),
        onSuccess: () => refrescar(), onError: falhou,
    });

    const retirar = useMutation({
        mutationFn: (id: number) => restaurante.aparencia.retirar(id),
        onSuccess: feito, onError: falhou,
    });

    if (dados.isPending || !forma) return <Carregando linhas={10} />;
    if (dados.isError) return <AvisoDeErro erro={dados.error} />;

    const d = dados.data;
    const podeEditar = d.permissoes.pode_editar;
    const cheio = d.destaques.length >= d.maximo_de_destaques;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Aparência da Carta')}
                subtitulo={t('A capa, as cores e os pratos em destaque')}
                icone="fa-palette"
                cor="rosa"
                accoes={
                    <>
                        <a href="/restaurant/settings" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-sliders" aria-hidden="true" />
                            {t('Definições')}
                        </a>
                        {d.url_da_carta && (
                            <a href={d.url_da_carta} target="_blank" rel="noopener" className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-arrow-up-right-from-square" aria-hidden="true" />
                                {t('Abrir a carta')}
                            </a>
                        )}
                    </>
                }
            />

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_26rem]">
                <div className="space-y-5">
                    <Cartao titulo={t('Imagens')} icone="fa-image" subtitulo={t('A capa é o que se vê primeiro; o logótipo fica por cima dela')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <ZonaDeImagem
                                rotulo={t('Capa')}
                                url={d.capa}
                                proporcao="aspect-[16/9]"
                                nota={t('Até 4 MB')}
                                podeEditar={podeEditar}
                                aTrabalhar={enviarImagem.isPending && enviarImagem.variables?.qual === 'capa'}
                                referencia={capaRef}
                                aoEscolher={(f) => enviarImagem.mutate({ qual: 'capa', ficheiro: f })}
                                aoRemover={() => porARemover('capa')}
                            />

                            <ZonaDeImagem
                                rotulo={t('Logótipo')}
                                url={d.logo}
                                proporcao="aspect-square"
                                nota={t('Até 2 MB, quadrado')}
                                podeEditar={podeEditar}
                                aTrabalhar={enviarImagem.isPending && enviarImagem.variables?.qual === 'logo'}
                                referencia={logoRef}
                                aoEscolher={(f) => enviarImagem.mutate({ qual: 'logo', ficheiro: f })}
                                aoRemover={() => porARemover('logo')}
                            />
                        </div>
                    </Cartao>

                    <Cartao titulo={t('Texto e cores')} icone="fa-font">
                        <div className="space-y-4">
                            <Campo etiqueta={t('Título da carta')} erro={erros.menu_title}>
                                <input
                                    value={forma.menu_title}
                                    disabled={!podeEditar}
                                    onChange={(e) => porForma({ ...forma, menu_title: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Descrição')} erro={erros.menu_description}>
                                <textarea
                                    value={forma.menu_description}
                                    disabled={!podeEditar}
                                    onChange={(e) => porForma({ ...forma, menu_description: e.target.value })}
                                    rows={3}
                                    className={cls(entrada, 'h-auto py-2')}
                                />
                            </Campo>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Campo etiqueta={t('Cor principal')} erro={erros.menu_primary_color}>
                                    <EscolherCor
                                        valor={forma.menu_primary_color}
                                        desactivado={!podeEditar}
                                        aoMudar={(v) => porForma({ ...forma, menu_primary_color: v })}
                                        etiqueta={t('Cor principal')}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Cor de acento')} erro={erros.menu_accent_color}>
                                    <EscolherCor
                                        valor={forma.menu_accent_color}
                                        desactivado={!podeEditar}
                                        aoMudar={(v) => porForma({ ...forma, menu_accent_color: v })}
                                        etiqueta={t('Cor de acento')}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Tema')} erro={erros.menu_theme}>
                                    <select
                                        value={forma.menu_theme}
                                        disabled={!podeEditar}
                                        onChange={(e) => porForma({ ...forma, menu_theme: e.target.value })}
                                        className={entrada}
                                    >
                                        {d.temas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                    </select>
                                </Campo>
                            </div>

                            <Campo
                                etiqueta={t('Título dos destaques')}
                                erro={erros.menu_destaques_titulo}
                                ajuda={t('«Sugestões do chefe», «Os nossos clássicos»…')}
                            >
                                <input
                                    value={forma.menu_destaques_titulo}
                                    disabled={!podeEditar}
                                    onChange={(e) => porForma({ ...forma, menu_destaques_titulo: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={forma.menu_show_prices}
                                    disabled={!podeEditar}
                                    onChange={(e) => porForma({ ...forma, menu_show_prices: e.target.checked })}
                                    className="h-4 w-4 rounded border-slate-300 text-pink-600 focus:ring-pink-500"
                                />
                                {t('Mostrar preços na carta')}
                            </label>

                            {podeEditar && (
                                <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                                    {t('Guardar aparência')}
                                </Botao>
                            )}
                        </div>
                    </Cartao>

                    <Cartao
                        titulo={t('Pratos em destaque')}
                        subtitulo={t('Até :n — uma fila longa deixa de destacar seja o que for', { n: String(d.maximo_de_destaques) })}
                        icone="fa-star"
                    >
                        <div className="space-y-4">
                            {d.destaques.length === 0 ? (
                                <SemNada icone="fa-star" frase={t('Ainda não há destaques.')} />
                            ) : (
                                <ul className="space-y-2">
                                    {d.destaques.map((x, i) => (
                                        <li key={x.id} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                            <span className="grid h-8 w-8 flex-none place-items-center rounded-lg bg-pink-50 text-sm font-bold text-pink-600">
                                                {i + 1}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold text-slate-800">{x.nome}</p>
                                                <p className="text-xs tabular-nums text-slate-500">{kz(x.preco)} Kz</p>
                                            </div>

                                            {podeEditar && (
                                                <div className="flex flex-none gap-1">
                                                    <Botao
                                                        altura="pequeno" icone="fa-arrow-up"
                                                        disabled={i === 0}
                                                        onClick={() => mover.mutate({ id: x.id, sentido: 'cima' })}
                                                        aria-label={t('Subir')}
                                                    />
                                                    <Botao
                                                        altura="pequeno" icone="fa-arrow-down"
                                                        disabled={i === d.destaques.length - 1}
                                                        onClick={() => mover.mutate({ id: x.id, sentido: 'baixo' })}
                                                        aria-label={t('Descer')}
                                                    />
                                                    <Botao
                                                        altura="pequeno" cor="perigo" icone="fa-xmark"
                                                        onClick={() => retirar.mutate(x.id)}
                                                        aria-label={t('Retirar destaque')}
                                                    />
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {podeEditar && !cheio && (
                                <div>
                                    <Campo etiqueta={t('Procurar prato para destacar')} ajuda={t('Escreva pelo menos duas letras.')}>
                                        <div className="relative">
                                            <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                            <input
                                                type="search"
                                                value={procura}
                                                onChange={(e) => porProcura(e.target.value)}
                                                className={cls(entrada, 'pl-9')}
                                            />
                                        </div>
                                    </Campo>

                                    {(candidatos.data?.data.length ?? 0) > 0 && (
                                        <ul className="mt-2 space-y-1">
                                            {candidatos.data?.data.map((c) => (
                                                <li key={c.id}>
                                                    <button
                                                        type="button"
                                                        onClick={() => destacar.mutate(c.id)}
                                                        className={cls(
                                                            'flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2 text-left text-sm hover:bg-pink-50',
                                                            FOCO,
                                                        )}
                                                    >
                                                        <span className="truncate font-medium text-slate-800">{c.nome}</span>
                                                        <span className="flex-none tabular-nums text-slate-500">{kz(c.preco)} Kz</span>
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            )}

                            {cheio && (
                                <p className={cls('border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900', RAIO)}>
                                    <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                                    {t('Já tem :n destaques. Retire um antes de acrescentar outro.', { n: String(d.maximo_de_destaques) })}
                                </p>
                            )}
                        </div>
                    </Cartao>
                </div>

                <div className="xl:sticky xl:top-4 xl:self-start">
                    <Cartao titulo={t('Como o cliente vê')} icone="fa-mobile-screen" semPadding>
                        {d.url_da_carta ? (
                            <div className="p-4">
                                <div className="mx-auto max-w-[22rem] overflow-hidden rounded-[2rem] border-8 border-slate-800 bg-slate-800 shadow-xl">
                                    <iframe
                                        key={versao}
                                        src={`${d.url_da_carta}?v=${versao}`}
                                        title={t('Pré-visualização da carta')}
                                        className="h-[36rem] w-full bg-white"
                                    />
                                </div>
                                <p className="mt-3 text-center text-xs text-slate-500">
                                    {t('É a carta verdadeira, não uma imitação.')}
                                </p>
                            </div>
                        ) : (
                            <SemNada
                                icone="fa-eye-slash"
                                titulo={t('A carta não está publicada')}
                                frase={t('Publique-a nas definições para ver aqui como fica.')}
                                accao={
                                    <a
                                        href="/restaurant/settings"
                                        className={cls('inline-flex h-10 items-center gap-2 rounded-xl bg-slate-100 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-200', FOCO)}
                                    >
                                        <i className="fas fa-sliders" aria-hidden="true" />
                                        {t('Ir às definições')}
                                    </a>
                                }
                            />
                        )}
                    </Cartao>
                </div>
            </div>

            <Modal
                aberto={aRemover !== null}
                aoFechar={() => porARemover(null)}
                titulo={aRemover === 'logo' ? t('Remover logótipo') : t('Remover capa')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porARemover(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-trash"
                            aTrabalhar={removerImagem.isPending}
                            onClick={() => aRemover && removerImagem.mutate(aRemover)}
                        >
                            {t('Remover')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">{t('O ficheiro é apagado do disco e a carta fica sem ele.')}</p>
            </Modal>
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
    rotulo, url, proporcao, nota, podeEditar, aTrabalhar, referencia, aoEscolher, aoRemover,
}: {
    rotulo: string;
    url: string | null;
    proporcao: string;
    nota: string;
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
                    <span className="text-xs text-slate-400">{nota}</span>
                </div>
            )}
        </div>
    );
}
