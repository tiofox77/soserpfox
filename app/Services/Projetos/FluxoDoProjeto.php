<?php

namespace App\Services\Projetos;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Services\Invoicing\ModuleInvoiceService;
use Illuminate\Support\Facades\DB;

/**
 * O caminho de um projeto: rascunho, activo, concluído. E a facturação das
 * horas que lá foram.
 *
 * A facturação passa pelo `ModuleInvoiceService`, o mesmo que o hotel, a
 * oficina e o salão usam — não há uma segunda forma de emitir facturas nesta
 * casa. As horas facturadas ficam marcadas com a factura que as levou, e é
 * essa marca que torna impossível facturá-las duas vezes.
 */
class FluxoDoProjeto
{
    public function criar(int $tenantId, ?int $userId, array $dados): Projeto
    {
        $nome = trim((string) ($dados['nome'] ?? ''));

        if ($nome === '') {
            throw new \InvalidArgumentException('Um projeto precisa de nome.');
        }

        $this->datasCoerentes($dados['data_inicio'] ?? null, $dados['data_fim_prevista'] ?? null);

        return Projeto::create([
            'tenant_id' => $tenantId,
            'nome' => $nome,
            'client_id' => $dados['client_id'] ?? null,
            'responsavel_id' => $dados['responsavel_id'] ?? null,
            'estado' => 'rascunho',
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim_prevista' => $dados['data_fim_prevista'] ?? null,
            'orcamento' => $this->numeroOuNulo($dados['orcamento'] ?? null),
            'valor_hora' => $this->numeroOuNulo($dados['valor_hora'] ?? null),
            'descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null,
            'created_by' => $userId,
        ]);
    }

    public function actualizar(Projeto $projeto, int $tenantId, array $dados): Projeto
    {
        $this->meu($projeto, $tenantId);

        $nome = trim((string) ($dados['nome'] ?? ''));

        if ($nome === '') {
            throw new \InvalidArgumentException('Um projeto precisa de nome.');
        }

        $this->datasCoerentes($dados['data_inicio'] ?? null, $dados['data_fim_prevista'] ?? null);

        // O valor/hora do projeto muda daqui para a frente. As horas já
        // lançadas guardam o seu próprio — não se reescrevem.
        $projeto->update([
            'nome' => $nome,
            'client_id' => $dados['client_id'] ?? null,
            'responsavel_id' => $dados['responsavel_id'] ?? null,
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim_prevista' => $dados['data_fim_prevista'] ?? null,
            'orcamento' => $this->numeroOuNulo($dados['orcamento'] ?? null),
            'valor_hora' => $this->numeroOuNulo($dados['valor_hora'] ?? null),
            'descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null,
        ]);

        return $projeto->fresh();
    }

    public function mudarEstado(Projeto $projeto, int $tenantId, string $novo): Projeto
    {
        $this->meu($projeto, $tenantId);

        if (! array_key_exists($novo, Projeto::ESTADOS)) {
            throw new \InvalidArgumentException('Estado desconhecido.');
        }

        if ($projeto->estado === $novo) {
            throw new \InvalidArgumentException("O projeto já está {$projeto->estadoRotulo()}.");
        }

        $valores = ['estado' => $novo];

        if ($novo === 'concluido') {
            $valores['data_fim_real'] = now()->toDateString();
        }

        // Reabrir limpa a data de fecho: um projeto que voltou a andar não
        // pode continuar a dizer que acabou naquele dia.
        if (in_array($novo, Projeto::ABERTOS, true)) {
            $valores['data_fim_real'] = null;
        }

        $projeto->update($valores);

        return $projeto->fresh();
    }

    /**
     * Quanto há por facturar neste projeto, e em quantas linhas.
     *
     * @return array{horas: float, valor: float, linhas: int}
     */
    public function porFacturar(Projeto $projeto): array
    {
        $linhas = $projeto->horas()->porFacturar()->get(['horas', 'valor_hora']);

        return [
            'horas' => round((float) $linhas->sum('horas'), 2),
            'valor' => round((float) $linhas->sum(fn ($l) => (float) $l->horas * (float) $l->valor_hora), 2),
            'linhas' => $linhas->count(),
        ];
    }

    /**
     * Facturar as horas por facturar deste projeto.
     *
     * Uma linha de factura por TAREFA, que é o que o cliente reconhece
     * («Levantamento de requisitos — 12h»); as horas sem tarefa juntam-se numa
     * linha do próprio projeto. Se dentro da mesma tarefa houver preços/hora
     * diferentes (a tabela mudou a meio), o grupo parte-se por preço — senão a
     * linha da factura mentia sobre o preço unitário.
     *
     * Cada hora levada fica marcada com a factura. Uma segunda tentativa não
     * encontra nada por facturar.
     */
    public function facturarHoras(Projeto $projeto, int $tenantId, ModuleInvoiceService $facturacao): SalesInvoice
    {
        $this->meu($projeto, $tenantId);

        if (! $projeto->client_id) {
            throw new \InvalidArgumentException('Este projeto não tem cliente — não há a quem facturar.');
        }

        $porFacturar = $projeto->horas()->porFacturar()->with('tarefa:id,titulo')->get();

        if ($porFacturar->isEmpty()) {
            throw new \InvalidArgumentException('Não há horas por facturar neste projeto.');
        }

        $grupos = $porFacturar->groupBy(fn ($l) => ($l->tarefa_id ?? 0).'|'.(string) $l->valor_hora);

        $linhas = [];
        foreach ($grupos as $grupo) {
            $primeira = $grupo->first();

            $linhas[] = [
                'name' => $primeira->tarefa?->titulo
                    ? $projeto->codigo.' · '.$primeira->tarefa->titulo
                    : $projeto->codigo.' · '.$projeto->nome,
                'quantity' => round((float) $grupo->sum('horas'), 2),
                'unit_price' => (float) $primeira->valor_hora,
                'is_service' => true,
                'unit' => 'HORA',
            ];
        }

        return DB::transaction(function () use ($projeto, $tenantId, $facturacao, $linhas, $porFacturar) {
            $factura = $facturacao->emitir([
                'tenant_id' => $tenantId,
                'client_id' => $projeto->client_id,
                'lines' => $linhas,
                'status' => 'draft',
                'notes' => 'Horas do projeto '.$projeto->codigo.' — '.$projeto->nome.'.',
                'origem_modulo' => 'projetos',
                'origem' => $projeto->codigo,
            ]);

            HoraLancada::withoutGlobalScopes()
                ->whereIn('id', $porFacturar->pluck('id'))
                ->update([
                    'facturado_em' => now(),
                    'sales_invoice_id' => $factura->id,
                    'updated_at' => now(),
                ]);

            return $factura;
        });
    }

    private function datasCoerentes($inicio, $fim): void
    {
        if ($inicio && $fim && strtotime((string) $fim) < strtotime((string) $inicio)) {
            throw new \InvalidArgumentException('A data de fim não pode ser anterior à de início.');
        }
    }

    private function numeroOuNulo($valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    private function meu(Projeto $projeto, int $tenantId): void
    {
        if ((int) $projeto->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Projeto de outra empresa.');
        }
    }
}
