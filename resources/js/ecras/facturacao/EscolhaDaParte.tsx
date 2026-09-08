import { useMemo, useState } from 'react';
import { useMutation } from '@tanstack/react-query';

import { PARTE_VAZIA, partes as portaDasPartes, type CriarParte, type Parte, type ParteRapida } from '@/api/partes';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * ESCOLHER O CLIENTE (OU O FORNECEDOR) DE UM DOCUMENTO — e criá-lo ali mesmo
 * quando ainda não existe.
 *
 * Duas coisas que a migração para React deixou pelo caminho e que aqui voltam:
 *
 * 1. A PROCURA. Um `<select>` com todos os clientes lá dentro funciona com
 *    doze e é inutilizável com quatrocentos — e é com quatrocentos que as
 *    empresas trabalham. A caixa de procura filtra por nome e por NIF, como o
 *    `searchClient` do ecrã de sempre.
 *
 * 2. O CLIENTE RÁPIDO. Está-se a emitir, o cliente não existe, e criá-lo
 *    obrigava a largar o documento a meio e a reescrever as linhas. O botão
 *    abre um formulário com os cinco campos de sempre — nome, NIF, email,
 *    telefone e morada — e, criado, ele fica ESCOLHIDO no documento. Era esse
 *    o ponto.
 *
 * A criação passa pela porta que já existe (`@/api/partes`): as validações e
 * as permissões são as mesmas do ecrã completo. Quem não pode criar não vê o
 * botão — e o servidor recusa na mesma, porque um botão escondido não é uma
 * guarda.
 */
