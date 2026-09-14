<?php

namespace App\Services\Invoicing\Relatorios;

/**
 * UM RELATÓRIO DA FACTURAÇÃO — a única implementação de cada mapa.
 *
 * Cada relatório é uma classe: o esquema diz o que o ecrã desenha (título,
 * filtros, cartões, tabelas) e os dados são os números. O ecrã Livewire de
 * sempre chama `dados()` e passa-os à sua vista com as MESMAS chaves de
 * antes; o ecrã genérico em React lê o esquema e desenha o que lá vem.
 * Uma correcção num mapa faz-se uma vez.
 */
interface Relatorio
{
    /**
     * O esquema do ecrã.
     *
     * - slug, titulo, descricao
     * - periodo: ['omissao' => 'month'] ou null quando o mapa não tem período
     * - filtros: [['nome', 'rotulo', 'tipo' (select|date|text|number|entidade), 'opcoes' (lista ou chave nos dados), 'omissao']]
     * - cartoes: [['rotulo', 'chave' (caminho nos dados), 'formato', 'cor']]
     * - tabelas: [['titulo', 'chave' (lista nos dados), 'colunas' => [['rotulo', 'chave', 'formato', 'alinhar', 'ligacao' (só no formato ligacao)]], 'rodape' => [coluna => caminho], 'numerada', 'vazio']]
     * - csv: true quando a primeira tabela se exporta
     */
    public function esquema(): array;

    /** Os dados, com as mesmas chaves que a vista Livewire recebe. */
    public function dados(int $tenantId, array $f): array;

    /**
     * O PAPEL DO MAPA com os filtros que estão no ecrã — ou null, quando não há.
     *
     * Ao contrário do CSV, que é um só para todos os mapas, o PDF é um documento
     * próprio de cada um (o extracto de conta tem cabeçalho, período e saldo
     * transportado) e com os seus parâmetros. É por isso o mapa que diz a
     * morada, e o ecrã não tem de saber traduzir os filtros para ela.
     */
    public function pdf(array $f, array $dados): ?string;
}
