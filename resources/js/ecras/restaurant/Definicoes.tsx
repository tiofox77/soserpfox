import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type CartaPublica, type RegrasDoRestaurante } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * AS DEFINIÇÕES DO RESTAURANTE.
 *
 * TRÊS GRAVAÇÕES SEPARADAS, e não um botão só: as REGRAS da casa, a
 * ESTRUTURA (estabelecimentos, zonas, mesas, postos de cozinha) e a CARTA
 * PÚBLICA. Publicar preços ao mundo é uma decisão diferente de escolher um
 * armazém por omissão, e misturá-las fazia um clique numa caixa qualquer
 * publicar a carta sem querer.
 *
 * O TURNO NÃO TEM INTERRUPTOR e é de propósito: vender com a caixa fechada é
 * vender sem ninguém responder pelo dinheiro.
 */

export default function Definicoes() {
    const cache = useQueryClient();

    const [aba, porAba] = useState('regras');
    const [regras, porRegras] = useState<RegrasDoRestaurante | null>(null);
    const [carta, porCarta] = useState<CartaPublica | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useState('');

    const [novoEstab, porNovoEstab] = useState<{ code: string; name: string; warehouse_id: string } | null>(null);
    const [novaZona, porNovaZona] = useState<{ venue_id: string; name: string } | null>(null);
    const [novoPosto, porNovoPosto] = useState<{ venue_id: string; code: string; name: string } | null>(null);
    const [aPedir, porAPedir] = useState<{ requested_limit: string; reason: string } | null>(null);
    const [aRenomear, porARenomear] = useState<{ tipo: string; id: number; name: string; code: string; capacity: string } | null>(null);
    const [aApagar, porAApagar] = useState<{ tipo: string; id: number; nome: string } | null>(null);

    const dados = useQuery({ queryKey: ['restaurante', 'definicoes'], queryFn: restaurante.definicoes.ler });

    useEffect(() => {
        if (dados.data) { porRegras(dados.data.regras); porCarta(dados.data.carta); }
    }, [dados.data]);

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['restaurante', 'definicoes'] });
    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };
    const feito = (r: { message: string }) => { porErros({}); porErro(null); porRecado(r.message); refrescar(); };

    const guardarRegras = useMutation({
        mutationFn: () => restaurante.definicoes.guardar(regras!),
        onSuccess: feito, onError: falhou,
    });

    const guardarCarta = useMutation({
        mutationFn: () => restaurante.definicoes.guardarCarta(carta!),
        onSuccess: feito, onError: falhou,
    });

    const criarEstab = useMutation({
        mutationFn: () => restaurante.definicoes.criarEstabelecimento({
            ...novoEstab, warehouse_id: novoEstab!.warehouse_id ? Number(novoEstab!.warehouse_id) : null,
        }),
        onSuccess: (r) => { porNovoEstab(null); feito(r); }, onError: falhou,
    });

    const criarZona = useMutation({
        mutationFn: () => restaurante.definicoes.criarZona({ ...novaZona, venue_id: Number(novaZona!.venue_id) }),
        onSuccess: (r) => { porNovaZona(null); feito(r); }, onError: falhou,
    });

    const criarPosto = useMutation({
        mutationFn: () => restaurante.definicoes.criarPosto({ ...novoPosto, venue_id: Number(novoPosto!.venue_id) }),
        onSuccess: (r) => { porNovoPosto(null); feito(r); }, onError: falhou,
    });

    const pedirMais = useMutation({
        mutationFn: () => restaurante.definicoes.pedirMais({
            requested_limit: Number(aPedir!.requested_limit), reason: aPedir!.reason,
        }),
        onSuccess: (r) => { porAPedir(null); feito(r); }, onError: falhou,
    });

    const alternar = useMutation({
        mutationFn: ({ tipo, id }: { tipo: string; id: number }) => restaurante.definicoes.alternar(tipo, id),
        onSuccess: feito, onError: falhou,
    });

    const renomear = useMutation({
        mutationFn: () => restaurante.definicoes.renomear(aRenomear!.tipo, aRenomear!.id, {
            name: aRenomear!.name, code: aRenomear!.code, capacity: Number(aRenomear!.capacity),
        }),
        onSuccess: (r) => { porARenomear(null); feito(r); }, onError: falhou,
    });

    const apagar = useMutation({
        mutationFn: () => restaurante.definicoes.apagar(aApagar!.tipo, aApagar!.id),
        onSuccess: (r) => { porAApagar(null); feito(r); },
        onError: (e) => { porAApagar(null); falhou(e); },
    });

    if (dados.isPending || !regras || !carta) return <Carregando linhas={10} />;
    if (dados.isError) return <AvisoDeErro erro={dados.error} />;

    const d = dados.data;
    const podeEditar = d.permissoes.pode_editar;
    const noLimite = d.estabelecimentos.length >= d.limite_de_estabelecimentos;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Definições do Restaurante')}
                subtitulo={t('As regras da casa, a sua estrutura e a carta pública')}
                icone="fa-sliders"
                cor="neutra"
                accoes={
                    <>
                        <a href="/restaurant/carta/aparencia" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-palette" aria-hidden="true" />
                            {t('Aparência da carta')}
                        </a>
                        <a href="/restaurant/menu/qr" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-qrcode" aria-hidden="true" />
                            {t('QR das mesas')}
                        </a>
                    </>
                }
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
                    { chave: 'regras', rotulo: t('Regras'), icone: 'fa-gear' },
                    { chave: 'estrutura', rotulo: t('Estrutura'), icone: 'fa-sitemap' },
                    { chave: 'carta', rotulo: t('Carta pública'), icone: 'fa-globe' },
                ]}
            />

            <PainelDoSeparador chave="regras" activa={aba}>
                <div className="space-y-5">
                    <Cartao titulo={t('Serviço')} icone="fa-bell-concierge">
                        <div className="space-y-3">
                            <Interruptor
                                ligado={regras.use_kitchen_workflow}
                                aoMudar={(v) => porRegras({ ...regras, use_kitchen_workflow: v })}
                                desactivado={!podeEditar}
                                titulo={t('Usar bilhetes de cozinha')}
                                nota={t('A comanda é enviada para preparação e a cozinha marca cada passo. Desligado, os artigos ficam servidos assim que são lançados.')}
                            />
                            <Interruptor
                                ligado={regras.require_recipe_for_products}
                                aoMudar={(v) => porRegras({ ...regras, require_recipe_for_products: v })}
                                desactivado={!podeEditar}
                                titulo={t('Exigir ficha técnica')}
                                nota={t('Um prato sem ficha deixa de aparecer no balcão. Aparece marcado na carta, para se saber porquê.')}
                            />
                            <Interruptor
                                ligado={regras.tips_enabled}
                                aoMudar={(v) => porRegras({ ...regras, tips_enabled: v })}
                                desactivado={!podeEditar}
                                titulo={t('Aceitar gorjetas')}
                                nota={t('A gorjeta é do pessoal: passa pela caixa e NÃO entra na factura.')}
                            />
                            <Interruptor
                                ligado={regras.kitchen_auto_print}
                                aoMudar={(v) => porRegras({ ...regras, kitchen_auto_print: v })}
                                desactivado={!podeEditar}
                                titulo={t('Imprimir bilhetes automaticamente')}
                                nota={t('Valor de arranque; cada aparelho decide o seu, porque a impressora está num posto só.')}
                            />

                            <Campo
                                etiqueta={t('Taxa de serviço (%)')}
                                erro={erros.service_charge_percent}
                                className="max-w-xs"
                                ajuda={t('É receita da casa e VAI à factura, ao contrário da gorjeta.')}
                            >
                                <input
                                    type="number" step="0.01" min="0" max="100"
                                    value={regras.service_charge_percent}
                                    disabled={!podeEditar}
                                    onChange={(e) => porRegras({ ...regras, service_charge_percent: Number(e.target.value) })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        </div>
                    </Cartao>

                    <Cartao titulo={t('Stock')} icone="fa-boxes-stacked">
                        <div className="space-y-3">
                            <Interruptor
                                ligado={regras.reserve_stock_on_confirm}
                                aoMudar={(v) => porRegras({ ...regras, reserve_stock_on_confirm: v })}
                                desactivado={!podeEditar}
                                titulo={t('Reservar stock ao confirmar')}
                                nota={t('Ao enviar para a cozinha, os ingredientes ficam apartados.')}
                            />
                            <Interruptor
                                ligado={regras.consume_stock_on_kitchen}
                                aoMudar={(v) => porRegras({ ...regras, consume_stock_on_kitchen: v })}
                                desactivado={!podeEditar}
                                titulo={t('Consumir stock na cozinha')}
                                nota={t('A saída é lançada quando o prato é produzido, pela ficha técnica.')}
                            />
                            <Interruptor
                                ligado={regras.allow_negative_stock}
                                aoMudar={(v) => porRegras({ ...regras, allow_negative_stock: v })}
                                desactivado={!podeEditar}
                                titulo={t('Permitir stock negativo')}
                                nota={t('Deixa vender sem existências. Útil em serviço cheio, mas o armazém deixa de bater certo.')}
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo etiqueta={t('Armazém por omissão')} erro={erros.default_warehouse_id}>
                                    <select
                                        value={regras.default_warehouse_id ?? ''}
                                        disabled={!podeEditar}
                                        onChange={(e) => porRegras({ ...regras, default_warehouse_id: e.target.value ? Number(e.target.value) : null })}
                                        className={entrada}
                                    >
                                        <option value="">{t('Nenhum')}</option>
                                        {d.armazens.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                                    </select>
                                </Campo>

                                <Campo
                                    etiqueta={t('Cliente por omissão')}
                                    erro={erros.default_client_id}
                                    ajuda={t('Quem fica na factura quando ninguém dá o nome.')}
                                >
                                    <select
                                        value={regras.default_client_id ?? ''}
                                        disabled={!podeEditar}
                                        onChange={(e) => porRegras({ ...regras, default_client_id: e.target.value ? Number(e.target.value) : null })}
                                        className={entrada}
                                    >
                                        <option value="">{t('Consumidor final')}</option>
                                        {d.clientes.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                                    </select>
                                </Campo>
                            </div>
                        </div>
                    </Cartao>

                    <p className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                        <i className="fas fa-lock mr-2 text-slate-400" aria-hidden="true" />
                        {t('O turno de caixa é sempre obrigatório e não se desliga: vender com a caixa fechada é vender sem ninguém responder pelo dinheiro.')}
                    </p>

                    {podeEditar && (
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardarRegras.isPending} onClick={() => guardarRegras.mutate()}>
                            {t('Guardar regras')}
                        </Botao>
                    )}
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="estrutura" activa={aba}>
                <div className="space-y-5">
                    <Cartao
                        titulo={t('Estabelecimentos')}
                        subtitulo={t('Salas, zonas e mesas — :u de :l usados', {
                            u: String(d.estabelecimentos.length), l: String(d.limite_de_estabelecimentos),
                        })}
                        icone="fa-store"
                        semPadding
                        accoes={podeEditar && (
                            noLimite ? (
                                d.pedido_de_limite ? (
                                    <Etiqueta cor="aviso" icone="fa-hourglass-half">{t('Pedido em análise')}</Etiqueta>
                                ) : (
                                    <Botao
                                        altura="pequeno" icone="fa-envelope"
                                        onClick={() => { porErros({}); porAPedir({ requested_limit: String(d.limite_de_estabelecimentos + 1), reason: '' }); }}
                                    >
                                        {t('Pedir mais')}
                                    </Botao>
                                )
                            ) : (
                                <Botao
                                    altura="pequeno" cor="primaria" tom="solida" icone="fa-plus"
                                    onClick={() => { porErros({}); porNovoEstab({ code: '', name: '', warehouse_id: '' }); }}
                                >
                                    {t('Novo')}
                                </Botao>
                            )
                        )}
                    >
                        {d.estabelecimentos.length === 0 ? (
                            <SemNada icone="fa-store" frase={t('Ainda não há estabelecimentos.')} />
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {d.estabelecimentos.map((v, i) => (
                                    <li key={v.id} style={cascata(i)} className="entra px-5 py-3">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-slate-100 text-slate-500">
                                                <i className="fas fa-store" aria-hidden="true" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold text-slate-800">
                                                    {v.nome} <span className="font-normal text-slate-400">· {v.codigo}</span>
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {t(':z zonas · :m mesas', {
                                                        z: String(v.zonas.length),
                                                        m: String(v.zonas.reduce((s, z) => s + z.mesas.length, 0)),
                                                    })}
                                                </p>
                                            </div>

                                            <Etiqueta cor={v.activo ? 'bom' : 'neutra'} ponto>
                                                {v.activo ? t('Activo') : t('Desligado')}
                                            </Etiqueta>

                                            {podeEditar && (
                                                <AccoesDaPeca
                                                    tipo="estabelecimento" id={v.id} nome={v.nome} activo={v.activo}
                                                    aoRenomear={() => porARenomear({ tipo: 'estabelecimento', id: v.id, name: v.nome, code: v.codigo, capacity: '4' })}
                                                    aoAlternar={() => alternar.mutate({ tipo: 'estabelecimento', id: v.id })}
                                                    aoApagar={() => porAApagar({ tipo: 'estabelecimento', id: v.id, nome: v.nome })}
                                                    extra={
                                                        <>
                                                            <Botao
                                                                altura="pequeno" icone="fa-map-pin"
                                                                onClick={() => { porErros({}); porNovaZona({ venue_id: String(v.id), name: '' }); }}
                                                            >
                                                                {t('Zona')}
                                                            </Botao>
                                                            <Botao
                                                                altura="pequeno" icone="fa-fire-burner"
                                                                onClick={() => { porErros({}); porNovoPosto({ venue_id: String(v.id), code: '', name: '' }); }}
                                                            >
                                                                {t('Posto')}
                                                            </Botao>
                                                        </>
                                                    }
                                                />
                                            )}
                                        </div>

                                        {v.zonas.length > 0 && (
                                            <ul className="mt-2 space-y-2 border-l-2 border-slate-100 pl-4">
                                                {v.zonas.map((z) => (
                                                    <li key={z.id}>
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <i className="fas fa-map-pin text-xs text-slate-400" aria-hidden="true" />
                                                            <span className="text-sm font-medium text-slate-700">{z.nome}</span>
                                                            <Etiqueta cor={z.activa ? 'bom' : 'neutra'}>
                                                                {z.activa ? t('Activa') : t('Desligada')}
                                                            </Etiqueta>

                                                            {podeEditar && (
                                                                <AccoesDaPeca
                                                                    tipo="zona" id={z.id} nome={z.nome} activo={z.activa}
                                                                    aoRenomear={() => porARenomear({ tipo: 'zona', id: z.id, name: z.nome, code: '', capacity: '4' })}
                                                                    aoAlternar={() => alternar.mutate({ tipo: 'zona', id: z.id })}
                                                                    aoApagar={() => porAApagar({ tipo: 'zona', id: z.id, nome: z.nome })}
                                                                />
                                                            )}
                                                        </div>

                                                        {z.mesas.length > 0 && (
                                                            <ul className="mt-1 flex flex-wrap gap-1.5 pl-5">
                                                                {z.mesas.map((mesa) => (
                                                                    <li
                                                                        key={mesa.id}
                                                                        className={cls(
                                                                            'flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs',
                                                                            mesa.activa ? 'bg-slate-100 text-slate-700' : 'bg-slate-50 text-slate-400 line-through',
                                                                        )}
                                                                    >
                                                                        <span className="font-semibold">{mesa.nome || mesa.codigo}</span>
                                                                        <span className="text-slate-400">
                                                                            <i className="fas fa-user-group mr-0.5" aria-hidden="true" />{mesa.lugares}
                                                                        </span>

                                                                        {podeEditar && (
                                                                            <>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => porARenomear({ tipo: 'mesa', id: mesa.id, name: mesa.nome, code: mesa.codigo, capacity: String(mesa.lugares) })}
                                                                                    title={t('Editar')}
                                                                                    aria-label={t('Editar :nome', { nome: mesa.nome || mesa.codigo })}
                                                                                    className={cls('rounded p-0.5 text-slate-400 hover:text-slate-700', FOCO)}
                                                                                >
                                                                                    <i className="fas fa-pen" aria-hidden="true" />
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => alternar.mutate({ tipo: 'mesa', id: mesa.id })}
                                                                                    title={mesa.activa ? t('Desligar') : t('Ligar')}
                                                                                    aria-label={mesa.activa ? t('Desligar :nome', { nome: mesa.nome || mesa.codigo }) : t('Ligar :nome', { nome: mesa.nome || mesa.codigo })}
                                                                                    className={cls('rounded p-0.5 text-slate-400 hover:text-slate-700', FOCO)}
                                                                                >
                                                                                    <i className={`fas ${mesa.activa ? 'fa-eye-slash' : 'fa-eye'}`} aria-hidden="true" />
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => porAApagar({ tipo: 'mesa', id: mesa.id, nome: mesa.nome || mesa.codigo })}
                                                                                    title={t('Eliminar')}
                                                                                    aria-label={t('Eliminar :nome', { nome: mesa.nome || mesa.codigo })}
                                                                                    className={cls('rounded p-0.5 text-slate-400 hover:text-red-600', FOCO)}
                                                                                >
                                                                                    <i className="fas fa-trash" aria-hidden="true" />
                                                                                </button>
                                                                            </>
                                                                        )}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Cartao>

                    <Cartao
                        titulo={t('Postos de cozinha')}
                        subtitulo={t('Onde os bilhetes aparecem: grelha, bar, sobremesas')}
                        icone="fa-fire-burner"
                        semPadding
                    >
                        {d.postos.length === 0 ? (
                            <SemNada icone="fa-fire-burner" frase={t('Sem postos — os bilhetes vão todos para o mesmo ecrã.')} />
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {d.postos.map((p, i) => (
                                    <li key={p.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                        <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-red-50 text-red-500">
                                            <i className="fas fa-fire-burner" aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-slate-800">
                                                {p.nome} <span className="font-normal text-slate-400">· {p.codigo}</span>
                                            </p>
                                            <p className="text-xs text-slate-500">{p.estabelecimento}</p>
                                        </div>

                                        <Etiqueta cor={p.activo ? 'bom' : 'neutra'} ponto>
                                            {p.activo ? t('Activo') : t('Desligado')}
                                        </Etiqueta>

                                        {podeEditar && (
                                            <AccoesDaPeca
                                                tipo="posto" id={p.id} nome={p.nome} activo={p.activo}
                                                aoRenomear={() => porARenomear({ tipo: 'posto', id: p.id, name: p.nome, code: p.codigo, capacity: '4' })}
                                                aoAlternar={() => alternar.mutate({ tipo: 'posto', id: p.id })}
                                                aoApagar={() => porAApagar({ tipo: 'posto', id: p.id, nome: p.nome })}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Cartao>
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="carta" activa={aba}>
                <div className="space-y-5">
                    <Cartao titulo={t('A carta online')} icone="fa-globe">
                        <div className="space-y-4">
                            <p className={cls('border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                                <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                                {t('Publicar a carta põe os seus preços numa página aberta a qualquer pessoa com o endereço.')}
                            </p>

                            <Interruptor
                                ligado={carta.online_menu_enabled}
                                aoMudar={(v) => porCarta({ ...carta, online_menu_enabled: v })}
                                desactivado={!podeEditar}
                                titulo={t('Carta publicada')}
                                nota={t('Desligada, o endereço devolve uma página de recusa.')}
                            />

                            <Campo
                                etiqueta={t('Endereço')}
                                obrigatorio={carta.online_menu_enabled}
                                erro={erros.menu_slug}
                                ajuda={d.url_da_carta ?? t('Só letras minúsculas, números e hífens.')}
                            >
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-slate-400">/menu/</span>
                                    <input
                                        value={carta.menu_slug}
                                        disabled={!podeEditar}
                                        onChange={(e) => porCarta({ ...carta, menu_slug: e.target.value })}
                                        className={entrada}
                                    />
                                    {d.url_da_carta && (
                                        <a
                                            href={d.url_da_carta}
                                            target="_blank"
                                            rel="noopener"
                                            title={t('Abrir')}
                                            aria-label={t('Abrir a carta pública')}
                                            className={cls('grid h-10 w-10 flex-none place-items-center rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200', FOCO)}
                                        >
                                            <i className="fas fa-arrow-up-right-from-square" aria-hidden="true" />
                                        </a>
                                    )}
                                </div>
                            </Campo>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo etiqueta={t('Título')} erro={erros.menu_title}>
                                    <input
                                        value={carta.menu_title}
                                        disabled={!podeEditar}
                                        onChange={(e) => porCarta({ ...carta, menu_title: e.target.value })}
                                        className={entrada}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Cor principal')} erro={erros.menu_primary_color}>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="color"
                                            value={carta.menu_primary_color}
                                            disabled={!podeEditar}
                                            onChange={(e) => porCarta({ ...carta, menu_primary_color: e.target.value })}
                                            className="h-10 w-14 cursor-pointer rounded-xl border border-slate-300 bg-white p-1"
                                            aria-label={t('Cor principal')}
                                        />
                                        <input
                                            value={carta.menu_primary_color}
                                            disabled={!podeEditar}
                                            onChange={(e) => porCarta({ ...carta, menu_primary_color: e.target.value })}
                                            className={cls(entrada, 'font-mono')}
                                        />
                                    </div>
                                </Campo>
                            </div>

                            <Campo etiqueta={t('Descrição')} erro={erros.menu_description}>
                                <textarea
                                    value={carta.menu_description}
                                    disabled={!podeEditar}
                                    onChange={(e) => porCarta({ ...carta, menu_description: e.target.value })}
                                    rows={3}
                                    className={cls(entrada, 'h-auto py-2')}
                                />
                            </Campo>

                            <Interruptor
                                ligado={carta.menu_show_prices}
                                aoMudar={(v) => porCarta({ ...carta, menu_show_prices: v })}
                                desactivado={!podeEditar}
                                titulo={t('Mostrar preços')}
                            />

                            <Interruptor
                                ligado={carta.menu_orders_enabled}
                                aoMudar={(v) => porCarta({ ...carta, menu_orders_enabled: v })}
                                desactivado={!podeEditar}
                                titulo={t('Aceitar pedidos pela carta')}
                                nota={t('O pedido chega ao mapa da sala e só vira comanda quando um empregado o aceita.')}
                            />

                            <Interruptor
                                ligado={carta.menu_whatsapp_enabled}
                                aoMudar={(v) => porCarta({ ...carta, menu_whatsapp_enabled: v })}
                                desactivado={!podeEditar}
                                titulo={t('Botão de WhatsApp')}
                            />

                            {carta.menu_whatsapp_enabled && (
                                <Campo
                                    etiqueta={t('Número do WhatsApp')}
                                    obrigatorio
                                    erro={erros.menu_whatsapp_number}
                                    className="max-w-sm"
                                    ajuda={t('Um botão que não leva a lado nenhum é pior do que botão nenhum.')}
                                >
                                    <input
                                        value={carta.menu_whatsapp_number}
                                        disabled={!podeEditar}
                                        onChange={(e) => porCarta({ ...carta, menu_whatsapp_number: e.target.value })}
                                        className={entrada}
                                        placeholder="+244 900 000 000"
                                    />
                                </Campo>
                            )}

                            {podeEditar && (
                                <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardarCarta.isPending} onClick={() => guardarCarta.mutate()}>
                                    {t('Guardar carta')}
                                </Botao>
                            )}
                        </div>
                    </Cartao>
                </div>
            </PainelDoSeparador>

            {/* ─── Modais da estrutura ─── */}
            <Modal
                aberto={novoEstab !== null}
                aoFechar={() => porNovoEstab(null)}
                titulo={t('Novo estabelecimento')}
                icone="fa-store"
                cor="neutra"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porNovoEstab(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={criarEstab.isPending} onClick={() => criarEstab.mutate()}>
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {novoEstab && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Código')} obrigatorio erro={erros.code}>
                                <input value={novoEstab.code} onChange={(e) => porNovoEstab({ ...novoEstab, code: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                                <input value={novoEstab.name} onChange={(e) => porNovoEstab({ ...novoEstab, name: e.target.value })} className={entrada} />
                            </Campo>
                        </div>
                        <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id}>
                            <select value={novoEstab.warehouse_id} onChange={(e) => porNovoEstab({ ...novoEstab, warehouse_id: e.target.value })} className={entrada}>
                                <option value="">{t('Nenhum')}</option>
                                {d.armazens.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={novaZona !== null}
                aoFechar={() => porNovaZona(null)}
                titulo={t('Nova zona')}
                subtitulo={t('Esplanada, primeiro andar, salão privado')}
                icone="fa-map-pin"
                cor="neutra"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porNovaZona(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={criarZona.isPending} onClick={() => criarZona.mutate()}>
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {novaZona && (
                    <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                        <input value={novaZona.name} onChange={(e) => porNovaZona({ ...novaZona, name: e.target.value })} className={entrada} autoFocus />
                    </Campo>
                )}
            </Modal>

            <Modal
                aberto={novoPosto !== null}
                aoFechar={() => porNovoPosto(null)}
                titulo={t('Novo posto de cozinha')}
                subtitulo={t('Grelha, bar, sobremesas — cada um vê os seus bilhetes')}
                icone="fa-fire-burner"
                cor="neutra"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porNovoPosto(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={criarPosto.isPending} onClick={() => criarPosto.mutate()}>
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {novoPosto && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Código')} obrigatorio erro={erros.code}>
                            <input value={novoPosto.code} onChange={(e) => porNovoPosto({ ...novoPosto, code: e.target.value })} className={entrada} />
                        </Campo>
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input value={novoPosto.name} onChange={(e) => porNovoPosto({ ...novoPosto, name: e.target.value })} className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aPedir !== null}
                aoFechar={() => porAPedir(null)}
                titulo={t('Pedir mais estabelecimentos')}
                subtitulo={t('O administrador da plataforma analisa e responde')}
                icone="fa-envelope"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAPedir(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="aviso" tom="solida" icone="fa-paper-plane" aTrabalhar={pedirMais.isPending} onClick={() => pedirMais.mutate()}>
                            {t('Enviar pedido')}
                        </Botao>
                    </>
                }
            >
                {aPedir && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Novo limite')} obrigatorio erro={erros.requested_limit}>
                            <input
                                type="number" min="2" max="20"
                                value={aPedir.requested_limit}
                                onChange={(e) => porAPedir({ ...aPedir, requested_limit: e.target.value })}
                                className={entrada}
                            />
                        </Campo>
                        <Campo etiqueta={t('Porquê')} erro={erros.reason}>
                            <textarea
                                value={aPedir.reason}
                                onChange={(e) => porAPedir({ ...aPedir, reason: e.target.value })}
                                rows={3}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aRenomear !== null}
                aoFechar={() => porARenomear(null)}
                titulo={t('Editar')}
                subtitulo={aRenomear?.name}
                icone="fa-pen"
                cor="neutra"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porARenomear(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={renomear.isPending} onClick={() => renomear.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {aRenomear && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input value={aRenomear.name} onChange={(e) => porARenomear({ ...aRenomear, name: e.target.value })} className={entrada} autoFocus />
                        </Campo>

                        {aRenomear.tipo !== 'zona' && (
                            <Campo etiqueta={t('Código')} erro={erros.code}>
                                <input value={aRenomear.code} onChange={(e) => porARenomear({ ...aRenomear, code: e.target.value })} className={entrada} />
                            </Campo>
                        )}

                        {aRenomear.tipo === 'mesa' && (
                            <Campo etiqueta={t('Lugares')} erro={erros.capacity}>
                                <input
                                    type="number" min="1" max="100"
                                    value={aRenomear.capacity}
                                    onChange={(e) => porARenomear({ ...aRenomear, capacity: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        )}
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => apagar.mutate()}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('O que tem histórico não se apaga — desactiva-se. Apagar uma mesa com comandas deixava documentos a apontar para o vazio.')}
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
                    ligado ? 'bg-indigo-600' : 'bg-slate-300',
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

function AccoesDaPeca({
    nome, activo, aoRenomear, aoAlternar, aoApagar, extra,
}: {
    tipo: string; id: number; nome: string; activo: boolean;
    aoRenomear: () => void; aoAlternar: () => void; aoApagar: () => void; extra?: React.ReactNode;
}) {
    return (
        <div className="flex flex-none flex-wrap gap-1">
            {extra}
            <Botao altura="pequeno" icone="fa-pen" onClick={aoRenomear} aria-label={t('Editar :nome', { nome })} />
            <Botao
                altura="pequeno"
                cor={activo ? 'aviso' : 'bom'}
                icone={activo ? 'fa-eye-slash' : 'fa-eye'}
                onClick={aoAlternar}
                aria-label={activo ? t('Desligar :nome', { nome }) : t('Ligar :nome', { nome })}
            />
            <Botao altura="pequeno" cor="perigo" icone="fa-trash" onClick={aoApagar} aria-label={t('Eliminar :nome', { nome })} />
        </div>
    );
}
