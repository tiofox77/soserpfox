{{-- Modal de visualização da Nota de Débito (paleta verde: débito ao cliente =
     entrada de valor para a empresa) --}}
@if($showViewModal && $selectedDebitNote)
    @include('livewire.invoicing.partials.note-view-modal', [
        'doc'          => $selectedDebitNote,
        'numero'       => $selectedDebitNote->debit_note_number,
        'titulo'       => 'Nota de Débito',
        'grad'         => 'from-green-600 to-emerald-600',
        'accent'       => 'text-green-600',
        'chipBg'       => 'bg-green-100',
        'close'        => 'closeViewModal',
        'rotaPreview'  => 'invoicing.debit-notes.preview',
        'rotaPdf'      => 'invoicing.debit-notes.pdf',
    ])
@endif