export function EscolhaDaParte({
    criar,
    partes,
    valor,
    aoEscolher,
    erro,
    className,
}: {
    /** O que o servidor disse: que parte é, se pode criar, e o país por omissão. */
    criar: CriarParte;
    partes: ReadonlyArray<Parte>;
    valor: string;
    aoEscolher: (id: string) => void;
    erro?: string[];
    className?: string;
}) {
    const [procura, porProcura] = useState('');
    const [aberto, porAberto] = useState(false);
    /* A lista de resultados está aberta — é o que separa «a escrever» de
       «já escolhido», e o que faz a caixa mostrar um ou outro. */
    const [aLista, porALista] = useState(false);
    const [rascunho, porRascunho] = useState<ParteRapida>({ ...PARTE_VAZIA });
    const [errosDoNovo, porErrosDoNovo] = useState<Record<string, string[]>>({});
    /* As criadas nesta sessão. As opções vêm em cache; sem isto, o que se
       acabou de criar não estaria na lista onde tem de ficar escolhido. */
    const [criadas, porCriadas] = useState<Parte[]>([]);

    const eFornecedor = criar.tipo === 'fornecedor';
    const etiqueta = eFornecedor ? t('Fornecedor') : t('Cliente');

    const todas = useMemo(() => {
        const porId = new Map<number, Parte>();
        for (const p of [...partes, ...criadas]) porId.set(p.id, p);

        return [...porId.values()];
    }, [partes, criadas]);

    /*
     * O QUE ESTÁ ESCOLHIDO NUNCA DESAPARECE DA LISTA.
     *
     * Um `<select>` cujo `value` não está entre as opções mostra-se vazio — e
     * o documento parecia ter perdido o cliente só porque alguém escreveu na
     * caixa de procura.
     */
    const visiveis = useMemo(() => {
        const termo = procura.trim().toLowerCase();

        if (termo === '') return todas;

        return todas.filter(
            (p) => String(p.id) === valor || `${p.name} ${p.nif ?? ''}`.toLowerCase().includes(termo),
        );
    }, [todas, procura, valor]);

    /* A que está escolhida agora — para o ecrã poder dizer o nome e o NIF. */
    const escolhida = todas.find((p) => String(p.id) === valor) ?? null;

    const gravar = useMutation({
        mutationFn: () => portaDasPartes.criar(criar, rascunho),
        onSuccess: (nova) => {
            porCriadas((c) => [...c, nova]);
            // Criado, fica ESCOLHIDO. Era esse o ponto.
            aoEscolher(String(nova.id));
            porProcura('');
            porErrosDoNovo({});
            porRascunho({ ...PARTE_VAZIA });
            porAberto(false);
        },
        onError: (e) => porErrosDoNovo(e instanceof ErroDaApi ? e.erros : {}),
    });

    const escrever = (campo: keyof ParteRapida, texto: string) =>
        porRascunho((r) => ({ ...r, [campo]: texto }));

    return (
        <div className={cls('min-w-0', className)}>
            {/*
              * UM CONTROLO SÓ — a caixa de procura É o campo do cliente.
              *
              * Estavam aqui QUATRO coisas para uma escolha: a caixa de
              * procura, um `<select>` por baixo, uma linha a dizer «5 de 6» e
              * um cartão a repetir quem ficou escolhido. Quatro maneiras de
              * dizer a mesma coisa, e nenhuma delas óbvia — a pessoa escrevia
              * na caixa e ficava à espera que alguma coisa acontecesse.
              *
              * Escreve-se, aparecem os resultados por baixo, carrega-se num.
              * É o que o ecrã de sempre fazia e é o que se espera de uma caixa
              * com uma lupa.
              */}
            <Campo etiqueta={etiqueta} erro={erro} obrigatorio>
                <div className="flex gap-2">
                    <div className="relative min-w-0 flex-1">
                        <i
                            className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"
                            aria-hidden="true"
                        />

                        <input
                            type="text"
                            role="combobox"
                            aria-expanded={aLista}
                            aria-controls="lista-de-partes"
                            autoComplete="off"
                            value={aLista ? procura : (escolhida?.name ?? '')}
                            onFocus={() => { porProcura(''); porALista(true); }}
                            onChange={(e) => { porProcura(e.target.value); porALista(true); }}
                            /* O rato tem de chegar à opção antes de o `blur`
                               fechar a lista — daí o atraso curto. */
                            onBlur={() => setTimeout(() => porALista(false), 150)}
                            onKeyDown={(e) => e.key === 'Escape' && porALista(false)}
                            placeholder={eFornecedor
                                ? t('Pesquisar fornecedor por nome ou NIF…')
                                : t('Pesquisar cliente por nome, NIF ou telefone…')}
                            aria-label={etiqueta}
                            className={cls(entrada, 'w-full pl-9', escolhida && !aLista && 'font-semibold text-slate-900')}
                        />

                        {/* LIMPAR o que está escolhido, sem ter de apagar letra
                            a letra o nome que lá está. */}
                        {escolhida && !aLista && (
                            <button
                                type="button"
                                onClick={() => { aoEscolher(''); porProcura(''); }}
                                title={t('Limpar')}
                                aria-label={t('Limpar')}
                                className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            >
                                <i className="fas fa-xmark" aria-hidden="true" />
                            </button>
                        )}

                        {aLista && (
                            <ul
                                id="lista-de-partes"
                                role="listbox"
                                className={cls(
                                    'absolute z-50 mt-1 max-h-60 w-full overflow-y-auto border border-slate-200 bg-white shadow-lg',
                                    RAIO,
                                )}
                            >
                                {visiveis.length === 0 && (
                                    <li className="px-3 py-3 text-sm text-slate-400">
                                        {t('Nada encontrado.')}
                                        {criar.pode && ' ' + t('Use «:botao».', { botao: eFornecedor ? t('Novo fornecedor') : t('Novo cliente') })}
                                    </li>
                                )}

                                {visiveis.slice(0, 50).map((p) => (
                                    <li key={p.id}>
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected={String(p.id) === valor}
                                            /* `mousedown` e não `click`: o
                                               `blur` do campo chega primeiro e
                                               o clique perdia-se. */
                                            onMouseDown={(e) => {
                                                e.preventDefault();
                                                aoEscolher(String(p.id));
                                                porProcura('');
                                                porALista(false);
                                            }}
                                            className={cls(
                                                'block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-indigo-50',
                                                String(p.id) === valor && 'bg-indigo-50',
                                            )}
                                        >
                                            <span className="block truncate text-sm font-bold text-slate-900">{p.name}</span>
                                            {p.nif && <span className="block text-xs text-slate-500">{p.nif}</span>}
                                        </button>
                                    </li>
                                ))}

                                {visiveis.length > 50 && (
                                    <li className="border-t border-slate-100 px-3 py-2 text-xs text-slate-400">
                                        {t('Mais :quantos — escreva para afinar.', { quantos: visiveis.length - 50 })}
                                    </li>
                                )}
                            </ul>
                        )}
                    </div>

                {/* O botão de criar é VERDE e não da cor do ecrã: cria uma
                    ficha nova, e no Blade era sempre o mesmo verde nos quatro
                    editores, fosse o ecrã roxo, laranja ou azul. */}
                {criar.pode && (
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone="fa-plus"
                        className="flex-none whitespace-nowrap"
                        onClick={() => {
                            porErrosDoNovo({});
                            porRascunho((r) => ({ ...r, name: r.name || procura.trim() }));
                            porAberto(true);
                        }}
                    >
                        {eFornecedor ? t('Novo fornecedor') : t('Novo cliente')}
                    </Botao>
                )}
                </div>

                {/* O NIF DE QUEM FICOU ESCOLHIDO — é o que a AGT compara, e
                    não cabe na caixa ao lado do nome. Uma linha, e só quando
                    a lista está fechada. */}
                {escolhida?.nif && !aLista && (
                    <p className="mt-1 text-xs text-slate-500">
                        <i className="fas fa-id-card mr-1 text-slate-400" aria-hidden="true" />
                        {t('NIF')}: <span className="font-mono">{escolhida.nif}</span>
                    </p>
                )}
            </Campo>

            <Modal
                aberto={aberto}
                aoFechar={() => porAberto(false)}
                titulo={eFornecedor ? t('Novo fornecedor') : t('Novo cliente')}
                subtitulo={t('Fica escolhido no documento, sem perder o que já escreveu.')}
                icone={eFornecedor ? 'fa-truck' : 'fa-user-plus'}
                cor="bom"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => porAberto(false)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="bom"
                            tom="solida"
                            icone="fa-check"
                            aTrabalhar={gravar.isPending}
                            onClick={() => gravar.mutate()}
                        >
                            {t('Criar e escolher')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={gravar.error} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Nome')} erro={errosDoNovo.name} obrigatorio className="sm:col-span-2">
                        <input
                            value={rascunho.name}
                            onChange={(e) => escrever('name', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    {/* O NIF é o do verificador angolano, e é único nesta
                        empresa: quem valida é o servidor, e o que ele disser
                        aparece aqui por baixo. */}
                    <Campo etiqueta={t('NIF')} erro={errosDoNovo.nif} obrigatorio={!eFornecedor}>
                        <input
                            value={rascunho.nif}
                            onChange={(e) => escrever('nif', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Email')} erro={errosDoNovo.email}>
                        <input
                            type="email"
                            value={rascunho.email}
                            onChange={(e) => escrever('email', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Telefone')} erro={errosDoNovo.phone}>
                        <input
                            value={rascunho.phone}
                            onChange={(e) => escrever('phone', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Morada')} erro={errosDoNovo.address}>
                        <input
                            value={rascunho.address}
                            onChange={(e) => escrever('address', e.target.value)}
                            className={entrada}
                        />
                    </Campo>
                </div>

                <p className="mt-4 text-xs text-slate-500">
                    {t('A ficha completa — morada, província, condições de pagamento — edita-se depois no ecrã próprio.')}
                </p>
            </Modal>
        </div>
    );
}
