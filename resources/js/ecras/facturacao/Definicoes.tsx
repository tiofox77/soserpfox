import { useEffect, useState, type ReactNode } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    definicoes as api,
    type AvisoDeNumeracao,
    type Definicoes as Valores,
    type EcraDasDefinicoes,
    type Serie,
} from '@/api/definicoes';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';

/**
 * AS DEFINIÇÕES DA FACTURAÇÃO.
 *
 * Sete separadores, um formulário. O ecrã guarda o que o utilizador muda e
 * manda tudo de uma vez ao servidor, que valida com as mesmas regras do
 * Livewire (`DefinicoesDaFacturacao`). As SÉRIES são à parte: cada criação,
 * renomeação ou escolha de padrão é um pedido próprio ao `GestorDeSeries`,
 * porque uma série é numeração fiscal — não se guarda "com o resto".
 *
 * O prefixo de uma série nova vem do catálogo e mostra-se, não se edita: é o
 * bloco do número que a AGT lê para classificar o documento.
 */

const SEPARADORES = [
    { chave: 'padroes', rotulo: 'Padrões', icone: 'fa-sliders' },
    { chave: 'series', rotulo: 'Documentos e séries', icone: 'fa-hashtag' },
    { chave: 'impostos', rotulo: 'Impostos e descontos', icone: 'fa-percent' },
    { chave: 'impressao', rotulo: 'Impressão', icone: 'fa-print' },
    { chave: 'pos', rotulo: 'Ponto de venda', icone: 'fa-cash-register' },
    { chave: 'pwa', rotulo: 'PWA', icone: 'fa-mobile-screen' },
    { chave: 'perfil', rotulo: 'Perfil do negócio', icone: 'fa-store' },
] as const;

type Separador = (typeof SEPARADORES)[number]['chave'];

type ModalDeSerie =
    | { modo: 'nova'; tipo: string; prefixo: string | null; nome: string }
    | { modo: 'renomear'; serie: Serie };

