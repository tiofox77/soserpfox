import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { pos, type ClienteDoPos } from '@/api/pos';
import { catalogos } from '@/api/catalogos';
import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';

/**
 * ESCOLHER O CLIENTE — e criá-lo aqui, se ainda não existir.
 *
 * Eram dois modais no ecrã de sempre, um a abrir o outro. Ao balcão isso é um
 * clique a mais com o cliente à frente: aqui a criação é um separador do
 * mesmo modal, e volta-se atrás sem perder a procura.
 *
 * O CLIENTE NASCE PELA PORTA DOS CATÁLOGOS, a mesma da ficha de clientes.
 * Um cliente criado ao balcão por um caminho próprio acabaria com metade dos
 * campos que os outros têm — e é o mesmo cliente.
 */
export function ModalDeCliente({
    aberto,
    podeCriar,
    aoFechar,
    aoEscolher,
}: {
    aberto: boolean;
    podeCriar: boolean;
    aoFechar: () => void;
    aoEscolher: (c: ClienteDoPos) => void;
}) {
    const [procura, porProcura] = useState('');
    const [aCriar, porACriar] = useState(false);

    const lista = useQuery({
        queryKey: ['pos', 'clientes', procura],
        queryFn: () => pos.clientes(procura),
        enabled: aberto && !aCriar,
    });

    return (
        <Modal
            aberto={aberto}
            aoFechar={() => {
                porACriar(false);
                aoFechar();
            }}
            titulo={aCriar ? t('Novo cliente') : t('Escolher cliente')}
            icone={aCriar ? 'fa-user-plus' : 'fa-users'}
            cor="primaria"
            largura="md"
        >
            {aCriar ? (
                <NovoCliente
                    aoVoltar={() => porACriar(false)}
                    aoCriado={(c) => {
                        porACriar(false);
                        aoEscolher(c);
                    }}
                />
            ) : (
                <div className="space-y-3">
                    <div className="relative">
                        <i
                            className="fas fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                            aria-hidden="true"
                        />
                        <input
                            type="search"
                            value={procura}
                            onChange={(e) => porProcura(e.target.value)}
                            placeholder={t('Nome, NIF ou telefone…')}
                            aria-label={t('Procurar cliente')}
                            autoFocus
                            className={cls(entrada, 'py-3 pl-10 text-base')}
                        />
                    </div>

                    {/* O CONSUMIDOR FINAL é uma escolha, e não a ausência de
                        escolha: está aqui em cima, à mão, porque é o mais usado
                        de todos ao balcão. */}
                    <button
                        type="button"
                        onClick={() =>
                            aoEscolher({ id: 0, nome: t('Consumidor Final'), nif: null, telefone: null, email: null })
                        }
                        className={cls(
                            'flex w-full items-center gap-3 border border-slate-200 bg-slate-50 p-3 text-left',
                            'transition-all duration-200 hover:border-indigo-300 hover:bg-indigo-50',
                            RAIO,
                            FOCO,
                        )}
                    >
                        <span className="grid h-10 w-10 flex-none place-items-center rounded-full bg-slate-200 text-slate-500">
                            <i className="fas fa-user-large" aria-hidden="true" />
                        </span>
                        <span>
                            <span className="block text-sm font-bold text-slate-800">{t('Consumidor Final')}</span>
                            <span className="block text-xs text-slate-500">{t('venda sem NIF')}</span>
                        </span>
                    </button>

                    <div className="max-h-80 space-y-1.5 overflow-y-auto">
                        {lista.isPending && <p className="py-6 text-center text-sm text-slate-400">{t('A procurar…')}</p>}

                        {lista.data?.data.length === 0 && (
                            <div className="py-8 text-center">
                                <i className="fas fa-user-slash mb-2 text-2xl text-slate-300" aria-hidden="true" />
                                <p className="text-sm text-slate-500">{t('Nenhum cliente encontrado')}</p>
                            </div>
                        )}

                        {lista.data?.data.map((c, i) => (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => aoEscolher(c)}
                                style={{ '--i': i } as React.CSSProperties}
                                className={cls(
                                    'entra flex w-full items-center gap-3 border border-slate-200 bg-white p-3 text-left',
                                    'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-400 hover:shadow-md',
                                    RAIO,
                                    FOCO,
                                )}
                            >
                                <span className="grid h-10 w-10 flex-none place-items-center rounded-full bg-indigo-100 text-indigo-600">
                                    <i className="fas fa-user" aria-hidden="true" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-bold text-slate-800">{c.nome}</span>
                                    <span className="block truncate text-xs text-slate-500">
                                        {c.nif ? `NIF: ${c.nif}` : t('sem NIF')}
                                        {c.telefone && ` · ${c.telefone}`}
                                    </span>
                                </span>
                            </button>
                        ))}
                    </div>

                    {podeCriar && (
                        <Botao cor="primaria" tom="suave" icone="fa-user-plus" onClick={() => porACriar(true)}>
                            {t('Criar cliente novo')}
                        </Botao>
                    )}
                </div>
            )}
        </Modal>
    );
}

