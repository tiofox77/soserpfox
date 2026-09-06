import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { adiantamentos, type Adiantamento } from '@/api/adiantamentos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

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
        onSuccess: (r) => { porFeito(r.data); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending || (id && existente.isPending)) return <Carregando linhas={6} />;

    if (opcoes.isError || existente.isError) {
        const erro = opcoes.error ?? existente.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o adiantamento')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    if (feito) {
        return (
            <div className={cls(CARTAO, 'p-8 text-center')}>
                <i className="fas fa-circle-check mb-3 text-4xl text-emerald-500" aria-hidden="true" />
                <h2 className="text-xl font-bold text-slate-900">{feito.numero}</h2>
                <p className="mt-1 text-sm text-slate-500">{t(':valor Kz disponíveis para abater em facturas.', { valor: kz(feito.amount) })}</p>
                <div className="mt-6 flex justify-center gap-2">
                    <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(feito.pdf, '_blank')}>{t('PDF')}</Botao>
                    <Botao icone="fa-list" onClick={() => (window.location.href = feito.abrir)}>{t('Ver adiantamentos')}</Botao>
                    {!id && <Botao icone="fa-plus" onClick={() => { porFeito(null); porClienteId(''); porValor(''); porFinalidade(''); porNotas(''); }}>{t('Registar outro')}</Botao>}
                </div>
            </div>
        );
    }

    const o = opcoes.data;
    const bloqueado = id ? !(existente.data?.data.pode_editar ?? true) : false;

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            {bloqueado && (
                <p role="alert" className={cls('border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                    {t('Este adiantamento já foi usado em facturas. Já não se edita.')}
                </p>
            )}

            <Cartao titulo={id ? t('Editar :numero', { numero: existente.data?.data.numero ?? '' }) : t('Novo adiantamento')}>
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
