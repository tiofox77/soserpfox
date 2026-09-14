import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type PessoaParaEntrar, plataforma } from '@/api/plataforma';
import { t, tPartes } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

/**
 * ENTRAR NUMA EMPRESA EM NOME DE ALGUÉM DE LÁ — a personificação.
 *
 * Uma janela só, para o Painel e para a lista das Empresas: são a mesma porta,
 * e duas cópias do aviso acabavam a dizer coisas diferentes.
 *
 * ESCOLHE-SE A PESSOA ANTES, e o dono vem escolhido: é quem vê tudo o que a
 * empresa tem, e é quase sempre em nome dele que se vai ajudar. Quem não se
 * pode escolher (a própria conta, outro super admin da plataforma, uma conta
 * desactivada) aparece na lista, apagado, com o porquê que o servidor deu —
 * esconder essas pessoas deixava a dúvida «onde está o Fulano?».
 *
 * O AVISO DIZ TUDO ANTES DO CLIQUE: em nome de quem, que fica na trilha como
 * feito pelo admin, quanto tempo dura e o que não se pode mudar. O botão leva
 * o nome da pessoa — carregar em «Entrar como Maria» não se faz por engano.
 */
export function EntrarComo({ empresa, aoFechar }: { empresa: { id: number; nome: string }; aoFechar: () => void }) {
    const [escolhida, porEscolhida] = useState<number | null>(null);
    const [aSeguir, porASeguir] = useState(false);

    const pessoas = useQuery({
        queryKey: ['plataforma', 'painel', 'pessoas-para-entrar', empresa.id],
        queryFn: () => plataforma.painel.pessoasParaEntrar(empresa.id),
        staleTime: 0,
    });

    // O dono vem escolhido; sem dono que sirva, a primeira pessoa que sirva.
    useEffect(() => {
        if (escolhida !== null || !pessoas.data) return;
        const servem = pessoas.data.utilizadores.filter((p) => p.pode_entrar);
        const primeira = servem.find((p) => p.dono) ?? servem[0];
        if (primeira) porEscolhida(primeira.id);
    }, [pessoas.data, escolhida]);

    const entrar = useMutation({
        mutationFn: (id: number) => plataforma.painel.entrarNaEmpresa(empresa.id, id),
        // A página muda já a seguir: um aviso no canto ficava a meio da viagem.
        meta: { aviso: false },
        onSuccess: (r) => {
            // Tapa o ecrã até a casa da empresa chegar: com a plataforma à
            // vista e os botões vivos, um clique a meio falava já como a pessoa.
            porASeguir(true);
            window.location.assign(r.seguir_para);
        },
    });

    const d = pessoas.data;
    const pessoa = d?.utilizadores.find((p) => p.id === escolhida) ?? null;
    const horas = d ? Math.round((d.duracao_em_minutos / 60) * 10) / 10 : 2;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Entrar em nome de alguém')}
            subtitulo={empresa.nome}
            icone="fa-user-secret"
            cor="aviso"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="aviso"
                        tom="solida"
                        icone="fa-user-secret"
                        disabled={!pessoa}
                        aTrabalhar={entrar.isPending || aSeguir}
                        onClick={() => pessoa && entrar.mutate(pessoa.id)}
                        data-ensaio="entrar-como"
                    >
                        {pessoa ? t('Entrar como :pessoa', { pessoa: pessoa.nome }) : t('Escolha uma pessoa')}
                    </Botao>
                </>
            }
        >
            {aSeguir && (
                <div className="fixed inset-0 z-[9999] flex cursor-wait items-center justify-center bg-white/85 backdrop-blur-sm" role="status">
                    <div className="animate-scale-in text-center">
                        <i className="fas fa-user-secret animate-pulse text-4xl text-orange-500" aria-hidden="true" />
                        <p className="mt-3 text-sm font-semibold text-gray-700">
                            {t('A entrar em :empresa…', { empresa: empresa.nome })}
                        </p>
                    </div>
                </div>
            )}

            {pessoas.isPending ? (
                <Carregando linhas={5} />
            ) : pessoas.isError ? (
                <p role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {pessoas.error instanceof ErroDaApi ? pessoas.error.message : t('Verifique a ligação.')}
                </p>
            ) : d && (
                <div className="space-y-4">
                    {/* EMPRESA DESACTIVADA: entra-se na mesma — é suporte —,
                        mas diz-se antes, para ninguém estranhar o que vê lá. */}
                    {!d.empresa.activa && (
                        <p role="note" className={cls('entra border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)}>
                            <i className="fas fa-power-off mr-2" aria-hidden="true" />
                            {t('Esta empresa está desactivada. Pode entrar para dar suporte, mas quem lá trabalha não consegue entrar.')}
                        </p>
                    )}

                    <AvisoDeErro erro={entrar.error} />

                    {d.utilizadores.length === 0 ? (
                        <SemNada icone="fa-users-slash" frase={t('Esta empresa não tem utilizadores.')} />
                    ) : (
                        <div role="radiogroup" aria-label={t('Em nome de quem')} className="max-h-72 space-y-2 overflow-y-auto pr-1">
                            {d.utilizadores.map((p, i) => (
                                <Pessoa
                                    key={p.id}
                                    pessoa={p}
                                    indice={i}
                                    escolhida={p.id === escolhida}
                                    aoEscolher={() => porEscolhida(p.id)}
                                />
                            ))}
                        </div>
                    )}

                    {pessoa && (
                        <div role="note" className={cls('entra border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                            <p className="flex items-start gap-2">
                                <i className="fas fa-fingerprint mt-0.5" aria-hidden="true" />
                                <span>
                                    {tPartes('Vai entrar como :pessoa. Tudo o que fizer fica na auditoria como feito por si em nome de :pessoa.', {
                                        pessoa: <b className="font-bold">{pessoa.nome}</b>,
                                    })}
                                </span>
                            </p>
                            <ul className="mt-2 space-y-1 pl-6 text-xs text-amber-800">
                                <li><i className="fas fa-hourglass-half mr-1.5" aria-hidden="true" />{t('Termina sozinha ao fim de :horas horas.', { horas })}</li>
                                <li><i className="fas fa-lock mr-1.5" aria-hidden="true" />{t('Não pode mudar a senha, o email nem o PIN desta pessoa.')}</li>
                                <li><i className="fas fa-right-from-bracket mr-1.5" aria-hidden="true" />{t('Volta à plataforma pela faixa no topo de todas as páginas.')}</li>
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

function Pessoa({ pessoa: p, indice, escolhida, aoEscolher }: {
    pessoa: PessoaParaEntrar;
    indice: number;
    escolhida: boolean;
    aoEscolher: () => void;
}) {
    const iniciais = p.nome.split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0]).join('').toUpperCase();

    return (
        <button
            type="button"
            role="radio"
            aria-checked={escolhida}
            disabled={!p.pode_entrar}
            onClick={aoEscolher}
            style={cascata(indice)}
            className={cls(
                'entra flex w-full items-center gap-3 border-2 px-3 py-2.5 text-left', RAIO, TRANSICAO, FOCO,
                escolhida
                    ? 'border-orange-400 bg-orange-50 shadow-sm'
                    : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-sm',
                'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:border-slate-200 disabled:hover:shadow-none',
            )}
        >
            <span className={cls(
                'grid h-10 w-10 flex-none place-items-center rounded-full text-sm font-bold text-white shadow',
                p.dono ? 'bg-gradient-to-br from-amber-400 to-orange-500' : 'bg-gradient-to-br from-slate-400 to-slate-500',
            )}>
                {iniciais || <i className="fas fa-user" aria-hidden="true" />}
            </span>

            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-1.5">
                    <span className="truncate font-semibold text-slate-900">{p.nome}</span>
                    {p.dono && <Etiqueta cor="aviso" icone="fa-crown">{t('Dono')}</Etiqueta>}
                    {p.super_admin_da_plataforma && <Etiqueta cor="primaria" icone="fa-shield-halved">{t('Plataforma')}</Etiqueta>}
                </span>
                <span className="block truncate text-xs text-slate-500">{p.email}</span>
                {(p.papeis.length > 0 || p.motivo) && (
                    <span className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                        {p.papeis.map((papel) => (
                            <span key={papel} className="rounded-md bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-600">{papel}</span>
                        ))}
                        {p.motivo && (
                            <span className="font-semibold text-red-600">
                                <i className="fas fa-ban mr-1" aria-hidden="true" />{p.motivo}
                            </span>
                        )}
                    </span>
                )}
            </span>

            <span
                className={cls(
                    'grid h-5 w-5 flex-none place-items-center rounded-full border-2', TRANSICAO,
                    escolhida ? 'border-orange-500 bg-orange-500 text-white' : 'border-slate-300 bg-white',
                )}
                aria-hidden="true"
            >
                {escolhida && <i className="fas fa-check text-[10px]" />}
            </span>
        </button>
    );
}
