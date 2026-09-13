import { useMemo, useState } from 'react';

import { t } from '@/i18n';

import { useAccao } from '../../ganchos';
import { refreshPendingCount } from '../../motor/fila';
import { createClientOffline } from '../../motor/vendas';
import { avisar } from '../../ui/Dialogos';
import { CAMPO, Folha, ROTULO } from '../../ui/Folha';
import { Camada } from './Camada';
import type { ControloDoPos } from './usePos';

/** A folha de escolher o cliente da venda. */
export function FolhaEscolherCliente({ pos }: { pos: ControloDoPos }) {
    const s = pos.pesquisaCliente.toLowerCase().trim();
    // Cinquenta chegam: ao balcão procura-se, não se percorre a lista.
    const lista = useMemo(() => (!s
        ? pos.clientes.slice(0, 50)
        : pos.clientes.filter((c) => String(c.name ?? '').toLowerCase().includes(s) || String(c.nif ?? '').toLowerCase().includes(s)).slice(0, 50)),
    [pos.clientes, s]);

    const fechar = () => pos.setEscolherCliente(false);
    const escolher = (c: (typeof pos.clientes)[number] | null) => { pos.selecionarCliente(c); fechar(); };

    return (
        <Camada>
            <Folha aberta={pos.escolherCliente} aoFechar={fechar} titulo={t('Escolher cliente')} icone="fa-user" zIndex="z-[60]" largura="sm:max-w-md"
                   rodape={(
                       <button type="button" onClick={pos.abrirCriarCliente}
                               className="pwa-toque w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white text-center font-bold text-sm shadow-lg transition">
                           <i className="fas fa-user-plus mr-1" aria-hidden="true" />{t('Criar novo cliente')}
                       </button>
                   )}>
                <div className="-mt-1 space-y-2">
                    <div className="flex items-center gap-2 bg-slate-50 border-2 border-slate-200 focus-within:border-blue-500 rounded-xl px-3 transition">
                        <i className="fas fa-magnifying-glass text-gray-400" aria-hidden="true" />
                        <input type="search" name="pesquisa-cliente" autoFocus autoComplete="off"
                               aria-label={t('Pesquisar cliente…')} placeholder={t('Pesquisar cliente…')}
                               value={pos.pesquisaCliente} onChange={(e) => pos.setPesquisaCliente(e.target.value)}
                               className="flex-1 min-w-0 py-2.5 text-sm bg-transparent focus:outline-none" />
                    </div>

                    <div className="space-y-1">
                        <button type="button" onClick={() => escolher(null)}
                                className={`pwa-toque w-full text-left p-3 rounded-xl flex items-center gap-2 transition ${!pos.cliente ? 'bg-blue-50 ring-2 ring-blue-200' : 'hover:bg-blue-50'}`}>
                            <span className="w-8 h-8 rounded-lg bg-slate-100 text-gray-500 flex items-center justify-center"><i className="fas fa-user-tag" aria-hidden="true" /></span>
                            <span className="font-semibold text-sm flex-1">{t('Consumidor Final')}</span>
                            {!pos.cliente && <i className="fas fa-check text-blue-600" aria-hidden="true" />}
                        </button>

                        {lista.map((c) => {
                            const escolhido = !!pos.cliente && (pos.cliente.id === c.id || (!!c.local_uuid && pos.cliente.local_uuid === c.local_uuid));

                            return (
                                <button key={String(c.id)} type="button" onClick={() => escolher(c)}
                                        className={`pwa-toque w-full text-left p-3 rounded-xl border-t border-gray-50 flex items-center gap-2 transition ${escolhido ? 'bg-blue-50 ring-2 ring-blue-200' : 'hover:bg-blue-50'}`}>
                                    <span className="w-8 h-8 shrink-0 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white text-xs font-bold flex items-center justify-center">
                                        {String(c.name ?? '?').trim().charAt(0).toUpperCase() || '?'}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold text-sm truncate">{String(c.name ?? '')}</span>
                                        {c.nif && <span className="block text-xs text-gray-500">{t('NIF: :nif', { nif: String(c.nif) })}</span>}
                                    </span>
                                    {!c._synced && typeof c.id === 'string' && (
                                        <i className="fas fa-cloud-arrow-up text-amber-500 text-xs" title={t('Pendente')} aria-hidden="true" />
                                    )}
                                    {escolhido && <i className="fas fa-check text-blue-600" aria-hidden="true" />}
                                </button>
                            );
                        })}

                        {!lista.length && (
                            <div className="text-center py-6 text-gray-400 text-sm italic">
                                <i className="fas fa-users-slash block text-3xl mb-2 opacity-40 not-italic" aria-hidden="true" />
                                {t('Nenhum cliente')}
                            </div>
                        )}
                    </div>
                </div>
            </Folha>
        </Camada>
    );
}

