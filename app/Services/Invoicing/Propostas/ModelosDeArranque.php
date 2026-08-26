<?php

namespace App\Services\Invoicing\Propostas;

use App\Models\Invoicing\QuoteTemplate;

/**
 * Modelos prontos a usar, por sector.
 *
 * Um editor em branco é uma parede: quem abre isto pela primeira vez não quer
 * decidir se a capa vem antes do âmbito — quer uma proposta decente hoje. Cada
 * um destes já está montado e é copiado para a empresa, onde fica a ser dela e
 * pode ser mexido à vontade.
 */
class ModelosDeArranque
{
    public static function catalogo(): array
    {
        return [
            'informatica' => [
                'nome'      => 'Software e Informática',
                'descricao' => 'Âmbito, metodologia, prazos e equipa. Para projectos de desenvolvimento e sistemas.',
                'icone'     => 'fa-laptop-code',
                'cor'       => '#4f46e5',
                'blocos'    => self::informatica(),
            ],
            'media' => [
                'nome'      => 'Media e Produção de Vídeo',
                'descricao' => 'Conceito criativo, dias de filmagem, entregáveis e direitos de utilização.',
                'icone'     => 'fa-video',
                'cor'       => '#db2777',
                'blocos'    => self::media(),
            ],
            'consultoria' => [
                'nome'      => 'Consultoria e Serviços',
                'descricao' => 'Diagnóstico, abordagem, plano de trabalho e resultados esperados.',
                'icone'     => 'fa-briefcase',
                'cor'       => '#0891b2',
                'blocos'    => self::consultoria(),
            ],
            'simples' => [
                'nome'      => 'Proposta simples',
                'descricao' => 'Uma página: cliente, itens, totais e condições. Sem capa.',
                'icone'     => 'fa-file-lines',
                'cor'       => '#16a34a',
                'blocos'    => self::simples(),
            ],
        ];
    }

    /** Copia um modelo do catálogo para a empresa. */
    public static function criarParaEmpresa(string $chave, int $tenantId, ?int $userId = null): QuoteTemplate
    {
        $base = self::catalogo()[$chave] ?? null;
        if (!$base) {
            throw new \InvalidArgumentException("Modelo de arranque desconhecido: {$chave}");
        }

        $estilos = array_merge(QuoteTemplate::ESTILOS_PADRAO, ['cor_principal' => $base['cor']]);

        $modelo = QuoteTemplate::create([
            'tenant_id'  => $tenantId,
            'nome'       => $base['nome'],
            'descricao'  => $base['descricao'],
            'sector'     => $chave,
            'blocos'     => self::comIds($base['blocos']),
            'estilos'    => $estilos,
            'is_active'  => true,
            'created_by' => $userId,
        ]);

        // O primeiro modelo da empresa fica a ser o padrão: sem isto, criar um
        // modelo e continuar a ver o PDF antigo parecia que nada acontecera.
        if (QuoteTemplate::where('tenant_id', $tenantId)->count() === 1) {
            $modelo->tornarPadrao();
        }

        return $modelo;
    }

    /** Cada bloco precisa de id próprio — é por ele que o editor os move. */
    private static function comIds(array $blocos): array
    {
        return array_map(function ($b, $i) {
            $b['id'] = 'b' . ($i + 1) . substr(md5($b['tipo'] . $i . uniqid('', true)), 0, 6);

            return $b;
        }, $blocos, array_keys($blocos));
    }

    // ── Os modelos ───────────────────────────────────────────────────────

