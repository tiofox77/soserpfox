import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { eventos, type TipoDeEvento } from '@/api/eventos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS TIPOS DE EVENTO — casamento, conferência, espectáculo.
 *
 * A COR DE CADA TIPO É A QUE PINTA O EVENTO NO CALENDÁRIO: é por ela que se lê
 * o mês de relance, antes de se ler um único nome. Por isso a cor tem
 * pré-visualização aqui, ao lado do ícone e do nome, e não é um campo escondido.
 *
 * A ORDEM VÊ-SE, NÃO SE ESCREVE. Era uma caixa com um número, e uma lista
 * criada de enfiada ficava toda a zero — trocar dois números não trocava nada.
 * Agora sobe-se e desce-se, e o servidor renumera a lista toda.
 */

const VAZIO = { name: '', icon: '📌', color: '#8b5cf6', description: '', is_active: true };

export default function Tipos() {
    const cache = useQueryClient();

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<TipoDeEvento | null>(null);

    const lista = useQuery({ queryKey: ['eventos', 'tipos'], queryFn: () => eventos.tipos.lista() });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['eventos'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => eventos.tipos.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const mover = useMutation({
        mutationFn: ({ id, direccao }: { id: number; direccao: 'cima' | 'baixo' }) =>
            eventos.tipos.mover(id, direccao),
        onSuccess: () => void cache.invalidateQueries({ queryKey: ['eventos'] }),
        onError: porErro,
    });

    const alternar = useMutation({
        mutationFn: (id: number) => eventos.tipos.alternar(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => eventos.tipos.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: porErro,
    });

    if (lista.isPending) return <Carregando linhas={6} />;
    if (lista.isError) return <AvisoDeErro erro={lista.error} />;

    const { data, emojis, resumo, permissoes } = lista.data;
    const pode = permissoes.pode_gerir;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Tipos de Eventos')}
                subtitulo={t('A cor de cada tipo é a que pinta o calendário')}
                icone="fa-tags"
                cor="aviso"
                accoes={
                    pode && (
                        <button type="button" className={ACCAO_DA_FAIXA}
                            onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Novo tipo')}
                        </button>
                    )
                }
            />

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="grid gap-4 sm:grid-cols-2">
                <CartaoNumero aspecto="claro" rotulo={t('Tipos')} valor={numero(resumo.total)} icone="fa-tags" tom="ambar" />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Activos')} valor={numero(resumo.activos)}
                    icone="fa-circle-check" tom="verde"
                    nota={t('Só os activos aparecem na marcação')}
                />
            </div>

            {data.length === 0 ? (
                <SemNada
                    icone="fa-tags"
                    titulo={t('Ainda não há tipos')}
                    frase={t('Um evento precisa de um tipo — e é a cor dele que se lê no calendário.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}>
                            {t('Novo tipo')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <Cartao
                    titulo={t('A ordem daqui é a ordem em que aparecem')}
                    subtitulo={t('Sobe-se e desce-se — não se escrevem números')}
                    icone="fa-list-ol"
                    semPadding
                >
                    <ul className="divide-y divide-slate-100">
                        {data.map((x, i) => (
                            <li key={x.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                <span
                                    className="grid h-11 w-11 flex-none place-items-center rounded-xl text-2xl"
                                    style={{ backgroundColor: `${x.cor}22` }}
                                >
                                    {x.icone}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{x.nome}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {x.descricao || t(':n eventos', { n: numero(x.eventos) })}
                                    </p>
                                </div>

                                {/* A cor vê-se aqui, que é onde ela conta. */}
                                <span
                                    className="h-6 w-10 flex-none rounded-lg ring-1 ring-inset ring-black/10"
                                    style={{ backgroundColor: x.cor }}
                                    title={x.cor}
                                />

                                <Etiqueta cor={x.activo ? 'bom' : 'neutra'} ponto>
                                    {x.activo ? t('Activo') : t('Desligado')}
                                </Etiqueta>

                                {pode && (
                                    <div className="flex items-center gap-1.5">
                                        <Botao altura="pequeno" icone="fa-arrow-up" disabled={i === 0}
                                            onClick={() => mover.mutate({ id: x.id, direccao: 'cima' })}
                                            aria-label={t('Subir')} />
                                        <Botao altura="pequeno" icone="fa-arrow-down" disabled={i === data.length - 1}
                                            onClick={() => mover.mutate({ id: x.id, direccao: 'baixo' })}
                                            aria-label={t('Descer')} />
                                        <Botao altura="pequeno" icone={x.activo ? 'fa-toggle-on' : 'fa-toggle-off'}
                                            onClick={() => alternar.mutate(x.id)}
                                            aria-label={x.activo ? t('Desligar tipo') : t('Ligar tipo')} />
                                        <Botao altura="pequeno" icone="fa-pen"
                                            onClick={() => {
                                                porAEditar(x.id);
                                                porFormulario({
                                                    name: x.nome, icon: x.icone, color: x.cor,
                                                    description: x.descricao ?? '', is_active: x.activo,
                                                });
                                            }}
                                            aria-label={t('Editar tipo')} />
                                        <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                            onClick={() => porAApagar(x)} aria-label={t('Eliminar tipo')} />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </Cartao>
            )}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar tipo') : t('Novo tipo')}
                subtitulo={t('O ícone e a cor são o que se vê no calendário')}
                icone="fa-tags"
                cor="aviso"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate(formulario)}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        {/* A pré-visualização: é assim que o evento vai aparecer. */}
                        <div
                            className={cls('flex items-center gap-3 px-4 py-3 text-white', RAIO)}
                            style={{ backgroundColor: formulario.color }}
                        >
                            <span className="text-2xl">{formulario.icon}</span>
                            <span className="text-sm font-bold">
                                {formulario.name || t('Nome do tipo')}
                            </span>
                        </div>

                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input type="text" value={formulario.name}
                                onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                placeholder={t('Ex.: Festa de aniversário')} className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Ícone')} obrigatorio erro={erros.icon}>
                            <div className="flex flex-wrap gap-2">
                                {emojis.map((e) => (
                                    <button key={e.valor} type="button" title={e.rotulo}
                                        onClick={() => porFormulario({ ...formulario, icon: e.valor })}
                                        className={cls(
                                            'grid h-11 w-11 place-items-center rounded-xl border-2 text-xl transition hover:-translate-y-0.5', FOCO,
                                            formulario.icon === e.valor ? 'border-amber-500 bg-amber-50' : 'border-slate-200 bg-white',
                                        )}>
                                        {e.valor}
                                    </button>
                                ))}
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Cor')} obrigatorio erro={erros.color}
                            ajuda={t('É esta que pinta o evento no calendário.')}>
                            <input type="color" value={formulario.color}
                                onChange={(e) => porFormulario({ ...formulario, color: e.target.value })}
                                className="h-11 w-full cursor-pointer rounded-xl border border-slate-200" />
                        </Campo>

                        <Campo etiqueta={t('Descrição')} erro={erros.description}>
                            <textarea rows={2} value={formulario.description}
                                onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <label className="flex items-center gap-3">
                            <input type="checkbox" checked={formulario.is_active}
                                onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                className="h-5 w-5 rounded text-amber-600" />
                            <span className="text-sm font-medium text-slate-700">
                                {t('Activo — aparece na marcação de eventos')}
                            </span>
                        </label>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar tipo')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />
                <p className="text-sm text-slate-600">
                    {t('Eliminar «:nome»? Um tipo com eventos não se apaga — desligue-o.', {
                        nome: aApagar?.nome ?? '',
                    })}
                </p>
            </Modal>
        </div>
    );
}
