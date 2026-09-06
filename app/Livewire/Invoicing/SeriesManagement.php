<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSeries;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Gestão de Séries de Documentos')]
class SeriesManagement extends Component
{
    use WithPagination;

    // Filtros
    public $filterType = '';
    public $search = '';
    
    // Modal
    public $showModal = false;
    public $seriesId = null;
    public $isEdit = false;
    
    // Form fields
    public $document_type = 'invoice';
    public $series_code = '';
    public $name = '';
    // Do catálogo, não literal: 'FT' à mão aqui era mais uma cópia do prefixo.
    public $prefix = InvoicingSeries::AGT_PREFIXES['invoice'];
    public $include_year = true;
    public $next_number = 1;
    public $number_padding = 6;
    public $is_default = false;
    public $is_active = true;
    public $reset_yearly = true;
    public $description = '';

    // === Campos AGT (DS.120 §4.5/4.6) ===
    public ?int $series_year = null;
    public string $establishment_number = 'SEDE';
    public ?string $invoicing_method = null; // FEPC, FESF, SF
    
    // Delete modal
    public $showDeleteModal = false;
    public $seriesToDelete = null;

    protected function rules(): array
    {
        return [
            'document_type' => 'required|in:invoice,proforma,receipt,credit_note,debit_note,pos,purchase,advance,transport',
            'series_code' => 'required|max:10',
            'name' => 'required|max:100',
            'prefix' => $this->regraDoPrefixo(),
            'next_number' => 'required|integer|min:1',
            'number_padding' => 'required|integer|min:1|max:10',
            'series_year' => 'nullable|integer|min:2024|max:2099',
            'establishment_number' => 'nullable|string|max:200',
            'invoicing_method' => 'nullable|in:FEPC,FESF,SF',
        ];
    }

    /**
     * O prefixo é um código fiscal, não uma preferência de quem preenche.
     *
     * Era 'required|max:10' — texto livre. Escrevia-se 'PRF' ou 'PP' onde a AGT
     * exige 'PR' e nada avisava; o ecrã continuava a mostrar o prefixo do
     * catálogo, portanto nem a olhar se percebia que o documento ia sair com
     * outro. A AGT lê o primeiro token do número para classificar o documento e
     * recusa (E32) o que não reconhece.
     *
     * Os tipos internos não têm catálogo a impor — não vão à AGT — e por isso
     * mantêm a regra antiga.
     *
     * @return array<int, mixed>|string
     */
    private function regraDoPrefixo(): array|string
    {
        $canonico = InvoicingSeries::prefixoDe((string) $this->document_type);

        if ($canonico === null) {
            return 'required|max:10';
        }

        return ['required', Rule::in([$canonico])];
    }

    protected function messages(): array
    {
        return [
            'prefix.in' => __('O prefixo deste tipo de documento é fixado pela AGT (:prefixo) e não pode ser outro.', [
                'prefixo' => InvoicingSeries::prefixoDe((string) $this->document_type),
            ]),
        ];
    }

    /**
     * O prefixo que vai para a base de dados.
     *
     * Nunca o que veio do formulário: o formulário é do cliente e o cliente não
     * decide um código fiscal. Para os tipos internos não há catálogo AGT que
     * mande, e aí fica o que estiver preenchido.
     */
    private function prefixoParaGravar(): string
    {
        return InvoicingSeries::prefixoDe((string) $this->document_type)
            ?? (string) $this->prefix;
    }

    /** O prefixo segue o tipo: trocar o tipo no formulário troca logo o prefixo. */
    public function updatedDocumentType(): void
    {
        $this->prefix = $this->prefixoParaGravar();
    }