    private static function informatica(): array
    {
        return [
            ['tipo' => 'capa', 'titulo' => 'Proposta de Desenvolvimento', 'subtitulo' => '{{cliente.nome}}',
             'mostrar_logo' => true, 'mostrar_dados' => true, 'cor_fundo' => '', 'quebrar_depois' => true],

            ['tipo' => 'dados_cliente', 'mostrar_validade' => true, 'mostrar_nif' => true],

            ['tipo' => 'titulo', 'texto' => 'Enquadramento', 'numerar' => true],
            ['tipo' => 'texto', 'html' => '<p>Agradecemos a {{cliente.nome}} a oportunidade de apresentar esta proposta. '
                . 'O documento descreve o que propomos fazer, como, em quanto tempo e por que valor.</p>'],

            ['tipo' => 'campo_livre', 'chave' => 'problema', 'rotulo' => 'Problema do cliente',
             'ajuda' => 'O que é que o cliente precisa de resolver? Nas palavras dele.',
             'titulo' => 'O desafio', 'linhas' => 5],

            ['tipo' => 'campo_livre', 'chave' => 'ambito', 'rotulo' => 'Âmbito do trabalho',
             'ajuda' => 'O que está incluído — e, quando fizer sentido, o que não está.',
             'titulo' => 'Âmbito do trabalho', 'linhas' => 8],

            ['tipo' => 'titulo', 'texto' => 'Como trabalhamos', 'numerar' => true],
            ['tipo' => 'texto', 'html' => '<p>Trabalhamos por entregas curtas, com uma versão utilizável desde cedo:</p>'
                . '<ul><li><strong>Levantamento</strong> — reuniões e documento de âmbito aprovado por escrito.</li>'
                . '<li><strong>Desenvolvimento</strong> — entregas a cada duas semanas, para verem o progresso.</li>'
                . '<li><strong>Testes e correcções</strong> — com a vossa equipa, sobre dados reais.</li>'
                . '<li><strong>Formação e arranque</strong> — acompanhamento nos primeiros dias.</li></ul>'],

            ['tipo' => 'campo_livre', 'chave' => 'prazos', 'rotulo' => 'Prazos',
             'ajuda' => 'Fases e datas. Ex.: Levantamento — 2 semanas; Desenvolvimento — 6 semanas.',
             'titulo' => 'Prazos', 'linhas' => 5],

            ['tipo' => 'campo_livre', 'chave' => 'equipa', 'rotulo' => 'Equipa',
             'ajuda' => 'Quem trabalha no projecto e com que papel.',
             'titulo' => 'Equipa', 'linhas' => 4],

            ['tipo' => 'quebra'],

            ['tipo' => 'itens', 'titulo' => 'Investimento', 'mostrar_descricao' => true,
             'mostrar_desconto' => true, 'mostrar_imposto' => true],
            ['tipo' => 'totais', 'mostrar_por_extenso' => false],

            ['tipo' => 'condicoes', 'titulo' => 'Condições',
             'html' => '<p><strong>Validade:</strong> esta proposta é válida até {{orcamento.validade}}.</p>'
                . '<p><strong>Pagamento:</strong> 50% à adjudicação, 50% na entrega.</p>'
                . '<p><strong>Garantia:</strong> 90 dias de correcção de defeitos sem custo, após a entrega.</p>'
                . '<p><strong>Não incluído:</strong> licenças de terceiros, alojamento e domínios, salvo indicação em contrário.</p>'],

            ['tipo' => 'assinaturas', 'esquerda' => '{{empresa.nome}}', 'direita' => '{{cliente.nome}}', 'duas' => true],
        ];
    }

    private static function media(): array
    {
        return [
            ['tipo' => 'capa', 'titulo' => 'Proposta de Produção de Vídeo', 'subtitulo' => '{{cliente.nome}}',
             'mostrar_logo' => true, 'mostrar_dados' => true, 'cor_fundo' => '#111827', 'quebrar_depois' => true],

            ['tipo' => 'dados_cliente', 'mostrar_validade' => true, 'mostrar_nif' => true],

            ['tipo' => 'campo_livre', 'chave' => 'conceito', 'rotulo' => 'Conceito criativo',
             'ajuda' => 'A ideia do vídeo: tom, história, referências.',
             'titulo' => 'Conceito criativo', 'linhas' => 7],

            ['tipo' => 'campo_livre', 'chave' => 'entregaveis', 'rotulo' => 'Entregáveis',
             'ajuda' => 'Ex.: 1 vídeo de 90s + 3 cortes para redes sociais (9:16), em 4K.',
             'titulo' => 'O que é entregue', 'linhas' => 5],

            ['tipo' => 'titulo', 'texto' => 'Como decorre a produção', 'numerar' => true],
            ['tipo' => 'texto', 'html' => '<ul>'
                . '<li><strong>Pré-produção</strong> — guião, storyboard, casting e escolha de locais.</li>'
                . '<li><strong>Filmagem</strong> — equipa, equipamento e direcção no local.</li>'
                . '<li><strong>Pós-produção</strong> — montagem, cor, som e grafismo.</li>'
                . '<li><strong>Entrega</strong> — ficheiros finais nos formatos acordados.</li></ul>'],

            ['tipo' => 'campo_livre', 'chave' => 'filmagem', 'rotulo' => 'Dias e locais de filmagem',
             'ajuda' => 'Quantos dias, onde, e com que equipa.',
             'titulo' => 'Filmagem', 'linhas' => 4],

            ['tipo' => 'campo_livre', 'chave' => 'revisoes', 'rotulo' => 'Revisões incluídas',
             'ajuda' => 'Ex.: duas rondas de alterações; a partir da terceira, orçamento à parte.',
             'titulo' => 'Revisões', 'linhas' => 3],

            ['tipo' => 'quebra'],

            ['tipo' => 'itens', 'titulo' => 'Investimento', 'mostrar_descricao' => true,
             'mostrar_desconto' => true, 'mostrar_imposto' => true],
            ['tipo' => 'totais', 'mostrar_por_extenso' => false],

            ['tipo' => 'condicoes', 'titulo' => 'Condições',
             'html' => '<p><strong>Validade:</strong> até {{orcamento.validade}}.</p>'
                . '<p><strong>Pagamento:</strong> 50% para reservar as datas de filmagem, 50% na entrega dos ficheiros finais.</p>'
                . '<p><strong>Direitos de utilização:</strong> o cliente pode usar o material nos meios acordados. '
                . 'Utilizações fora disso são orçamentadas à parte.</p>'
                . '<p><strong>Adiamentos:</strong> filmagens adiadas com menos de 48h de aviso podem implicar custos de equipa e equipamento.</p>'],

            ['tipo' => 'assinaturas', 'esquerda' => '{{empresa.nome}}', 'direita' => '{{cliente.nome}}', 'duas' => true],
        ];
    }