/**
 * O cliente rápido.
 *
 * O NIF fica opcional de propósito: ao balcão o cliente ou o dá ou não o dá, e
 * exigi-lo obrigava a inventar um — que é como as bases de dados acabam com
 * dezenas de «999999999». Sem NIF, a venda sai como Consumidor Final.
 */
function NovoCliente({
    aoVoltar,
    aoCriado,
}: {
    aoVoltar: () => void;
    aoCriado: (c: ClienteDoPos) => void;
}) {
    const [nome, porNome] = useState('');
    const [nif, porNif] = useState('');
    const [telefone, porTelefone] = useState('');
    const [email, porEmail] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const criar = useMutation({
        mutationFn: () =>
            catalogos.criar('clientes', {
                name: nome,
                nif: nif || null,
                phone: telefone || null,
                email: email || null,
                type: 'pessoa_singular',
                is_active: true,
            }),
        onSuccess: (r) => {
            const criado = r as unknown as { id: number };

            aoCriado({ id: criado.id, nome, nif: nif || null, telefone: telefone || null, email: email || null });
        },
        onError: (e) => {
            porErros(e instanceof ErroDaApi ? (e.erros ?? {}) : {});
        },
    });

    return (
        <div className="space-y-3">
            <Campo etiqueta={t('Nome')} erro={erros.name} obrigatorio>
                <input
                    value={nome}
                    onChange={(e) => porNome(e.target.value)}
                    placeholder={t('Ex.: João Manuel')}
                    autoFocus
                    className={entrada}
                />
            </Campo>

            <Campo etiqueta={t('NIF')} erro={erros.nif} ajuda={t('Deixe vazio para sair como Consumidor Final.')}>
                <input value={nif} onChange={(e) => porNif(e.target.value)} placeholder="005123456" className={entrada} />
            </Campo>

            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                    <input value={telefone} onChange={(e) => porTelefone(e.target.value)} placeholder="+244 9…" className={entrada} />
                </Campo>
                <Campo etiqueta={t('Email')} erro={erros.email}>
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => porEmail(e.target.value)}
                        placeholder="email@exemplo.com"
                        className={entrada}
                    />
                </Campo>
            </div>

            <div className="flex justify-end gap-2 pt-2">
                <Botao onClick={aoVoltar} icone="fa-arrow-left">{t('Voltar')}</Botao>
                <Botao
                    cor="primaria"
                    tom="solida"
                    icone="fa-check"
                    disabled={nome.trim() === ''}
                    aTrabalhar={criar.isPending}
                    onClick={() => criar.mutate()}
                >
                    {t('Criar e escolher')}
                </Botao>
            </div>
        </div>
    );
}