export default function Definicoes() {
    const fila = useQueryClient();
    const ecra = useQuery({ queryKey: ['definicoes'], queryFn: api.ler });

    const [separador, porSeparador] = useState<Separador>('padroes');
    const [forma, porForma] = useState<Valores | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [mensagem, porMensagem] = useState<{ tom: 'bom' | 'aviso'; texto: string } | null>(null);

    /* A forma nasce do servidor uma vez; o que o utilizador escreve não se perde numa releitura. */
    useEffect(() => {
        if (ecra.data && !forma) porForma(ecra.data.definicoes);
    }, [ecra.data, forma]);

    useEffect(() => {
        if (!mensagem) return;
        const t = setTimeout(() => porMensagem(null), 5000);
        return () => clearTimeout(t);
    }, [mensagem]);

    const guardar = useMutation({
        mutationFn: (valores: Valores) => api.guardar(valores),
        onSuccess: (r) => {
            porErros({});
            if (r.aviso) {
                porForma((f) => (f ? { ...f, default_warehouse_id: null } : f));
                porMensagem({ tom: 'aviso', texto: r.aviso });
            } else {
                porMensagem({ tom: 'bom', texto: r.message });
            }
            fila.invalidateQueries({ queryKey: ['definicoes'] });
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (ecra.isPending || !forma) return <Carregando linhas={10} />;

    if (ecra.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir as definições</h2>
                <p className="text-sm text-red-800">{ecra.error instanceof ErroDaApi ? ecra.error.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const o = ecra.data.opcoes;
    const podeEditar = ecra.data.permissoes.pode_editar;

    const mudar = <K extends keyof Valores>(campo: K, valor: Valores[K]) =>
        porForma((f) => (f ? { ...f, [campo]: valor } : f));

    const texto = (campo: keyof Valores) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        mudar(campo, e.target.value as never);

    const numero = (campo: keyof Valores) => (e: React.ChangeEvent<HTMLSelectElement>) =>
        mudar(campo, (e.target.value ? Number(e.target.value) : null) as never);

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            {mensagem && (
                <p role="status" className={cls('px-4 py-3 text-sm font-medium', RAIO, mensagem.tom === 'bom' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800')}>
                    {mensagem.texto}
                </p>
            )}

            {/* Os separadores. */}
            <nav aria-label="Secções das definições" className="flex flex-wrap gap-1 border-b border-slate-200">
                {SEPARADORES.map((s) => (
                    <button
                        key={s.chave}
                        type="button"
                        role="tab"
                        aria-selected={separador === s.chave}
                        onClick={() => porSeparador(s.chave)}
                        className={cls(
                            'flex items-center gap-2 px-4 py-2.5 text-sm font-semibold transition',
                            FOCO,
                            separador === s.chave ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-slate-800',
                        )}
                    >
                        <i className={cls('fas', s.icone)} aria-hidden="true" />
                        {s.rotulo}
                    </button>
                ))}
            </nav>

            {separador === 'padroes' && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <Cartao titulo="Padrões dos documentos">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta="Armazém principal" erro={erros.default_warehouse_id}>
                                <select value={forma.default_warehouse_id ?? ''} onChange={numero('default_warehouse_id')} className={entrada}>
                                    <option value="">Nenhum</option>
                                    {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Cliente padrão" erro={erros.default_client_id}>
                                <select value={forma.default_client_id ?? ''} onChange={numero('default_client_id')} className={entrada}>
                                    <option value="">Nenhum</option>
                                    {o.clientes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Fornecedor padrão" erro={erros.default_supplier_id}>
                                <select value={forma.default_supplier_id ?? ''} onChange={numero('default_supplier_id')} className={entrada}>
                                    <option value="">Nenhum</option>
                                    {o.fornecedores.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Imposto padrão" erro={erros.default_tax_id}>
                                <select value={forma.default_tax_id ?? ''} onChange={numero('default_tax_id')} className={entrada}>
                                    <option value="">Nenhum</option>
                                    {o.impostos.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.rate}%)</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Condição de pagamento dos clientes novos" erro={erros.default_payment_term_id} className="sm:col-span-2">
                                <select value={forma.default_payment_term_id ?? ''} onChange={numero('default_payment_term_id')} className={entrada}>
                                    <option value="">Nenhuma</option>
                                    {o.condicoes_de_pagamento.map((c) => <option key={c.id} value={c.id}>{c.name} · {c.days} dias</option>)}
                                </select>
                            </Campo>
                        </div>
                    </Cartao>

                    <Cartao titulo="Moeda e números">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta="Moeda" erro={erros.default_currency} obrigatorio>
                                <select value={forma.default_currency} onChange={texto('default_currency')} className={entrada}>
                                    {o.moedas.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Taxa de câmbio" erro={erros.default_exchange_rate} obrigatorio>
                                <input type="number" min="0" step="0.0001" value={forma.default_exchange_rate} onChange={texto('default_exchange_rate')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta="Forma de pagamento habitual" erro={erros.default_payment_method}>
                                <select value={forma.default_payment_method ?? ''} onChange={texto('default_payment_method')} className={entrada}>
                                    {o.metodos_de_pagamento.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Formato dos números" erro={erros.number_format}>
                                <select value={forma.number_format ?? 'angola'} onChange={texto('number_format')} className={entrada}>
                                    {o.formatos_de_numero.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Casas decimais" erro={erros.decimal_places}>
                                <input type="number" min="0" max="4" value={forma.decimal_places} onChange={texto('decimal_places')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta="Arredondamento" erro={erros.rounding_mode}>
                                <select value={forma.rounding_mode ?? 'normal'} onChange={texto('rounding_mode')} className={entrada}>
                                    {o.modos_de_arredondamento.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>
                        <div className="mt-4">
                            <Interruptor etiqueta="Máscara nos preços (1.234,56) ao escrever" valor={forma.price_mask_enabled} aoMudar={(v) => mudar('price_mask_enabled', v)} />
                        </div>
                    </Cartao>
                </div>
            )}

            {separador === 'series' && (
                <Series ecra={ecra.data} podeEditar={podeEditar} aoMudar={() => fila.invalidateQueries({ queryKey: ['definicoes'] })} aoAvisar={(t) => porMensagem({ tom: 'bom', texto: t })} />
            )}

            {separador === 'impostos' && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <Cartao titulo="Impostos">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta="IVA padrão (%)" erro={erros.default_tax_rate} obrigatorio>
                                <input type="number" min="0" max="100" step="0.01" value={forma.default_tax_rate} onChange={texto('default_tax_rate')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta="IRT nos serviços (%)" erro={erros.default_irt_rate} obrigatorio>
                                <input type="number" min="0" max="100" step="0.01" value={forma.default_irt_rate} onChange={texto('default_irt_rate')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>
                        <div className="mt-4">
                            <Interruptor etiqueta="Reter IRT nas prestações de serviço" valor={forma.apply_irt_services} aoMudar={(v) => mudar('apply_irt_services', v)} />
                        </div>
                    </Cartao>

                    <Cartao titulo="Descontos e prazos">
                        <div className="space-y-2">
                            <Interruptor etiqueta="Permitir desconto por linha" valor={forma.allow_line_discounts} aoMudar={(v) => mudar('allow_line_discounts', v)} />
                            <Interruptor etiqueta="Permitir desconto comercial (antes do IVA)" valor={forma.allow_commercial_discount} aoMudar={(v) => mudar('allow_commercial_discount', v)} />
                            <Interruptor etiqueta="Permitir desconto financeiro (depois do IVA)" valor={forma.allow_financial_discount} aoMudar={(v) => mudar('allow_financial_discount', v)} />
                        </div>
                        <div className="mt-4 grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta="Desconto máximo (%)" erro={erros.max_discount_percent} obrigatorio>
                                <input type="number" min="0" max="100" step="0.01" value={forma.max_discount_percent} onChange={texto('max_discount_percent')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta="Validade das proformas (dias)" erro={erros.proforma_validity_days} obrigatorio>
                                <input type="number" min="1" value={forma.proforma_validity_days} onChange={texto('proforma_validity_days')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta="Vencimento das facturas (dias)" erro={erros.invoice_due_days} obrigatorio>
                                <input type="number" min="1" value={forma.invoice_due_days} onChange={texto('invoice_due_days')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>
                    </Cartao>
                </div>
            )}

            {separador === 'impressao' && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <Cartao titulo="Papel e cabeçalho">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {/* Dois valores e mais nenhum: isto decide o que sai na impressora de quem está ao balcão. */}
                            <Campo etiqueta="Papel da venda ao balcão" erro={erros.pos_formato_impressao}>
                                <select value={forma.pos_formato_impressao} onChange={texto('pos_formato_impressao')} className={entrada}>
                                    <option value="talao">Talão (80 mm)</option>
                                    <option value="a4">Factura A4</option>
                                </select>
                            </Campo>
                            <Campo etiqueta="Nome da empresa nos documentos" erro={erros.nome_nos_documentos} obrigatorio>
                                <select value={forma.nome_nos_documentos} onChange={texto('nome_nos_documentos')} className={entrada}>
                                    {o.nomes_nos_documentos.map((n) => <option key={n.valor} value={n.valor}>{n.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>
                        <div className="mt-4 space-y-2">
                            <Interruptor etiqueta="Mostrar o logótipo da empresa" valor={forma.show_company_logo} aoMudar={(v) => mudar('show_company_logo', v)} />
                            <Interruptor etiqueta="Imprimir automaticamente ao gravar" valor={forma.auto_print_after_save} aoMudar={(v) => mudar('auto_print_after_save', v)} />
                        </div>
                    </Cartao>

                    <Cartao titulo="Textos por omissão">
                        <div className="space-y-4">
                            <Campo etiqueta="Rodapé das facturas" erro={erros.invoice_footer_text}>
                                <textarea rows={2} value={forma.invoice_footer_text ?? ''} onChange={texto('invoice_footer_text')} className={cls(entrada, 'h-auto py-2')} />
                            </Campo>
                            <Campo etiqueta="Observações" erro={erros.default_notes}>
                                <textarea rows={2} value={forma.default_notes ?? ''} onChange={texto('default_notes')} className={cls(entrada, 'h-auto py-2')} />
                            </Campo>
                            <Campo etiqueta="Condições" erro={erros.default_terms}>
                                <textarea rows={2} value={forma.default_terms ?? ''} onChange={texto('default_terms')} className={cls(entrada, 'h-auto py-2')} />
                            </Campo>
                        </div>
                    </Cartao>
                </div>
            )}

            {separador === 'pos' && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <Cartao titulo="Comportamento do balcão">
                        <div className="space-y-2">
                            <Interruptor etiqueta="Imprimir automaticamente" valor={forma.pos_auto_print} aoMudar={(v) => mudar('pos_auto_print', v)} />
                            <Interruptor etiqueta="Sons ao adicionar e vender" valor={forma.pos_play_sounds} aoMudar={(v) => mudar('pos_play_sounds', v)} />
                            <Interruptor etiqueta="Fechar a venda sem passar pelo resumo" valor={forma.pos_auto_complete_sale} aoMudar={(v) => mudar('pos_auto_complete_sale', v)} />
                            <Interruptor etiqueta="Exigir cliente em todas as vendas" valor={forma.pos_require_customer} aoMudar={(v) => mudar('pos_require_customer', v)} />
                        </div>
                        <div className="mt-4">
                            <Campo etiqueta="Forma de pagamento inicial" erro={erros.pos_default_payment_method_id}>
                                <select value={forma.pos_default_payment_method_id ?? ''} onChange={numero('pos_default_payment_method_id')} className={entrada}>
                                    <option value="">Perguntar sempre</option>
                                    {o.formas_de_pagamento.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                                </select>
                            </Campo>
                        </div>
                    </Cartao>

                    <Cartao titulo="Stock e catálogo">
                        <div className="space-y-2">
                            <Interruptor etiqueta="Validar stock antes de vender" valor={forma.pos_validate_stock} aoMudar={(v) => mudar('pos_validate_stock', v)} />
                            <Interruptor etiqueta="Permitir vender para stock negativo" valor={forma.pos_allow_negative_stock} aoMudar={(v) => mudar('pos_allow_negative_stock', v)} />
                            <Interruptor etiqueta="Esconder o que está a zero" valor={forma.pos_hide_out_of_stock} aoMudar={(v) => mudar('pos_hide_out_of_stock', v)} />
                            <Interruptor etiqueta="Mostrar imagens dos artigos" valor={forma.pos_show_product_images} aoMudar={(v) => mudar('pos_show_product_images', v)} />
                        </div>
                        <div className="mt-4">
                            <Campo etiqueta="Artigos por página" erro={erros.pos_products_per_page}>
                                <input type="number" min="1" max="200" value={forma.pos_products_per_page} onChange={texto('pos_products_per_page')} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>
                    </Cartao>
                </div>
            )}

            {separador === 'pwa' && (
                <Cartao titulo="O menu do aparelho">
                    <p className="mb-4 text-sm text-slate-500">O que aparece no menu do PWA nos telemóveis e tablets da empresa. O Início vai sempre.</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {o.entradas_do_pwa.map((e) => (
                            <Interruptor
                                key={e.chave}
                                etiqueta={e.etiqueta}
                                icone={e.icone}
                                valor={forma.pwa_menu.includes(e.chave)}
                                aoMudar={(v) => mudar('pwa_menu', v ? [...forma.pwa_menu, e.chave] : forma.pwa_menu.filter((c) => c !== e.chave))}
                            />
                        ))}
                    </div>
                    {erros.pwa_menu?.[0] && <p role="alert" className="mt-2 text-sm text-red-700">{erros.pwa_menu[0]}</p>}
                </Cartao>
            )}

            {separador === 'perfil' && (
                <Cartao titulo="Perfil do negócio">
                    <p className="mb-4 text-sm text-slate-500">Podem estar todos ligados ao mesmo tempo — um supermercado com balcão de farmácia e prateleira de cosmética é as três coisas. Cada perfil liga as funções do seu sector.</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <Interruptor etiqueta="Farmácia" icone="fa-pills" valor={forma.profile_pharmacy} aoMudar={(v) => mudar('profile_pharmacy', v)} />
                        <Interruptor etiqueta="Vestuário e calçado" icone="fa-shirt" valor={forma.profile_clothing} aoMudar={(v) => mudar('profile_clothing', v)} />
                        <Interruptor etiqueta="Cosmética" icone="fa-spray-can-sparkles" valor={forma.profile_cosmetics} aoMudar={(v) => mudar('profile_cosmetics', v)} />
                        <Interruptor etiqueta="Mercearia e supermercado" icone="fa-basket-shopping" valor={forma.profile_grocery} aoMudar={(v) => mudar('profile_grocery', v)} />
                    </div>
                </Cartao>
            )}

            {separador !== 'series' && (
                <div className="sticky bottom-0 flex items-center justify-end gap-2 border-t border-slate-200 bg-white/90 py-3 backdrop-blur">
                    {!podeEditar && <span className="mr-auto text-sm text-slate-500">Só pode ver — não tem permissão para editar.</span>}
                    <Botao cor="primaria" tom="solida" altura="grande" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} disabled={!podeEditar} onClick={() => guardar.mutate(forma)}>
                        Guardar definições
                    </Botao>
                </div>
            )}
        </div>
    );
}

/* ─── As séries ─────────────────────────────────────────────────────────── */

function Series({ ecra, podeEditar, aoMudar, aoAvisar }: { ecra: EcraDasDefinicoes; podeEditar: boolean; aoMudar: () => void; aoAvisar: (t: string) => void }) {
    const [modal, porModal] = useState<ModalDeSerie | null>(null);
    const [codigo, porCodigo] = useState('');
    const [nome, porNome] = useState('');
    const [descricao, porDescricao] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [confirmacao, porConfirmacao] = useState<{ serie: Serie; aviso: AvisoDeNumeracao } | null>(null);

    const abrirNova = (tipo: string, prefixo: string | null, nomeDoTipo: string) => {
        porCodigo(''); porNome(''); porDescricao(''); porErros({});
        porModal({ modo: 'nova', tipo, prefixo, nome: nomeDoTipo });
    };

    const abrirRenomear = (serie: Serie) => {
        porCodigo(serie.series_code); porNome(serie.name); porDescricao(serie.description ?? ''); porErros({});
        porModal({ modo: 'renomear', serie });
    };

    const gravar = useMutation({
        mutationFn: () => {
            if (!modal) throw new Error('sem modal');
            return modal.modo === 'nova'
                ? api.criarSerie({ tipo: modal.tipo, codigo, nome: nome || undefined, descricao: descricao || undefined })
                : api.renomearSerie(modal.serie.id, { codigo, nome: nome || undefined, descricao: descricao || undefined });
        },
        onSuccess: (r) => { porModal(null); aoAvisar(r.message); aoMudar(); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const padrao = useMutation({
        mutationFn: ({ serie, confirmado }: { serie: Serie; confirmado: boolean }) => api.tornarPadrao(serie.id, confirmado).then((r) => ({ serie, r })),
        onSuccess: ({ serie, r }) => {
            if (r.aviso) {
                porConfirmacao({ serie, aviso: r.aviso });
                return;
            }
            porConfirmacao(null);
            if (r.message) aoAvisar(r.message);
            aoMudar();
        },
    });

    const { tipos, por_tipo: porTipo, outras } = ecra.series;

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={padrao.error} />

            <div className="grid gap-4 md:grid-cols-2">
                {tipos.map((t) => (
                    <Cartao
                        key={t.tipo}
                        titulo={
                            <span className="flex items-center gap-2">
                                <i className={cls('fas', 'fa-' + t.icone, 'text-slate-400')} aria-hidden="true" />
                                {t.nome}
                                {t.prefixo && <Etiqueta>{t.prefixo}</Etiqueta>}
                            </span>
                        }
                        accoes={podeEditar && <Botao icone="fa-plus" onClick={() => abrirNova(t.tipo, t.prefixo, t.nome)}>Nova série</Botao>}
                    >
                        <ListaDeSeries series={porTipo[t.tipo] ?? []} podeEditar={podeEditar} aRenomear={abrirRenomear} aTornarPadrao={(s) => padrao.mutate({ serie: s, confirmado: false })} />
                    </Cartao>
                ))}
            </div>

            {outras.length > 0 && (
                <Cartao titulo="Outras séries" >
                    <p className="mb-3 text-xs text-slate-500">Tipos de documento que não têm cartão próprio acima.</p>
                    <ListaDeSeries series={outras} podeEditar={podeEditar} aRenomear={abrirRenomear} aTornarPadrao={(s) => padrao.mutate({ serie: s, confirmado: false })} comTipo />
                </Cartao>
            )}

            <Modal
                aberto={modal !== null}
                aoFechar={() => porModal(null)}
                titulo={modal?.modo === 'renomear' ? `Renomear a série ${modal.serie.series_code}` : `Nova série · ${modal?.nome ?? ''}`}
                rodape={
                    <>
                        <Botao onClick={() => porModal(null)}>Cancelar</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>Guardar série</Botao>
                    </>
                }
            >
                <AvisoDeErro erro={gravar.error} />
                <div className="grid gap-4 sm:grid-cols-3">
                    {/* O prefixo mostra-se, não se edita: vem do catálogo. */}
                    <Campo etiqueta="Prefixo">
                        <input value={(modal?.modo === 'nova' ? modal.prefixo : modal?.serie.prefix) ?? '—'} readOnly className={cls(entrada, 'bg-slate-50 text-slate-500')} />
                    </Campo>
                    <Campo etiqueta="Código" erro={erros.codigo} obrigatorio className="sm:col-span-2">
                        <input value={codigo} onChange={(e) => porCodigo(e.target.value.toUpperCase())} maxLength={10} className={entrada} />
                    </Campo>
                    <Campo etiqueta="Nome" erro={erros.nome} className="sm:col-span-3">
                        <input value={nome} onChange={(e) => porNome(e.target.value)} maxLength={100} placeholder="Deixe vazio para o nome automático" className={entrada} />
                    </Campo>
                    <Campo etiqueta="Descrição" erro={erros.descricao} className="sm:col-span-3">
                        <input value={descricao} onChange={(e) => porDescricao(e.target.value)} maxLength={500} className={entrada} />
                    </Campo>
                </div>
            </Modal>

            {/* Abrir uma segunda numeração é uma decisão, não um clique. */}
            <Modal
                aberto={confirmacao !== null}
                aoFechar={() => porConfirmacao(null)}
                titulo="Isto abre uma segunda numeração"
                rodape={
                    <>
                        <Botao onClick={() => porConfirmacao(null)}>Cancelar</Botao>
                        <Botao cor="aviso" tom="solida" icone="fa-check" aTrabalhar={padrao.isPending} onClick={() => confirmacao && padrao.mutate({ serie: confirmacao.serie, confirmado: true })}>Sim, mudar de série</Botao>
                    </>
                }
            >
                {confirmacao && (
                    <div className="space-y-2 text-sm text-slate-700">
                        <p>
                            A série <strong>{confirmacao.aviso.em_uso}</strong> já vai no número <strong>{confirmacao.aviso.em_uso_proximo}</strong>, e a <strong>{confirmacao.aviso.nova}</strong> ainda está por estrear.
                        </p>
                        <p>Passar o padrão agora deixa duas sequências do mesmo tipo de documento a andar em paralelo no mesmo exercício — o que não passa num SAFT. Pode ser deliberado (mudar de série no início do ano), mas tem de ser uma decisão tomada.</p>
                    </div>
                )}
            </Modal>
        </div>
    );
}

function ListaDeSeries({ series, podeEditar, aRenomear, aTornarPadrao, comTipo = false }: {
    series: Serie[];
    podeEditar: boolean;
    aRenomear: (s: Serie) => void;
    aTornarPadrao: (s: Serie) => void;
    comTipo?: boolean;
}) {
    if (series.length === 0) return <p className="text-sm text-slate-400">Sem séries activas.</p>;

    return (
        <ul className="divide-y divide-slate-100">
            {series.map((s) => (
                <li key={s.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm">
                    <span className="font-mono font-semibold text-slate-900">{s.series_code}</span>
                    <span className="text-slate-600">{s.name}</span>
                    {comTipo && <Etiqueta>{s.document_type}</Etiqueta>}
                    <span className="text-xs text-slate-400">próximo nº {s.next_number}</span>
                    {s.is_default && <Etiqueta cor="bom" icone="fa-star">Padrão</Etiqueta>}
                    {s.agt_series_id && <Etiqueta cor="primaria" icone="fa-shield">AGT {s.agt_series_id}</Etiqueta>}
                    {podeEditar && (
                        <span className="ml-auto flex gap-1">
                            {s.pode_renomear && (
                                <button type="button" onClick={() => aRenomear(s)} aria-label={`Renomear a série ${s.series_code}`} className={cls('px-2 py-1 text-xs text-slate-500 hover:bg-slate-100', RAIO, FOCO)}>
                                    <i className="fas fa-pen" aria-hidden="true" />
                                </button>
                            )}
                            {!s.is_default && (
                                <button type="button" onClick={() => aTornarPadrao(s)} className={cls('px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50', RAIO, FOCO)}>
                                    Tornar padrão
                                </button>
                            )}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}

/* ─── Um interruptor ────────────────────────────────────────────────────── */

function Interruptor({ etiqueta, valor, aoMudar, icone }: { etiqueta: ReactNode; valor: boolean; aoMudar: (v: boolean) => void; icone?: string }) {
    return (
        <label className={cls('flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50', RAIO)}>
            <input type="checkbox" checked={valor} onChange={(e) => aoMudar(e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
            {icone && <i className={cls('fas w-4 text-center text-slate-400', icone)} aria-hidden="true" />}
            {etiqueta}
        </label>
    );
}
