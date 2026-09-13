/**
 * A ENTRADA DO REACT — e a ponte para o Blade que já existe.
 *
 * Durante a migração os dois convivem na mesma página. O Livewire continua a
 * desenhar tudo o que ainda não mudou; o React só acorda onde encontrar um
 * ponto de montagem posto pelo componente `<x-ecra-react>`.
 *
 * Cada ecrã é um pedaço à parte (`import()` preguiçoso), e não um pacote só:
 * quem abre a lista de facturas não descarrega o ecrã dos produtos. Numa loja
 * com rede fraca isso é a diferença entre abrir e esperar.
 */

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import { carregarDicionario, lingua } from '@/i18n';
import { LimiteDeErro } from '@/casca/LimiteDeErro';
import { Carregando } from '@/ui/Carregando';
import { ecras } from '@/ecras/registo';
import { activarDicasDeAccao } from '@/ui/DicasDeAccao';

/**
 * Uma consulta que falha não se repete três vezes.
 *
 * A rede aqui cai por minutos, não por milissegundos: insistir só empurra o
 * erro para mais tarde e entretanto a página fica parada sem dizer nada. Uma
 * tentativa, e depois o ecrã que diz o que aconteceu.
 */
const clienteDeConsultas = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            staleTime: 30_000,
            refetchOnWindowFocus: false,
        },
    },
});

// Uma unica camada de legendas serve todos os ecras e todos os modais,
// incluindo os elementos que chegam depois de uma resposta da API.
activarDicasDeAccao();

function montar(elemento: HTMLElement): void {
    const nome = elemento.dataset.ecra ?? elemento.dataset.peca;
    // Uma peça do topo (`data-peca`): sem esqueleto e com o erro discreto.
    const peca = Boolean(elemento.dataset.peca);

    if (!nome) {
        console.warn('[React] ponto de montagem sem data-ecra nem data-peca', elemento);
        return;
    }

    const carregar = ecras[nome];

    if (!carregar) {
        console.error(`[React] ecrã desconhecido: "${nome}". Ver resources/js/ecras/registo.ts`);
        return;
    }

    // As props vêm do servidor em JSON — é por aqui que o Blade passa o que já
    // sabe (o id do documento, as permissões do utilizador) sem o React ter de
    // ir buscá-lo outra vez.
    let props: Record<string, unknown> = {};

    if (elemento.dataset.props) {
        try {
            props = JSON.parse(elemento.dataset.props) as Record<string, unknown>;
        } catch (e) {
            console.error('[React] data-props não é JSON válido', e);
        }
    }

    const raiz = createRoot(elemento);

    void carregar().then(({ default: Ecra }) => {
        raiz.render(
            <StrictMode>
                <LimiteDeErro ecra={nome} discreto={peca}>
                    <QueryClientProvider client={clienteDeConsultas}>
                        <Ecra {...props} />
                    </QueryClientProvider>
                </LimiteDeErro>
            </StrictMode>,
        );
    });

    // As peças pequenas do topo não mostram esqueleto: ocupavam o cabeçalho.
    if (!peca) raiz.render(<Carregando />);
}

function montarTudo(): void {
    document
        .querySelectorAll<HTMLElement>('[data-ecra]:not([data-montado]), [data-peca]:not([data-montado])')
        .forEach((el) => {
            el.dataset.montado = '1';
            montar(el);
        });
}

/*
 * O DICIONÁRIO PRIMEIRO, E SÓ QUANDO É PRECISO.
 *
 * Em português não há nada a carregar e monta-se já. Em inglês ou francês
 * espera-se pelo dicionário, senão o ecrã aparecia em português e trocava de
 * língua a meio — pior do que demorar um instante.
 */
if (lingua() === 'pt') {
    montarTudo();
} else {
    void carregarDicionario().then(montarTudo);
}

/*
 * O `wire:navigate` troca o corpo da página sem recarregar, e o
 * DOMContentLoaded não volta a disparar — foi assim que a escada de desenho
 * dos gráficos teve de nascer. Aqui não é preciso escada nenhuma: o Livewire
 * avisa quando acabou de trocar, e nós montamos o que aparecer de novo.
 */
document.addEventListener('livewire:navigated', montarTudo);
