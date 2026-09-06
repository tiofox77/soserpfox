<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\SeriesService;
use DomainException;
use Illuminate\Validation\Rule;

/**
 * A GESTÃO COMPLETA DAS SÉRIES — a ficha inteira, não só o código.
 *
 * Vivia dentro do `SeriesManagement`. Ao migrar o ecrã para React, saiu
 * para aqui; o Livewire e a API chamam o mesmo. (O `GestorDeSeries` das
 * definições trata do caso simples — criar com o prefixo do catálogo,
 * renomear, escolher a padrão; este trata da ficha toda: numeração,
 * exercício, estabelecimento, método de facturação.)
 *
 * O QUE ESTE CAMINHO GARANTE:
 *
 *  · O PREFIXO É UM CÓDIGO FISCAL, não uma preferência de quem preenche:
 *    para os tipos do catálogo AGT é o do catálogo, sempre; só os tipos
 *    internos aceitam o que se escreveu.
 *
 *  · UMA SÉRIE REGISTADA NA AGT quase não se mexe: só o nome, a descrição
 *    e a preferência de padrão. E não se elimina — encerra-se pelo fluxo
 *    fiscal.
 *
 *  · O EXERCÍCIO respeita a janela de 15 de Dezembro (DS.120 §4.5).
 *
 *  · SÓ UMA PADRÃO POR TIPO.
 */
class GestaoDeSeries
{
    public const TIPOS = ['invoice', 'proforma', 'receipt', 'credit_note', 'debit_note', 'pos', 'purchase', 'advance', 'transport'];

    public const METODOS = ['FEPC', 'FESF', 'SF'];

    public function regras(string $tipo): array
    {
        return [
            'document_type' => 'required|in:' . implode(',', self::TIPOS),
            'series_code' => 'required|max:10',
            'name' => 'required|max:100',
            'prefix' => $this->regraDoPrefixo($tipo),
            'next_number' => 'required|integer|min:1',
            'number_padding' => 'required|integer|min:1|max:10',
            'series_year' => 'nullable|integer|min:2024|max:2099',
            'establishment_number' => 'nullable|string|max:200',
            'invoicing_method' => 'nullable|in:' . implode(',', self::METODOS),
            'include_year' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'reset_yearly' => 'boolean',
            'description' => 'nullable|string|max:500',
        ];
    }

    /** O prefixo que vai para a base: o do catálogo, ou o preenchido nos tipos internos. */
    public function prefixoParaGravar(string $tipo, ?string $preenchido): string
    {
        return InvoicingSeries::prefixoDe($tipo) ?? (string) $preenchido;
    }

    /** @throws DomainException quando o exercício cai fora da janela */
    public function criar(array $d, int $tenantId): InvoicingSeries
    {
        $this->validarExercicio($d, $tenantId);

        if (! empty($d['is_default'])) {
            $this->desmarcarPadrao($tenantId, $d['document_type']);
        }

        return InvoicingSeries::create($this->valores($d) + [
            'tenant_id' => $tenantId,
            'current_year' => now()->year,
        ]);
    }

    /**
     * @return string  A mensagem para o ecrã (a registada na AGT diz o que ficou de fora).
     *
     * @throws DomainException
     */
    public function actualizar(InvoicingSeries $serie, array $d, int $tenantId): string
    {
        $this->validarExercicio($d, $tenantId);

        $registada = $serie->isAGTRegistered();

        if (! empty($d['is_default'])) {
            // Numa série registada o tipo não muda: o padrão desmarca-se no tipo dela.
            $this->desmarcarPadrao($tenantId, $registada ? $serie->document_type : $d['document_type']);
        }

        if ($registada) {
            $serie->update([
                'name' => $d['name'],
                'description' => $d['description'] ?? null,
                'is_default' => (bool) ($d['is_default'] ?? false),
            ]);

            return __('Série registada: apenas nome, descrição e preferência padrão foram actualizados.');
        }

        $serie->update($this->valores($d) + ['current_year' => now()->year]);

        return __('Série atualizada com sucesso!');
    }

    /** @throws DomainException quando está registada na AGT */
    public function eliminar(InvoicingSeries $serie): void
    {
        if ($serie->isAGTRegistered()) {
            throw new DomainException(__('Uma série registada na AGT não pode ser eliminada. Encerre-a pelo fluxo fiscal apropriado.'));
        }

        $serie->delete();
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function regraDoPrefixo(string $tipo): array|string
    {
        $canonico = InvoicingSeries::prefixoDe($tipo);

        return $canonico === null ? 'required|max:10' : ['required', Rule::in([$canonico])];
    }

    private function validarExercicio(array $d, int $tenantId): void
    {
        if (empty($d['series_year'])) {
            return;
        }

        try {
            // A janela é uma regra do calendário (DS.120 §4.5), não da AGT:
            // valida-se sem construir o serviço, que exigiria o par RSA.
            SeriesService::validateSeriesYearWindow((int) $d['series_year']);
        } catch (\InvalidArgumentException $e) {
            throw new DomainException($e->getMessage());
        }
    }

    private function desmarcarPadrao(int $tenantId, string $tipo): void
    {
        InvoicingSeries::where('tenant_id', $tenantId)->where('document_type', $tipo)->update(['is_default' => false]);
    }

    private function valores(array $d): array
    {
        return [
            'document_type' => $d['document_type'],
            'series_code' => $d['series_code'],
            'name' => $d['name'],
            'prefix' => $this->prefixoParaGravar($d['document_type'], $d['prefix'] ?? null),
            'include_year' => (bool) ($d['include_year'] ?? true),
            'next_number' => (int) $d['next_number'],
            'number_padding' => (int) $d['number_padding'],
            'is_default' => (bool) ($d['is_default'] ?? false),
            'is_active' => (bool) ($d['is_active'] ?? true),
            'reset_yearly' => (bool) ($d['reset_yearly'] ?? true),
            'description' => $d['description'] ?? null,
            'series_year' => ($d['series_year'] ?? null) ?: null,
            'establishment_number' => ($d['establishment_number'] ?? null) ?: 'SEDE',
            'invoicing_method' => ($d['invoicing_method'] ?? null) ?: InvoicingSeries::INVOICING_FEPC,
        ];
    }
}
