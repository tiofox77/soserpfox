import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { FormularioDoMovimento, Movimento, OpcoesDosMovimentos } from '@/api/tesouraria';
import { movimentos } from '@/api/tesouraria';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { RAIO, cls } from '@/ui/tokens';

/**
 * LANÇAR OU CORRIGIR UM MOVIMENTO.
 *
 * Os mesmos treze campos do modal em Blade, na mesma ordem, com as mesmas
 * regras — incluindo as três que o ecrã fazia por si e que aqui continuam a
 * fazer-se, mas sem uma ida ao servidor por cada tecla:
 *
 *  · ESCOLHER A FORMA DE PAGAMENTO PREENCHE O DESTINO. Dinheiro cai no caixa
 *    que o método declara; o resto na conta bancária. É o `default_account_id`
 *    / `default_cash_register_id` do próprio método.
 *
 *  · O TIPO MANDA NA NATUREZA. Entrada ou saída não é uma escolha à parte: é
 *    o que o tipo de movimento declara, e é isso que decide o sinal no saldo.
 *
 *  · UMA CATEGORIA DE OUTRO TIPO NÃO FICA PENDURADA. Ao mudar de tipo, a
 *    categoria que pertencia ao anterior sai — senão gravava-se um movimento
 *    com uma classificação que os relatórios daquele tipo nunca mostram.
 *
 * E o destino é UM. Conta OU caixa, nunca os dois nem nenhum: com os dois o
 * servidor escolhia a conta e ignorava o caixa em silêncio.
 */
