<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Client;
use App\Models\Supplier;
use App\Services\Invoicing\ContaCorrenteQuery;

/**
 * O extracto de conta corrente — de um cliente ou de um fornecedor.
 *
 * Responde à pergunta que um cliente faz ao telefone: "o que é que eu devo,
 * e porquê?" — pela ordem dos acontecimentos, com saldo acumulado e o saldo
 * que transitava de antes do período.
 */
class ExtractoDeConta extends Base
{
    public const ENTIDADES = [ContaCorrenteQuery::CLIENTE => 'Cliente', ContaCorrenteQuery::FORNECEDOR => 'Fornecedor'];

    public function esquema(): array
    {
        return [
            'slug' => 'account-statement',
            'titulo' => 'Extracto de Conta Corrente',
            'descricao' => 'Movimentos e saldo de um cliente ou fornecedor, com o saldo que transitava.',
            // Um ano: um extracto lê-se para trás, não para o mês.
            'periodo' => ['omissao' => 'ytd'],
            'filtros' => [
                ['nome' => 'entidade', 'rotulo' => 'Conta de', 'tipo' => 'select', 'opcoes' => self::opcoes(self::ENTIDADES), 'omissao' => ContaCorrenteQuery::CLIENTE],
                ['nome' => 'entidadeId', 'rotulo' => 'Quem', 'tipo' => 'entidade'],
            ],
            'cartoes' => [
                self::cartao('Saldo anterior', 'resumo.saldo_anterior', 'dinheiro', 'gray'),
                self::cartao('Débito', 'resumo.debito', 'dinheiro', 'blue'),
                self::cartao('Crédito', 'resumo.credito', 'dinheiro', 'green'),
                self::cartao('Saldo final', 'resumo.saldo_final', 'dinheiro', 'red'),
            ],
            'tabelas' => [[
                'chave' => 'movimentos',
                'colunas' => [
                    self::col('Data', 'data', 'data'), self::col('Tipo', 'tipo'), self::col('Documento', 'numero'),
                    self::col('Débito', 'debito', 'dinheiro'), self::col('Crédito', 'credito', 'dinheiro'), self::col('Saldo', 'saldo', 'dinheiro'),
                ],
                'vazio' => 'Escolha um cliente ou fornecedor para ver o extracto.',
            ]],
            'csv' => true,
        ];
    }

    /** Resultados da procura, já limitados à empresa. */
    public function procurar(int $tenantId, string $entidade, string $termo)
    {
        $termo = trim($termo);
        if ($termo === '') {
            return collect();
        }

        $modelo = $entidade === ContaCorrenteQuery::FORNECEDOR ? Supplier::class : Client::class;

        return $modelo::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('name', 'like', "%{$termo}%")->orWhere('nif', 'like', "%{$termo}%"))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'nif']);
    }

    public function entidade(int $tenantId, string $entidade, ?int $id)
    {
        if (!$id) {
            return null;
        }

        $modelo = $entidade === ContaCorrenteQuery::FORNECEDOR ? Supplier::class : Client::class;

        return $modelo::where('tenant_id', $tenantId)->find($id);
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'ytd');
        $entidade = ($this->filtro($f, 'entidade') ?? '') === ContaCorrenteQuery::FORNECEDOR ? ContaCorrenteQuery::FORNECEDOR : ContaCorrenteQuery::CLIENTE;
        $id = (int) ($this->filtro($f, 'entidadeId') ?? 0) ?: null;

        $escolhida = $this->entidade($tenantId, $entidade, $id);

        /*
         * UM ID DE CLIENTE NÃO SERVE PARA PROCURAR UM FORNECEDOR.
         *
         * Trocar de tipo de conta deixava o id anterior no endereço, e o
         * extracto passava a mostrar a conta do fornecedor com aquele número —
         * outra conta, com o nome de ninguém por cima. Se a entidade não
         * existe neste lado, não há conta escolhida.
         */
        $id = $escolhida?->id;

        $consulta = new ContaCorrenteQuery($tenantId, $entidade, $id, $de, $ate);

        return [
            'movimentos' => $id ? $consulta->movimentos() : collect(),
            'resumo' => $id ? $consulta->resumo() : null,
            // Com outro nome que não o da propriedade calculada do Livewire, para não se pisarem na vista.
            'entidade_escolhida' => $escolhida ? ['id' => $escolhida->id, 'name' => $escolhida->name, 'nif' => $escolhida->nif] : null,
        ];
    }
}
