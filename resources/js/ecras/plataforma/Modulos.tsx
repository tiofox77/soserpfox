import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type FichaDoModulo, type ModuloDaLista, plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { EscolherIcone } from '@/ui/EscolherIcone';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * OS MÓDULOS — o catálogo do que a plataforma sabe fazer.
 *
 * O QUE FALTAVA E AGORA ESTÁ:
 *
 *  · AS DEPENDÊNCIAS têm campo. A lista contava-as («3 dependências») e o
 *    formulário não tinha por onde as pôr — só por `tinker`. E são elas que
 *    levam a Tesouraria atrás da Facturação: um módulo activado sem a sua
 *    dependência dá ecrãs que rebentam à primeira.
 *  · O ÍCONE escolhe-se da galeria, com o desenho à vista. A caixa de texto que
 *    aqui estava pedia `puzzle-piece` de cor, e um nome mal escrito não dá erro
 *    nenhum: dá um quadrado vazio no menu de quem paga.
 *  · O QUE IMPEDE O APAGAR diz-se no cartão, antes de se tentar. E passou a
 *    incluir uma razão que ninguém verificava: OUTRO MÓDULO QUE DEPENDA DESTE —
 *    apagá-lo deixava a dependência a apontar para um slug que já não existe.
 */
export default function Modulos() {
    const fila = useQueryClient();
    const [procura, porProcura] = useState('');
    const [aEditar, porAEditar] = useState<number | null | 'novo'>(null);
    const [aApagar, porAApagar] = useState<ModuloDaLista | null>(null);
    const [recado, porRecado] = useState<string | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'modulos', procura],
        queryFn: () => plataforma.modulos.ler(procura),
        staleTime: 15_000,
    });

    const refrescar = () => void fila.invalidateQueries({ queryKey: ['plataforma', 'modulos'] });

    const alternar = useMutation({
        mutationFn: (id: number) => plataforma.modulos.alternar(id),
        onSuccess: (r) => { porRecado(r.message); refrescar(); },
    });

    const apagar = useMutation({
        mutationFn: (id: number) => plataforma.modulos.apagar(id),
        onSuccess: (r) => { porRecado(r.message); porAApagar(null); refrescar(); },
    });

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os módulos')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const { modulos, numeros, galeria_de_icones, escolhas } = lista.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Módulos')}
                subtitulo={t('O catálogo do que a plataforma sabe fazer')}
                icone="fa-puzzle-piece"
                cor="roxo"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porAEditar('novo')}>
                        <i className="fas fa-plus" aria-hidden="true" />
                        {t('Novo módulo')}
                    </button>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-puzzle-piece">
                        {t(':n módulo(s) · :a activo(s)', { n: numeros.modulos, a: numeros.activos })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-shield-halved">
                        {t(':n de núcleo', { n: numeros.nucleo })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            {recado && (
                <div
                    role="status"
                    className={cls('entra flex items-start justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}
                >
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado(null)} className={cls('text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                        <span className="sr-only">{t('Fechar')}</span>
                    </button>
                </div>
            )}

            <AvisoDeErro erro={alternar.error ?? apagar.error} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero rotulo={t('Módulos')} valor={String(numeros.modulos)} icone="fa-puzzle-piece" tom="roxo" aspecto="claro" />
                <CartaoNumero rotulo={t('Activos')} valor={String(numeros.activos)} icone="fa-circle-check" tom="verde" aspecto="claro" />
                <CartaoNumero rotulo={t('De núcleo')} valor={String(numeros.nucleo)} icone="fa-shield-halved" tom="ambar" aspecto="claro" />
                <CartaoNumero rotulo={t('Planos')} valor={String(numeros.planos)} icone="fa-layer-group" tom="azul" aspecto="claro" />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <label className="block">
                    <Rotulo>{t('Procurar')}</Rotulo>
                    <div className="relative">
                        <i className="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input
                            type="search"
                            className={cls(entrada, 'pl-9')}
                            placeholder={t('Nome, identificador ou descrição…')}
                            value={procura}
                            onChange={(e) => porProcura(e.target.value)}
                        />
                    </div>
                </label>
            </div>

            {modulos.length === 0 ? (
                <SemNada
                    icone="fa-puzzle-piece"
                    titulo={procura ? t('Nenhum módulo encontrado') : t('Ainda não há módulos')}
                    frase={procura
                        ? t('Nenhum módulo com «:p».', { p: procura })
                        : t('Um módulo é uma área da aplicação que um plano pode levar.')}
                />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {modulos.map((m, i) => (
                        <article
                            key={m.id}
                            className={cls(CARTAO, 'card-hover cascata flex flex-col overflow-hidden', !m.activo && 'opacity-70')}
                            style={cascata(i)}
                        >
                            <header className="flex items-start gap-3 bg-gradient-to-br from-purple-500 to-indigo-600 px-5 py-4 text-white">
                                <span className="icon-float grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/20">
                                    <i className={cls('fas text-xl', m.icone)} aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <h3 className="truncate text-lg font-extrabold">{m.nome}</h3>
                                    <code className="text-xs text-white/80">{m.slug}</code>
                                </div>
                                <span className="shrink-0 rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-bold">
                                    v{m.versao}
                                </span>
                            </header>

                            <div className="flex-1 space-y-3 p-5">
                                {m.descricao && <p className="text-sm text-slate-600">{m.descricao}</p>}

                                <dl className="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div className="rounded-lg bg-slate-50 px-2 py-1.5">
                                        <dt className="text-[10px] font-bold uppercase text-slate-400">{t('Empresas')}</dt>
                                        <dd className="text-base font-bold text-slate-800">{m.empresas}</dd>
                                    </div>
                                    <div className="rounded-lg bg-slate-50 px-2 py-1.5">
                                        <dt className="text-[10px] font-bold uppercase text-slate-400">{t('Planos')}</dt>
                                        <dd className="text-base font-bold text-slate-800">{m.planos}</dd>
                                    </div>
                                    <div className="rounded-lg bg-slate-50 px-2 py-1.5">
                                        <dt className="text-[10px] font-bold uppercase text-slate-400">{t('Preço')}</dt>
                                        <dd className="text-base font-bold text-slate-800">{kz(m.preco, 0)}</dd>
                                    </div>
                                </dl>

                                {/* AS DEPENDÊNCIAS, com o nome de cada uma e não
                                    só a contagem. */}
                                {m.dependencias.length > 0 && (
                                    <div>
                                        <p className="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                            {t('Precisa de')}
                                        </p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {m.dependencias.map((dep) => (
                                                <span key={dep.slug} className="rounded-lg bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                                                    <i className="fas fa-link mr-1" aria-hidden="true" />{dep.nome}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-1.5 border-t border-slate-100 pt-3">
                                    <Etiqueta cor={m.activo ? 'bom' : 'neutra'} ponto>
                                        {m.activo ? t('Activo') : t('Desligado')}
                                    </Etiqueta>
                                    {m.nucleo && <Etiqueta cor="aviso" icone="fa-shield-halved">{t('Núcleo')}</Etiqueta>}
                                    <Etiqueta cor="neutra" icone="fa-arrow-down-1-9">{t('ordem :n', { n: m.ordem })}</Etiqueta>
                                </div>

                                {/* PORQUE NÃO SE APAGA, dito antes de tentar. */}
                                {m.porque_nao_apaga && (
                                    <p className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-600">
                                        <i className="fas fa-lock mr-1.5 text-slate-400" aria-hidden="true" />
                                        {m.porque_nao_apaga}
                                    </p>
                                )}
                            </div>

                            <footer className="flex gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                                <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-pen" onClick={() => porAEditar(m.id)} className="flex-1">
                                    {t('Editar')}
                                </Botao>
                                <Botao
                                    cor={m.activo ? 'aviso' : 'bom'}
                                    tom="suave"
                                    altura="pequeno"
                                    icone={m.activo ? 'fa-pause' : 'fa-play'}
                                    aTrabalhar={alternar.isPending}
                                    onClick={() => alternar.mutate(m.id)}
                                >
                                    {m.activo ? t('Desligar') : t('Ligar')}
                                </Botao>
                                <Botao
                                    cor="perigo"
                                    tom="suave"
                                    altura="pequeno"
                                    icone="fa-trash"
                                    disabled={!m.pode_apagar}
                                    title={m.porque_nao_apaga ?? t('Apagar o módulo')}
                                    onClick={() => porAApagar(m)}
                                >
                                    <span className="sr-only">{t('Apagar')}</span>
                                </Botao>
                            </footer>
                        </article>
                    ))}
                </div>
            )}

            {aEditar !== null && (
                <Formulario
                    id={aEditar === 'novo' ? null : aEditar}
                    galeria={galeria_de_icones}
                    escolhas={escolhas}
                    aoFechar={() => porAEditar(null)}
                    aoGuardar={(m) => { porRecado(m); porAEditar(null); refrescar(); }}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar o módulo?')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAApagar(null)}>{t('Deixar estar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}
                        >
                            {t('Apagar')}
                        </Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('O módulo deixa de existir no catálogo. Os ecrãs dele continuam no código, mas nenhum plano os pode oferecer.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── O formulário ────────────────────────────────────────────────────── */

const VAZIO: FichaDoModulo = {
    id: 0, name: '', slug: '', description: '',
    icon: 'fa-puzzle-piece', version: '1.0.0', order: 0,
    is_active: true, is_core: false, default_price: 0,
    dependencies: [],
};

function Formulario({ id, galeria, escolhas, aoFechar, aoGuardar }: {
    id: number | null;
    galeria: Parameters<typeof EscolherIcone>[0]['galeria'];
    escolhas: Array<{ valor: string; rotulo: string }>;
    aoFechar: () => void;
    aoGuardar: (recado: string) => void;
}) {
    const [f, porF] = useState<FichaDoModulo>(VAZIO);

    const ficha = useQuery({
        queryKey: ['plataforma', 'modulos', 'ficha', id],
        queryFn: () => plataforma.modulos.ficha(id!),
        enabled: id !== null,
    });

    useEffect(() => {
        if (ficha.data) porF(ficha.data.ficha);
    }, [ficha.data]);

    const guardar = useMutation({
        mutationFn: () => plataforma.modulos.guardar(id, {
            name: f.name,
            slug: f.slug,
            description: f.description,
            icon: f.icon,
            version: f.version,
            order: f.order,
            default_price: f.default_price,
            is_active: f.is_active,
            is_core: f.is_core,
            dependencies: f.dependencies,
        }),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const mexer = <K extends keyof FichaDoModulo>(campo: K, valor: FichaDoModulo[K]) =>
        porF((a) => ({ ...a, [campo]: valor }));

    // UM MÓDULO NÃO DEPENDE DE SI MESMO: nem se oferece na lista.
    const candidatos = escolhas.filter((e) => e.valor !== f.slug);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar módulo') : t('Novo módulo')}
            subtitulo={t('O nome, o ícone, o preço sugerido e de que outros módulos depende')}
            icone="fa-puzzle-piece"
            cor="roxo"
            largura="lg"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone="fa-floppy-disk"
                        aTrabalhar={guardar.isPending}
                        onClick={() => guardar.mutate()}
                    >
                        {t('Guardar')}
                    </Botao>
                </div>
            }
        >
            {ficha.isPending && id !== null ? (
                <Carregando linhas={7} />
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={guardar.error} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input className={entrada} value={f.name} onChange={(e) => mexer('name', e.target.value)} />
                        </Campo>
                        <Campo
                            etiqueta={t('Identificador')}
                            obrigatorio
                            erro={erros.slug}
                            ajuda={t('Minúsculas e hífens. É por ele que o código pergunta se o módulo está activo.')}
                        >
                            <input className={entrada} value={f.slug} onChange={(e) => mexer('slug', e.target.value)} />
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Descrição')} obrigatorio erro={erros.description}>
                        <textarea
                            rows={2}
                            className={cls(entrada, 'h-auto py-2')}
                            value={f.description}
                            onChange={(e) => mexer('description', e.target.value)}
                        />
                    </Campo>

                    <Campo
                        etiqueta={t('Ícone')}
                        obrigatorio
                        erro={erros.icon}
                        ajuda={t('É o desenho que a empresa vê no menu.')}
                    >
                        <EscolherIcone
                            valor={f.icon}
                            aoMudar={(v) => mexer('icon', v)}
                            etiqueta={t('Ícone do módulo')}
                            galeria={galeria}
                        />
                    </Campo>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta={t('Versão')} obrigatorio erro={erros.version}>
                            <input className={entrada} value={f.version} onChange={(e) => mexer('version', e.target.value)} />
                        </Campo>
                        <Campo
                            etiqueta={t('Preço sugerido (Kz/mês)')}
                            erro={erros.default_price}
                            ajuda={t('Serve para somar um plano à medida.')}
                        >
                            <input
                                type="number" min="0" step="100" className={entrada}
                                value={f.default_price}
                                onChange={(e) => mexer('default_price', Number(e.target.value))}
                            />
                        </Campo>
                        <Campo etiqueta={t('Ordem')} obrigatorio erro={erros.order}>
                            <input
                                type="number" min="0" className={entrada}
                                value={f.order}
                                onChange={(e) => mexer('order', Number(e.target.value))}
                            />
                        </Campo>
                    </div>

                    {/* AS DEPENDÊNCIAS. É este campo que nunca existiu. */}
                    <Campo
                        etiqueta={t('Precisa de')}
                        erro={erros.dependencies}
                        ajuda={t('Activar este módulo numa empresa leva estes atrás. É assim que a Tesouraria vai com a Facturação.')}
                    >
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {candidatos.map((c) => (
                                <label
                                    key={c.valor}
                                    className={cls(
                                        'flex cursor-pointer items-center gap-2 border px-3 py-2 text-sm', RAIO, TRANSICAO,
                                        f.dependencies.includes(c.valor)
                                            ? 'border-amber-300 bg-amber-50'
                                            : 'border-slate-200 hover:bg-slate-50',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-slate-300 text-amber-600"
                                        checked={f.dependencies.includes(c.valor)}
                                        onChange={() => porF((a) => ({
                                            ...a,
                                            dependencies: a.dependencies.includes(c.valor)
                                                ? a.dependencies.filter((d) => d !== c.valor)
                                                : [...a.dependencies, c.valor],
                                        }))}
                                    />
                                    <span className="min-w-0 flex-1 truncate text-slate-800">{c.rotulo}</span>
                                </label>
                            ))}
                            {candidatos.length === 0 && (
                                <p className="text-sm text-slate-400">{t('Não há outros módulos.')}</p>
                            )}
                        </div>
                    </Campo>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <label className={cls(
                            'flex cursor-pointer items-start gap-2.5 border px-3 py-2.5', RAIO, TRANSICAO,
                            f.is_active ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200 hover:bg-slate-50',
                        )}>
                            <input
                                type="checkbox"
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                                checked={f.is_active}
                                onChange={(e) => mexer('is_active', e.target.checked)}
                            />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800">{t('Activo')}</span>
                                <span className="block text-[11px] text-slate-500">{t('Desligado, não entra em planos novos.')}</span>
                            </span>
                        </label>

                        <label className={cls(
                            'flex cursor-pointer items-start gap-2.5 border px-3 py-2.5', RAIO, TRANSICAO,
                            f.is_core ? 'border-amber-300 bg-amber-50/60' : 'border-slate-200 hover:bg-slate-50',
                        )}>
                            <input
                                type="checkbox"
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-600"
                                checked={f.is_core}
                                onChange={(e) => mexer('is_core', e.target.checked)}
                            />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800">{t('De núcleo')}</span>
                                <span className="block text-[11px] text-slate-500">{t('Vai em todos os planos e não se apaga.')}</span>
                            </span>
                        </label>
                    </div>
                </div>
            )}
        </Modal>
    );
}
