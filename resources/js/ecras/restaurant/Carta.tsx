import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type CategoriaDaCarta, type Prato } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { EscolherIcone } from '@/ui/EscolherIcone';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * MONTAR A CARTA.
 *
 * TUDO EM LINHA: escreve-se o nome e o preço, Enter, próximo. O preço muda-se
 * a tocar-lhe. A categoria arrasta-se pelo selector. Nada de páginas de
 * formulário para uma carta que muda todas as semanas — era o que o ecrã dos
 * produtos da Facturação obrigava, com quarenta campos que um prato não usa.
 *
 * AS CATEGORIAS ESTÃO AQUI AO LADO e não noutro ecrã: a ordem delas É a ordem
 * da carta, no balcão e na carta online, e decide-se a olhar para os pratos.
 *
 * NUNCA SE APAGA UM PRATO: um prato já vendido está em facturas. Esconde-se —
 * faz o mesmo serviço sem deixar documentos fiscais a apontar para o vazio.
 */

const categoriaVazia = () => ({
    name: '', description: '', icon: 'fa-utensils', color: '#EA580C', is_active: true,
});

export default function Carta({ separador }: { separador?: string }) {
    const cache = useQueryClient();

    const [aba, porAba] = useState(separador === 'categorias' ? 'categorias' : 'pratos');
    const [categoria, porCategoria] = useState<number | ''>('');
    const [procura, porProcura] = useState('');
    const [atrasada, porAtrasada] = useState('');
    const [soFora, porSoFora] = useState(false);
    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const [novoNome, porNovoNome] = useState('');
    const [novoPreco, porNovoPreco] = useState('');

    const [aEditar, porAEditar] = useState<{ id: number | null; dados: ReturnType<typeof categoriaVazia> } | null>(null);
    const [aApagar, porAApagar] = useState<CategoriaDaCarta | null>(null);

    useEffect(() => {
        const id = setTimeout(() => porAtrasada(procura), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const opcoes = useQuery({
        queryKey: ['restaurante', 'carta', 'opcoes'],
        queryFn: restaurante.carta.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['restaurante', 'carta', 'lista', categoria, atrasada, soFora],
        queryFn: () => restaurante.carta.lista({ categoria, procura: atrasada, so_fora: soFora }),
        placeholderData: keepPreviousData,
    });

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['restaurante', 'carta'] });

    const falhou = (e: unknown) => {
        porErro(e);
        porErros(e instanceof ErroDaApi ? e.erros : {});
    };

    const criarPrato = useMutation({
        mutationFn: () => restaurante.carta.criarPrato({
            name: novoNome, price: Number(novoPreco),
            category_id: categoria === '' ? null : categoria,
        }),
        onSuccess: (r) => {
            porNovoNome(''); porNovoPreco(''); porErros({}); porErro(null);
            porRecado(r.message); refrescar();
        },
        onError: falhou,
    });

    const mudarPreco = useMutation({
        mutationFn: ({ id, valor }: { id: number; valor: string }) => restaurante.carta.preco(id, valor),
        onSuccess: () => { porErro(null); refrescar(); },
        onError: falhou,
    });

    const mudarNome = useMutation({
        mutationFn: ({ id, valor }: { id: number; valor: string }) => restaurante.carta.nome(id, valor),
        onSuccess: () => { porErro(null); refrescar(); },
        onError: falhou,
    });

    const mudarCategoria = useMutation({
        mutationFn: ({ id, valor }: { id: number; valor: number | null }) => restaurante.carta.categoriaDoPrato(id, valor),
        onSuccess: () => { porErro(null); refrescar(); },
        onError: falhou,
    });

    const disponibilidade = useMutation({
        mutationFn: (id: number) => restaurante.carta.disponibilidade(id),
        onSuccess: () => { porErro(null); refrescar(); },
        onError: falhou,
    });

    const guardarCategoria = useMutation({
        mutationFn: () => restaurante.carta.guardarCategoria(aEditar!.id, aEditar!.dados),
        onSuccess: (r) => { porAEditar(null); porErros({}); porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const alternarCategoria = useMutation({
        mutationFn: (id: number) => restaurante.carta.alternarCategoria(id),
        onSuccess: () => { porErro(null); refrescar(); },
        onError: falhou,
    });

    const moverCategoria = useMutation({
        mutationFn: ({ id, direccao }: { id: number; direccao: 'cima' | 'baixo' }) =>
            restaurante.carta.moverCategoria(id, direccao),
        onSuccess: () => refrescar(),
        onError: falhou,
    });

    const apagarCategoria = useMutation({
        mutationFn: (id: number) => restaurante.carta.apagarCategoria(id),
        onSuccess: (r) => { porAApagar(null); porErro(null); porRecado(r.message); refrescar(); },
        onError: (e) => { porAApagar(null); falhou(e); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const categorias = lista.data?.categorias ?? o.categorias;
    const pratos = lista.data?.data ?? [];
    const semFicha = pratos.filter((p) => p.falta_ficha).length;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('A Carta')}
                subtitulo={t('Os pratos e as categorias, no mesmo sítio')}
                icone="fa-book-open"
                cor="laranja"
                accoes={
                    <>
                        <a href="/restaurant/carta/aparencia" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-palette" aria-hidden="true" />
                            {t('Aparência')}
                        </a>
                        <a href="/restaurant/recipes" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-clipboard-list" aria-hidden="true" />
                            {t('Fichas técnicas')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-utensils">
                        {t(':n pratos', { n: String(pratos.length) })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-layer-group">
                        {t(':n categorias', { n: String(categorias.length) })}
                    </EstadoNaFaixa>
                    {semFicha > 0 && (
                        <EstadoNaFaixa icone="fa-triangle-exclamation">
                            {t(':n sem ficha técnica — não vendem', { n: String(semFicha) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

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
                    { chave: 'pratos', rotulo: t('Pratos'), icone: 'fa-utensils' },
                    { chave: 'categorias', rotulo: t('Categorias'), icone: 'fa-layer-group' },
                ]}
            />

            <PainelDoSeparador chave="pratos" activa={aba}>
                <div className="space-y-4">
                    {o.permissoes.pode_gerir && (
                        <Cartao titulo={t('Prato novo')} icone="fa-plus" subtitulo={t('Nome, preço, Enter — e o próximo')}>
                            <form
                                className="flex flex-wrap items-end gap-3"
                                onSubmit={(e) => { e.preventDefault(); criarPrato.mutate(); }}
                            >
                                <Campo etiqueta={t('Nome do prato')} obrigatorio erro={erros.name} className="min-w-[14rem] flex-1">
                                    <input
                                        value={novoNome}
                                        onChange={(e) => porNovoNome(e.target.value)}
                                        className={entrada}
                                        placeholder={t('Bitoque à casa')}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Preço')} obrigatorio erro={erros.price} className="w-36">
                                    <input
                                        type="number" step="0.01" min="0"
                                        value={novoPreco}
                                        onChange={(e) => porNovoPreco(e.target.value)}
                                        className={cls(entrada, 'text-right tabular-nums')}
                                    />
                                </Campo>

                                <Botao
                                    type="submit" cor="primaria" tom="solida" icone="fa-plus"
                                    aTrabalhar={criarPrato.isPending}
                                    disabled={!novoNome.trim() || novoPreco === ''}
                                >
                                    {t('Pôr no menu')}
                                </Botao>

                                {categoria !== '' && (
                                    <p className="w-full text-xs text-slate-500">
                                        {t('Vai para :categoria — a categoria filtrada.', {
                                            categoria: categorias.find((c) => c.id === categoria)?.nome ?? '',
                                        })}
                                    </p>
                                )}
                            </form>
                        </Cartao>
                    )}

                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[12rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    type="search"
                                    value={procura}
                                    onChange={(e) => porProcura(e.target.value)}
                                    placeholder={t('Nome ou código…')}
                                    className={cls(entrada, 'pl-9')}
                                />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Categoria')} className="w-52">
                            <select
                                value={categoria}
                                onChange={(e) => porCategoria(e.target.value ? Number(e.target.value) : '')}
                                className={entrada}
                            >
                                <option value="">{t('Todas')}</option>
                                {categorias.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
                            </select>
                        </Campo>

                        <label className="flex h-10 cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={soFora}
                                onChange={(e) => porSoFora(e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            />
                            {t('Só os que estão fora do menu')}
                        </label>
                    </div>

                    <div className={cls(CARTAO, 'overflow-hidden')}>
                        {lista.isPending ? (
                            <div className="p-5"><Carregando linhas={6} /></div>
                        ) : pratos.length === 0 ? (
                            <SemNada
                                icone="fa-utensils"
                                titulo={t('A carta está vazia')}
                                frase={t('Escreva o nome e o preço do primeiro prato aqui em cima.')}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[44rem] text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50/60 text-left">
                                            <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Prato')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Categoria')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Preço')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{t('No menu')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {pratos.map((p, i) => (
                                            <LinhaDoPrato
                                                key={p.id}
                                                prato={p}
                                                indice={i}
                                                categorias={categorias}
                                                podeGerir={o.permissoes.pode_gerir}
                                                aoNome={(valor) => mudarNome.mutate({ id: p.id, valor })}
                                                aoPreco={(valor) => mudarPreco.mutate({ id: p.id, valor })}
                                                aoCategoria={(valor) => mudarCategoria.mutate({ id: p.id, valor })}
                                                aoAlternar={() => disponibilidade.mutate(p.id)}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="categorias" activa={aba}>
                <Cartao
                    titulo={t('Categorias')}
                    subtitulo={t('A ordem daqui é a ordem da carta, no balcão e na página pública')}
                    icone="fa-layer-group"
                    semPadding
                    accoes={o.permissoes.pode_gerir && (
                        <Botao
                            altura="pequeno" cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => { porErros({}); porAEditar({ id: null, dados: categoriaVazia() }); }}
                        >
                            {t('Nova')}
                        </Botao>
                    )}
                >
                    {categorias.length === 0 ? (
                        <SemNada icone="fa-layer-group" frase={t('Ainda não há categorias.')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {categorias.map((c, i) => (
                                <li key={c.id} style={cascata(i)} className="entra flex items-center gap-3 px-4 py-3">
                                    <span
                                        className="grid h-10 w-10 flex-none place-items-center rounded-xl text-white shadow-sm"
                                        style={{ backgroundColor: c.cor }}
                                    >
                                        <i className={`fas ${c.icone}`} aria-hidden="true" />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {c.nome}
                                            {c.da_casa && (
                                                <span className="ml-2 text-xs font-normal text-slate-400">{t('(da casa)')}</span>
                                            )}
                                        </p>
                                        <p className="truncate text-xs text-slate-500">
                                            {t(':n pratos', { n: String(c.pratos) })}
                                            {c.descricao && ` · ${c.descricao}`}
                                        </p>
                                    </div>

                                    <Etiqueta cor={c.activa ? 'bom' : 'neutra'} ponto>
                                        {c.activa ? t('Activa') : t('Desligada')}
                                    </Etiqueta>

                                    {o.permissoes.pode_gerir && (
                                        <div className="flex flex-none gap-1">
                                            <Botao
                                                altura="pequeno" icone="fa-arrow-up"
                                                disabled={i === 0}
                                                onClick={() => moverCategoria.mutate({ id: c.id, direccao: 'cima' })}
                                                aria-label={t('Subir')}
                                            />
                                            <Botao
                                                altura="pequeno" icone="fa-arrow-down"
                                                disabled={i === categorias.length - 1}
                                                onClick={() => moverCategoria.mutate({ id: c.id, direccao: 'baixo' })}
                                                aria-label={t('Descer')}
                                            />
                                            <Botao
                                                altura="pequeno" icone="fa-pen"
                                                onClick={() => {
                                                    porErros({});
                                                    porAEditar({
                                                        id: c.id,
                                                        dados: {
                                                            name: c.nome, description: c.descricao ?? '',
                                                            icon: c.icone, color: c.cor, is_active: c.activa,
                                                        },
                                                    });
                                                }}
                                                aria-label={t('Editar')}
                                            />
                                            <Botao
                                                altura="pequeno"
                                                cor={c.activa ? 'aviso' : 'bom'}
                                                icone={c.activa ? 'fa-eye-slash' : 'fa-eye'}
                                                disabled={c.da_casa && c.activa}
                                                onClick={() => alternarCategoria.mutate(c.id)}
                                                aria-label={c.activa ? t('Desligar') : t('Ligar')}
                                            />
                                            <Botao
                                                altura="pequeno" cor="perigo" icone="fa-trash"
                                                disabled={c.da_casa || c.pratos > 0}
                                                onClick={() => porAApagar(c)}
                                                aria-label={t('Eliminar')}
                                            />
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </PainelDoSeparador>

            {/* ─── Categoria ─── */}
            <Modal
                aberto={aEditar !== null}
                aoFechar={() => porAEditar(null)}
                titulo={aEditar?.id ? t('Editar categoria') : t('Nova categoria')}
                subtitulo={t('Aparece no balcão, na Facturação e na carta online')}
                icone={aEditar?.id ? 'fa-pen' : 'fa-plus'}
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAEditar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check"
                            aTrabalhar={guardarCategoria.isPending}
                            onClick={() => guardarCategoria.mutate()}
                        >
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {aEditar && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input
                                value={aEditar.dados.name}
                                onChange={(e) => porAEditar({ ...aEditar, dados: { ...aEditar.dados, name: e.target.value } })}
                                className={entrada}
                                autoFocus
                            />
                        </Campo>

                        <Campo etiqueta={t('Descrição')} erro={erros.description}>
                            <textarea
                                value={aEditar.dados.description}
                                onChange={(e) => porAEditar({ ...aEditar, dados: { ...aEditar.dados, description: e.target.value } })}
                                rows={2}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Ícone')} obrigatorio erro={erros.icon}>
                                <EscolherIcone
                                    valor={aEditar.dados.icon}
                                    aoMudar={(v) => porAEditar({ ...aEditar, dados: { ...aEditar.dados, icon: v } })}
                                    etiqueta={t('Ícone')}
                                    galeria={o.galeria_de_icones ?? []}
                                />
                            </Campo>

                            <Campo etiqueta={t('Cor')} obrigatorio erro={erros.color}>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        value={aEditar.dados.color}
                                        onChange={(e) => porAEditar({ ...aEditar, dados: { ...aEditar.dados, color: e.target.value } })}
                                        className="h-10 w-14 cursor-pointer rounded-xl border border-slate-300 bg-white p-1"
                                        aria-label={t('Cor')}
                                    />
                                    <input
                                        value={aEditar.dados.color}
                                        onChange={(e) => porAEditar({ ...aEditar, dados: { ...aEditar.dados, color: e.target.value } })}
                                        className={cls(entrada, 'font-mono uppercase')}
                                    />
                                </div>
                            </Campo>
                        </div>

                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={aEditar.dados.is_active}
                                onChange={(e) => porAEditar({ ...aEditar, dados: { ...aEditar.dados, is_active: e.target.checked } })}
                                className="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            />
                            {t('Activa')}
                        </label>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar categoria')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-trash"
                            aTrabalhar={apagarCategoria.isPending}
                            onClick={() => aApagar && apagarCategoria.mutate(aApagar.id)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Só se elimina uma categoria sem pratos. Se tiver, mova-os primeiro.')}
                </p>
            </Modal>
        </div>
    );
}

/**
 * UMA LINHA QUE SE EDITA A TOCAR-LHE.
 *
 * O valor volta ao que estava se o servidor recusar, e só se grava ao sair do
 * campo — escrever «12» a caminho de «1200» não pode pôr o prato a 12 Kz.
 */
function LinhaDoPrato({
    prato, indice, categorias, podeGerir, aoNome, aoPreco, aoCategoria, aoAlternar,
}: {
    prato: Prato;
    indice: number;
    categorias: CategoriaDaCarta[];
    podeGerir: boolean;
    aoNome: (valor: string) => void;
    aoPreco: (valor: string) => void;
    aoCategoria: (valor: number | null) => void;
    aoAlternar: () => void;
}) {
    const [nome, porNome] = useState(prato.nome);
    const [preco, porPreco] = useState(String(prato.preco));

    useEffect(() => { porNome(prato.nome); porPreco(String(prato.preco)); }, [prato.nome, prato.preco]);

    return (
        <tr style={cascata(indice)} className={cls('entra', !prato.disponivel && 'bg-slate-50/60')}>
            <td className="px-4 py-2">
                {podeGerir ? (
                    <input
                        value={nome}
                        onChange={(e) => porNome(e.target.value)}
                        onBlur={() => nome !== prato.nome && aoNome(nome)}
                        onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                        aria-label={t('Nome de :nome', { nome: prato.nome })}
                        className={cls(
                            'w-full rounded-lg border border-transparent bg-transparent px-2 py-1 text-sm font-semibold text-slate-800',
                            'hover:border-slate-200 focus:border-orange-400 focus:bg-white', FOCO,
                        )}
                    />
                ) : (
                    <span className="px-2 text-sm font-semibold text-slate-800">{prato.nome}</span>
                )}

                {prato.falta_ficha && (
                    <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                        <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                        {t('sem ficha')}
                    </span>
                )}
            </td>

            <td className="px-4 py-2">
                {podeGerir ? (
                    <select
                        value={prato.category_id ?? ''}
                        onChange={(e) => aoCategoria(e.target.value ? Number(e.target.value) : null)}
                        aria-label={t('Categoria de :nome', { nome: prato.nome })}
                        className={cls(
                            'w-full rounded-lg border border-transparent bg-transparent px-2 py-1 text-sm text-slate-600',
                            'hover:border-slate-200 focus:border-orange-400 focus:bg-white', FOCO,
                        )}
                    >
                        <option value="">{t('Sem categoria')}</option>
                        {categorias.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
                    </select>
                ) : (
                    <span className="px-2 text-sm text-slate-600">{prato.categoria ?? '—'}</span>
                )}
            </td>

            <td className="px-4 py-2 text-right">
                {podeGerir ? (
                    <input
                        value={preco}
                        onChange={(e) => porPreco(e.target.value)}
                        onBlur={() => preco !== String(prato.preco) && aoPreco(preco)}
                        onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                        aria-label={t('Preço de :nome', { nome: prato.nome })}
                        className={cls(
                            'w-28 rounded-lg border border-transparent bg-transparent px-2 py-1 text-right text-sm font-bold tabular-nums text-slate-900',
                            'hover:border-slate-200 focus:border-orange-400 focus:bg-white', FOCO,
                        )}
                    />
                ) : (
                    <span className="px-2 text-sm font-bold tabular-nums text-slate-900">{kz(prato.preco)}</span>
                )}
            </td>

            <td className="px-4 py-2 text-center">
                <button
                    type="button"
                    disabled={!podeGerir}
                    onClick={aoAlternar}
                    role="switch"
                    aria-checked={prato.disponivel}
                    aria-label={t('No menu: :nome', { nome: prato.nome })}
                    className={cls(
                        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                        FOCO,
                        prato.disponivel ? 'bg-emerald-500' : 'bg-slate-300',
                    )}
                >
                    <span
                        className={cls(
                            'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
                            prato.disponivel ? 'translate-x-6' : 'translate-x-1',
                        )}
                    />
                </button>
            </td>
        </tr>
    );
}
