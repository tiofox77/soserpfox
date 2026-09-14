import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { adiantamentos, type Adiantamento } from '@/api/adiantamentos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { useImprimirAoGravar } from './imprimirAoGravar';
import { Aviso, NaoAbriu, PainelDeSucesso, PapelBloqueado } from './PecasDoEditor';

/**
 * REGISTAR UM ADIANTAMENTO — dinheiro recebido de um cliente antes de haver
 * factura. Nasce disponível por inteiro e vai sendo usado ao pagar facturas.
 *
 * Um adiantamento já usado não se edita: a regra é do servidor
 * (`EmissorDeAdiantamentos`), o ecrã só a mostra. Com `id` nas props abre em
 * edição.
 */
export default function EmitirAdiantamento({ id }: { id?: number }) {
    const [clienteId, porClienteId] = useState('');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [valor, porValor] = useState('');
    const [forma, porForma] = useState('cash');
    const [finalidade, porFinalidade] = useState('');
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<Adiantamento | null>(null);

    const opcoes = useQuery({ queryKey: ['adiantamentos', 'opcoes'], queryFn: adiantamentos.opcoes, staleTime: 5 * 60_000 });
    const existente = useQuery({ queryKey: ['adiantamentos', id], queryFn: () => adiantamentos.mostrar(id as number), enabled: !!id });
    /* O PDF abre sozinho ao registar, se a empresa o pediu — ver `imprimirAoGravar`. */
    const impressao = useImprimirAoGravar(opcoes.data?.imprimir_ao_gravar);

    /* Em edição, a forma nasce do servidor. */
    useEffect(() => {
        const a = existente.data?.data;
        if (!a) return;
        porClienteId(String(a.client_id));
        porDia(a.payment_date);
        porValor(String(a.amount));
        porForma(a.payment_method);
        porFinalidade(a.purpose ?? '');
        porNotas(a.notes ?? '');
    }, [existente.data]);

    const guardar = useMutation({
        mutationFn: () => {
            const corpo = {
                client_id: Number(clienteId) || null,
                payment_date: dia,
                amount: Number(valor) || 0,
                payment_method: forma,
                purpose: finalidade || null,
                notes: notas || null,
            };
            return id ? adiantamentos.actualizar(id, corpo) : adiantamentos.guardar(corpo);
        },
        onSuccess: (r) => {
            porFeito(r.data);
            porErros({});
            // O adiantamento não tem rascunho: registado é o papel do dinheiro recebido.
            impressao.depoisDeGravar(r.data.pdf);
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending || (id && existente.isPending)) return <Carregando linhas={6} />;

    if (opcoes.isError || existente.isError) {
        const erro = opcoes.error ?? existente.error;
        return (
            <NaoAbriu
                titulo={t('Não foi possível abrir o adiantamento')}
                mensagem={erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}
            />
        );
    }

    if (feito) {
        return (
            <PainelDeSucesso
                numero={feito.numero}
                mensagem={t(':valor Kz disponíveis para abater em facturas.', { valor: kz(feito.amount) })}
                icone="fa-hand-holding-dollar"
                aviso={impressao.bloqueado && <PapelBloqueado />}
            >
                <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(feito.pdf, '_blank')}>{t('PDF')}</Botao>
                <Botao icone="fa-list" onClick={() => (window.location.href = feito.abrir)}>{t('Ver adiantamentos')}</Botao>
                {!id && <Botao icone="fa-plus" onClick={() => { porFeito(null); impressao.esquecer(); porClienteId(''); porValor(''); porFinalidade(''); porNotas(''); }}>{t('Registar outro')}</Botao>}
            </PainelDeSucesso>
        );
    }

    const o = opcoes.data;
    const bloqueado = id ? !(existente.data?.data.pode_editar ?? true) : false;

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            {bloqueado && (
                <Aviso icone="fa-lock">{t('Este adiantamento já foi usado em facturas. Já não se edita.')}</Aviso>
            )}

            <Cartao
                titulo={id ? t('Editar :numero', { numero: existente.data?.data.numero ?? '' }) : t('Novo adiantamento')}
                icone="fa-hand-holding-dollar"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Cliente')} erro={erros.client_id} obrigatorio className="sm:col-span-2">
                        <select value={clienteId} onChange={(e) => porClienteId(e.target.value)} disabled={bloqueado} className={entrada}>
                            <option value="">{t('Escolher…')}</option>
                            {o.clientes.map((c) => <option key={c.id} value={c.id}>{c.name}{c.nif ? ` · ${c.nif}` : ''}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Data do pagamento')} erro={erros.payment_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} disabled={bloqueado} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Valor (Kz)')} erro={erros.amount} obrigatorio>
                        <input type="number" min="0.01" step="0.01" value={valor} onChange={(e) => porValor(e.target.value)} disabled={bloqueado} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                    <Campo etiqueta={t('Forma de pagamento')} erro={erros.payment_method} obrigatorio>
                        <select value={forma} onChange={(e) => porForma(e.target.value)} disabled={bloqueado} className={entrada}>
                            {o.formas.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Finalidade')} erro={erros.purpose}>
                        <input value={finalidade} onChange={(e) => porFinalidade(e.target.value)} disabled={bloqueado} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Observações')} erro={erros.notes} className="sm:col-span-2">
                        <textarea rows={2} value={notas} onChange={(e) => porNotas(e.target.value)} disabled={bloqueado} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                </div>
            </Cartao>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = '/invoicing/advances')}>{t('Cancelar')}</Botao>
                <Botao cor="primaria" tom="solida" altura="grande" icone="fa-hand-holding-dollar" aTrabalhar={guardar.isPending} disabled={bloqueado || !o.permissoes.pode_criar} onClick={() => guardar.mutate()}>
                    {id ? t('Guardar') : t('Registar adiantamento')}
                </Botao>
            </div>
        </div>
    );
}