    public function openCreateModal()
    {
        $this->reset([
            'seriesId', 'document_type', 'series_code', 'name', 'prefix',
            'include_year', 'next_number', 'number_padding', 'is_default',
            'is_active', 'reset_yearly', 'description',
            'series_year', 'establishment_number', 'invoicing_method',
        ]);
        $this->isEdit = false;
        $this->document_type = 'invoice';
        $this->prefix = $this->prefixoParaGravar();
        $this->include_year = true;
        $this->next_number = 1;
        $this->number_padding = 6;
        $this->is_active = true;
        $this->reset_yearly = true;
        $this->series_year = (int) now()->year;
        $this->establishment_number = 'SEDE';
        $this->invoicing_method = InvoicingSeries::INVOICING_FEPC;
        $this->showModal = true;
    }

    public function editSeries($id)
    {
        $series = InvoicingSeries::forTenant(activeTenantId())->findOrFail($id);
        
        $this->seriesId = $series->id;
        $this->document_type = $series->document_type;
        $this->series_code = $series->series_code;
        $this->name = $series->name;
        $this->prefix = $series->prefix;
        $this->include_year = $series->include_year;
        $this->next_number = $series->next_number;
        $this->number_padding = $series->number_padding;
        $this->is_default = $series->is_default;
        $this->is_active = $series->is_active;
        $this->reset_yearly = $series->reset_yearly;
        $this->description = $series->description;
        $this->series_year = $series->series_year ?? (int) now()->year;
        $this->establishment_number = $series->establishment_number ?? 'SEDE';
        $this->invoicing_method = $series->invoicing_method ?? InvoicingSeries::INVOICING_FEPC;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    /*
     * A FICHA DA SÉRIE GRAVA-SE NO `GestaoDeSeries`: o prefixo do catálogo, a
     * série registada na AGT que quase não se mexe, a janela do exercício,
     * uma só padrão por tipo. Este ecrã e o ecrã em React chamam o mesmo.
     */
    public function save()
    {
        $gestao = app(\App\Services\Invoicing\GestaoDeSeries::class);

        // Derivar ANTES de validar: o prefixo é consequência do tipo, não uma
        // escolha de quem preenche.
        $this->prefix = $gestao->prefixoParaGravar((string) $this->document_type, $this->prefix);

        $this->validate($gestao->regras((string) $this->document_type));

        $dados = [
            'document_type' => $this->document_type,
            'series_code' => $this->series_code,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'include_year' => $this->include_year,
            'next_number' => $this->next_number,
            'number_padding' => $this->number_padding,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'reset_yearly' => $this->reset_yearly,
            'description' => $this->description,
            'series_year' => $this->series_year,
            'establishment_number' => $this->establishment_number,
            'invoicing_method' => $this->invoicing_method,
        ];

        try {
            if ($this->isEdit) {
                $series = InvoicingSeries::forTenant(activeTenantId())->findOrFail($this->seriesId);
                $message = $gestao->actualizar($series, $dados, activeTenantId());
            } else {
                $gestao->criar($dados, activeTenantId());
                $message = 'Série criada com sucesso!';
            }
        } catch (\DomainException $e) {
            $this->addError('series_year', $e->getMessage());

            return;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro: :detalhe', ['detalhe' => $e->getMessage()])]);

            return;
        }

        $this->showModal = false;
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message
        ]);
    }

    public function confirmDelete($id)
    {
        $this->seriesToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteSeries()
    {
        try {
            $series = InvoicingSeries::forTenant(activeTenantId())->findOrFail($this->seriesToDelete);
            app(\App\Services\Invoicing\GestaoDeSeries::class)->eliminar($series);

            $this->showDeleteModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Série eliminada com sucesso!')
            ]);
        } catch (\DomainException $e) {
            $this->showDeleteModal = false;
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao eliminar série: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    public function render()
    {
        $query = InvoicingSeries::forTenant(activeTenantId())
            ->orderBy('document_type')
            ->orderBy('is_default', 'desc')
            ->orderBy('series_code');

        if ($this->filterType) {
            $query->where('document_type', $this->filterType);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('series_code', 'like', '%' . $this->search . '%');
            });
        }

        $series = $query->paginate(15);

        return view('livewire.invoicing.series-management', [
            'series' => $series,
        ]);
    }
}