/** O cliente rápido — só o que se pergunta ao balcão. Fica logo escolhido na venda. */
export function FolhaClienteRapido({ pos }: { pos: ControloDoPos }) {
    // Só existe enquanto está aberta: cada abertura começa com o formulário limpo.
    if (!pos.criarCliente) return null;

    return <Camada><FormularioDoCliente pos={pos} /></Camada>;
}

type TipoDeCliente = 'pessoa_fisica' | 'pessoa_juridica';

function FormularioDoCliente({ pos }: { pos: ControloDoPos }) {
    const fechar = () => pos.setCriarCliente(false);
    const [tipo, setTipo] = useState<TipoDeCliente>('pessoa_fisica');
    const [nome, setNome] = useState(pos.nomeDoNovoCliente);
    const [nif, setNif] = useState('');
    const [telemovel, setTelemovel] = useState('');

    const [guardar, aGuardar] = useAccao(async () => {
        if (!nome.trim()) return;

        try {
            const registo = await createClientOffline({
                type: tipo,
                name: nome.trim(),
                // Sem NIF é normal ao balcão: a maioria dos clientes não dá contribuinte.
                nif: nif.trim() || null,
                mobile: telemovel.trim() || null,
                country: 'Angola',
                tax_regime: 'geral',
                is_iva_subject: false,
            });
            // A lista de clientes é viva — o novo aparece sozinho; fica já escolhido nesta venda.
            pos.selecionarCliente(registo);
            fechar();
            await refreshPendingCount();
        } catch (err) {
            console.error(err);
            avisar(t('Erro ao criar cliente: :erro', { erro: (err as Error).message }), 'erro');
        }
    });

    const botaoDoTipo = (valor: TipoDeCliente, icone: string, rotulo: string, cor: string) => (
        <button type="button" role="radio" aria-checked={tipo === valor} onClick={() => setTipo(valor)}
                className={`pwa-toque border-2 rounded-xl py-2.5 text-sm font-bold transition ${tipo === valor ? `${cor} text-white shadow` : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'}`}>
            <i className={`fas ${icone} mr-1`} aria-hidden="true" />{rotulo}
        </button>
    );

    return (
        <Folha aberta aoFechar={fechar} titulo={t('Novo Cliente')} icone="fa-user-plus"
               cor="from-emerald-600 to-green-700" largura="sm:max-w-md"
               rodape={(
                   <div className="flex gap-2">
                       <button type="button" onClick={fechar}
                               className="pwa-toque flex-1 py-3 border-2 border-gray-300 bg-white text-gray-700 rounded-xl font-bold text-sm hover:bg-gray-50">{t('Cancelar')}</button>
                       <button type="submit" form="pos-form-cliente" disabled={aGuardar || !nome.trim()}
                               className="pwa-toque flex-[2] bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg disabled:opacity-50">
                           {aGuardar
                               ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A guardar…')}</>
                               : <><i className="fas fa-check mr-1" aria-hidden="true" />{t('Guardar e selecionar')}</>}
                       </button>
                   </div>
               )}>
            {/* Um formulário a sério: o Enter no nome guarda, como no ecrã antigo. */}
            <form id="pos-form-cliente" className="space-y-3" onSubmit={(e) => { e.preventDefault(); void guardar(); }}>
                {/* Tipo */}
                <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label={t('Tipo')}>
                    {botaoDoTipo('pessoa_fisica', 'fa-user', t('Singular'), 'bg-purple-600 border-purple-600')}
                    {botaoDoTipo('pessoa_juridica', 'fa-building', t('Empresa'), 'bg-blue-600 border-blue-600')}
                </div>

                <div>
                    <label htmlFor="pos-cliente-nome" className={ROTULO}>{t('Nome')} <span className="text-red-500">*</span></label>
                    <input id="pos-cliente-nome" name="name" type="text" maxLength={255} autoFocus required
                           placeholder={t('Nome ou Designação Social')}
                           value={nome} onChange={(e) => setNome(e.target.value)} className={CAMPO} />
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label htmlFor="pos-cliente-nif" className={ROTULO}>{t('NIF / BI')}</label>
                        <input id="pos-cliente-nif" name="nif" type="text" maxLength={50} inputMode="numeric" placeholder={t('Opcional')}
                               value={nif} onChange={(e) => setNif(e.target.value)} className={CAMPO} />
                    </div>
                    <div>
                        <label htmlFor="pos-cliente-telemovel" className={ROTULO}>{t('Telemóvel')}</label>
                        <input id="pos-cliente-telemovel" name="mobile" type="tel" maxLength={50} placeholder={t('Opcional')}
                               value={telemovel} onChange={(e) => setTelemovel(e.target.value)} className={CAMPO} />
                    </div>
                </div>

                <p className="text-[11px] text-gray-400">
                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />{t('Guardado localmente e sincronizado automaticamente. Fica logo selecionado nesta venda.')}
                </p>
            </form>
        </Folha>
    );
}