export function ModalDoMovimento({
    aberto,
    o,
    aEditar,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    o: OpcoesDosMovimentos;
    /** Nulo cria; com movimento, edita. */
    aEditar: Movimento | null;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState<FormularioDoMovimento>(() => vazio(o));
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aGravar, porAGravar] = useState(false);
    const [recado, porRecado] = useState('');

    useEffect(() => {
        if (!aberto) return;

        porErros({});
        porRecado('');
        porF(aEditar ? deMovimento(aEditar) : vazio(o));
    }, [aberto, aEditar, o]);

    const tipoEscolhido = o.tipos.find((x) => x.id === Number(f.transaction_type_id));

    /*
     * AS CATEGORIAS QUE SERVEM A ESTE TIPO. As que não declaram tipo servem
     * sempre; as outras só ao seu. O ecrã de sempre ia ao servidor buscar esta
     * lista a cada troca — aqui vieram todas de uma vez.
     */
    const categorias = o.categorias.filter(
        (c) => c.tipo_id === null || c.tipo_id === Number(f.transaction_type_id),
    );

    function mudarTipo(id: string) {
        porF((a) => {
            const escolhida = o.categorias.find((c) => c.id === Number(a.transaction_category_id));
            const serve = !escolhida || escolhida.tipo_id === null || escolhida.tipo_id === Number(id);

            return {
                ...a,
                transaction_type_id: id ? Number(id) : '',
                transaction_category_id: serve ? a.transaction_category_id : '',
            };
        });
    }

    function mudarForma(id: string) {
        const m = o.formas_de_pagamento.find((x) => x.id === Number(id));

        porF((a) => ({
            ...a,
            payment_method_id: id ? Number(id) : '',
            // Sem método escolhido não se mexe no destino que já lá estiver.
            ...(m
                ? m.tipo === 'cash'
                    ? { account_id: '' as const, cash_register_id: m.caixa_padrao ?? ('' as const) }
                    : { account_id: m.conta_padrao ?? ('' as const), cash_register_id: '' as const }
                : {}),
        }));
    }

    const semDestino = !f.account_id && !f.cash_register_id;
    const destinoDuplo = Boolean(f.account_id) && Boolean(f.cash_register_id);

    /*
     * O AVISO SÓ ACENDE QUANDO HÁ ALGO A AVISAR.
     *
     * Um formulário acabado de abrir não tem destino nenhum — é o normal, não
     * é um erro. Pintá-lo de âmbar logo à entrada ensina a ignorar o aviso, e
     * então ele já não serve para o caso em que faz falta. Acende quando a
     * forma de pagamento já foi escolhida e mesmo assim não há destino, ou
     * quando estão os dois.
     */
    const avisar = destinoDuplo || (Boolean(f.payment_method_id) && semDestino);

    async function gravar(e: React.FormEvent) {
        e.preventDefault();
        porErros({});
        porRecado('');

        if (semDestino || destinoDuplo) {
            porRecado(t('Seleccione exactamente um destino: conta bancária ou caixa.'));
            return;
        }

        porAGravar(true);

        try {
            const r = aEditar
                ? await movimentos.actualizar(aEditar.id, f)
                : await movimentos.criar(f);

            aoGravar(r.message);
        } catch (erro) {
            if (erro instanceof ErroDaApi) {
                porErros(erro.erros);
                porRecado(erro.message);
            } else {
                porRecado(t('Não foi possível gravar. Verifique a ligação.'));
            }
        } finally {
            porAGravar(false);
        }
    }

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={aEditar ? t('Editar Transação') : t('Nova Transação')}
            subtitulo={aEditar ? aEditar.numero : undefined}
            icone="fa-exchange-alt"
            cor="teal"
            largura="xl"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times" disabled={aGravar}>
                        {t('Cancelar')}
                    </Botao>
                    <Botao
                        form="movimento"
                        type="submit"
                        cor="bom"
                        tom="solida"
                        icone="fa-save"
                        aTrabalhar={aGravar}
                    >
                        {aEditar ? t('Atualizar Transação') : t('Criar Transação')}
                    </Botao>
                </>
            }
        >
            <form id="movimento" onSubmit={gravar} className="space-y-5">
                {recado && (
                    <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {recado}
                    </div>
                )}

                <div className="grid gap-5 md:grid-cols-2">
                    <Campo etiqueta={t('Tipo')} obrigatorio erro={erros.transaction_type_id}>
                        <select
                            value={f.transaction_type_id}
                            onChange={(e) => mudarTipo(e.target.value)}
                            className={entrada}
                        >
                            <option value="">{t('Selecione o tipo')}</option>
                            {o.tipos.map((x) => (
                                <option key={x.id} value={x.id}>{x.nome}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo
                        etiqueta={t('Categoria')}
                        erro={erros.transaction_category_id}
                        ajuda={
                            tipoEscolhido
                                ? t('Só as categorias de :tipo.', { tipo: tipoEscolhido.nome })
                                : undefined
                        }
                    >
                        <select
                            value={f.transaction_category_id}
                            onChange={(e) =>
                                porF((a) => ({ ...a, transaction_category_id: e.target.value ? Number(e.target.value) : '' }))
                            }
                            className={entrada}
                        >
                            <option value="">{t('Selecione a categoria')}</option>
                            {categorias.map((c) => (
                                <option key={c.id} value={c.id}>{c.nome}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Valor')} obrigatorio erro={erros.amount}>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={f.amount}
                            onChange={(e) => porF((a) => ({ ...a, amount: e.target.value }))}
                            placeholder="0.00"
                            className={cls(entrada, 'text-right tabular-nums font-semibold')}
                        />
                    </Campo>

                    <Campo etiqueta={t('Moeda')} obrigatorio erro={erros.currency}>
                        <select
                            value={f.currency}
                            onChange={(e) => porF((a) => ({ ...a, currency: e.target.value }))}
                            className={entrada}
                        >
                            {o.moedas.map((m) => (
                                <option key={m} value={m}>{m}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Data da Transação')} obrigatorio erro={erros.transaction_date}>
                        <input
                            type="date"
                            value={f.transaction_date}
                            onChange={(e) => porF((a) => ({ ...a, transaction_date: e.target.value }))}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Método de Pagamento')} obrigatorio erro={erros.payment_method_id}>
                        <select
                            value={f.payment_method_id}
                            onChange={(e) => mudarForma(e.target.value)}
                            className={entrada}
                        >
                            <option value="">{t('Selecione')}</option>
                            {o.formas_de_pagamento.map((m) => (
                                <option key={m.id} value={m.id}>{m.nome}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Conta Bancária')} erro={erros.account_id}>
                        <select
                            value={f.account_id}
                            onChange={(e) =>
                                porF((a) => ({
                                    ...a,
                                    account_id: e.target.value ? Number(e.target.value) : '',
                                    // Escolher a conta larga o caixa: o destino é um só.
                                    cash_register_id: e.target.value ? '' : a.cash_register_id,
                                }))
                            }
                            className={cls(entrada, destinoDuplo && 'border-amber-400')}
                        >
                            <option value="">{t('Selecione')}</option>
                            {o.contas.map((c) => (
                                <option key={c.id} value={c.id}>{c.nome}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Caixa')} erro={erros.cash_register_id}>
                        <select
                            value={f.cash_register_id}
                            onChange={(e) =>
                                porF((a) => ({
                                    ...a,
                                    cash_register_id: e.target.value ? Number(e.target.value) : '',
                                    account_id: e.target.value ? '' : a.account_id,
                                }))
                            }
                            className={cls(entrada, destinoDuplo && 'border-amber-400')}
                        >
                            <option value="">{t('Selecione')}</option>
                            {o.caixas.map((c) => (
                                <option key={c.id} value={c.id}>{c.nome}</option>
                            ))}
                        </select>
                    </Campo>

                    {/* O AVISO DO DESTINO. Estava no modal de sempre e é o que
                        evita a pergunta «porque é que o saldo não mexeu?». */}
                    <div
                        className={cls(
                            'md:col-span-2 border px-4 py-3 text-sm',
                            RAIO,
                            avisar
                                ? 'border-amber-300 bg-amber-50 text-amber-900'
                                : 'border-blue-200 bg-blue-50 text-blue-800',
                        )}
                    >
                        <i
                            className={cls('mr-1 fas', avisar ? 'fa-triangle-exclamation' : 'fa-info-circle')}
                            aria-hidden="true"
                        />
                        {t('Seleccione uma conta bancária ou um caixa, nunca os dois. O destino configurado no método de pagamento é preenchido automaticamente e determina onde o saldo será actualizado.')}
                    </div>

                    <Campo etiqueta={t('Referência')} erro={erros.reference}>
                        <input
                            type="text"
                            value={f.reference}
                            onChange={(e) => porF((a) => ({ ...a, reference: e.target.value }))}
                            placeholder="REF-001"
                            className={entrada}
                        />
                    </Campo>

                    <Campo
                        etiqueta={t('Status')}
                        obrigatorio
                        erro={erros.status}
                        ajuda={t('Só as concluídas mexem no saldo.')}
                    >
                        <select
                            value={f.status}
                            onChange={(e) =>
                                porF((a) => ({ ...a, status: e.target.value as FormularioDoMovimento['status'] }))
                            }
                            className={entrada}
                        >
                            <option value="pending">{t('Pendente')}</option>
                            <option value="completed">{t('Concluído')}</option>
                            <option value="cancelled">{t('Cancelado')}</option>
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Descrição')} obrigatorio erro={erros.description} className="md:col-span-2">
                        <textarea
                            rows={2}
                            value={f.description}
                            onChange={(e) => porF((a) => ({ ...a, description: e.target.value }))}
                            placeholder={t('Descrição da transação')}
                            className={cls(entrada, 'h-auto py-2')}
                        />
                    </Campo>

                    <Campo etiqueta={t('Notas')} erro={erros.notes} className="md:col-span-2">
                        <textarea
                            rows={2}
                            value={f.notes}
                            onChange={(e) => porF((a) => ({ ...a, notes: e.target.value }))}
                            placeholder={t('Observações adicionais')}
                            className={cls(entrada, 'h-auto py-2')}
                        />
                    </Campo>
                </div>
            </form>
        </Modal>
    );
}

/** Hoje, na hora de cá. `toISOString()` recua um dia em Angola (UTC+1). */
function hoje(): string {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function vazio(o: OpcoesDosMovimentos): FormularioDoMovimento {
    // O tipo de omissão é o primeiro de ENTRADA, como no ecrã de sempre: a
    // esmagadora maioria do que se lança à mão é dinheiro que entra.
    const entrada = o.tipos.find((x) => x.natureza === 'income');

    return {
        transaction_type_id: entrada?.id ?? '',
        transaction_category_id: '',
        amount: '',
        currency: o.moedas[0] ?? 'AOA',
        transaction_date: hoje(),
        payment_method_id: '',
        account_id: '',
        cash_register_id: '',
        reference: '',
        description: '',
        notes: '',
        status: 'completed',
    };
}

function deMovimento(m: Movimento): FormularioDoMovimento {
    return {
        transaction_type_id: m.transaction_type_id ?? '',
        transaction_category_id: m.transaction_category_id ?? '',
        amount: m.valor,
        currency: m.moeda,
        transaction_date: m.data ?? hoje(),
        payment_method_id: m.payment_method_id ?? '',
        account_id: m.account_id ?? '',
        cash_register_id: m.cash_register_id ?? '',
        reference: m.referencia ?? '',
        description: m.descricao ?? '',
        notes: m.notas ?? '',
        status: m.estado,
    };
}