    private static function consultoria(): array
    {
        return [
            ['tipo' => 'capa', 'titulo' => 'Proposta de Consultoria', 'subtitulo' => '{{cliente.nome}}',
             'mostrar_logo' => true, 'mostrar_dados' => true, 'cor_fundo' => '', 'quebrar_depois' => true],

            ['tipo' => 'dados_cliente', 'mostrar_validade' => true, 'mostrar_nif' => true],

            ['tipo' => 'campo_livre', 'chave' => 'diagnostico', 'rotulo' => 'Diagnóstico',
             'ajuda' => 'O que observámos e o que está a custar dinheiro ou tempo ao cliente.',
             'titulo' => 'Diagnóstico', 'linhas' => 6],

            ['tipo' => 'campo_livre', 'chave' => 'abordagem', 'rotulo' => 'Abordagem',
             'ajuda' => 'Como vamos resolver, por etapas.',
             'titulo' => 'Abordagem', 'linhas' => 7],

            ['tipo' => 'campo_livre', 'chave' => 'resultados', 'rotulo' => 'Resultados esperados',
             'ajuda' => 'O que muda no fim — de preferência com números.',
             'titulo' => 'Resultados esperados', 'linhas' => 5],

            ['tipo' => 'itens', 'titulo' => 'Honorários', 'mostrar_descricao' => true,
             'mostrar_desconto' => true, 'mostrar_imposto' => true],
            ['tipo' => 'totais', 'mostrar_por_extenso' => false],

            ['tipo' => 'condicoes', 'titulo' => 'Condições',
             'html' => '<p><strong>Validade:</strong> até {{orcamento.validade}}.</p>'
                . '<p><strong>Pagamento:</strong> a 30 dias, contra factura.</p>'
                . '<p><strong>Confidencialidade:</strong> tudo o que nos for partilhado fica entre nós.</p>'],

            ['tipo' => 'assinaturas', 'esquerda' => '{{empresa.nome}}', 'direita' => '{{cliente.nome}}', 'duas' => true],
        ];
    }

    private static function simples(): array
    {
        return [
            ['tipo' => 'dados_cliente', 'mostrar_validade' => true, 'mostrar_nif' => true],
            ['tipo' => 'texto', 'html' => '<p>Conforme solicitado, apresentamos a nossa proposta.</p>'],
            ['tipo' => 'itens', 'titulo' => '', 'mostrar_descricao' => true,
             'mostrar_desconto' => true, 'mostrar_imposto' => true],
            ['tipo' => 'totais', 'mostrar_por_extenso' => false],
            ['tipo' => 'condicoes', 'titulo' => 'Condições',
             'html' => '<p>Proposta válida até {{orcamento.validade}}.</p>'],
            ['tipo' => 'assinaturas', 'esquerda' => '{{empresa.nome}}', 'direita' => '', 'duas' => false],
        ];
    }
}
