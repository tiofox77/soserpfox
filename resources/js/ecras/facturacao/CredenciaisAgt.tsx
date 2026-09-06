import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { agt, type Contribuinte } from '@/api/agt';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * A FICHA DO CONTRIBUINTE NA AGT: o NIF, o estabelecimento, os avisos e o
 * comportamento ao emitir. É a mesma ficha que o ecrã de sempre grava,
 * pela mesma `GestaoAgt`.
 *
 * O que fica de fora, de propósito: a chave privada «do modo antigo», sem
 * ambiente. As chaves gerem-se por ambiente em AGT Angola — e mudar o
 * ambiente que emite também é lá, com a regra das chaves.
 */
export default function CredenciaisAgt() {
    const q = useQuery({ queryKey: ['agt', 'contribuinte'], queryFn: () => agt.contribuinte() });
    const opcoes = useQuery({ queryKey: ['agt', 'opcoes'], queryFn: agt.opcoes, staleTime: 5 * 60_000 });

    if (q.isPending || opcoes.isPending) return <Carregando linhas={8} />;
    if (q.isError || opcoes.isError) {
        const erro = q.error ?? opcoes.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a ficha do contribuinte')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    return <Ficha inicial={q.data.data} podeEditar={q.data.permissoes.pode_editar} cae={opcoes.data.cae} />;
}

function Ficha({ inicial, podeEditar, cae }: { inicial: Contribuinte; podeEditar: boolean; cae: Array<{ codigo: string; descricao: string }> }) {
    const cache = useQueryClient();
    const [forma, porForma] = useState({
        tax_registration_number: inicial.tax_registration_number,
        agt_establishment_number: inicial.agt_establishment_number,
        agt_notification_emails: inicial.agt_notification_emails,
        agt_eac_code: inicial.agt_eac_code ?? '',
        agt_auto_submit: inicial.agt_auto_submit,
        agt_require_validation: inicial.agt_require_validation,
    });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [recado, porRecado] = useState('');
    const [ligacao, porLigacao] = useState<{ success?: boolean; error?: string } | null>(null);

    const gravar = useMutation({
        mutationFn: () => agt.guardarContribuinte(forma),
        onSuccess: (r) => { porRecado(r.message); porErros({}); void cache.invalidateQueries({ queryKey: ['agt'] }); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });
    const testar = useMutation({ mutationFn: () => agt.testarLigacao(inicial.agt_environment), onSuccess: (r) => porLigacao(r.data), onError: (e) => porLigacao({ success: false, error: e instanceof ErroDaApi ? e.message : t('Não foi possível testar.') }) });

    const m = (chave: keyof typeof forma) => (ev: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => porForma({ ...forma, [chave]: ev.target.value });
    const ambiente = inicial.agt_environment === 'production' ? t('Produção') : t('Homologação');

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}
            <AvisoDeErro erro={gravar.error} />

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-id-card text-slate-400" aria-hidden="true" />{t('Contribuinte na AGT')}</span>}
                accoes={<span className="flex gap-2"><Botao icone="fa-plug" aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>{t('Testar ligação')}</Botao>{podeEditar && <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao>}</span>}
            >
                <div className="mb-4 flex flex-wrap gap-2" data-estado>
                    <Etiqueta cor="primaria" icone="fa-bolt">{t('A emitir em')} {ambiente}</Etiqueta>
                    <Etiqueta cor={inicial.produtor ? 'bom' : 'aviso'} icone="fa-building">{inicial.produtor ? t('Produtor configurado') : t('Produtor por configurar')}</Etiqueta>
                    {inicial.chave_legado && <Etiqueta icone="fa-key">{t('Chave do modo antigo presente')}</Etiqueta>}
                </div>
                {ligacao && (
                    <p role="status" className={cls('mb-4 border px-4 py-3 text-sm', RAIO, ligacao.success ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                        {ligacao.success ? t('A AGT respondeu.') : ligacao.error ?? t('A AGT não respondeu.')}
                    </p>
                )}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('NIF do contribuinte')} erro={erros.tax_registration_number}><input value={forma.tax_registration_number} onChange={m('tax_registration_number')} maxLength={15} disabled={!podeEditar} className={cls(entrada, 'font-mono')} /></Campo>
                    <Campo etiqueta={t('Estabelecimento')} erro={erros.agt_establishment_number} obrigatorio><input value={forma.agt_establishment_number} onChange={m('agt_establishment_number')} maxLength={200} disabled={!podeEditar} className={entrada} /></Campo>
                    <Campo etiqueta={t('Código CAE (classe)')} erro={erros.agt_eac_code} className="sm:col-span-2">
                        <select value={forma.agt_eac_code} onChange={m('agt_eac_code')} disabled={!podeEditar} className={entrada}>
                            <option value="">{t('— sem código —')}</option>
                            {cae.map((c) => <option key={c.codigo} value={c.codigo}>{c.codigo} · {c.descricao}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('E-mails de aviso de erros da AGT')} erro={erros.agt_notification_emails} className="sm:col-span-2"><input value={forma.agt_notification_emails} onChange={m('agt_notification_emails')} placeholder="um@empresa.ao, outro@empresa.ao" disabled={!podeEditar} className={entrada} /></Campo>
                    <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.agt_auto_submit} disabled={!podeEditar} onChange={(ev) => porForma({ ...forma, agt_auto_submit: ev.target.checked })} className="h-4 w-4 rounded border-slate-300" />{t('Enviar à AGT automaticamente ao emitir')}</label>
                    <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.agt_require_validation} disabled={!podeEditar} onChange={(ev) => porForma({ ...forma, agt_require_validation: ev.target.checked })} className="h-4 w-4 rounded border-slate-300" />{t('Exigir validação prévia')}</label>
                </div>
                <p className="mt-4 text-sm text-slate-500">{t('As chaves RSA e o ambiente que emite gerem-se em')} <a href="/invoicing/agt-settings" className="font-semibold text-indigo-700 underline">AGT Angola</a>, {t('por ambiente.')}</p>
            </Cartao>
        </div>
    );
}
