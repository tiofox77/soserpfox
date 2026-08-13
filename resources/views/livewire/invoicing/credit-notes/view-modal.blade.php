{{-- Modal de visualização da Nota de Crédito (paleta vermelha: crédito ao
     cliente = saída de valor para a empresa) --}}
@if($showViewModal && $selectedCreditNote)
    @include('livewire.invoicing.partials.note-view-modal', [
        'doc'          => $selectedCreditNote,
        'numero'       => $selectedCreditNote->credit_note_number,
        'titulo'       => __('Nota de Crédito'),
        'grad'         => 'from-red-600 to-rose-600',
        'accent'       => 'text-red-600',
        'chipBg'       => 'bg-red-100',
        'close'        => 'closeViewModal',
        'rotaPreview'  => 'invoicing.credit-notes.preview',
        'rotaPdf'      => 'invoicing.credit-notes.pdf',
    ])
@endif
